# Résultats — Expérience B : même spec en PHP et en Rust, évaluée en aveugle

> Expérience du [protocole](protocole.md), sur la [spec « totaux de devis »](spec-totaux-devis.md), menée le 2026-10-02.
>
> - **Implémenteurs** : deux agents Claude Opus 5.5 indépendants, avec les mêmes consignes et le même budget.
> - **Oracle** : un troisième agent a écrit une implémentation de référence en Python (`decimal`) et 302 cas de test cachés, sans voir les implémentations.
> - **Évaluation** : faite par le coordinateur, de façon mécanique (cas cachés, test différentiel, sondes, mutation testing, PHPStan max).
> - **Journaux bruts** : [B-PHP](journal-b-php.md) · [B-Rust](journal-b-rust.md) · [outillage de l'oracle](oracle/).
> - **Expérience A non menée.** Le portage de la PR #105 avec Postgres a été interrompu deux fois par la limite d'usage de la session, puis abandonné faute de fenêtre. Les worktrees avaient été nettoyés.

## 1. Correction : égalité parfaite

| Mesure                                                                                                                                    | B-PHP                                                                                            | B-Rust          |
| ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | --------------- |
| 302 cas cachés : nominal, arrondis aux frontières `x,xx5`, ventilation de remise, franchise, bornes, formats invalides, erreurs combinées | **302 / 302**                                                                                    | **302 / 302**   |
| Test différentiel : 3 000 entrées aléatoires (751 calculs, 2 249 erreurs), comparées à la référence Python                                | **0 désaccord**                                                                                  | **0 désaccord** |
| 15 sondes exotiques ciblées (Unicode, BOM, surrogates, clés `\u0000`, `1e400`, 5 000 lignes…)                                             | 1 écart propre : JSON valide avec une clé `\u0000` rejeté, à cause d'une limite de `json_decode` | 0 écart propre  |
| PHPStan **niveau max** (10) sur le code produit                                                                                           | 0 erreur                                                                                         | —               |
| `clippy -D warnings` (pedantic et restriction)                                                                                            | —                                                                                                | 0 avertissement |

Les deux autres écarts relevés par les sondes ne sont pas des défauts des implémentations :

- **Trim de `\x1c`** : seule la référence Python le supprime (particularité de `str.strip()`). PHP et Rust s'accordent.
- **Surrogate UTF-16 isolé** : PHP et Rust le rejettent, Python l'accepte. La RFC 8259 laisse le comportement indéfini.

**Lecture.** Avec un modèle de 2026 et une spec précise, **les deux agents atteignent zéro défaut détectable** sur un domaine de calcul exigeant : arrondis au centime, ventilation au prorata, validation exhaustive. Le seuil Q-A (« Rust ≤ 50 % des défauts de PHP ») ne peut pas être franchi : 0 contre 0.

## 2. Comment chaque agent y est arrivé : la vraie différence

|                                                                                                | B-PHP                                                                                                                                                                                                                       | B-Rust                                                                                                                                                                                                                                                                                  |
| ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Durée active                                                                                   | ≈ 35 min                                                                                                                                                                                                                    | ≈ 31 min                                                                                                                                                                                                                                                                                |
| Boucles de vérification                                                                        | 22                                                                                                                                                                                                                          | 26                                                                                                                                                                                                                                                                                      |
| Défauts attrapés par l'analyse statique qui auraient été des bugs à l'exécution                | 0                                                                                                                                                                                                                           | 0                                                                                                                                                                                                                                                                                       |
| Défauts attrapés **seulement par les tests**                                                   | **1** : avec `-q` ou `SHELL_VERBOSITY=-1`, la commande Symfony n'écrivait rien sur stdout tout en sortant en 0                                                                                                              | 0                                                                                                                                                                                                                                                                                       |
| Pièges du langage ou de la bibliothèque standard évités **par sondes manuelles avant le code** | **5** : `BcMath\Number` accepte `".5"` ; le `$` PCRE accepte un `\n` final ; `json_decode` en tableau associatif confond `{}` et `[]` ; un trim Unicode par regex devient quadratique sans JIT ; la division bcmath tronque | 2 : `rust_decimal` perd de la précision **silencieusement** en cas de débordement (écarté au profit de `BigInt`) ; serde_json rejette tout le document sur `1e400` sans la feature `arbitrary_precision`                                                                                |
| Défaut d'**environnement de prod**                                                             | **1** : l'extension bcmath était absente de l'image Docker. Tout était vert en local, la prod aurait planté (`Class "BcMath\Number" not found`). Corrigé dans `composer.json`, le `Dockerfile` et la CI                     | 0 : binaire statique, aucune extension système                                                                                                                                                                                                                                          |
| Où vit la garantie                                                                             | Dans la **vigilance de l'agent**. Aucun des 5 pièges n'est signalé par PHPStan, ni au niveau 9 ni au niveau max                                                                                                             | Dans les **types**. `DemandeDevis` n'est constructible que par `valider()`, donc le calcul ne peut pas échouer. La division par zéro est impossible (`DenominateurPositif`). Une erreur de ligne sans index est impossible. Un `match` exhaustif garantit au moins une erreur par refus |

**Lecture.**

- **Même résultat, mécanismes différents.**
  - En PHP, la correction a reposé sur la diligence d'un agent soigneux : 5 sondes anticipées et un test de robustesse du contrat CLI. Aucun outil ne l'y obligeait.
  - En Rust, le journal le dit : « le typage n'a pas _attrapé_ de bug, la conception a rendu les bugs classiques _irreprésentables_ avant d'écrire le code ».
  - L'expérience ne peut pas mesurer ce que deviendrait chaque implémentation sous un agent moins soigneux, un modèle plus faible, une pression de temps ou une modification de spec par une autre session (n = 1). Les expériences C et D, et des runs répétés, restent nécessaires (revue croisée §6).
- **Le défaut d'environnement est le signal le plus net.** Une dépendance d'extension système, invisible en local et fatale en prod, appartient exactement à la classe qui domine l'historique de bugs de Cordeau (#81 : extension image absente en prod). Le modèle « un binaire statique » de Rust supprime cette classe pour les dépendances en Rust pur. Il ne la supprime pas pour les bibliothèques natives liées comme libheif. **Ce point nuance le §2.2 (g) du rapport** : le gain de Rust sur l'environnement est marginal en général, mais réel sur cette classe précise. Côté PHP, le remède est un smoke test de l'image de prod, déjà recommandé.

## 3. Vitesse de la boucle de feedback, à l'échelle de Cordeau

| Mesure                                                                       | B-PHP                                                                                           | B-Rust                                          |
| ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| Vérification statique incrémentale après modification d'une ligne du domaine | PHPStan (projet entier) ≈ **0,8 s** (2,9 s à froid)                                             | `cargo check` ≈ **0,14 s**                      |
| Build à froid                                                                | —                                                                                               | 4,2 s (7 s avec téléchargement des dépendances) |
| Suite de tests                                                               | 0,04 s (0,15 s avec les tests CLI)                                                              | 1,8 s (dont tests de contrat sur le binaire)    |
| Exécution d'un appel CLI                                                     | ≈ 120 ms : démarrage du kernel Symfony par processus, non représentatif de FrankenPHP en worker | ≈ 11 ms                                         |

**Lecture.**

- À l'échelle d'un domaine de quelques milliers de lignes, **le temps de compilation n'est pas un sujet**.
- Le chiffre de GitHub (120 s de médiane par `cargo check`/`cargo test`) porte sur 830 k lignes. Le risque apparaît avec la taille de l'API complète et ses dépendances (Axum, SQLx, Tokio), pas sur le domaine. L'expérience A devait le mesurer.

## 4. Force des tests

| Mesure                                 | B-PHP                                                                                                                  | B-Rust                                                                                                                                                                                         |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Nombre de tests                        | 207 tests, 279 assertions (Devis)                                                                                      | 76 tests : 63 domaine dont 2 proptests, 13 de contrat sur le binaire                                                                                                                           |
| Mutation testing                       | **Non mesuré** : pas de pilote de couverture (pcov ou xdebug) installé, et PHPUnit 12 ne prend plus en charge `phpdbg` | `cargo-mutants` : 152 mutants. **122 tués, 1 survivant (équivalent : `Montant::zero` remplacé par `Default::default()`), 29 non viables (rejetés par le compilateur)**. Score effectif ≈ 100 % |
| Tests différentiels écrits par l'agent | 6 000 demandes aléatoires + 1 demande de 5 000 lignes contre sa propre référence Python                                | 400 000 cas aléatoires (exploration d'un invariant)                                                                                                                                            |
| Faiblesse relevée par l'agent lui-même | —                                                                                                                      | Le proptest d'invariants passait **sur un stub qui renvoyait tout à zéro** : seuls les exemples calculés à la main vérifiaient réellement les valeurs                                          |

