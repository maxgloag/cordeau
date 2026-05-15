# ADR 0012 — Pattern offline-first : queryFn hybride SQLite/API + useOfflineMutation

- **Status** : Accepted
- **Date** : 2026-05-15
- **Deciders** : Maxime
- **Lié à** : [ADR 0005](0005-offline-first-sqlite-drizzle.md)

## Context

[ADR 0005](0005-offline-first-sqlite-drizzle.md) décide la stack technique (expo-sqlite + Drizzle ORM + Outbox pattern). Ce présent ADR formalise **comment ces couches s'intègrent avec TanStack Query** côté React Native, en définissant deux patterns réutilisables :

1. **queryFn hybride** pour les lectures (Stale-While-Revalidate SQLite → API)
2. **useOfflineMutation** pour les écritures (SQLite-first + outbox)

Ces patterns sont la brique fondamentale de toute entité offline-capable. Client et Chantier les implémentent en Phase 3 ; toute entité future (Photo, Devis) les réutilisera à l'identique.

## Decision

### Lectures — queryFn hybride

```typescript
// apps/mobile/lib/sync.ts
async function queryFnHybride<T>(
  localRead: () => T[],
  apiRead: () => Promise<T[]>,
  upsertLocal: (items: T[]) => void,
  queryClient: QueryClient,
  queryKey: QueryKey,
  isConnected: boolean,
): Promise<T[]> {
  const local = localRead();
  if (local.length > 0 && !isConnected) return local;
  if (isConnected) {
    // fire-and-forget : fetch en background, invalide la query
    apiRead()
      .then((items) => { upsertLocal(items); void queryClient.invalidateQueries({ queryKey }); })
      .catch(() => { /* silencieux si réseau instable */ });
  }
  return local.length > 0 ? local : apiRead().then((items) => { upsertLocal(items); return items; });
}
```

Comportement :
- **SQLite non vide** : retourne le cache local immédiatement, refresh API en arrière-plan
- **SQLite vide + online** : fetch API, upsert SQLite, retourne le résultat (hydratation initiale)
- **SQLite vide + offline** : retourne `[]` avec `isError = false` (UI affiche "aucun chantier" plutôt qu'erreur)
- `staleTime: Infinity` dans TanStack Query (la fraîcheur est gérée par le worker, pas par Query)

### Écritures — useOfflineMutation

```typescript
// apps/mobile/hooks/useOfflineMutation.ts
function useOfflineMutation<TPayload>(opts: {
  localWrite: (payload: TPayload, localId: string) => void;
  outboxEntry: (payload: TPayload, localId: string) => OutboxEntry;
  queryKey: QueryKey;
}) {
  // 1. Génère un UUID local si create, utilise l'id existant si update/delete
  // 2. localWrite() — synchrone, SQLite
  // 3. OutboxService.push(entry)
  // 4. queryClient.invalidateQueries(queryKey)
  // 5. OutboxService.processQueue() si isConnected (fire-and-forget)
}
```

Comportement :
- L'UI se met à jour **avant** que le réseau soit consulté (optimiste)
- L'outbox garantit que la mutation sera poussée même si la connexion revient des heures plus tard
- Pas de `rollback` UI en V1 (last write wins suffit) — si l'API renvoie 422, l'erreur est loggée et l'entrée outbox passée à `error`

### Worker de sync

Monté dans `app/(app)/_layout.tsx` via `useSyncWorker()` :
- `AppState` listener : chaque passage en `active` déclenche `processQueue()` + `refreshAll()`
- Polling `expo-network` toutes les 30s quand l'app est au premier plan : si `isConnected` passe à `true` → même déclenchement
- Mutex `isSyncing` (ref) : évite les appels concurrents
- Pas de `BackgroundFetch` en V1 (iOS limite l'intervalle à ~15 min, insuffisant pour le cas d'usage terrain)

### Schéma outbox

```
outbox {
  id          TEXT PRIMARY KEY,   -- UUID local
  entityType  TEXT NOT NULL,       -- 'chantier' | 'client'
  entityId    TEXT NOT NULL,       -- UUID (local ou serveur)
  operation   TEXT NOT NULL,       -- 'create' | 'update' | 'delete'
  payload     TEXT NOT NULL,       -- JSON stringifié
  status      TEXT NOT NULL,       -- 'pending' | 'syncing' | 'synced' | 'error'
  retryCount  INTEGER DEFAULT 0,
  createdAt   INTEGER NOT NULL,    -- Unix ms
  lastAttemptAt INTEGER            -- Unix ms
}
```

Backoff exponentiel : délai = `min(2^retryCount * 1000, 604800000)` ms (max 7 jours).
Au-delà de `retryCount > 20`, statut = `abandoned` (log Sentry + notification utilisateur future).

## Consequences

**Bénéfices** :
- L'UI est toujours réactive (zéro spinner bloquant sur lecture)
- Les mutations fonctionnent 100% hors-ligne
- Pattern identique pour toute entité future (Photo, Devis) → réutilisation directe

**Trade-offs** :
- `staleTime: Infinity` couplé au worker de sync : si le worker est défaillant, les données ne se rafraîchissent plus. Atténué par le trigger sur `AppState active`.
- Les UUIDs locaux (pre-créés côté client) doivent être stables côté serveur (UUID v4 accepté par l'API — déjà le cas).
- Pas de résolution de conflit UI en V1 : "last write wins" sur timestamp serveur. Acceptable pour usage solo/petite équipe (cible : ≤5 personnes par compte).

**Décision différée** :
- Résolution de conflits multi-utilisateurs → Phase 8+
- Sync bidirectionnelle "push serveur → mobile" (WebSocket / SSE) → Phase 8+
- `BackgroundFetch` iOS → si les artisans remontent le problème de données obsolètes après une longue nuit sans ouvrir l'app
