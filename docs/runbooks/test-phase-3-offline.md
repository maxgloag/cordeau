# Runbook — Tester Phase 3 (Offline-first) sur iPhone

Guide pas-à-pas pour valider la verticale offline sur un device physique iOS via Expo Go.

## ⚠️ Limitation Expo Go

Expo Go charge le bundle JS depuis Metro à chaque ouverture de l'app. Donc :

- ✅ **Activer le mode avion APRÈS le lancement de l'app** → tout fonctionne (SQLite, outbox, mutations, sync au retour)
- ❌ **Killer l'app + la rouvrir en mode avion** → écran rouge "Could not connect to development server" (Metro injoignable)

Le cold start offline complet nécessite un build EAS preview (compte Apple Developer requis). Reporté à plus tard.

## Prérequis

### Machine de dev
- Docker Desktop lancé (`docker compose up -d` depuis la racine du repo → Postgres + Redis)
- Node 24 + pnpm installés
- iPhone et Mac sur **le même réseau Wi-Fi**

### iPhone
- App **Expo Go** installée depuis l'App Store
- Wi-Fi activé

## 1. Démarrer les services

Depuis la racine du repo :

```bash
docker compose up -d                    # Postgres + Redis
pnpm install                            # si pas déjà fait
pnpm --filter @cordeau/api dev          # API Symfony sur :8000
```

Vérifier que l'API répond : `curl http://localhost:8000/health` → 200.

## 2. Configurer l'URL de l'API pour l'iPhone

`localhost` ne fonctionne pas depuis l'iPhone : il faut l'IP locale du Mac.

```bash
ipconfig getifaddr en0                  # IP Wi-Fi du Mac, ex: 192.168.1.42
```

Éditer (ou créer) [apps/mobile/.env](../../apps/mobile/.env) :

```
EXPO_PUBLIC_API_URL=http://192.168.1.42:8000
```

Remplacer par l'IP réelle. Pas de `/` final.

## 3. Lancer Expo

```bash
pnpm --filter @cordeau/mobile dev       # ouvre Expo Dev Tools + QR code
```

Sur l'iPhone : ouvrir l'app **Caméra**, scanner le QR → Expo Go se lance avec l'app Cordeau.

> Si l'app crash au démarrage avec une erreur SQLite, c'est que la migration ne s'est pas exécutée. Forcer un reload (secouer le device → "Reload").

## 4. Scénarios de test

### 4.1 — Premier lancement (online)

1. Se créer un compte (ou se connecter)
2. Vérifier qu'on arrive sur l'écran **Chantiers** (vide ou avec données serveur)
3. **Critère** : aucune barre `SyncStatusBar` visible (état `synced`)

### 4.2 — Reads offline (cache SQLite + queryFn hybride)

(app déjà ouverte depuis 4.1)

1. Créer 1 chantier + 1 client depuis l'app online
2. **Activer le mode avion** sur l'iPhone (Centre de contrôle → avion)
3. Naviguer entre la liste chantiers ↔ liste clients plusieurs fois
4. **Critère** : navigation instantanée, données toujours visibles (lectures depuis SQLite via TanStack Query cache + `getAllChantiers`/`getAllClients`)
5. Pull-to-refresh sur la liste chantiers
6. **Critère** : pas d'erreur visible, la liste reste affichée (le refresh API silencieux échoue mais le cache tient)
7. **Critère** : `SyncStatusBar` apparaît en gris : *"Hors-ligne — vos changements seront synchronisés à la reconnexion"*

### 4.3 — Création offline d'un chantier

(toujours en mode avion)

1. Appuyer sur le + → créer un nouveau chantier (adresse + ville + CP)
2. **Critère** : le chantier apparaît immédiatement dans la liste (optimistic update)
3. **Critère** : `SyncStatusBar` passe en **orange** : *"Synchronisation en cours — 1 en attente"*

### 4.4 — Modification + suppression offline

(toujours en mode avion)

