# Cordeau

> SaaS de gestion d'activité pour artisans du bâtiment indépendants. Mobile-first, mesure AR, offline-first.

Monorepo : `apps/api` (Symfony), `apps/web` (Vite + React), `apps/mobile` (Expo).

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
- [docs/architecture/](docs/architecture/) — diagrammes et notes d'architecture
- [docs/runbooks/](docs/runbooks/) — procédures opérationnelles

Vision produit, persona, stratégie financière et veille concurrentielle : Notion (workspace privé Cordeau).

## Licence

Privé. Tous droits réservés.
