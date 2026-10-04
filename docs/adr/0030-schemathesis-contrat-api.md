# ADR 0030 — Schemathesis contre l'image de production, en observation

- **Status** : Accepted (choix du fondateur le 2026-10-04 : périmètre « Schemathesis seul », en observation d'abord)
- **Date** : 2026-10-04
- **Deciders** : Maxime
- **Contrôle** : `scripts/smoke-image.sh`
- **Lié à** : issue #131, [ADR 0029](0029-worker-messenger-prod.md) (smoke test de l'image, #139), [ADR 0019](0019-durcissement-ci-cd.md) (dépendances)

## Context

Le contrat OpenAPI committé (`packages/shared/openapi.json`) est vérifié contre l'export de l'API (#154), mais **personne ne vérifie que l'API respecte ce contrat** : aucun test de fuzzing, aucun test HTTP indépendant de PHP.

Un premier essai local (Schemathesis 4.29.1, 60 s, API en `prod`) a trouvé 25 écarts, dont deux erreurs `500` réelles :

- `POST /api/chantiers` avec une valeur numérique extrême (`surfaceM2` à `6.5e307`) répond 500 ;
- `POST /api/clients` avec un corps non valide en `application/ld+json` répond 500 au lieu de 400.

Les autres écarts sont des statuts non documentés (404, 415), des paramètres `page` invalides acceptés, et des requêtes conformes rejetées en 422.

## Decision

**Schemathesis, version épinglée, lancé en CI comme dernière phase du smoke test de l'image de production, en observation : il ne fait jamais échouer le job.**

- La phase est portée par `scripts/smoke-image.sh` (activée par `CONTRACT_TEST=1`, posé par le job `ci / image`) : l'image, Postgres et le process `app` de `fly.toml` y sont déjà démarrés. Le serveur testé est celui de la production (FrankenPHP), pas le serveur intégré de PHP.
- Schemathesis est installé dans un venv jetable (`pip install schemathesis==4.29.1`, version exacte).
- Le spec testé est `packages/shared/openapi.json` ; le jeton vient d'un compte créé par `app:user:create` puis de `/auth/login` (ce endpoint n'est pas dans le contrat).
- Le résultat est publié dans le résumé du job (`GITHUB_STEP_SUMMARY`) ; le journal complet reste dans les logs du job.
- Chaque `500` trouvé devient une issue `fix:`, avec le protocole double-fix. Les écarts de documentation (statuts non documentés) se traitent en documentant le contrat ou en filtrant le check.
- **Passage en bloquant** : quand le bruit est maîtrisé (`500` corrigés, statuts documentés ou filtrés), la phase devient un échec du job. Décision du fondateur, notée dans cet ADR.

## Consequences

- +1 à 2 minutes sur le job `ci / image`.
- Du bruit au départ : une API jamais fuzzée produit beaucoup de findings. L'observation évite de bloquer les PR pendant le tri.
- Le fuzzing est aléatoire : une graine différente peut révéler de nouveaux écarts à chaque run. La graine est affichée pour rejouer (`--seed`).
- Le mode stateful n'est pas validé : le contrat ne décrit presque aucun lien entre opérations (« API Links : 4 covered / 68 »).

## Implications sécurité

- **Dépendance externe** : un paquet PyPI (Schemathesis), exécuté en CI uniquement. Version exacte épinglée ; les empreintes (`--require-hashes`) ne sont pas utilisées : limite connue, à durcir si la phase devient bloquante.
- **Télémétrie** : la version 4 a retiré le service hébergé Schemathesis.io et ses options de téléversement (guide de migration v3 → v4). Le script n'active aucun rapport distant.
- **Secrets** : aucun. Le secret d'application et le mot de passe du compte de test sont des valeurs jetables, la base est éphémère, `GITHUB_TOKEN` reste en lecture seule.
- **Données** : aucune donnée réelle ; le fuzzing n'écrit que dans la base éphémère.
- **Surface d'attaque** : le fuzzer ne cible que `127.0.0.1`. Il ne doit **jamais** être pointé vers la production ou un staging avec de vraies données (il écrit et supprime).