1. Ouvrir le chantier créé → modifier l'adresse → Enregistrer
2. **Critère** : la modification s'affiche dans le détail + dans la liste
3. **Critère** : compteur en attente passe à `2`
4. Créer un client → le supprimer
5. **Critère** : compteur en attente monte jusqu'à `4` (1 update chantier + 1 create client + 1 delete client + 1 create chantier de 4.3)

### 4.5 — Reconnexion → sync automatique

1. **Désactiver le mode avion**
2. Attendre 2-5 secondes
3. **Critère** : `SyncStatusBar` disparaît (compteur → 0)
4. Vérifier côté API que tout est bien remonté :

```bash
# Depuis le Mac
psql postgresql://cordeau:cordeau@localhost:5432/cordeau \
  -c "SELECT id, adresse_rue, statut FROM chantier ORDER BY created_at DESC LIMIT 5;"

psql postgresql://cordeau:cordeau@localhost:5432/cordeau \
  -c "SELECT id, nom FROM client ORDER BY created_at DESC LIMIT 5;"
```

> Ou se connecter à l'interface web sur [http://localhost:5173](http://localhost:5173) avec le même compte et vérifier que les chantiers/clients apparaissent.

### 4.6 — Background → foreground sync

1. Avec l'app ouverte et online, créer 1 chantier
2. Mettre l'iPhone en mode avion → créer 2 chantiers de plus (compteur = 2)
3. Mettre Cordeau **en arrière-plan** (swipe up → home)
4. Désactiver le mode avion **pendant que l'app est en background**
5. Rouvrir Cordeau (AppState → active)
6. **Critère** : le `useSyncWorker` détecte l'event foreground + connecté → drain l'outbox. Compteur → 0 dans la barre.

### 4.7 — Résilience aux erreurs réseau temporaires

(simule un backoff)

1. Couper l'API (`Ctrl-C` sur le terminal `pnpm --filter @cordeau/api dev`)
2. Créer un chantier depuis l'app (iPhone toujours online)
3. **Critère** : le chantier apparaît localement, compteur = 1, mais ne se sync pas
4. Relancer l'API → attendre 2-30s (le backoff exponentiel monte rapidement)
5. **Critère** : compteur → 0, chantier visible côté API

## 5. Inspecter l'état local (debug)

Si quelque chose semble bloqué, inspecter SQLite directement via les Expo Dev Tools (`j` dans le terminal expo pour ouvrir le debugger React Native), ou ajouter temporairement un `console.log(getPendingCount(), db.select().from(outbox).all())` dans un écran.

Les états possibles d'une entrée outbox :
- `pending` — en attente d'envoi
- `syncing` — push en cours
- `synced` — envoyée avec succès (sera purgée après 7 jours)
- `error` — erreur transitoire, sera retentée
- `abandoned` — abandonnée après 20 retries

## 6. Reset complet (entre 2 sessions de test)

Pour repartir d'une SQLite vide :

1. Désinstaller Cordeau dans Expo Go (long press sur la card → Remove)
2. Côté serveur, vider les tables :

```bash
psql postgresql://cordeau:cordeau@localhost:5432/cordeau \
  -c "TRUNCATE chantier, client RESTART IDENTITY CASCADE;"
```

3. Recharger Expo Go → recréer un compte ou se reconnecter

## 7. Critère de sortie Phase 3 (à valider)

> Mode avion → créer 5 chantiers + 3 clients → connexion → tout remonte sans doublon ni perte.

Procédure exacte :

1. App online, compte créé, état initial propre
2. Mode avion ON
3. Créer 5 chantiers (adresses différentes)
4. Créer 3 clients (noms différents)
5. **Vérifier** : compteur `SyncStatusBar` = 8
6. Mode avion OFF
7. Attendre 5s
8. **Vérifier** :
   - Compteur → 0
   - Côté API/web : 5 chantiers + 3 clients présents, **aucun doublon**, IDs cohérents
   - Côté mobile : liste identique à avant la reconnexion