> Les scores de mutation ne sont pas comparables d'un outil à l'autre (revue croisée §2.5). La donnée intéressante côté Rust : **19 % des mutants ne compilent même pas**, le système de types élimine une partie de l'espace d'erreurs avant les tests.

## 5. Taille et dépendances

|                      | B-PHP                                            | B-Rust                                                                                                                                                         |
| -------------------- | ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Lignes de source     | 1 483 brutes (984 utiles), 37 fichiers           | 1 255                                                                                                                                                          |
| Lignes de tests      | 1 471 brutes (1 156 utiles)                      | 1 633                                                                                                                                                          |
| Dépendances ajoutées | 0 bibliothèque, **1 extension système** (bcmath) | 5 crates directes (num-bigint, num-integer, num-traits, serde, serde_json) + proptest en dev ; 14 crates externes dans le graphe, 51 paquets dans `Cargo.lock` |

## 6. Ce que l'expérience change (ou pas) à la recommandation

1. **H1 (moins de défauts en Rust) : non démontré.** Égalité à zéro défaut, avec n = 1. Cohérent avec la littérature : sur un domaine bien spécifié, un agent de pointe atteint la correction dans les deux langages.
2. **H2 (le compilateur attrape en amont ce que PHP ne voit qu'aux tests) : nuancé.**
   - Ni le compilateur ni PHPStan n'ont attrapé de défaut d'exécution « en flagrant délit ».
   - En revanche, Rust **a déplacé la garantie dans la conception** : états impossibles, fonction de calcul totale.
   - PHP l'a laissée à la **diligence de l'agent** : 5 pièges de la bibliothèque standard, invisibles pour PHPStan même au niveau max.
   - C'est l'argument « solide à long terme » le plus concret de l'étude. Il reste qualitatif.
3. **H3 (friction d'écosystème) : faible sur du pur domaine.** Deux pièges Rust réels (`rust_decimal`, serde_json), un faux positif de clippy sur `BigInt`, et aucune friction de compilation. La friction DB/HTTP n'est pas mesurée.
4. **Actifs directement réutilisables pour l'option O1, quelle que soit la suite** :
   - les 302 vecteurs et la référence Python servent de **fixtures de conformité** pour les totaux de devis de la Phase 8 ;
   - la correction `ext-bcmath` (Dockerfile, CI, `composer.json`) est **indispensable** si la Phase 8 utilise bcmath ;
   - la liste des pièges PHP doit entrer dans les règles du harness (regex en `\z`, `json_decode` en objet, constructeur `Number` précédé d'une regex stricte).

## 7. Où trouver le code

| Implémentation                                                                                   | Branche locale                     | Worktree                                    |
| ------------------------------------------------------------------------------------------------ | ---------------------------------- | ------------------------------------------- |
| B-PHP (`apps/api/src/Domain/Devis`, `Application/Devis`, commande `app:experience:totaux-devis`) | `worktree-agent-ab6ecd713ce85860f` | `.claude/worktrees/agent-ab6ecd713ce85860f` |
| B-Rust (`spikes/rust-devis/`)                                                                    | `worktree-agent-a10cbcf5100a20564` | `.claude/worktrees/agent-a10cbcf5100a20564` |

Rien n'a été poussé. Pour rejouer l'évaluation **avec les vecteurs exacts utilisés** (archive `oracle/vecteurs.json.gz`) :

```bash
cd docs/etudes/rust-agentique/experience/oracle
gunzip -k vecteurs.json.gz                                  # vecteurs.json est ignoré par git
python3 executer.py "<chemin du worktree>/experience/run.sh"
```

`generer.py` produit un jeu équivalent mais pas identique octet pour octet : l'oracle a retouché le fichier après génération. Pour reproduire l'expérience, c'est l'archive qui fait foi.

Les chemins codés en dur dans `differentiel.py` et `sondes.py` pointent vers les worktrees ci-dessus.
