# CLAUDE.md — Racine du monorepo Cordeau

> Lu en premier par Claude Code à chaque session. Tient les conventions globales et les pointeurs vers les `CLAUDE.md` par app.

## Le projet

Cordeau est un SaaS mobile-first de gestion d'activité pour artisans du bâtiment indépendants (auto-entrepreneurs, TPE 1-5 personnes). Trois plateformes : **API** Symfony, **web** back-office React, **mobile** Expo. Différenciateurs V1 : modèle Chantier/Lots/Tâches/Mesures pensé pour l'artisan, capture terrain sans friction, offline-first, double interface mobile + web. Mesure AR repoussée V2 (cf [ADR 0016](docs/adr/0016-positionnement-v1-outil-de-suivi.md)).

La pensée stratégique et business vit sur Notion (vision, persona, roadmap business, finances, specs collaboratives). Le repo est la **source de vérité technique** : code, conventions (`CLAUDE.md`), ADRs, roadmap technique, runbooks. Vue d'ensemble de l'architecture (diagrammes C4 vivants) : [docs/architecture.md](docs/architecture.md) ([ADR 0018](docs/adr/0018-documentation-architecture-as-code.md)).

## Workflow Notion ↔ repo

**Règle d'or** : Notion pour penser, repo pour exécuter. Pas de duplication.

- Décision technique structurante → ADR dans `docs/adr/` (pas en Notion)
- Spec produit → Notion, validée → 1 issue GitHub `type:feature` par story → branche `feat/<n>-<slug>`
- Conventions techniques → `CLAUDE.md` racine + un par app

## Stack en un coup d'œil

| App               | Stack                                                             | CLAUDE                                         |
| ----------------- | ----------------------------------------------------------------- | ---------------------------------------------- |
| `apps/api`        | Symfony 7 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8    | [apps/api/CLAUDE.md](apps/api/CLAUDE.md)       |
| `apps/web`        | Vite + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui | [apps/web/CLAUDE.md](apps/web/CLAUDE.md)       |
| `apps/mobile`     | Expo SDK 54+ + expo-router + NativeWind + expo-sqlite/Drizzle     | [apps/mobile/CLAUDE.md](apps/mobile/CLAUDE.md) |
| `packages/shared` | Types partagés (générés via openapi-typescript)                   | —                                              |

Détails et justifications dans [docs/adr/](docs/adr/).

## Commandes principales

```bash
# Dev local (lance les 3 apps en parallèle via Turborepo)
docker compose up -d        # Postgres + Redis (infra uniquement — PHP géré par Symfony CLI)
pnpm install                # à la racine (workspaces pnpm)
pnpm dev                    # turbo run dev

# API Symfony : toujours `symfony console`, jamais `php bin/console` (voir docs/ENV-SETUP.md)

# Tests / lint / build
pnpm test
pnpm lint
pnpm build
pnpm type-check
pnpm format                 # Prettier en place
```

## Conventions transverses

### Commits — Conventional Commits

