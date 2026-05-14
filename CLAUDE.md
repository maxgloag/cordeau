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

## Protocole de démarrage de phase

À dérouler **dans l'ordre** au début de chaque nouvelle phase de [ROADMAP.md](ROADMAP.md). Aucune ligne de code métier tant que les étapes 1 à 5 ne sont pas faites.

1. **Charger le contexte** : `CLAUDE.md` racine + `apps/*/CLAUDE.md` concernés, section de la phase dans `ROADMAP.md`, ADRs liés, memories Serena (`project_overview`, `architecture`, `conventions`) et auto-memories pertinentes
2. **Explorer le verticale précédent** comme modèle de référence (Serena `get_symbols_overview` / `list_dir`, pas Grep+Read exhaustif)
3. **Rédiger un plan** dans `~/.claude/plans/` : sous-étapes, fichiers à créer/modifier (chemins précis), patterns à réutiliser, tests prévus, critères de done, estimation par sous-étape, vérification end-to-end, et **mesure de vélocité attendue** vs phase précédente
4. **Trancher les décisions structurantes ouvertes** avec l'utilisateur (`AskUserQuestion`) — modélisation de domaine, choix de libs, frontières de contexte
5. **Rédiger les ADRs** correspondants dans `docs/adr/NNNN-titre.md` et mettre à jour [docs/adr/README.md](docs/adr/README.md) — toujours **avant** le code
6. **Créer la milestone GitHub** de la phase + **une issue par sous-étape** (templates existants), liées à la milestone
7. **Une branche par sous-étape** : `feat/<n>-<slug>`, squash merge sur `main` après PR + CI verte
8. **En cours de phase** : à chaque sous-étape, surveiller la duplication révélée ; refactor dès qu'un pattern devient évident, pas à la fin
9. **Fin de phase** : mise à jour de `ROADMAP.md` (✅), `CLAUDE.md` racine, memories Serena (`architecture`, `conventions`), auto-memories pertinentes. Vérifier le critère de sortie de la phase **avant** de basculer sur la suivante

Si un signal de vélocité ou d'archi se dégrade (cf critère de sortie de chaque phase), **stop** et rétro avant de continuer.

## Automatisation skills

Skills à invoquer automatiquement selon le contexte (sans qu'on ait à le demander) :

- **Avant chaque `gh pr create`** → lancer `/simplify` sur les changements de la branche, puis intégrer les corrections suggérées avant d'ouvrir la PR
- **Avant de merger une PR liée à l'auth, à la facturation ou au stockage de fichiers** (`ctx:auth`, `ctx:facture`, `ctx:photo`, ou modifs touchant aux secrets/permissions) → lancer `/security-review` et résoudre les findings critiques avant merge
- **Avant chaque merge** → lancer `/review` pour un second avis sur la PR
- **Pour toute question DB / Neon / queries / connexion / migration prod** → utiliser le skill `neon-postgres` au lieu de répondre depuis la mémoire
- **Phase 1.4 et 1.5 (UI web et mobile)** → utiliser le skill `frontend-design` quand on génère des écrans nouveaux pour éviter le rendu "AI générique"
- **Phase 6 (Devis) et au-delà, queries SQL complexes** → consulter `supabase-postgres-best-practices` (best practices Postgres génériques)

Si une de ces règles s'applique mais que tu juges qu'elle ne sert à rien dans le cas précis, l'expliquer plutôt que de l'appliquer aveuglément.

## Aide-mémoire pour Claude Code

- Avant de proposer une lib externe : vérifier si elle est déjà dans le stack acté (cf ADRs)
- Avant de proposer une décision structurante : proposer un ADR d'abord
- Pour explorer le projet : **Serena (MCP)** pour la navigation sémantique (find_symbol, find_referencing_symbols, get_symbols_overview, rename_symbol) — voir [ADR 0009](docs/adr/0009-serena-mcp-outillage-semantique.md). Tomber sur grep/Read uniquement pour la recherche en texte plein (commentaires, strings, docs) ou l'édition de petits blocs. Pour les versions de libs externes, utiliser le serveur MCP Context7
- Serena tient ses propres memories versionnées dans `.serena/memories/` (project_overview, architecture, conventions). À lire en début de tâche complexe, et à mettre à jour en fin de phase au même rythme que `CLAUDE.md`
- Quand l'utilisateur référence une page Notion, utiliser le serveur MCP Notion (workspace Cordeau uniquement)
- Conventions FR : tout est en français (UI, doc, identifiants métier comme `Chantier`, `Devis`, `Client`)
- **Pas de hacks** : si quelque chose ne fonctionne pas, consulter Context7 MCP avant de coder un workaround. Cf [feedback memory](~/.claude/projects/-Users-MaximeG-Developer-cordeau/memory/feedback_no_hacks.md).
