# Décisions clés

Six décisions d'architecture et de process, tirées des [ADR](adr/README.md). Chacune renvoie à son ADR, qui fait foi : statut, amendements et conséquences y sont complets.

## 1. Offline-first : SQLite local et outbox

- **Contexte** : les artisans travaillent sans réseau fiable (sous-sols, zones rurales, cages d'escalier). Toute création ou modification doit rester possible hors ligne.
- **Choix** : SQLite local (expo-sqlite + Drizzle) et patron outbox. Les mutations s'écrivent localement, l'interface se met à jour tout de suite, un worker pousse l'outbox vers l'API avec des retries à backoff exponentiel. En cas de conflit, « last write wins » sur les timestamps serveur.
- **Écarté / assumé** : la gestion des conflits entre plusieurs utilisateurs est volontairement hors périmètre ; l'ADR assume deux sources de vérité à garder cohérentes.
- **ADR** : [0005](adr/0005-offline-first-sqlite-drizzle.md)

## 2. Tokens opaques en base plutôt que JWT

- **Contexte** : l'app mobile doit s'authentifier auprès de l'API, avec des données clients à protéger.
- **Choix** : access token d'1 h et refresh token de 30 jours à rotation, stockés en base sous forme hachée. Le vol d'un téléphone se traite par une révocation immédiate.
- **Écarté** : JWT sans révocation (jugé trop risqué), et JWT avec liste de révocation (même complexité que les tokens opaques, sans leurs avantages).
- **Amendé le 2026-10-04** : le cache Redis de 60 s prévu dans le texte d'origine n'existe pas.
- **ADR** : [0003](adr/0003-tokens-opaques-mobile.md)

## 3. Lots optionnels sous le Chantier

- **Contexte** : un chantier peut mêler plusieurs modes de facturation (au temps, à la surface, au forfait) ; un `Chantier` plat ne le permet pas.
- **Choix** : le `Lot` est une entité fille optionnelle (1-N) du `Chantier`, qui garde ses champs et son API. Le découpage en Lots et Tâches n'est imposé que quand l'artisan en a besoin.
- **Écarté** : conserver un `Chantier` plat avec des champs en plus (casse dès qu'il y a deux modes) ; faire du `Lot` l'agrégat central (refonte profonde des phases 1 et 2 pour un gain surtout conceptuel).
- **ADR** : [0015](adr/0015-modele-chantier-lots-taches-mesures.md)

## 4. Pas d'IA en V1

- **Contexte** : des fonctions pilotées par un LLM étaient envisagées dès les premiers mois. Elles brouillent ce que la bêta mesure et ajoutent un coût variable avant toute validation de la demande.
- **Choix** : aucune dépendance LLM en V1. La magie LLM (V1.2+) est conditionnée à un critère de validation défini hors du dépôt : la valeur doit être démontrée en saisie manuelle.
- **Écarté** : l'IA dès la V1, et l'IA optionnelle derrière un interrupteur (deux chemins d'interface, tests doublés, coût non éliminé).
- **Amendé le 2026-10-04** : les critères chiffrés, hypothèses de prix et détails de recrutement ont été retirés du dépôt public.
- **ADR** : [0017](adr/0017-differer-ia-validation-manuelle.md)

## 5. Durcissement de la chaîne CI/CD

- **Contexte** : la CI déploie en production (API sur Fly.io avec migration au `release_command`, web sur Cloudflare Pages). Un audit de mai 2026 a relevé, entre autres, un déploiement possible sur CI partiellement rouge, des actions sur références mutables et un `GITHUB_TOKEN` aux droits par défaut.
- **Choix** : le déploiement dépend de `ci-pass`, les permissions sont `contents: read` par défaut, les actions sont épinglées par SHA (vérifié par `scripts/check-conventions.sh`), la concurrence de déploiement n'annule jamais une migration en cours. Dependabot, audits de dépendances, CodeQL et hooks Lefthook complètent.
- **Écarté** : le filtrage par chemin dans le monorepo (pièges sur les changements transverses, coût en minutes négligeable pour un développeur seul) ; Husky + lint-staged au profit de Lefthook.
- **ADR** : [0019](adr/0019-durcissement-ci-cd.md)

## 6. Revue automatisée et auto-merge

- **Contexte** : l'agent de développement ne peut pas fusionner sa propre PR, et le fondateur, seul développeur, ne veut pas fusionner à la main.
- **Choix** : le workflow `pr-review.yml` fait relire chaque PR interne par Claude dans un contexte frais. Il rend un verdict structuré (`approve`, `request_changes`, `needs_human`) ; une étape shell séparée approuve et active l'auto-merge GitHub, qui attend la CI verte et une approbation. Le workflow tourne sous `pull_request_target` sans jamais exécuter le code de la PR, et toute PR touchant le workflow lui-même, `CODEOWNERS` ou `.claude/` passe en `needs_human`.
- **Écarté** : une règle locale autorisant `gh pr merge` (sans revue indépendante), le merge par le `GITHUB_TOKEN` (il ne déclenche pas le déploiement), une GitHub App dédiée (plus lourde pour un seul développeur).
- **Risque assumé** : un faux `approve` atteint la production sans regard humain ; la CI et le label `needs-human` sont les seuls filets.
- **ADR** : [0025](adr/0025-reviewer-claude-auto-merge.md)
