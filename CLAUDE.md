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

Format : `type(scope): subject`. Types autorisés : `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `perf`, `ci`, `build`. Scope = nom d'app (`api`, `web`, `mobile`, `infra`, `ci`) ou bounded context (`chantier`, `client`, `auth`, `photo`, `lot`, `mesure`, `materiau`, `devis`, `facture`).

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

Le hook `.claude/settings.json` + [scripts/ci-watch.sh](scripts/ci-watch.sh) avec `asyncRewake` **ne se déclenche pas** dans la version Claude Code actuelle (testé 2026-05-14, aucun log écrit après push). Le script reste utile en CLI manuel.

**Pratique en vigueur** : après chaque `git push`, Claude lance un `gh run watch <RUN_ID> --exit-status` en `run_in_background: true`. Le harness notifie automatiquement à la fin du process (notification courte, ~0 token tant qu'il tourne). Si la CI est rouge, Claude récupère les logs filtrés et propose un fix.

Récupération du RUN_ID après le push :

```bash
sleep 3 && gh run list --branch <branch> --limit 1 --json databaseId -q '.[0].databaseId'
```

Cf [memory `feedback_ci_watch_pattern`](~/.claude/projects/-Users-MaximeG-Developer-cordeau/memory/feedback_ci_watch_pattern.md).

**Avec worktrees** : quand on travaille dans un worktree (cf `superpowers:using-git-worktrees`), le `gh run list --branch <branch>` doit refléter la branche **du worktree actif**, pas celle du repo principal. Le `gh run watch` lancé en `run_in_background` est local au worktree courant ; la notification de fin remonte dans la session qui l'a lancé.

## Protocole de démarrage de phase

À dérouler **dans l'ordre** au début de chaque nouvelle phase de [ROADMAP.md](ROADMAP.md). Aucune ligne de code métier tant que les étapes 1 à 5 ne sont pas faites.

1. **Charger le contexte** : `CLAUDE.md` racine + `apps/*/CLAUDE.md` concernés, section de la phase dans `ROADMAP.md`, ADRs liés, memories Serena (`project_overview`, `architecture`, `conventions`) et auto-memories pertinentes
2. **Explorer le verticale précédent** comme modèle de référence (Serena `get_symbols_overview` / `list_dir`, pas Grep+Read exhaustif)
3. **Rédiger un plan** dans `~/.claude/plans/` : sous-étapes, fichiers à créer/modifier (chemins précis), patterns à réutiliser, tests prévus, critères de done, estimation par sous-étape, vérification end-to-end, et **mesure de vélocité attendue** vs phase précédente. Ce plan est macro phase-level ; en cours de phase, chaque story non triviale (> 3 étapes) déclenche en plus un plan story-level via `superpowers:writing-plans` (cf section [Automatisation skills](#automatisation-skills))
4. **Trancher les décisions structurantes ouvertes** avec l'utilisateur (`AskUserQuestion`) — modélisation de domaine, choix de libs, frontières de contexte
5. **Rédiger les ADRs** correspondants dans `docs/adr/NNNN-titre.md` et mettre à jour [docs/adr/README.md](docs/adr/README.md) — toujours **avant** le code
6. **Créer la milestone GitHub** de la phase + **une issue par sous-étape** (templates existants), liées à la milestone
7. **Une branche par sous-étape** : `feat/<n>-<slug>`, squash merge sur `main` après PR + CI verte
8. **En cours de phase** : à chaque sous-étape, surveiller la duplication révélée ; refactor dès qu'un pattern devient évident, pas à la fin
9. **Fin de phase** : mise à jour de `ROADMAP.md` (✅), `CLAUDE.md` racine, memories Serena (`architecture`, `conventions`), auto-memories pertinentes. Lancer `./scripts/check-docs.sh` (CI : job `format`) et corriger les écarts entre la doc et le code. Vérifier le critère de sortie de la phase **avant** de basculer sur la suivante

Si un signal de vélocité ou d'archi se dégrade (cf critère de sortie de chaque phase), **stop** et rétro avant de continuer.

## Automatisation skills

Skills à invoquer automatiquement selon le contexte (sans qu'on ait à le demander) :

- **Avant chaque `gh pr create`** → lancer `/simplify` sur les changements de la branche, puis intégrer les corrections suggérées avant d'ouvrir la PR
- **Revue avant merge** → faite par le workflow `PR Review` (ADR 0025) : second avis en contexte frais sur chaque PR, plus revue sécurité si la PR touche auth/sessions, permissions/RBAC, secrets, données personnelles (clients, adresses, téléphones, photos identifiables, au titre RGPD), stockage de fichiers, données financières, dépendance externe ou workflows CI. Un finding sécurité high/critical → label `needs-human`, pas d'auto-merge. Après un verdict `request_changes`, Claude corrige et pousse : la revue repart automatiquement
- **Pour toute question DB / Neon / queries / connexion / migration prod** → utiliser le skill `neon-postgres` au lieu de répondre depuis la mémoire
- **Phase 1.4 et 1.5 (UI web et mobile)** → utiliser le skill `frontend-design` quand on génère des écrans nouveaux pour éviter le rendu "AI générique"
- **Phase 6 (Devis) et au-delà, queries SQL complexes** → consulter `supabase-postgres-best-practices` (best practices Postgres génériques)

### Skills superpowers (harness Claude Code)

Les skills `superpowers:*` sont chargés au démarrage de session via le harness, donc disponibles sans installation côté projet. Ceux explicitement attendus dans le workflow Cordeau :

- **Avant toute nouvelle feature ou composant** (spec floue, design ouvert, choix d'archi à explorer) → `superpowers:brainstorming` avant de planifier ou coder. Sauf si la spec Notion est déjà validée et fermée
- **Plan macro par phase + plan par story non triviale** → `superpowers:writing-plans`. Le plan macro phase-level est couvert par l'étape 3 du [protocole de démarrage de phase](#protocole-de-démarrage-de-phase). En plus, pour chaque story dont l'implémentation dépasse 3 étapes non triviales, rédiger un plan story-level dans `~/.claude/plans/<slug>.md` (hors-repo, éphémère, non versionné). Frontière avec l'issue GitHub : l'issue (template `feature.yml`) capture le **quoi** (critères d'acceptation, contexte produit, références) ; le plan capture le **comment** (séquence d'étapes ordonnées, fichiers exacts, tests par étape, critère de done par étape, vérification end-to-end). En deçà de 3 étapes, le template issue suffit.
- **Investigations indépendantes en parallèle** → `superpowers:dispatching-parallel-agents` dès que 2+ explorations peuvent se mener sans état partagé. Cas d'usage Cordeau : auditer N bounded contexts en parallèle au moment d'un refactor, vérifier une convention sur N fichiers, charger plusieurs ADRs + memories en début de phase, investiguer cause racine sur deux layers techniques distincts en debug. Anti-patterns : ne pas dispatcher pour de l'implémentation (uniquement lecture/exploration), ne pas dispatcher des tâches dépendantes — si l'output d'un agent conditionne le prompt d'un autre, c'est séquentiel
- **Spike exploratoire ou implémentation longue à isoler** → `superpowers:using-git-worktrees`. Trigger : on veut tester une approche sans polluer la branche en cours, ou comparer deux approches sur la même story. **Préférer l'outil natif `EnterWorktree`** (cleanup auto via `ExitWorktree`) à `git worktree add` manuel. Anti-patterns : pas de worktree pour un fix court (< 30 min, surcharge cognitive supérieure au gain) ; pas de worktrees orphelins (cleanup systématique via `ExitWorktree` en fin de spike). Implication CI watch : un worktree = une branche distincte = un `gh run watch` séparé (cf section [Surveillance CI automatique](#surveillance-ci-automatique))
- **Avant d'écrire l'implémentation d'une story** → `superpowers:test-driven-development` (red → green → refactor, cohérent avec la règle "Pas de feature shippée sans test sur le domaine")
- **Sur tout bug, test qui échoue, ou comportement inattendu** → `superpowers:systematic-debugging` avant de proposer un fix. Si le bug sort du dev local, enchaîner avec l'audit système de test (cf [Bug-fix : protocole double-fix](#bug-fix--protocole-double-fix))
- **Avant de dire "c'est fait" / d'ouvrir une PR** → `superpowers:verification-before-completion` (lancer les commandes de vérification — tsc, tests, lint — et confirmer le output, pas se contenter d'asserter)
- **À réception d'une code review** (PR commentée par /review, /ultrareview, ou humain) → `superpowers:receiving-code-review` avant d'implémenter les suggestions (vérifier la rigueur technique avant l'agrément performatif)

Si une de ces règles s'applique mais que tu juges qu'elle ne sert à rien dans le cas précis, l'expliquer plutôt que de l'appliquer aveuglément.

## Aide-mémoire pour Claude Code

- Avant de proposer une lib externe : vérifier si elle est déjà dans le stack acté (cf ADRs)
- Avant de proposer une décision structurante : proposer un ADR d'abord
- Pour explorer le projet : **Serena (MCP)** pour la navigation sémantique (find_symbol, find_referencing_symbols, get_symbols_overview, rename_symbol) — voir [ADR 0009](docs/adr/0009-serena-mcp-outillage-semantique.md). Tomber sur grep/Read uniquement pour la recherche en texte plein (commentaires, strings, docs) ou l'édition de petits blocs. Pour les versions de libs externes, utiliser le serveur MCP Context7
- Serena tient ses propres memories versionnées dans `.serena/memories/` (project_overview, architecture, conventions). À lire en début de tâche complexe, et à mettre à jour en fin de phase au même rythme que `CLAUDE.md`
- Quand l'utilisateur référence une page Notion, utiliser le serveur MCP Notion (workspace Cordeau uniquement)
- Conventions FR : tout est en français (UI, doc, identifiants métier comme `Chantier`, `Devis`, `Client`)
- **Pas de hacks** : si quelque chose ne fonctionne pas, consulter Context7 MCP avant de coder un workaround. Cf [feedback memory](~/.claude/projects/-Users-MaximeG-Developer-cordeau/memory/feedback_no_hacks.md).
- **Ambiguïté en cours d'implémentation** : présenter les interprétations possibles plutôt que d'en choisir une silencieusement. `AskUserQuestion` couvre les décisions structurantes (étape 4 du protocole de phase) ; cette règle couvre les micro-choix d'implémentation
- **Surgical changes** : chaque ligne modifiée doit tracer à la demande ou au scope de la story en cours. Dead code ou style adjacent hors scope : mentionner, ne pas toucher. N'invalide pas la règle de refactor en cours de phase (étape 8) : refactor à l'intérieur du bounded context travaillé, pas drive-by sur du code adjacent
- **Pas de PR drive-by** : si une amélioration adjacente vaut le coup, ouvrir une issue séparée plutôt que de l'embarquer dans la PR en cours
- **Dispatch parallèle vs séquentiel** : explorations indépendantes sans état partagé → `superpowers:dispatching-parallel-agents`. Étapes dépendantes (chaque action utilise l'output de la précédente) → séquentiel, pas de dispatch
- **Trajectoire V1 manuelle → V1.2+ magie** : avant toute proposition de feature « IA / vocale / structuration automatique / chrono auto / détection risques », vérifier la trajectoire dans [ROADMAP.md](ROADMAP.md). V1 est manuelle, V1.1 fluidifie l'UX, V1.2 ajoute la magie LLM **conditionnée au critère de validation bêta** ([ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)). Ne pas court-circuiter — c'est un garde-fou explicite

## Évolution du workflow (retours d'expérience)

Le workflow n'est pas figé : quand une friction ou une erreur révèle un défaut de process, **la règle correspondante est ajoutée ici dans la même PR** (ou, si elle demande une décision, une issue + un ADR). Pas de leçon qui reste seulement dans une conversation. Entrées datées, les plus récentes en bas.

**2026-10-03 — nuit d'autonomie (#123, #153, #155, #156, #78)**

- **Claude ne fusionne pas ses propres PR** : le classifieur du mode auto bloque ce merge, même après revue locale. Le circuit voulu est PR → CI + revue `PR Review` → auto-merge ([ADR 0025](docs/adr/0025-reviewer-claude-auto-merge.md)). Ne jamais contourner un refus du classifieur par un autre outil ; le signaler et continuer le reste.
- **Premier maillon d'une chaîne de confiance** (workflow du reviewer, `CODEOWNERS`, `.claude/`) : toujours `needs-human`, et fusionné par le fondateur. Une PR ne doit jamais pouvoir réécrire son propre contrôle.
- **PR Dependabot** : le reviewer ne les relit pas (pas de secrets Dependabot). Pratique : reprendre leur contenu dans une PR du propriétaire (qui passe par le reviewer), puis fermer les PR Dependabot avec un lien. Ne pas fermer avant que la PR de reprise soit fusionnée.
- **Mise à jour de dépendances** : `pnpm update -r` réécrit les manifestes de façon non voulue (épingle `react` à la valeur d'un override, sort des plages Expo). Relire le diff des `package.json`, lancer `expo install --check` côté mobile et comparer au résultat de `main`, vérifier `pnpm audit`, `type-check`, `lint`, les tests et `expo export`. Une nouvelle version de Prettier peut reformater des fichiers existants : les reformater dans la même PR.
- **Montée de version d'un outil de contrat** (API Platform, openapi-typescript) : le contrat OpenAPI committé et les types partagés sont contrôlés par la CI (#154). Regénérer avec `pnpm --filter @cordeau/shared generate`, jamais à la main.
- **Contrainte de framework hors du repo** : une restriction posée par un plugin local (Symfony Flex) n'existe pas pour Dependabot. Toute contrainte qui compte doit vivre dans le manifeste (`conflict`) **et** avoir un garde CI (#158).
- **Commits** : ne jamais masquer la sortie des hooks (`> /dev/null`) : un échec de commitlint ou de Prettier est passé inaperçu. Types autorisés par commitlint : `feat, fix, chore, docs, refactor, test, perf, ci, build, revert` (pas `style`).
- **`/simplify` avant `gh pr create`** : peut être omis quand le diff ne contient que de la configuration, des lockfiles, de la documentation ou du code généré. Le dire explicitement dans la description de la PR.
- **Protection `strict` de `main`** : plusieurs PR prêtes en même temps obligent à les mettre à jour une par une, et chaque mise à jour relance une revue (quota Pro). Suivi : #163.
- **Sessions autonomes** : tester une commande Bash triviale en premier. Si tout Bash est bloqué, ne pas boucler : écrire le plan de reprise dans `~/.claude/plans/` et le signaler.
- **Une affirmation sur la prod se vérifie sur la prod** : l'ADR 0027 a affirmé que `SYMFONY_TRUSTED_PROXIES` n'était pas configuré, parce qu'il n'était ni dans `.env` ni dans `fly.toml` ; il l'était, comme secret Fly. Avant d'écrire qu'une configuration d'exécution est absente, la lire sur la prod (`flyctl secrets list`, `flyctl status`, `flyctl logs`) ; si l'accès manque (`flyctl auth whoami`), le dire au lieu de supposer. Après un déploiement qui corrige une faille, vérifier les endpoints publics (`/health`, 401 sans jeton) et l'état de la release.
- **Hook Stop de vérification** (#137) : [scripts/stop-verify.sh](scripts/stop-verify.sh), branché dans `.claude/settings.json`, bloque la fin de tour (code 2) si PHPStan (fichiers PHP modifiés), `tests/Unit`, les tests d'intégration modifiés ou `tsc` + tests liés (web, mobile) sont rouges. Mesuré : ~0,4 s sans changement, ~1,5 s sur du PHP, ~3,5 s sur du web. Les tests d'intégration non modifiés restent à la CI (ils exigent Postgres). Un second passage après blocage n'est pas rejoué (`stop_hook_active`) ; `CORDEAU_SKIP_STOP_VERIFY=1` désactive le hook ponctuellement.
- **Un transport de test `in-memory` masque l'absence de consommateur** (#124, ADR 0029) : en prod, `messenger:consume` ne tournait nulle part, donc aucune vignette photo n'était générée et aucun objet R2 supprimé, sans qu'un test ne le voie. Tout message routé vers `async` exige un process `worker` dans `apps/api/fly.toml` ; `ConsommateurMessengerTest` échoue sinon. Un nouveau message asynchrone se teste de bout en bout : émission (transport `in-memory`) et handler.