Format : `type(scope): subject`. Types autorisés (commitlint) : `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `perf`, `ci`, `build`, `revert` (pas `style`). Scope = nom d'app (`api`, `web`, `mobile`, `infra`, `ci`) ou bounded context (`chantier`, `client`, `auth`, `photo`, `lot`, `mesure`, `materiau`, `devis`, `facture`).

Exemples : `feat(chantier): add archivage use case`, `fix(api): handle null adresse on creation`, `chore(ci): cache composer downloads`.

### Branches

- `feat/<issue-number>-<slug>` (`feat/12-creation-chantier`)
- `fix/<issue-number>-<slug>`
- `chore/<slug>` (sans issue si purement local)

Squash merge sur `main`. `main` est protégée : PR obligatoire, CI verte requise, 1 approbation requise.

**Merge automatisé** ([ADR 0025](docs/adr/0025-reviewer-claude-auto-merge.md)) : le workflow `PR Review` relit chaque PR interne avec Claude, l'approuve si le verdict est `approve` et active l'auto-merge ; GitHub fusionne dès que la CI est verte. **Claude ne fusionne jamais lui-même** : il crée la PR, surveille la CI et la revue, et corrige si besoin. Une PR labellisée `needs-human` attend la décision du fondateur, qui la fusionne s'il l'accepte (`gh pr merge <n> --admin --squash`). Les PR qui touchent la racine de confiance du reviewer (`pr-review.yml`, `CODEOWNERS`, `.claude/`) sont toujours `needs-human`. Le workflow ne doit jamais exécuter le code de la PR (`pull_request_target`, cf ADR).

### ADRs

Toute décision structurante (choix de lib, pattern d'archi, migration) → un ADR dans `docs/adr/`. Format : `NNNN-titre-court.md` avec `Status`, `Date`, `Deciders`, `Context`, `Decision`, `Consequences`. L'index est dans [docs/adr/README.md](docs/adr/README.md).

**Avant** d'introduire une décision non triviale, écrire l'ADR. Pas après.

Section **Implications sécurité** optionnelle, **obligatoire** si la décision touche : auth/sessions/tokens, permissions/RBAC, secrets, données personnelles, stockage de fichiers, données financières, ajout d'une dépendance externe. Couvrir au minimum : surface d'attaque ajoutée, secrets manipulés, données personnelles touchées (et leur base légale RGPD), points de fuite potentiels.

### Tests

- Domain riche → tests unitaires sans DB
- Use cases → tests d'intégration avec DB
- API → tests d'intégration avec une vraie réponse HTTP
- Pas de feature shippée sans test sur le domaine au minimum

### Bug-fix : protocole double-fix

Tout bug qui sort du dev local (caught en CI, en démo, ou plus tard en prod) = **deux bugs en un** : le défaut code, et le défaut du système de test qui l'a laissé passer. Un fix sans audit du système de test est incomplet.

À chaque PR `fix:` qui répare un bug hors dev local, la description doit contenir une section **Audit système de test** répondant à :

1. **Quel layer aurait dû attraper ce bug ?** (unit / intégration / e2e / type-check / lint / contract / property)
2. **Pourquoi ce layer ne l'a pas attrapé ?** (test absent, assertion incomplète, mock divergent, scénario hors paramétrage, exclusion de couverture mal calibrée)
3. **Quelle modification concrète de ce layer ferme la fente ?** (test ajouté, assertion renforcée, mock retiré, contrat ajouté, couverture étendue). Cette modif fait partie du même PR.

Si la réponse à (3) est "rien, on ne pouvait pas l'attraper" — le diagnostic est presque toujours faux. Creuser plus.

### Naming métier

[docs/THESAURUS.md](docs/THESAURUS.md) liste les termes canoniques du domaine (`Chantier`, `Client`, `ClientRef`, `Adresse`, etc.) avec définition / N'EST PAS / synonymes à éviter. Consulter **avant** de nommer une classe, un champ, une route, une table. Ajouter une entrée avant d'introduire un terme métier nouveau dans le code. À auditer en fin de phase.

### Architecture hexagonale pragmatique

Voir [docs/adr/0002-architecture-hexagonale.md](docs/adr/0002-architecture-hexagonale.md). En résumé : **rigoureuse** sur les bounded contexts complexes (Lot/Mesure/Devis/TVA, AR V2), **légère** sur les CRUD simples (clients, adresses, tags). Les dépendances pointent toujours vers l'intérieur — le domaine ne connaît ni Doctrine ni Symfony.

## Discipline produit

- **Verticales end-to-end**, pas horizontales : un bounded context complet sur les 3 plateformes avant de répliquer
- **Phase 1 = moment de validation** : si quelque chose semble bancal, refactor MAINTENANT avant Phase 2
- **Valider en saisie manuelle avant d'introduire la magie** : pas de feature « intelligente » (LLM, structuration vocale, géofencing, détection risques, automation) tant que le concept manuel n'a pas validé son utilité auprès des testeurs (cf [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)). Le wedge V1 est la combinaison modèle Chantier/Lots/Tâches/Mesures + UX mobile sans friction + offline-first, pas la magie en aval
- **Démo perso hebdo** (vendredi) : lancer l'app comme un user

## Roadmap

Plan d'attaque par phases dans [ROADMAP.md](ROADMAP.md). Statut courant : Phase 5 (Photos + R2) ✅ terminée (juin 2026, iOS on-device inclus) ; prochaine étape Phase 6 Lots/Tâches. Suivi résiduel : #87 (job CI build natif iOS). V1 ciblée septembre 2026 (bêta payante).

## Surveillance CI automatique

Après chaque `git push`, lancer `gh run watch <RUN_ID> --exit-status` en `run_in_background: true` : le harness notifie à la fin (~0 token tant qu'il tourne). Si la CI est rouge, récupérer les logs filtrés et proposer un fix. Le hook `asyncRewake` de `.claude/settings.json` ([scripts/ci-watch.sh](scripts/ci-watch.sh)) rapporte la CI du commit poussé après un `git push` (premier déclenchement observé le 2026-10-04, après la correction de son chemin, #181). La CI ne démarrant qu'à l'ouverture de la PR, le premier push d'une branche ne donne rien : suivre alors le run à la main. Cas des worktrees : [docs/workflow-agentique.md](docs/workflow-agentique.md#surveillance-ci--détails).

RUN_ID après le push (**toujours `--workflow CI`** : sans filtre, le dernier run de la branche peut être `PR Review` ou `CodeQL`, et son succès ne dit rien de la CI) :

```bash
sleep 3 && gh run list --branch <branch> --workflow CI --limit 1 --json databaseId -q '.[0].databaseId'
```

## Protocole de démarrage de phase

À dérouler **dans l'ordre** au début de chaque nouvelle phase de [ROADMAP.md](ROADMAP.md). **Aucune ligne de code métier tant que les étapes 1 à 5 ne sont pas faites.** Détail de chaque étape : [docs/workflow-agentique.md](docs/workflow-agentique.md#protocole-de-démarrage-de-phase).

1. Charger le contexte (CLAUDE.md, ROADMAP, ADRs, memories)
2. Explorer le verticale précédent comme modèle
3. Rédiger le plan macro dans `~/.claude/plans/`
4. Trancher les décisions structurantes avec l'utilisateur (`AskUserQuestion`)
5. Rédiger les ADRs, avant le code
6. Créer la milestone et une issue par sous-étape
7. Une branche par sous-étape, squash merge après PR + CI verte
8. En cours de phase : refactor dès qu'un pattern devient évident
9. Fin de phase : `ROADMAP.md` (✅), `CLAUDE.md`, memories Serena, `./scripts/check-docs.sh` ; vérifier le critère de sortie avant la phase suivante

Si un signal de vélocité ou d'archi se dégrade, **stop** et rétro avant de continuer.

## Automatisation skills

Skills à invoquer automatiquement selon le contexte (sans qu'on ait à le demander) :

- **Avant chaque `gh pr create`** → lancer `/simplify` sur les changements de la branche, puis intégrer les corrections suggérées avant d'ouvrir la PR
- **Revue avant merge** → faite par le workflow `PR Review` (ADR 0025) : second avis en contexte frais sur chaque PR, plus revue sécurité si la PR touche auth/sessions, permissions/RBAC, secrets, données personnelles (clients, adresses, téléphones, photos identifiables, au titre RGPD), stockage de fichiers, données financières, dépendance externe ou workflows CI. Un finding sécurité high/critical → label `needs-human`, pas d'auto-merge. Après un verdict `request_changes`, Claude corrige et pousse : la revue repart automatiquement
- **Pour toute question DB / Neon / queries / connexion / migration prod** → utiliser le skill `neon-postgres` au lieu de répondre depuis la mémoire
- **Phase 1.4 et 1.5 (UI web et mobile)** → utiliser le skill `frontend-design` quand on génère des écrans nouveaux pour éviter le rendu "AI générique"
- **Phase 6 (Devis) et au-delà, queries SQL complexes** → consulter `supabase-postgres-best-practices` (best practices Postgres génériques)
- **`/simplify` omis** : possible quand le diff ne contient que de la configuration, des lockfiles, de la documentation ou du code généré ; le dire dans la description de la PR

### Skills superpowers (harness Claude Code)

Chargés par le harness. Attendus dans le workflow Cordeau (détail et anti-patterns : [docs/workflow-agentique.md](docs/workflow-agentique.md#skills-superpowers-harness-claude-code)) :

- `brainstorming` avant toute feature ou composant à spec floue (sauf spec Notion déjà validée)
- `writing-plans` pour une story de plus de 3 étapes non triviales : plan dans `~/.claude/plans/<slug>.md`, hors repo ; l'issue dit le **quoi**, le plan le **comment**
- `test-driven-development` avant d'écrire l'implémentation ; `systematic-debugging` sur tout bug (puis audit double-fix s'il sort du dev local)
- `verification-before-completion` avant de dire « c'est fait » ou d'ouvrir une PR ; `receiving-code-review` à réception d'une revue
- `dispatching-parallel-agents` pour des explorations indépendantes en lecture seule, jamais pour de l'implémentation ni des tâches dépendantes
- `using-git-worktrees` pour un spike isolé : préférer l'outil natif `EnterWorktree`, jamais pour un fix de moins de 30 min

Si une de ces règles s'applique mais que tu juges qu'elle ne sert à rien dans le cas précis, l'expliquer plutôt que de l'appliquer aveuglément.

## Aide-mémoire pour Claude Code

- Avant de proposer une lib externe : vérifier si elle est déjà dans le stack acté (cf ADRs)
- Avant de proposer une décision structurante : proposer un ADR d'abord
- Pour explorer le projet : **Serena (MCP)** pour la navigation sémantique (find_symbol, find_referencing_symbols, get_symbols_overview, rename_symbol) — voir [ADR 0009](docs/adr/0009-serena-mcp-outillage-semantique.md). Tomber sur grep/Read uniquement pour la recherche en texte plein (commentaires, strings, docs) ou l'édition de petits blocs. Pour les versions de libs externes, utiliser le serveur MCP Context7
- Serena tient ses propres memories versionnées dans `.serena/memories/` (project_overview, architecture, conventions). À lire en début de tâche complexe, et à mettre à jour en fin de phase au même rythme que `CLAUDE.md`
- Quand l'utilisateur référence une page Notion, utiliser le serveur MCP Notion (workspace Cordeau uniquement)
- Conventions FR : tout est en français (UI, doc, identifiants métier comme `Chantier`, `Devis`, `Client`)
- **Pas de hacks** : si quelque chose ne fonctionne pas, consulter Context7 MCP avant de coder un workaround.
- **Ambiguïté en cours d'implémentation** : présenter les interprétations possibles plutôt que d'en choisir une silencieusement. `AskUserQuestion` couvre les décisions structurantes (étape 4 du protocole de phase) ; cette règle couvre les micro-choix d'implémentation
- **Surgical changes** : chaque ligne modifiée doit tracer à la demande ou au scope de la story en cours. Dead code ou style adjacent hors scope : mentionner, ne pas toucher. N'invalide pas la règle de refactor en cours de phase (étape 8) : refactor à l'intérieur du bounded context travaillé, pas drive-by sur du code adjacent
- **Pas de PR drive-by** : si une amélioration adjacente vaut le coup, ouvrir une issue séparée plutôt que de l'embarquer dans la PR en cours
- **Dispatch parallèle vs séquentiel** : explorations indépendantes sans état partagé → `superpowers:dispatching-parallel-agents`. Étapes dépendantes (chaque action utilise l'output de la précédente) → séquentiel, pas de dispatch
- **Trajectoire V1 manuelle → V1.2+ magie** : avant toute proposition de feature « IA / vocale / structuration automatique / chrono auto / détection risques », vérifier la trajectoire dans [ROADMAP.md](ROADMAP.md). V1 est manuelle, V1.1 fluidifie l'UX, V1.2 ajoute la magie LLM **conditionnée au critère de validation bêta** ([ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)). Ne pas court-circuiter — c'est un garde-fou explicite

## Évolution du workflow (retours d'expérience)

Le workflow n'est pas figé : quand une friction ou une erreur révèle un défaut de process, **la règle correspondante est ajoutée dans la même PR** (ou, si elle demande une décision, une issue + un ADR). Pas de leçon qui reste seulement dans une conversation. Le détail daté vit dans [docs/workflow-agentique.md](docs/workflow-agentique.md#retours-dexpérience) ; ne sont résumées ici que les règles qui valent à chaque session.

- **Ne jamais contourner un refus du classifieur du mode auto** par un autre outil : le signaler et continuer le reste. Claude ne fusionne pas ses propres PR (circuit : [ADR 0025](docs/adr/0025-reviewer-claude-auto-merge.md))
- **Sessions autonomes** : tester une commande Bash triviale en premier ; si tout Bash est bloqué, ne pas boucler : plan de reprise dans `~/.claude/plans/` et le signaler
- **Commits** : ne jamais masquer la sortie des hooks (`> /dev/null`), un échec de commitlint ou de Prettier passe sinon inaperçu
- **Une affirmation sur la prod se vérifie sur la prod** (`flyctl secrets list`, `status`, `logs`) ; si l'accès manque, le dire au lieu de supposer. Après un déploiement, vérifier `/health` et le 401 sans jeton
- **Dépôt public** (choix du fondateur, pour les protections de GitHub) : aucun commit, issue, PR ni commentaire ne contient une valeur de secret, une donnée client, ni l'analyse d'une faille non corrigée (elle va dans un signalement privé : onglet Security → Report a vulnerability, cf. [SECURITY.md](SECURITY.md)) ; les branches d'étude ou d'expérience internes (`chore/etude-rust-agentique`, `worktree-agent-*`) ne sont jamais poussées
- **Hook Stop** ([scripts/stop-verify.sh](scripts/stop-verify.sh)) : bloque la fin de tour si PHPStan, les tests ou `tsc` sont rouges sur la branche
- **Avant de toucher aux dépendances, à une PR Dependabot, au contrat OpenAPI, à une contrainte de framework ou à la protection de `main`** : lire les leçons correspondantes dans [docs/workflow-agentique.md](docs/workflow-agentique.md#retours-dexpérience)
