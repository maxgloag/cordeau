# ADR 0019 — Durcissement de la chaîne CI/CD

- **Status** : Accepted
- **Date** : 2026-05-30
- **Deciders** : Maxime
- **Lié à** : [ADR 0008](0008-trajectoire-hebergement.md) (hébergement Fly.io + Cloudflare), [ADR 0018](0018-documentation-architecture-as-code.md) (enforcement en CI)

## Context

La CI (`.github/workflows/ci.yml` + `pr-checks.yml`) a été montée incrémentalement au fil des phases. Un audit de fin mai 2026, mené avec le regard « projet d'entreprise », a confirmé des fondations saines (jobs parallèles `format`/`api`/`web`/`mobile`, agrégation `ci-pass`, services Postgres/Redis avec healthchecks, caches composer/pnpm, Deptrac pour l'archi hexagonale) mais a relevé plusieurs écarts avec les standards attendus sur un pipeline qui **déploie en production** (Fly.io pour l'API avec migration de schéma au `release_command`, Cloudflare Pages pour le web).

Écarts retenus comme à corriger :

1. **Gating de déploiement incomplet** : `deploy-api needs: [api]` et `deploy-web needs: [web]` uniquement. Un CI partiellement rouge (ex. `format` ou `web` cassés, `api` vert) déclenche quand même le déploiement de l'API en prod, migration de schéma comprise.
2. **Actions sur refs mutables** : `superfly/flyctl-actions/setup-flyctl@master`, `browser-actions/setup-chrome@latest`. Surface d'attaque supply-chain (exécution de code tiers non figé au moment du run).
3. **`GITHUB_TOKEN` aux permissions par défaut** (write implicite), aucun bloc `permissions:`.
4. **Aucune mise à jour automatisée des dépendances** (npm/composer/actions) ni scan de vulnérabilités, alors que le produit manipule des données personnelles (clients, adresses, photos) soumises au RGPD.
5. **Job `mobile` qui ne valide rien** : le « build check » est un `echo`, les tests sont `jest --passWithNoTests`. Les erreurs de bundling Metro / plugins natifs / transformers babel passent la CI (trou déjà documenté en mémoire de travail).
6. **Pas de garde-fous d'exécution** : aucun `timeout-minutes`, concurrency de déploiement partagée avec celle du CI (risque d'annulation d'un déploiement en plein `release_command` / migration).
7. **Pas de feedback local avant push** (shift-left) : tout repose sur la CI ; les échecs `format:check`/lint sont découverts après le push.

Le choix structurant à trancher était le périmètre et deux options d'outillage (runner de hooks pre-commit ; filtrage par chemin dans le monorepo).

## Decision

On durcit le pipeline en quatre lots, sans changer son architecture générale (jobs parallèles + `ci-pass` agrégateur conservés).

### Lot 1 — Sécurité du pipeline

- **Gating** : `deploy-api` et `deploy-web` dépendent désormais de `ci-pass` (et non plus du seul job de leur app). Le déploiement ne part que si **l'ensemble** du CI est vert.
- **Permissions** : `permissions: contents: read` par défaut au niveau workflow ; élargissement explicite par job uniquement si nécessaire (ex. `security-events: write` pour CodeQL).
- **Pinning par SHA** : toutes les actions tierces sont épinglées par SHA de commit complet, avec le tag lisible en commentaire. Les `@master` / `@latest` disparaissent.
- **`timeout-minutes`** sur chaque job (hygiène contre les jobs qui pendent).
- **Concurrency de déploiement dédiée** et **sans `cancel-in-progress`** : un déploiement en cours (donc une migration en cours) ne peut pas être annulé par un push suivant. Le CI de PR garde son `cancel-in-progress`.

### Lot 2 — Sécurité des dépendances

- **`dependabot.yml`** sur trois écosystèmes : `npm` (pnpm), `composer`, `github-actions` (ce dernier maintient à jour les SHA du Lot 1).
- **Audit de vulnérabilités** en CI : `composer audit` (job api) et `pnpm audit` (non bloquant au départ pour éviter le bruit des advisories transitives, à durcir ensuite).
- **CodeQL** (SAST natif GitHub) sur le code JS/TS, en workflow dédié.

### Lot 3 — Validation mobile réelle

Le job `mobile` lance un **`expo export`** (ou équivalent bundling) en plus de lint + tests, pour attraper les erreurs de bundling Metro, plugins natifs et transformers que `tsc`/`jest` ne voient pas. Cohérent avec le protocole double-fix : un bug mobile qui passe aujourd'hui la CI est par construction un défaut du système de test.

### Lot 4 — Confort & vitesse

- **Hooks pre-commit via Lefthook** (et non Husky/lint-staged). Justification : binaire Go rapide, config YAML unique, à l'aise pour orchestrer du PHP (composer/PHPStan côté `api`) **et** du JS/TS dans le même monorepo sans surcouche Node. Le hook lance au minimum `prettier --check` + lint sur les fichiers concernés, et `commitlint` sur le message (commit-msg).
- **Cache Turbo en CI** (`actions/cache` sur `.turbo`) pour mémoïser builds/lints/tests entre runs.

### Choix écartés

- **Filtrage par chemin** (turbo `--filter ...[origin/main]` ou `dorny/paths-filter`) : **rejeté pour l'instant**. En contexte solo, le coût en minutes du full matrix est négligeable et le filtrage introduit des pièges sur les changements transverses (shared, lockfile, config racine). On réévaluera si le temps de CI devient gênant.
- **Husky + lint-staged** : écarté au profit de Lefthook (cf supra).

## Implications sécurité

Décision touchant secrets, permissions et dépendances externes → section obligatoire (cf CLAUDE.md).

- **Surface d'attaque ajoutée** : Dependabot ouvre des PR automatiques (ne peuvent pas merger seules — `main` protégée, CI + revue requises) ; CodeQL et Lefthook sont des dépendances d'outillage supplémentaires. Le pinning par SHA **réduit** la surface existante (plus d'exécution de code tiers non figé).
- **Secrets manipulés** : `FLY_API_TOKEN`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `VITE_SENTRY_DSN`. La restriction `permissions: contents: read` par défaut limite ce qu'un workflow compromis pourrait faire avec le `GITHUB_TOKEN`. Les secrets de déploiement restent scopés aux jobs `deploy-*` (push sur `main` uniquement). Migration ultérieure possible vers des GitHub Environments pour scoper davantage.
- **Données personnelles** : aucune donnée RGPD ne transite par la CI (pas de dump prod en CI ; base de test éphémère avec données synthétiques). Le scan CodeQL/audit contribue **indirectement** à la protection des données perso en réduisant le risque de vulnérabilité shippée.
- **Points de fuite potentiels** : logs de CI (ne jamais y echo de secret), et le `release_command` de migration. Le gating sur `ci-pass` + concurrency de déploiement non annulable réduisent le risque d'un déploiement/migration partiel ou concurrent.

## Consequences

**Bénéfices** :

- Plus de déploiement prod sur CI partiellement rouge ; plus de migration interruptible par un push concurrent.
- Surface supply-chain réduite (SHA figés), token CI au moindre privilège.
- Veille de sécurité continue (Dependabot + audit + CodeQL) alignée sur l'exigence RGPD du projet.
- Le job mobile valide enfin le bundling réel.
- Boucle de feedback locale (Lefthook) : moins de commits « chore: format » correctifs après push.

**Trade-offs / coûts** :

- Maintenance des SHA d'actions → automatisée par Dependabot (écosystème `github-actions`).
- Bruit potentiel de PR Dependabot (mitigé par regroupement et cadence hebdomadaire).
- `expo export` allonge le job mobile (quelques dizaines de secondes), accepté contre la valeur de détection.
- Lefthook ajoute une étape d'install locale (`pnpm` postinstall ou `lefthook install`) ; contournable via `--no-verify` en cas d'urgence (assumé).

**Décisions différées** :

- **GitHub Environments** (protection rules, required reviewers, secrets scopés) : pertinent quand un second contributeur rejoint ou en approche de prod payante.
- **Seuil de couverture** en CI : non posé pour l'instant (`coverage: none` conservé côté PHP).
- **Smoke test post-déploiement + rollback** automatique : à ajouter quand un endpoint `/health` stable sera en place.
- **Push protection / secret scanning** côté settings du repo : à activer hors-repo (non versionnable), noté comme action manuelle.
- **Durcissement de `pnpm audit` en bloquant** : une fois le bruit des advisories transitives maîtrisé.
