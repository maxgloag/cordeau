# Architecture Cordeau

Voir aussi : [ADR 0002 — Architecture hexagonale pragmatique](../../docs/adr/0002-architecture-hexagonale.md), [ADR 0015 — Modèle Chantier→Lots→Tâches→Mesures](../../docs/adr/0015-modele-chantier-lots-taches-mesures.md).

## Principe directeur

**Verticales end-to-end, pas horizontales.** On livre un bounded context complet sur les 3 plateformes (API + web + mobile) avant d'en attaquer un nouveau.

## API (apps/api) — Architecture hexagonale pragmatique

Structure par bounded context, sous `src/<Context>/` (ex. `src/Chantier/`).

```
src/Chantier/
  Domain/                    # Pur PHP, ne dépend de rien
    Entity/                  # Entités riches (Chantier, Lot, Tache, Mesure, Materiau, Pointage)
    ValueObject/             # Adresse, Surface, Statut, ModeFacturation, Estimation, Reel, Imprevu
    Port/                    # Interfaces (ChantierRepository, LotRepository, MesureRepository)
    Exception/               # Exceptions métier
  Application/               # Use cases
    UseCase/                 # CreerChantier, CreerLot, DemarrerChrono, etc.
    Dto/                     # DTOs entrée/sortie
  Infrastructure/            # Adapters (dépend de Domain + libs)
    Persistence/Doctrine/    # Repository concret
    ApiPlatform/             # Resource + Provider + Processor
```

### Règles strictes

- **Le domaine ne connaît ni Doctrine ni Symfony.** Aucun `use Doctrine\\...` ou `use Symfony\\...` dans `Domain/`
- **Dépendances pointent vers l'intérieur** : Infrastructure → Application → Domain
- **API Platform 4 patterns récurrents** (cf memory `api_platform_patterns`) :
  - Provider pour GET/GET_COLLECTION
  - Processor pour POST/PATCH/DELETE
  - Extraction systématique de `$uriVariables` dans les processors PATCH/DELETE

### Application stricte vs légère

- **Stricte** sur bounded contexts complexes : `Lot` (modes de facturation, estimation/réel), `Mesure` (calcul assisté), `Devis` / `Facture` (Phase 8, génération brouillon depuis Lots/Materiaux/Pointages), règles TVA. AR V2 stricte également quand introduite.
- **Légère** (cf [ADR 0010](../../docs/adr/0010-crud-leger-pattern-reference.md)) sur CRUD simples : `Client`, `Adresse`, `Tag`, `Photo`, `Tache`, `Materiau`, `Pointage` (sans logique métier dense)

### Modèle Chantier (post-ADR 0015)

`Chantier` reste l'aggregate root. `Lot` = entité fille **optionnelle** (1-N, mode création express = chantier sans lot). Sous `Lot` : `Tache`, `Materiau` (état PREVU/UTILISE), `Mesure` (source MANUEL V1, AR V2), `Pointage` (chrono manuel V1, geofencé V1.2+). `Imprevu` = VO (note libre horodatée), pas entité.

Migration additive Phase 6/7 : nouvelles tables `lot`, `tache`, `materiau`, `mesure`, `pointage`, FK vers `chantier` ou `lot`. **Pas de modif destructive** sur `chantier` (Phases 1-2 préservées).

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
- expo-sqlite + Drizzle + outbox pattern (cf [ADR 0005](../../docs/adr/0005-offline-first-sqlite-drizzle.md) et [ADR 0012](../../docs/adr/0012-offline-first-query-pattern.md))
- À partir de Phase 6/7 : outbox étend à 5 entités filles supplémentaires (Lot, Tache, Materiau, Mesure, Pointage) avec dépendance d'ordre (Lot avant ses enfants)
- UI Phase 7 — focus UX critique : 1-2 taps, gros boutons, formulaires courts, photo en un geste, calcul auto sur Mesure depuis dimensions sources

## Types partagés

`packages/shared` consomme l'OpenAPI exposé par API Platform et génère les types TS via `openapi-typescript`. Web et mobile importent depuis là — pas de duplication de types.

## VO partagés (Shared/ValueObject/)

- `Adresse` (Phase 1)
- `Surface`, `Longueur`, `Volume` (promus Phase 7 — utilisés par Mesure et LigneDevis en mode SURFACE)
- Promotion vers `Shared/` dès qu'un VO est utilisé par ≥ 2 BC (cf [ADR 0010](../../docs/adr/0010-crud-leger-pattern-reference.md))
