# CLAUDE.md — Racine du monorepo Cordeau

> Lu en premier par Claude Code à chaque session. Tient les conventions globales et les pointeurs vers les `CLAUDE.md` par app.

## Le projet

Cordeau est un SaaS mobile-first de gestion d'activité pour artisans du bâtiment indépendants (auto-entrepreneurs, TPE 1-5 personnes). Trois plateformes : **API** Symfony, **web** back-office React, **mobile** Expo. Différenciateurs : mesure AR, offline-first, double interface mobile + web.

La pensée stratégique et business vit sur Notion (vision, persona, roadmap business, finances, specs collaboratives). Le repo est la **source de vérité technique** : code, conventions (`CLAUDE.md`), ADRs, roadmap technique, runbooks.

## Workflow Notion ↔ repo

**Règle d'or** : Notion pour penser, repo pour exécuter. Pas de duplication.

- Décision technique structurante → ADR dans `docs/adr/` (pas en Notion)
- Spec produit → Notion, validée → 1 issue GitHub `type:feature` par story → branche `feat/<n>-<slug>`
- Conventions techniques → `CLAUDE.md` racine + un par app

## Stack en un coup d'œil

| App | Stack | CLAUDE |
|---|---|---|
| `apps/api` | Symfony 7 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8 | [apps/api/CLAUDE.md](apps/api/CLAUDE.md) |
| `apps/web` | Vite + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui | [apps/web/CLAUDE.md](apps/web/CLAUDE.md) |
| `apps/mobile` | Expo SDK 54+ + expo-router + NativeWind + expo-sqlite/Drizzle | [apps/mobile/CLAUDE.md](apps/mobile/CLAUDE.md) |
| `packages/shared` | Types partagés (générés via openapi-typescript) | — |

Détails et justifications dans [docs/adr/](docs/adr/).

## Commandes principales

```bash
# Dev local (lance les 3 apps en parallèle via Turborepo)
docker compose up -d        # Postgres + Redis
pnpm install                # à la racine (workspaces pnpm)
pnpm dev                    # turbo run dev

# Tests / lint / build
pnpm test
pnpm lint
pnpm build
pnpm type-check
pnpm format                 # Prettier en place
```

## Conventions transverses

### Commits — Conventional Commits

Format : `type(scope): subject`. Types autorisés : `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `perf`, `ci`, `build`. Scope = nom d'app (`api`, `web`, `mobile`, `infra`, `ci`) ou bounded context (`chantier`, `client`, `auth`, `photo`, `devis`, `metre-ar`).

Exemples : `feat(chantier): add archivage use case`, `fix(api): handle null adresse on creation`, `chore(ci): cache composer downloads`.

### Branches

- `feat/<issue-number>-<slug>` (`feat/12-creation-chantier`)
- `fix/<issue-number>-<slug>`
- `chore/<slug>` (sans issue si purement local)

Squash merge sur `main`. `main` est protégée : PR obligatoire, CI verte requise.

### ADRs

Toute décision structurante (choix de lib, pattern d'archi, migration) → un ADR dans `docs/adr/`. Format : `NNNN-titre-court.md` avec `Status`, `Date`, `Deciders`, `Context`, `Decision`, `Consequences`. L'index est dans [docs/adr/README.md](docs/adr/README.md).

**Avant** d'introduire une décision non triviale, écrire l'ADR. Pas après.

### Tests

- Domain riche → tests unitaires sans DB
- Use cases → tests d'intégration avec DB
- API → tests d'intégration avec une vraie réponse HTTP
- Pas de feature shippée sans test sur le domaine au minimum

### Architecture hexagonale pragmatique

Voir [docs/adr/0002-architecture-hexagonale.md](docs/adr/0002-architecture-hexagonale.md). En résumé : **rigoureuse** sur les bounded contexts complexes (mesure AR, métré, devis, TVA), **légère** sur les CRUD simples (clients, adresses, tags). Les dépendances pointent toujours vers l'intérieur — le domaine ne connaît ni Doctrine ni Symfony.

## Discipline produit

- **Verticales end-to-end**, pas horizontales : un bounded context complet sur les 3 plateformes avant de répliquer
- **Phase 1 = moment de validation** : si quelque chose semble bancal, refactor MAINTENANT avant Phase 2
- **Démo perso hebdo** (vendredi) : lancer l'app comme un user

## Roadmap

Plan d'attaque par phases dans [ROADMAP.md](ROADMAP.md). Statut courant : Phase 0 (fondations).

## Surveillance CI automatique

Un hook Claude Code (`.claude/settings.json` → [scripts/ci-watch.sh](scripts/ci-watch.sh)) se déclenche en `asyncRewake` après chaque `git push`. Il attend le résultat du run GitHub Actions et :

- exit 0 (CI verte) → silence, aucun réveil
- exit 2 (CI rouge) → réveille la session avec les logs filtrés (≤80 lignes des erreurs)

Conséquence pratique : **après un `git push`, ne jamais lancer manuellement `gh run watch` ou poller**. Le hook fait le travail. Si la CI échoue, Claude est notifié automatiquement avec le contexte d'erreur.

## Aide-mémoire pour Claude Code

- Avant de proposer une lib externe : vérifier si elle est déjà dans le stack acté (cf ADRs)
- Avant de proposer une décision structurante : proposer un ADR d'abord
- Pour explorer le projet : grep > Read > Bash. Pour les versions de libs externes, utiliser le serveur MCP Context7
- Quand l'utilisateur référence une page Notion, utiliser le serveur MCP Notion (workspace Cordeau uniquement)
- Conventions FR : tout est en français (UI, doc, identifiants métier comme `Chantier`, `Devis`, `Client`)
