# Architecture Cordeau

Voir aussi : [ADR 0002 — Architecture hexagonale pragmatique](../../docs/adr/0002-architecture-hexagonale.md).

## Principe directeur

**Verticales end-to-end, pas horizontales.** On livre un bounded context complet sur les 3 plateformes (API + web + mobile) avant d'en attaquer un nouveau.

## API (apps/api) — Architecture hexagonale pragmatique

Structure par bounded context, sous `src/<Context>/` (ex. `src/Chantier/`).

```
src/Chantier/
  Domain/                    # Pur PHP, ne dépend de rien
    Entity/                  # Entités riches (Chantier)
    ValueObject/             # Adresse, Surface, Statut
    Port/                    # Interfaces (ChantierRepository)
    Exception/               # Exceptions métier
  Application/               # Use cases
    UseCase/                 # CreerChantier, ListerChantiers, etc.
    Dto/                     # DTOs entrée/sortie
  Infrastructure/            # Adapters (dépend de Domain + libs)
    Persistence/Doctrine/    # Repository concret
    ApiPlatform/             # Resource + Provider + Processor
```

### Règles strictes

- **Le domaine ne connaît ni Doctrine ni Symfony.** Aucun `use Doctrine\...` ou `use Symfony\...` dans `Domain/`
- **Dépendances pointent vers l'intérieur** : Infrastructure → Application → Domain
- **API Platform 4 patterns récurrents** (cf memory `api_platform_patterns`) :
  - Provider pour GET/GET_COLLECTION
  - Processor pour POST/PATCH/DELETE
  - Extraction systématique de `$uriVariables` dans les processors PATCH/DELETE

### Application stricte vs légère

- **Stricte** sur bounded contexts complexes : `metre-ar`, `devis`, calculs TVA
- **Légère** sur CRUD simples : adresses, tags. Pas de port quand un seul adapter, pas de DTO quand l'entité suffit

## Web (apps/web) — Pattern container / view

Voir [ADR 0007](../../docs/adr/0007-pattern-container-view.md).

- **Container** : connecté à TanStack Query + RHF + Zod, expose props pures
- **View** : composant pur React, testable en isolation avec Vitest Browser Mode

Tests UI avec Vitest Browser Mode + Playwright (cf [ADR 0006](../../docs/adr/0006-vitest-browser-mode.md)).

Routing : TanStack Router (typé). Auth : cookie de session côté firewall web.

## Mobile (apps/mobile)

- Expo SDK 54 + expo-router (file-based routing)
- NativeWind pour le styling (Tailwind compilé en RN)
- expo-secure-store pour les tokens (tokens opaques, pas JWT — cf [ADR 0003](../../docs/adr/0003-tokens-opaques-mobile.md))
- À partir de Phase 3 : expo-sqlite + Drizzle + outbox pattern (cf [ADR 0005](../../docs/adr/0005-offline-first-sqlite-drizzle.md))

## Types partagés

`packages/shared` consomme l'OpenAPI exposé par API Platform et génère les types TS via `openapi-typescript`. Web et mobile importent depuis là — pas de duplication de types.
