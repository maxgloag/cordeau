# Cordeau

Cordeau est un outil de suivi d'activité pour les artisans du bâtiment indépendants (auto-entrepreneurs, TPE de 1 à 5 personnes).
Il structure un chantier en lots, tâches et mesures, avec une capture terrain sans friction, pour suivre l'estimation et le réel par poste de travail.
L'application est pensée mobile-first et offline-first : toute saisie reste possible sans réseau et se synchronise au retour de la connexion.

## État du projet

**Pré-bêta, sans utilisateurs en production** (voir [SECURITY.md](SECURITY.md)). Le dépôt est porté par une seule personne.

L'état d'avancement par phase est dans [ROADMAP.md](ROADMAP.md), seule source du statut. La V1 est volontairement manuelle : pas d'IA, pas de mesure en réalité augmentée, pas de facturation conforme PDP ([ADR 0016](docs/adr/0016-positionnement-v1-outil-de-suivi.md), [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)).

Il n'y a pas de capture d'écran ni de vidéo dans ce dépôt : aucune donnée de démonstration n'est prête à être publiée.

## Stack

Monorepo Turborepo + pnpm.

| App               | Stack                                                             |
| ----------------- | ----------------------------------------------------------------- |
| `apps/api`        | Symfony 7 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8    |
| `apps/web`        | Vite + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui |
| `apps/mobile`     | Expo SDK 54+ + expo-router + NativeWind + expo-sqlite/Drizzle     |
| `packages/shared` | Types partagés (générés via openapi-typescript)                   |

Hébergement : API sur Fly.io (Paris), base Neon, web sur Cloudflare Pages, photos sur Cloudflare R2, mobile via EAS ([ADR 0008](docs/adr/0008-trajectoire-hebergement.md)).

## Décisions d'architecture

Les décisions coûteuses à défaire sont consignées dans [docs/adr/](docs/adr/). [docs/decisions-cles.md](docs/decisions-cles.md) en résume six : offline-first, tokens opaques, modèle Chantier / Lots, IA différée, durcissement de la CI/CD, revue automatisée et auto-merge. Vue d'ensemble : [docs/architecture.md](docs/architecture.md).

## Pré-requis

- **Node** 24+ (`nvm use` lit le `.nvmrc`)
- **pnpm** 10+ (activé via `corepack enable`)
- **Docker** + Docker Compose (Postgres 18 + Redis 8 en local)
- **PHP** 8.4+ (via Homebrew : `brew install php`). Pour cibler une version spécifique côté API, `apps/api/.php-version`.
- **Composer** 2+
- **Symfony CLI** (recommandé pour le serveur de dev API)

## Setup initial

```bash
nvm use                       # Node 24 via .nvmrc
corepack enable               # active pnpm
cp .env.example .env          # variables racine partagées
pnpm install                  # workspaces pnpm
docker compose up -d          # Postgres + Redis
pnpm dev                      # lance les 3 apps en parallèle via turbo
```

## Commandes utiles

```bash
pnpm dev                      # tout
pnpm --filter @cordeau/api dev
pnpm --filter @cordeau/web dev
pnpm --filter @cordeau/mobile dev

pnpm test
pnpm lint
pnpm build
pnpm type-check
pnpm format
```

## Documentation

- [CLAUDE.md](CLAUDE.md) — conventions globales (lu en premier par Claude Code)
- [ROADMAP.md](ROADMAP.md) — plan d'attaque par phases
- [docs/adr/](docs/adr/) — Architecture Decision Records
- [docs/decisions-cles.md](docs/decisions-cles.md) — six décisions clés résumées
- [docs/architecture.md](docs/architecture.md) — diagrammes et notes d'architecture
- [docs/runbooks/](docs/runbooks/) — procédures opérationnelles

Vision produit, persona, stratégie financière et veille concurrentielle : Notion (workspace privé Cordeau).

## Licence

Privé. Tous droits réservés.
