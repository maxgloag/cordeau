# ADR 0025 — Reviewer Claude automatisé et auto-merge des PR

- **Status** : Accepted
- **Date** : 2026-10-02
- **Deciders** : Maxime
- **Lié à** : [ADR 0019](0019-durcissement-ci-cd.md) (durcissement CI/CD), issue #156

## Context

Le workflow de dev est piloté par un agent Claude Code : branche → PR → CI → `/review` → merge. Le dernier maillon bloque systématiquement :

- Le **classifieur du mode auto** de Claude Code refuse que l'agent fusionne sa propre PR (« merge sans revue »), même après `/review`, `/security-review` et une CI verte. La revue faite dans la session n'est pas une preuve qu'il peut vérifier.
- Le fondateur, seul développeur, **ne veut pas fusionner à la main**. Chaque PR attend donc qu'il intervienne, alors qu'elle est prête.

État du dépôt au 2026-10-02 :

- `main` protégée : CI verte requise (`ci / pass`), **0 approbation requise**.
- Auto-merge GitHub désactivé ; les Actions ne peuvent pas approuver de PR.
- Chaque `push` sur `main` déclenche le déploiement prod (`deploy-api` Fly.io, `deploy-web` Cloudflare Pages).
- Dépôt **public**.

## Decision

### Une revue indépendante, tracée sur la PR, qui conditionne un merge côté serveur

1. **Workflow `pr-review.yml`** (événement `pull_request`) : `anthropics/claude-code-action` relit le diff dans un contexte frais et rend un **verdict structuré** (`--json-schema`) : `approve` / `request_changes` / `needs_human`, avec résumé et findings.
2. **Revue sécurité dans le même passage** : si la PR touche auth/sessions, permissions, secrets, données personnelles (clients, adresses, téléphones, photos), stockage de fichiers, données financières, dépendances externes ou workflows CI, le reviewer applique en plus les critères de `/security-review` (CLAUDE.md, section Automatisation skills). Un finding critique ou haut → `needs_human`.
3. **Étape d'approbation séparée** : l'action Claude ne peut pas approuver par conception. Une étape `gh pr review --approve` (`GITHUB_TOKEN`, identité `github-actions[bot]`) ne s'exécute que si `verdict == approve`. Sinon : commentaire sur la PR et, pour `needs_human`, label `needs-human`.
4. **Auto-merge GitHub** activé dans la même étape (`gh pr merge --auto --squash`) avec un **PAT fine-grained** (`AUTOMERGE_TOKEN`). GitHub fusionne quand toutes les conditions de protection sont remplies : CI verte **et** 1 approbation.
5. **Protection de `main`** : 1 approbation requise. `dismiss_stale_reviews` (déjà actif) annule l'approbation à chaque nouveau push, ce qui relance la revue sur le nouveau diff.

L'agent local se contente désormais de créer la PR. Il ne fusionne plus.

### Pourquoi un PAT pour l'auto-merge

Un merge effectué avec le `GITHUB_TOKEN` **ne déclenche aucun workflow** (règle GitHub anti-récursion). Le `push` sur `main` ne lancerait donc plus `deploy-*`. Activer l'auto-merge avec un token au nom du fondateur rend le merge équivalent à un merge manuel, et le déploiement reste inchangé.

### Périmètre

- PR **internes uniquement** (`head.repo == repo`), auteur = propriétaire du dépôt. Les PR de forks ne reçoivent ni secrets ni revue automatique.
- Les PR **Dependabot** n'ont pas accès aux secrets Actions : pas de revue automatique, elles restent manuelles (#78, #155).
- Authentification Claude par **`CLAUDE_CODE_OAUTH_TOKEN`** (abonnement Pro du fondateur) : une revue par PR et par push, sur le quota de l'abonnement.

### Choix écartés

- **Règle locale `autoMode.allow` / permission `gh pr merge *`** : immédiate, mais sans revue indépendante ni trace côté GitHub, et elle contourne précisément le garde-fou qui a bloqué.
- **`gh pr merge --auto` lancé par l'agent local** : rien ne garantit que le classifieur l'accepte, et le merge dépendrait encore de la session locale.
- **Merge par le `GITHUB_TOKEN`** : casse le déploiement (voir plus haut).
- **GitHub App dédiée** au lieu d'un PAT : plus propre (tokens courts), mais plus lourde à mettre en place pour un seul développeur. À reconsidérer si un second contributeur arrive.

## Implications sécurité

Décision touchant permissions, secrets, données personnelles (le reviewer lit des diffs qui peuvent en concerner) et dépendance externe → section obligatoire.

- **Surface d'attaque ajoutée** :
  - nouvelle action tierce `anthropics/claude-code-action`, épinglée par SHA (ADR 0019) ;
  - **le merge en prod ne requiert plus d'humain** : un défaut que la CI et le reviewer laissent passer part en prod sans regard humain ;
  - **injection de prompt** : le diff est une entrée du reviewer. Mitigé ainsi : seules les PR internes du propriétaire sont revues, le verdict est un JSON validé par schéma, Claude n'a que des outils en lecture (`gh pr diff`, `gh pr view`) plus le commentaire de PR, et l'approbation est faite par une étape shell séparée, jamais par le modèle.
- **Secrets manipulés** :
  - `CLAUDE_CODE_OAUTH_TOKEN` : accès à l'abonnement Claude du fondateur, exposé au seul job de revue ;
  - `AUTOMERGE_TOKEN` : PAT fine-grained limité à **ce dépôt**, permissions `Contents: write` + `Pull requests: write`, expiration ≤ 1 an. Il n'est exposé qu'à l'étape d'auto-merge, qui ne s'exécute qu'après un verdict `approve`. Rotation : à l'expiration, ou immédiatement en cas de doute (`docs/runbooks/rotation-secrets.md`).
  - les secrets ne sont pas disponibles aux PR de forks (événement `pull_request`, pas `pull_request_target`).
- **Données personnelles** : le reviewer envoie le diff (code, fixtures) à l'API Anthropic. Le code ne doit pas contenir de données personnelles réelles (fixtures Foundry factices). Base légale RGPD sans objet tant que c'est le cas ; à revoir si des données réelles entrent dans le dépôt.
- **Points de fuite potentiels** : logs Actions (ne jamais afficher les tokens), commentaires de revue publics (dépôt public : ils ne doivent citer aucun secret, ce qui est déjà le cas du code).

## Consequences

**Positives**

- Plus aucune PR bloquée en attente d'un merge manuel ; l'agent enchaîne les issues.
- Chaque merge porte une revue indépendante **tracée** sur la PR (approbation + commentaire), ce que la revue locale ne laissait pas.
- La revue sécurité exigée par le CLAUDE.md devient systématique au lieu de dépendre de la vigilance de l'agent.

**Négatives / à surveiller**

- Consommation du quota Pro : une revue par push. `concurrency` annule les revues obsolètes. Si le quota devient un frein, passer à `ANTHROPIC_API_KEY` facturée à l'usage.
- Le reviewer peut se tromper dans les deux sens. Le label `needs-human` et la CI restent les filets ; un faux `approve` atteint la prod.
- Deux actions manuelles hors dépôt (non versionnables) : créer les deux secrets, et ajuster les réglages du dépôt (auto-merge, approbation par Actions, 1 approbation requise).
