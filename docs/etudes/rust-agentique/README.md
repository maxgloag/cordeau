# Étude — Passer Cordeau en Rust pour un développement agentique solide ?

- **Date** : 2026-10-02
- **Commanditaire** : Maxime
- **Statut** : étude (aide à la décision). Ce n'est **pas** un ADR : aucune décision n'est actée ici.
- **Branche** : `chore/etude-rust-agentique`

> **La question posée** : faut-il passer en Rust « tout ce qui est possible » de Cordeau ? Le but n'est pas d'aller plus vite. C'est de rendre le développement avec agents (Claude Code) plus efficace et surtout **plus solide**, pour aller le plus loin possible.
>
> **Méthode.**
>
> - **Recherche** : 9 notes indépendantes, chacune produite par un agent (preuves empiriques, adoption industrielle, stack backend, front/mobile/offline, outillage agentique, red team, alternatives, outils de recherche, inventaire du code).
> - **Contrôle** : une vérification des faits (52 affirmations revues à la source primaire) et une revue croisée (contradictions, angles morts, matrice d'options).
> - **Expérience A/B en aveugle** : la même spec métier est codée en PHP et en Rust par deux agents, puis évaluée sur 302 cas de test cachés produits par un troisième agent (§4).
> - **Tout est dans [`notes/`](notes/) et [`experience/`](experience/).** Aucune trace de notre précédente discussion sur Rust n'a été retrouvée (Notion, memories, ADRs) : l'étude repart de zéro.

---

## En bref

1. **Le gain démontré vient d'un typage statique strict sans échappatoire, combiné à des tests forts. Il ne vient pas de Rust en tant que tel.**
   - La meilleure source de 2026 est la migration du runtime Copilot vers Rust par GitHub (832 k lignes). Les erreurs propres à Rust (ownership, borrow, lifetimes) n'y pèsent que **1,7 %** des diagnostics. **84 %** relèvent du typage ordinaire qu'« un compilateur C#, Java ou Go attraperait aussi bien ».
   - **Toutes** les régressions de cette migration compilaient : ce sont les tests qui les ont trouvées. [01](notes/01-preuves-empiriques-llm-rust.md), [10 #10-11](notes/10-verification-faits.md)
2. **Les agents ne sont pas démontrablement meilleurs en Rust qu'en PHP.**
   - Une fois les chiffres corrigés, les benchmarks se contredisent. Rust est devant sur SWE-bench Multilingual (58 % contre 49 % pour PHP, ~43 tâches par langage, donc dans le bruit) mais derrière Python et TypeScript sur AutoCodeBench.
   - Aucun benchmark ne ventile les résultats par langage pour les modèles de 2026. [10, « Ce qui change »](notes/10-verification-faits.md#ce-qui-change-pour-la-conclusion)
3. **Rust apporte une part propre réelle, mais étroite** : types somme avec données, `match` exhaustif, `Result`, et frontières d'architecture vérifiées à la compilation. Ce gain se concentre sur le **domaine de calcul** (Mesures, Devis, TVA). Ce domaine représente environ 660 lignes aujourd'hui et devra **aussi** tourner hors ligne sur mobile, en TypeScript. [11 §4](notes/11-revue-croisee.md)
4. **Le code de Cordeau dit où sont les vrais risques.**
   - Environ 19 bugs sur 25 relèvent de la config, de l'infra, de la plateforme ou de l'UI. **Aucun** n'est une erreur de type que PHPStan 9 aurait laissée passer.
   - La duplication métier est **PHP ↔ TS** : migrer l'API en Rust ne la réduit pas, elle la déplace.
   - Les montants sont aujourd'hui des `float`. [09](notes/09-inventaire-codebase.md)
5. **« Tout en Rust » est exclu en 2026.**
   - Côté UI, aucune alternative à React/Expo n'est prête : Leptos est en maintenance légère, Dioxus mobile tourne dans une WebView sans API natives.
   - Le pont Rust ↔ React Native se déclare lui-même « should not yet be used in production ». [04](notes/04-front-mobile-offline-rust.md)
6. **Le coût dominant n'est ni les tokens ni la compilation : c'est le temps calendaire avant la bêta.**
   - Aucun commit sur `main` depuis le 2026-06-14 et bêta de septembre non lancée.
   - Émission de factures électroniques obligatoire pour les TPE au 1er septembre 2027.
   - Aucun artisan n'achète « une API en Rust ». [06](notes/06-contre-arguments-risques.md)
7. **Recommandation.**
   - **Ne pas migrer maintenant.**
   - **Construire dès maintenant ce qui rend toute migration future sûre et bon marché** : un oracle de tests indépendant du langage et un socle durci (PHPStan 10, montants décimaux, fixtures partagées, contrat OpenAPI, harness Stop-hook, socle ops).
   - **Reprendre le produit jusqu'à la bêta.**
   - **Trancher ensuite une trajectoire Rust contexte par contexte** sur des seuils chiffrés fixés à l'avance (§9).
   - Ce n'est pas un « non » à Rust : c'est l'ordre qui garde Rust possible sans parier le produit dessus.
8. **L'expérience A/B confirme sans trancher** (§4).
   - Même spec métier, PHP et Rust : **302 cas cachés sur 302 des deux côtés**, et 0 désaccord sur 3 000 entrées aléatoires.
   - La différence est dans le _mécanisme_. En PHP, la justesse a reposé sur la vigilance de l'agent : 5 pièges de la bibliothèque standard, invisibles pour PHPStan, plus un oubli d'extension dans l'image de prod qui l'aurait fait planter. En Rust, elle a reposé sur des types qui rendent les erreurs impossibles.
   - C'est l'argument pro-Rust le plus concret de l'étude. Il est qualitatif, et sur un seul run par langage.
9. **Sécurité, hors étude mais urgent.**
   - 14 failles ouvertes (`composer audit`, dont 1 haute sur Guzzle), Dependabot rouge depuis le 15 juin.
   - Aucun worker Messenger déclaré en prod.
   - Sentry absent de l'API et du mobile.
   - Voir §11.

---

## 1. Ce que « solide » veut dire — à trancher en premier

La conclusion dépend de ce qu'on met derrière « solide ». La revue croisée propose de séparer les dimensions et de leur attacher une mesure [11 §3.5] :

| Dimension                 | Mesure proposée                                                                              | Qui a l'avantage, d'après l'étude                                                                                             |
| ------------------------- | -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Correction**            | Défauts échappés en prod par mois ; défauts résiduels sur cas cachés                         | Rust en partie, sur le domaine de calcul ; les tests dominent                                                                 |
| **Évolutivité**           | Coût d'une story à 6-12 mois ; régressions après un changement de spec                       | Non mesuré (expérience C proposée, §9)                                                                                        |
| **Reprise après pause**   | Temps pour retrouver une CI verte et livrer une PR utile après N mois                        | Processus (MAJ automatisées), pas langage : la pause actuelle le montre                                                       |
| **Pérennité**             | Churn des dépendances clés, bus factor, LTS                                                  | Langage Rust stable, mais crates web mouvantes (axum 0.9, sqlx 0.9, utoipa 6 en rupture) [03]                                 |
| **Autonomie**             | Ce que tu sais faire **sans agent** (panne, quota, régression de modèle, bug en prod à 23 h) | PHP > TS > Rust à court terme                                                                                                 |
| **Sécurité / conformité** | Failles ouvertes, surface d'auth, audit                                                      | Neutre à défavorable pour une réécriture (auth à ré-assembler et ré-auditer)                                                  |
| **Exploitation**          | Disponibilité, détection, MTTR                                                               | Rust léger à l'exécution ; un `panic` peut tuer un process long, alors qu'en PHP une exception ne fait échouer qu'une requête |

> **Angle mort majeur : le bus factor, c'est l'agent** [11 §3.1]. Pour un dev Symfony solo qui ne lit pas encore couramment Rust, une stack Rust fait de l'agent un point unique de défaillance. C'est la dimension « autonomie », et la seule qu'aucune note de recherche ne traitait.

---

## 2. Ce que disent les preuves

### 2.1 Les agents écrivent-ils mieux en Rust ?

| Source (date)                                               | Ce qu'elle mesure                 | Résultat                                                                                                                            | Portée                                                       |
| ----------------------------------------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| SWE-bench Multilingual (2025, Claude 3.7 Sonnet)            | Résolution d'issues réelles       | Rust 58,1 % · Java 53,5 % · **PHP 48,8 %** · JS/TS 34,9 % · Go 31,0 %                                                               | ~43 tâches par langage, écart Rust/PHP dans le bruit         |
| Multi-SWE-bench (2025)                                      | Résolution d'issues, 7 langages   | Rust 2ᵉ (15,9 %) derrière Java, devant TS/JS                                                                                        | Pas de PHP                                                   |
| AutoCodeBench (2025, Claude Opus 4) — **chiffres corrigés** | Génération de fonctions           | Rust 38,7 · Python 40,3 · TS 47,2 · PHP 28,1                                                                                        | Les notes 01 et 07 avaient décalé les colonnes [10 #30]      |
| Multi-LCB (juin 2026, modèles open-weight)                  | Même problème dans chaque langage | Rust ≈ Python ou en dessous, forte variance selon le modèle                                                                         | Seul benchmark à difficulté égale entre langages             |
| SWE-Bench ProMax (2026)                                     | Issues, 7 langages                | Rust 63,6 % (Sonnet 4.6)                                                                                                            | ~22 tâches Rust ; le même modèle fait 17 % en Python : bruit |
| Tokenmaxxing (juillet 2026)                                 | Tokens de sortie                  | **Claude Sonnet 4.6 : Rust 1,16× Python**, Java 1,18×                                                                               | Coût en tokens modeste                                       |
| Endoh (mars 2026, Opus 4.6)                                 | Mini-projet greenfield            | Rust 0,54 $ / 114 s · TS 0,62 $ / 133 s · Go 0,50 $ / 102 s · Python 0,38 $                                                         | Tâche trop petite pour que le typage paie                    |
| Rust-SWE-bench (2026)                                       | Échecs des agents en Rust         | Erreurs de compilation surtout liées à la navigation dans le dépôt (43,7 %) et aux types/traits (32,6 %), plus qu'au borrow checker | —                                                            |

**Lecture.**

- Avec les modèles de frontière, **aucune pénalité nette de Rust n'est démontrée, et aucune supériorité non plus**.
- PHP est honorable sur des dépôts réels (Laravel figure dans SWE-bench Multilingual) et faible en génération isolée.
- Le critère « l'agent est meilleur dans ce langage » ne départage pas les options. [01](notes/01-preuves-empiriques-llm-rust.md), [07 Q2](notes/07-alternatives-comparees.md), [10](notes/10-verification-faits.md)

**Ce que le compilateur apporte vraiment** :

- **Typage strict.** 94 % des erreurs de compilation du code TS généré par LLM sont des erreurs de type (ETH/Berkeley, PLDI 2025). Le décodage contraint par les types réduit ces erreurs de 74,8 %, mais le gain de correction fonctionnelle n'est que de +3,5 à +5,5 %.
- **Échappatoires.** Les agents ajoutent 9 fois plus de `any` que les humains dans les PR TypeScript (MSR 2026, effet petit). **Une échappatoire ouverte sera utilisée** : la règle utile est « pas d'échappatoire sans justification relue », quel que soit le langage.
- **Latence de boucle.** Les journaux de GitHub donnent un temps médian de **120 s** par `cargo check`/`cargo test`, contre **22 s** pour `pnpm test`, sur 830 k lignes. À l'échelle de Cordeau, c'est très différent : voir la mesure de l'expérience au §4.

### 2.2 D'où vient la solidité ? Ce que Rust apporte en propre

Comparé à la stack actuelle **durcie** (PHPStan 10 strict, TS strict avec lints d'exhaustivité et interdiction de `any`) [11 §4] :

| Composante de la solidité                                                                        | Part propre à Rust                                                                                                                                                                           | Équivalent sans Rust                                                | Poids pour Cordeau                                                                     |
| ------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| (a) Sûreté mémoire, data races                                                                   | ≈ 0 (PHP et TS sont déjà memory-safe ; tous les gains mesurés le sont **contre C/C++**)                                                                                                      | Déjà acquis                                                         | Faible                                                                                 |
| (b) Types expressifs : types somme avec données, exhaustivité, pas de `null`, `Result`, newtypes | **Moyenne face à PHP**, faible face à TS strict                                                                                                                                              | VO `final readonly`, enums + PHPStan, `Result` maison, type décimal | **Moyen à fort en Phases 7-8** (états Devis/Facture, montants)                         |
| (c) Boucle de feedback                                                                           | **Négative** sur la vitesse, positive sur la qualité des messages                                                                                                                            | Cache PHPStan, tests ciblés, hooks                                  | Multiplicateur de chaque itération                                                     |
| (d) Tests : mutation, propriétés, contrats                                                       | ≈ 0 (tout existe en PHP/TS)                                                                                                                                                                  | Infection, Stryker, Schemathesis, fixtures                          | **Le levier le plus fort** : seul levier sur les ~45 % de bugs qu'aucun type n'attrape |
| (e) Architecture : frontières, source unique du métier                                           | Faible (frontières vérifiées au build)                                                                                                                                                       | Deptrac étendu + hook local ; fixtures partagées                    | Fort pour la source unique                                                             |
| (f) Harness agentique                                                                            | ≈ 0 (LSP officiel aussi pour PHP et TS)                                                                                                                                                      | Stop-hook, `php-lsp`, reviewer frais                                | Fort et immédiat                                                                       |
| (g) Environnement, config, plateforme                                                            | Marginale en général ; **réelle** sur la classe « extension ou bibliothèque système absente en prod » (binaire statique : cf. #81 et bcmath, §4), sauf bibliothèques natives liées (libheif) | Smoke test de l'image, CI build natif, E2E, Sentry                  | **Le plus fort** : c'est là que sont allés les bugs coûteux                            |

> Les bénéfices propres à Rust se situent **exactement là où l'[ADR 0002](../../adr/0002-architecture-hexagonale.md) place déjà la rigueur** : les contextes complexes (Lot, Mesure, Devis, TVA). Ils ne concernent pas le CRUD léger.

### 2.3 Ce que font les grandes entreprises

[02](notes/02-adoption-industrielle.md), [06](notes/06-contre-arguments-risques.md)

**Les succès sont réels, mais tous mesurés contre C/C++ ou une JVM, et motivés par la mémoire, la performance ou l'ABI** :

- **Google Android** : 1 000 fois moins de vulnérabilités mémoire par million de lignes, 4 fois moins de rollbacks, 25 % de temps de revue en moins (contre C++).
- **AWS Aurora DSQL** : un composant réécrit de Kotlin vers Rust passe de 3 000 à 30 000 TPS.
- **Cloudflare** : FL2 en Rust.
- **Microsoft** : Rust devient langage « Tier 1 » interne (septembre 2026).

**Les grands portages assistés par IA (2026) ont tous un comportement figé par un oracle massif** :

- **Bun, de Zig vers Rust** : environ 1 M de lignes en 11 jours, environ 165 k$, 1,4 M d'assertions comme oracle, 19 régressions. Bun appartient à Anthropic : c'est un retour **éditeur**.
- **Runtime Copilot** (GitHub) et compilateur C d'Anthropic : mêmes conditions.
- Le seul contre-exemple documenté est **FastRender (Cursor)** : 3 M de lignes, note de maintenabilité 1,3/5 attribuée par SIG.
- Ces projets prouvent que les agents savent **traduire sous oracle**. Ils ne disent rien de la capacité à **concevoir sous une spec qui bouge**, qui est le régime de Cordeau.

**Les contre-cas, tous confirmés en source primaire** :

- **Prisma 7** abandonne son moteur Rust pour TypeScript : barrière de compétences, un binaire par OS, frontière entre langages.
- **Microsoft porte le compilateur TypeScript en Go**, pas en Rust, pour pouvoir _porter_ plutôt que _refondre_.
- **Ronacher et Hashimoto** recommandent Go pour les agents : simplicité, outillage, cache de tests. C'est une opinion, pas une mesure.

**Les pratiques à copier, quel que soit le langage** :

- **Cloudflare**, après sa panne du 18 novembre 2025 causée par un `.unwrap()` : « Do not use `.unwrap()` outside of tests and `build.rs` ».
- **Microsoft** : _Pragmatic Rust Guidelines_, avec une section IA (M-DESIGN-FOR-AI, M-TAUTOLOGICAL-TESTS) et une version pour agents.
- **Google, Mozilla** : `cargo-vet` pour auditer les dépendances.
- **Enquêtes Rust** : 55 % des développeurs attendent plus de 10 s par rebuild ; 41,6 % craignent la complexité croissante du langage.

### 2.4 Ce que dit le code de Cordeau

[09](notes/09-inventaire-codebase.md)

- **Taille.** L'API fait environ 9 k lignes (5 042 src, 3 746 tests, 355 migrations), le web 4,4 k lignes de TS, le mobile 4,7 k.
- **Architecture.** Un seul contexte est en hexagonal strict (Chantier/Lot/Tâche, 1 912 lignes) ; Auth, Client et Photo sont en CRUD léger.
- **API Platform** ne sert que de routeur, de validateur (55 `#[Assert]`) et de générateur OpenAPI : 4 ressources, 15 opérations, providers et processors écrits à la main. Ni filtres, ni groupes de sérialisation, ni pagination. **L'écart avec un socle Rust est donc plus petit qu'on ne le croirait** [03 Q11].
- **Bugs passés** : environ 19 sur 25 relèvent de la config, de l'infra, de la plateforme ou de l'UI (build natif iOS #84, CORS #83, extension image absente en prod #81). 3 ou 4 concernent la sync mobile, en TS. 2 ou 3 viennent de la magie runtime Symfony/Doctrine : ce sont les seuls que Rust aurait rendus improbables.
- **Biais de cet échantillon** : 5 mois de CRUD et d'infra, aucun calcul monétaire. Ce passé ne prouve pas que les types ne compteront pas en Phase 8 [11 §2.1].
- **Duplication.** 10 règles métier sont déjà dupliquées entre PHP et les deux clients TS, avec **3 divergences constatées** (code postal, surface maximale, email). Avec les Phases 7-8 arrivent `Mesure::calculer`, en PHP et en TS hors ligne, puis **3 implémentations des totaux de devis** : serveur, mobile et web. Les montants sont des `float`.
- **Tests.** 189 tests côté API, 18 côté web, 38 côté mobile ; aucun E2E, aucun contrôle de dérive du contrat OpenAPI.

---

## 3. Composant par composant : que peut-on passer en Rust ?

État au 2026-10-02. Les notes [03](notes/03-stack-backend-rust.md) et [05](notes/05-outillage-agentique-rust.md) répondent à « **si oui, comment ?** » ; ce ne sont pas des recommandations d'adoption.

| Composant                                        | Option Rust crédible                                                                                                                       | Maturité                                                                                                                                                    | Verdict                                                                                                           |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Domaine de calcul** (Mesures, Devis, TVA)      | Crate pure + `rust_decimal`, newtypes, enums exhaustifs                                                                                    | Production                                                                                                                                                  | **Là où Rust apporte le plus** ; mais il faut aussi ce calcul hors ligne côté mobile (cf. ligne « cœur partagé ») |
| **API HTTP**                                     | Axum 0.8 + SQLx 0.9 (SQL vérifié à la compilation) + utoipa 6 (OpenAPI → `packages/shared` inchangé) + tower-sessions/openidconnect/argon2 | Production pour Axum et SQLx ; utoipa a un seul mainteneur, axum 0.9 et sqlx 0.9 apportent des ruptures                                                     | Faisable (environ 9 k lignes à porter) ; auth à ré-assembler et ré-auditer                                        |
| **Jobs async** (Messenger)                       | File Postgres maison `SKIP LOCKED` (~200 lignes) ou apalis                                                                                 | apalis en RC breaking depuis des mois                                                                                                                       | Pas d'équivalent stable                                                                                           |
| **Vignettes HEIC**                               | `libheif-rs` (binding de la lib C++) + `image`                                                                                             | Mono-mainteneur, même surface C++ qu'Imagick pour le HEIC                                                                                                   | Gain de sécurité faible                                                                                           |
| **PDF devis/facture**                            | Typst comme bibliothèque (PDF/A-3)                                                                                                         | Bon                                                                                                                                                         | Un point fort de Rust                                                                                             |
| **Factur-X / EN 16931**                          | Crates de 2026, jeunes, mono-mainteneur                                                                                                    | Expérimental                                                                                                                                                | Pas d'équivalent fiable ; reste agnostique du langage si l'émission passe par une plateforme agréée (ADR 0016)    |
| **UI web**                                       | Leptos (« lightly maintained » depuis mai 2026), Dioxus 0.7→0.8 alpha                                                                      | Beta                                                                                                                                                        | **Exclu**                                                                                                         |
| **UI mobile**                                    | Dioxus mobile (WebView, pas de caméra ni de stockage sécurisé avant la 0.8), Tauri 3 alpha                                                 | Expérimental                                                                                                                                                | **Exclu**                                                                                                         |
| **Cœur partagé** API + web (WASM) + mobile (FFI) | UniFFI + `uniffi-bindgen-react-native`                                                                                                     | Le pattern est en production chez 1Password, Signal, Bitwarden, mais avec des UI natives. **Le pont React Native « should not yet be used in production »** | Différé ; signal de veille (§9)                                                                                   |
| **Sync offline**                                 | PowerSync (cœur = extension SQLite écrite en Rust, GA, Postgres + Expo + web)                                                              | Production                                                                                                                                                  | La façon réaliste de « profiter de Rust » côté sync **sans écrire de Rust**, à évaluer contre l'outbox maison     |
| **AR (V2)**                                      | Aucune piste Rust sérieuse                                                                                                                 | —                                                                                                                                                           | Module natif Expo                                                                                                 |

**Ce qui n'a pas de bon équivalent en Rust** [03] :

- un API Platform déclaratif ;
- un Messenger stable ;
- une pile Factur-X/EN 16931 mature ;
- un décodeur HEIC en Rust pur ;
- des factories de test de type Foundry ;
- un framework de sécurité unifié et audité comme Symfony Security.

---

## 4. Résultats de l'expérience A/B (PHP vs Rust, même spec, cas de test cachés)

> Détail, journaux et outillage : [`experience/resultats.md`](experience/resultats.md).
>
> **Conditions.**
>
> - Même spec métier exigeante : totaux de devis, TVA multi-taux, ventilation d'une remise au prorata, arrondis au centime, franchise de TVA, validation exhaustive.
> - Deux agents Opus 5.5 indépendants, chacun dans un worktree isolé, avec les mêmes consignes de qualité production.
> - Un oracle indépendant (référence Python et **302 cas cachés**).
> - n = 1 par bras.

|                                                 | B-PHP (PHPStan 9 + deptrac + PHPUnit)                                                                                                                                                                                          | B-Rust (clippy pedantic + restriction)                                      |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------- |
| Cas cachés                                      | **302 / 302**                                                                                                                                                                                                                  | **302 / 302**                                                               |
| Test différentiel (3 000 entrées aléatoires)    | 0 désaccord                                                                                                                                                                                                                    | 0 désaccord                                                                 |
| Sondes exotiques (15)                           | 1 écart (JSON valide rejeté : clé `\u0000`, limite de `json_decode`)                                                                                                                                                           | 0                                                                           |
| Durée active / boucles                          | ≈ 35 min / 22                                                                                                                                                                                                                  | ≈ 31 min / 26                                                               |
| Défauts trouvés seulement par les tests         | 1 (verbosité de Symfony Console : sortie vide, code 0)                                                                                                                                                                         | 0                                                                           |
| Pièges évités par la seule vigilance de l'agent | **5** pièges de la bibliothèque standard (`BcMath\Number` accepte `".5"`, `$` PCRE accepte `"12\n"`, `json_decode` confond `{}` et `[]`, regex quadratique, division tronquée), **invisibles pour PHPStan même au niveau max** | 2 (`rust_decimal` perd de la précision en silence, serde_json et `1e400`)   |
| Défaut d'environnement de prod                  | **1** : bcmath absent de l'image Docker, plantage en prod avec tout vert en local                                                                                                                                              | 0 (binaire statique)                                                        |
| Boucle statique incrémentale                    | PHPStan ≈ 0,8 s                                                                                                                                                                                                                | `cargo check` ≈ 0,14 s (build à froid 4,2 s)                                |
| Mutation testing                                | Non mesurable : ni pcov ni xdebug, et `phpdbg` n'est plus pris en charge par PHPUnit 12                                                                                                                                        | 122 mutants tués, 1 survivant équivalent, **29 rejetés par le compilateur** |

**Ce que ça dit.**

1. **Correction : égalité.** Avec un agent de 2026 et une spec précise, les deux langages atteignent zéro défaut détectable. Le seuil Q-A n'est pas franchi (0 contre 0) : **aucune preuve, ici non plus, que Rust produise du code plus juste**.
2. **Le mécanisme diffère.**
   - En PHP, la correction a reposé sur la **diligence de l'agent** : 5 pièges anticipés par sondes manuelles, qu'aucun outil n'aurait signalés.
   - En Rust, elle a reposé sur la **conception par les types** : une demande validée est un type distinct, le calcul ne peut pas échouer, la division par zéro et une erreur de ligne sans index sont impossibles.
   - C'est l'argument « solide à long terme » le plus concret de l'étude : une garantie portée par le compilateur survit à un agent moins soigneux, ou à une autre session qui modifie le code ; une garantie portée par la vigilance, non.
   - Il reste **qualitatif** : il faut des runs répétés et une expérience de modification de spec (C) pour le chiffrer.
3. **Le seul défaut « prod » est un défaut d'environnement** : une extension système manquante, exactement la classe du bug #81. Le binaire statique Rust supprime cette classe pour les dépendances en Rust pur. Côté PHP, le remède est le smoke test de l'image de prod recommandé au §6.
4. **À cette échelle, la compilation n'est pas un frein** : 0,14 s en incrémental. Le coût de boucle reste à mesurer sur une API complète (Axum, SQLx, Tokio), ce que devait faire l'expérience A, non menée.

---

## 5. Options

Résumé de la matrice de la [revue croisée §5](notes/11-revue-croisee.md#5-matrice-doptions) :

| Option                                                     | Gain de solidité                                                                | Coût calendaire avant bêta        | Risque principal                                                                              | Réversibilité                                         | Statut                                                     |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------- | --------------------------------- | --------------------------------------------------------------------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------- |
| **O0** Statu quo                                           | —                                                                               | 0                                 | Dette composée, `float`, 3 implémentations des totaux                                         | Totale                                                | Pas stable : c'est O1 laissé en friche                     |
| **O1** Socle durci                                         | Couvre (d), (f) et (g), une partie de (b) et (e) ; construit l'oracle           | Faible (1-2 semaines, estimation) | Discipline ; pas de types somme avec données                                                  | Totale, et rend toutes les autres options réversibles | **Sans regret, prérequis**                                 |
| **O2a** Cœur de calcul Rust partagé (WASM + FFI)           | (b) et (e) sur le calcul ; seule option Rust qui **supprime** de la duplication | Moyen à élevé                     | Pont React Native non prêt, 3 chaînes de build, alors que la CI native iOS n'existe pas (#87) | Bonne si fixtures                                     | **Différée** jusqu'au déclencheur de veille                |
| **O2b** Cœur Rust côté serveur seul                        | (b) sur le calcul                                                               | Moyen                             | 3ᵉ langage sans supprimer aucune duplication                                                  | Bonne                                                 | **Dominée**                                                |
| **O3** API en Rust par strangler fig, clients TS conservés | (b) et frontières côté serveur                                                  | **Élevé**                         | Bus factor de l'agent, refonte déguisée, auth à ré-auditer                                    | Moyenne, puis faible                                  | **Conditionnelle**, après la bêta, sur seuils              |
| **O4** TypeScript de bout en bout                          | Source unique directe du métier                                                 | **Élevé**                         | Typage TS non sûr (`any`), churn, backend TS non étudié ici                                   | Moyenne, puis faible                                  | Conditionnelle (concurrente d'O3 si la duplication domine) |
| **O5** Tout en Rust, UI comprise                           | Théorique                                                                       | Prohibitif                        | Écosystème UI mobile immature                                                                 | Quasi nulle                                           | **Exclue en 2026**                                         |

**Dominances.**

- O1 domine O0 ; O2b est dominée ; O5 est exclue.
- Le choix entre « O1 + fixtures », O3 et O4 dépend de données qui n'existeront qu'après les Phases 7-8 : volume des règles qui doivent tourner hors ligne, divergences constatées, classes des bugs échappés.

**Attendre a une valeur d'option.**

- Plus les agents progressent, moins un portage sous oracle coûte : pour environ 9 k lignes, l'ordre de grandeur est de 10³ $ de tokens.
- **Le seul actif qui ne se rattrape pas, c'est l'oracle** : il faut le construire maintenant, quelle que soit l'option retenue.

---

## 6. Recommandation

**Trajectoire en trois temps, avec une décision explicite au bout.**

### Temps 1 — Maintenant (≈ 1-2 semaines) : O1, le socle « solide » indépendant du langage

Dans l'ordre :

1. **Sécurité et reprise.**
   - Remettre Dependabot et `composer audit` au vert : 14 failles, dont 1 haute sur Guzzle.
   - Vérifier le traitement asynchrone des vignettes en prod (aucun worker dans `fly.toml`).
   - Brancher Sentry sur l'API et le mobile.
   - Corriger les docs et memories périmées : la memory Serena `architecture` décrit une auth par cookie, alors que le code utilise un token Bearer en `localStorage`.
2. **Un type `Montant` décimal, en PHP et en TS, avant toute ligne de Phase 8.**
   - Le `float` actuel est une décision de modélisation, pas une fatalité du langage : un agent Rust peut tout aussi bien écrire `f64`.
   - Si le choix est `BcMath\Number` (celui de l'expérience), il faut **déclarer `ext-bcmath`** dans `composer.json`, le `Dockerfile` et la CI : l'extension manque dans l'image actuelle. La correction est déjà écrite sur la branche de l'expérience B-PHP. Les 302 vecteurs de l'expérience servent de fixtures de départ.
3. **L'oracle indépendant du langage.**
   - Des **fixtures de conformité JSON** pour chaque règle métier, exécutées par PHPUnit, Vitest et Jest. Le format du jeu de 302 vecteurs de l'expérience est directement réutilisable.
   - **Schemathesis** sur l'OpenAPI et un **contrôle de dérive** de `openapi.json` en CI.
   - Quelques tests HTTP **boîte noire** écrits hors PHP.
   - C'est l'actif qui rend O2a, O3 et O4 réversibles.
4. **Durcissement statique.**
   - PHPStan 10 + strict-rules + loaders Symfony/Doctrine, et interdiction de toute nouvelle baseline ou d'ignore non justifié.
   - Deptrac étendu à Auth, Client et Photo.
   - TS : `no-explicit-any`, `switch-exhaustiveness-check`, ESLint sur le mobile.
5. **Tests qui mordent.** Infection (mutation testing), limitée au diff, sur le domaine ; tests de propriétés sur les calculs.
6. **Harness agentique** (§7) : Stop-hook de vérification, plugin `php-lsp`, reviewer en contexte frais, protocole double-fix étendu à la classification des bugs (seuil Q-E, §9).
7. **Socle ops** : job CI de build natif iOS (#87), smoke test de l'image de prod (extensions, config), E2E minimal.

### Temps 2 — Reprendre le produit jusqu'à la bêta (Phases 6.2 → 9)

- Aucune réécriture tant que le critère de l'[ADR 0017](../../adr/0017-differer-ia-validation-manuelle.md) n'est pas levé.
- La même logique que pour l'IA s'applique : **pas de sophistication invisible pour l'artisan avant la validation du concept**.
- Pendant ces phases, on **collecte les données** qui trancheront : classe de chaque bug échappé (Q-E) et nombre de règles dupliquées et divergentes (Q-F).

### Temps 3 — Décision Rust formelle à la sortie de la Phase 9 (ADR)

Sur les seuils du §9, fixés **avant** de voir les données :

- **Si les données disent « types »** (bugs échappés majoritairement attrapables par Rust, avantage net à l'expérience face à un PHP durci) : **O3 par strangler fig**, en commençant par le domaine de calcul et le contexte Devis, avec l'oracle comme filet. Clients TS conservés.
- **Si les données disent « duplication »** : O4 (TS de bout en bout), ou O2a si le pont React Native est devenu prêt pour la production.
- **Sinon** : « O1 + fixtures » suffit, et Rust reste un outil ponctuel (PDF Typst, services isolés).

> **Pourquoi pas « Rust maintenant, puisqu'on vise le long terme » ?**
>
> - Le long terme se joue sur ce qui ne se rattrape pas : la **validation produit**, l'**oracle** et la **discipline de maintenance**. La pause de 109 jours le montre : ce qui s'est dégradé (CI rouge, failles, docs) est processuel, pas linguistique, et une stack Rust aurait subi ses propres ruptures sur la même période.
> - Le portage, lui, devient **moins cher chaque trimestre** à mesure que les agents progressent, à condition d'avoir l'oracle.
> - **Faire Rust après la bêta ne coûte presque rien de plus ; le faire avant peut coûter le produit.**

---

## 7. Le harness agentique « ultra efficace et sain »

La plupart des leviers sont **indépendants du langage** et applicables dès maintenant [05](notes/05-outillage-agentique-rust.md), [11 §3.8](notes/11-revue-croisee.md) :

| Levier                                                                                     | Maintenant (PHP/TS)                                                                                                                                                                                                                                                                                          | Si Rust demain                                                                                                                                                            |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Gate déterministe en fin de tour**                                                       | Stop-hook : PHPStan ciblé + PHPUnit filtré + `tsc` + tests du paquet touché                                                                                                                                                                                                                                  | `cargo fmt --check && cargo clippy -- -D warnings && cargo nextest run`                                                                                                   |
| **Diagnostics à chaque édition**                                                           | Plugin officiel `php-lsp` + `typescript-lsp`                                                                                                                                                                                                                                                                 | Plugin officiel `rust-analyzer-lsp` (target dir séparé pour éviter la contention de lock)                                                                                 |
| **Pièges de la bibliothèque standard** (relevés par l'expérience, invisibles pour PHPStan) | Règles CLAUDE.md et tests dédiés : regex ancrées en `\z` (pas `$`) ; `json_decode` en objet (pas `assoc=true`) quand objet et tableau doivent se distinguer ; regex stricte avant `new BcMath\Number` ; division bcmath avec échelle explicite ; sortie console en `OUTPUT_RAW` pour les commandes à contrat | `rust_decimal` : `checked_*` et attention à la perte de précision silencieuse ; serde_json `arbitrary_precision` si les nombres hors plage doivent être signalés finement |
| **Fermer les échappatoires**                                                               | Pas de nouvelle baseline PHPStan, `no-explicit-any`, `@phpstan-ignore` seulement avec raison                                                                                                                                                                                                                 | Lints `unwrap_used`, `expect_used`, `panic`, `indexing_slicing` interdits hors tests ; `#[expect(..., reason = "…")]` ; `forbid(unsafe_code)`                             |
| **Invariants dans les types**                                                              | VO `final readonly`, enums, `Montant` décimal, constructeurs nommés                                                                                                                                                                                                                                          | Newtypes (`nutype`), enums exhaustifs sans wildcard, typestate, SQLx                                                                                                      |
| **Tests qui spécifient**                                                                   | Fixtures partagées, Infection en `--git-diff`, tests de propriétés                                                                                                                                                                                                                                           | proptest, insta, `cargo-mutants --in-diff`                                                                                                                                |
| **Frontières d'architecture**                                                              | Deptrac sur 100 % des contextes, en hook local                                                                                                                                                                                                                                                               | Une crate par couche et par bounded context, plus des bans `cargo-deny`                                                                                                   |
| **Contexte de l'agent**                                                                    | CLAUDE.md courts qui renvoient aux lints ; **vérification automatique de quelques assertions de la doc** (la doc fausse a déjà contaminé une note de recherche)                                                                                                                                              | Idem ; _Pragmatic Rust Guidelines_ version agents                                                                                                                         |
| **Revue**                                                                                  | Reviewer en contexte frais avant PR (déjà : `/review`, `/simplify`)                                                                                                                                                                                                                                          | Idem                                                                                                                                                                      |
| **Chaîne d'approvisionnement**                                                             | Dependabot vert, `composer audit` bloquant                                                                                                                                                                                                                                                                   | `cargo-deny` (licences, advisories), `cargo-vet`                                                                                                                          |

À éviter :

- les gros packs de skills communautaires (des centaines de règles) : ils vont contre la recommandation d'Anthropic de garder le contexte léger ;
- les MCP « docs.rs » communautaires peu maintenus : rust-analyzer lit déjà le code source exact des dépendances sur disque.

---

## 8. Outils de recherche et de veille

[08](notes/08-outils-recherche-veille.md)

| Usage                         | Outil recommandé                                                                                                                | Remarque                                                        |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| Recherche web pour agent      | **Exa** (plugin du marketplace officiel Anthropic, utilisable sans clé, crédits gratuits) ; Firecrawl ou Brave en complément    | Serveurs **hébergés par l'éditeur**, pas de code tiers en local |
| Docs de bibliothèques         | **Context7** : déjà configuré mais **non authentifié** dans cette session, à finaliser dans les connecteurs claude.ai           | Couverture Rust vérifiée                                        |
| Questions sur un dépôt GitHub | **DeepWiki MCP** (gratuit, sans auth) ; **grep.app MCP** pour la recherche de code                                              | —                                                               |
| Littérature scientifique      | **arxiv-mcp-server** + API **Semantic Scholar**                                                                                 | Papers with Code est fermé depuis 2025                          |
| Santé d'une crate             | API **crates.io** (téléchargements sur 90 jours, owners), **deps.dev** (Scorecard), **OSV/RustSec**, lib.rs, audits `cargo-vet` | Ne jamais utiliser Scorecard seul (axum a 5,5/10)               |
| Veille récurrente             | **Routine Claude Code** hebdomadaire sur les signaux du §9 (Q-I)                                                                | Tourne dans le cloud, machine éteinte                           |

Sécurité : Anthropic n'audite aucun serveur MCP, et il y a eu des incidents réels (injection via des issues GitHub, serveur malveillant publié sur npm). Il faut des scopes minimaux, et tout contenu web doit être traité comme non fiable.

---

## 9. Seuils de décision et signaux de veille

À calibrer **avant** de lire les données [11 §6.3] :

| #   | Question                                                              | Mesure                                                                                                      | « Go » vers plus de Rust (ou de TS)                                                                                                                                                                                       | « No-go »                                |
| --- | --------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| Q-A | À effort égal, Rust produit-il moins de défauts qu'un PHP **durci** ? | Échecs sur cas cachés, ≥ 3 runs par bras, ≥ 2 specs                                                         | Échecs Rust ≤ 50 % de ceux de PHP+, au-delà de la dispersion                                                                                                                                                              | Écart < 25 % ou dans le bruit            |
| Q-B | Le coût de boucle est-il acceptable ?                                 | Tokens et temps mural par story vérifiée                                                                    | Rust ≤ 1,5 × PHP                                                                                                                                                                                                          | Rust > 2 × PHP                           |
| Q-C | Le code reste-t-il solide quand la spec change ?                      | Régressions qui passent après une modification de spec (expérience C)                                       | Rust ≤ 50 % de PHP+                                                                                                                                                                                                       | Égalité                                  |
| Q-D | Es-tu autonome sans agent ?                                           | Corriger seul un bug injecté                                                                                | Rust ≤ 2 × le temps PHP, avec succès                                                                                                                                                                                      | Échec en 60 min (**veto**)               |
| Q-E | De quelle classe sont les bugs échappés ?                             | Classification double-fix étendue, Phases 6-8                                                               | ≥ 30 % des bugs API échappés attrapables par Rust et pas par PHPStan 10, sur ≥ 10 bugs                                                                                                                                    | < 10 %                                   |
| Q-F | Quel est le poids réel de la duplication ?                            | Règles à plusieurs implémentations et divergences constatées malgré les fixtures                            | ≥ 10 règles **et** ≥ 3 divergences → source unique (O4 ou O2a)                                                                                                                                                            | En dessous : fixtures suffisantes        |
| Q-G | Les préconditions de toute réécriture sont-elles réunies ?            | Critère ADR 0017 levé + oracle couvrant les endpoints + CI verte + ADR avec section sécurité                | Toutes réunies                                                                                                                                                                                                            | Une seule manque : no-go                 |
| Q-H | Quand arrêter une réécriture lancée ?                                 | Premier contexte > 2 × l'estimation ; vélocité < 50 % de la Phase 2 ; contexte en double stack > 8 semaines | —                                                                                                                                                                                                                         | Un seul critère atteint : retour arrière |
| Q-I | Quels signaux de veille doivent rouvrir le dossier ?                  | Routine hebdomadaire                                                                                        | `uniffi-bindgen-react-native` sans l'avertissement « not for production » + recette Expo → rouvrir O2a ; Dioxus 1.0 avec API natives → réexaminer O5 ; ventilation par langage pour les modèles 2026 → mettre à jour §2.1 | —                                        |

---

## 10. Limites de l'étude

- **Pas de comparaison directe dans le monde.** Aucune étude publique ne compare Rust, PHP + PHPStan max et TS strict sur une application métier web développée avec des agents. Notre expérience (§4) est un indice, avec n = 1 par bras, sur du pur domaine (ni HTTP, ni DB). Le bras PHP n'avait pas les mêmes règles strictes que le bras Rust ; c'est en partie compensé par le passage du code PHP final à PHPStan **niveau max**, sans erreur. Le mutation testing PHP n'a pas pu être mesuré.
- **Benchmarks datés et peu fiables.** Ceux qui sont ventilés par langage datent de modèles 2025, et plusieurs ont de petits échantillons (22 à 43 tâches par langage).
- **Sources orientées.** Les deux plus gros succès « IA + Rust » de 2026 viennent de l'éditeur du modèle (Bun appartient à Anthropic, compilateur C d'Anthropic). Le troisième vient de GitHub, qui vend Copilot.
- **Option non instruite.** Le backend TypeScript (O4) n'a pas été étudié en détail : frameworks, ORM, auth, jobs.
- **Environnement de l'expérience.** Docker était indisponible (socket OrbStack rattaché à un autre compte macOS), et l'expérience A (portage de la PR #105 avec Postgres) n'a pas pu être menée à terme dans la fenêtre d'usage. La friction SQLx/Postgres reste donc documentaire [03 Q2].
- **Interruptions.** L'exécution a été coupée deux fois par la limite d'usage de la session. Les notes ont été reprises ou relancées sans perte, mais certaines vérifications complémentaires ont été écourtées.
- **Ce que l'étude ne mesure pas** : la maintenance par un humain seul (expérience D) et l'évolution du code sous changement de spec (expérience C). Ce sont pourtant les deux dimensions les plus proches de « solide à long terme ».

---

## 11. Points d'attention relevés en chemin (hors étude)

| Constat                                                                                                                                                                | Vérifié                                                                       | Action suggérée                                                                                             |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **14 failles** `composer audit` sur 4 paquets (api-platform/core ×2, guzzlehttp/guzzle dont 1 **haute** CVE-2026-69246…) ; Dependabot rouge depuis le 2026-06-15       | Oui, `composer audit` du 2026-10-02                                           | PR `fix(api)` de montée de version, avant toute autre chose                                                 |
| `fly.toml` ne déclare que le process `app` ; transport Messenger `doctrine://default?auto_setup=0` dans `.env` ; pas de table `messenger_messages` dans les migrations | Oui pour la config du repo ; la valeur de prod (secret Fly) n'est pas visible | Vérifier si les vignettes sont réellement générées en prod (`fly secrets list`, logs)                       |
| Sentry absent de l'API et du mobile (présent sur le web)                                                                                                               | Oui (dépendances)                                                             | Brancher avant la bêta                                                                                      |
| Tokens web en `localStorage`, alors que la memory Serena `architecture` parle de cookie de session                                                                     | Selon la note 09                                                              | Corriger la memory ; évaluer l'exposition XSS (CSP)                                                         |
| Rate limiter absent (#63), CodeQL ne couvre pas PHP                                                                                                                    | Selon la note 09                                                              | À planifier avec le durcissement                                                                            |
| Extension `bcmath` absente de l'image Docker de prod                                                                                                                   | Oui, trouvé par l'expérience B-PHP                                            | À déclarer avant toute arithmétique décimale bcmath (correctif prêt sur `worktree-agent-ab6ecd713ce85860f`) |

---

## Annexes

### Notes de recherche

| #   | Note                                                                     | Contenu                                                                                      |
| --- | ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------- |
| 01  | [Preuves empiriques LLM × Rust](notes/01-preuves-empiriques-llm-rust.md) | Benchmarks par langage, compilateur comme boucle de feedback, grands portages IA, praticiens |
| 02  | [Adoption industrielle](notes/02-adoption-industrielle.md)               | Google, Microsoft, AWS, Cloudflare, OpenAI… ; guidelines ; contre-cas                        |
| 03  | [Stack backend Rust](notes/03-stack-backend-rust.md)                     | Composant par composant pour remplacer Symfony + API Platform                                |
| 04  | [Front, mobile, offline](notes/04-front-mobile-offline-rust.md)          | Leptos, Dioxus, Tauri, UniFFI/ubrn, PowerSync, AR                                            |
| 05  | [Outillage agentique Rust](notes/05-outillage-agentique-rust.md)         | LSP, hooks, clippy, mutants, patterns, temps de compilation                                  |
| 06  | [Contre-arguments et risques](notes/06-contre-arguments-risques.md)      | Red team : réécritures, coût d'opportunité, mitigations                                      |
| 07  | [Alternatives comparées](notes/07-alternatives-comparees.md)             | Rust, TS, Go, Kotlin, langages fonctionnels, PHP durci                                       |
| 08  | [Outils de recherche et veille](notes/08-outils-recherche-veille.md)     | MCP de recherche, signaux de santé des crates, sécurité MCP                                  |
| 09  | [Inventaire du code](notes/09-inventaire-codebase.md)                    | Surface de migration, bugs passés, duplication métier                                        |
| 10  | [Vérification des faits](notes/10-verification-faits.md)                 | 52 affirmations contrôlées à la source primaire                                              |
| 11  | [Revue croisée](notes/11-revue-croisee.md)                               | Contradictions, angles morts, décomposition de la solidité, matrice, seuils                  |

### Expérience

- [Protocole](experience/protocole.md) · [Spec « totaux de devis »](experience/spec-totaux-devis.md) · [Résultats](experience/resultats.md)

### Sources primaires clés (vérifiées le 2026-10-02)

- GitHub — [Migrating the GitHub Copilot runtime to Rust using Copilot](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/) (sept. 2026)
- Bun — [Bun in Rust](https://bun.com/blog/bun-in-rust) (juillet 2026, éditeur Anthropic)
- [SWE-bench Multilingual](https://www.swebench.com/multilingual.html) · [Multi-SWE-bench](https://arxiv.org/html/2504.02605v1) · [AutoCodeBench](https://arxiv.org/html/2508.09101v1) · [Multi-LCB](https://arxiv.org/html/2606.20517) · [Rust-SWE-bench](https://arxiv.org/html/2602.22764v1) · [Tokenmaxxing](https://arxiv.org/html/2607.22807v1)
- Mündler et al., [Type-Constrained Code Generation](https://arxiv.org/pdf/2504.09246) (PLDI 2025) · [Agents et `any` en TypeScript](https://arxiv.org/html/2602.17955v2) (MSR 2026)
- Cloudflare — [panne du 18 nov. 2025](https://blog.cloudflare.com/18-november-2025-outage/) · [Code Orange: Fail Small](https://blog.cloudflare.com/code-orange-fail-small-complete/)
- Google — [Rust in Android: move fast and fix things](https://blog.google/security/rust-in-android-move-fast-fix-things/) (nov. 2025)
- Prisma — [From Rust to TypeScript](https://www.prisma.io/blog/from-rust-to-typescript-a-new-chapter-for-prisma-orm) · Microsoft — [TypeScript native port](https://devblogs.microsoft.com/typescript/typescript-native-port/)
- Microsoft — [Pragmatic Rust Guidelines, section AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- Rust — [Compiler performance survey 2025](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results/) · [State of Rust 2025](https://blog.rust-lang.org/2026/03/02/2025-State-Of-Rust-Survey-results/)
- Ronacher — [Agentic Coding Recommendations](https://lucumr.pocoo.org/2025/6/12/agentic-coding/) · [A Language for Agents](https://lucumr.pocoo.org/2026/2/9/a-language-for-agents/)
- Leptos — [issue #4707](https://github.com/leptos-rs/leptos/issues/4707) · AWS — [Aurora DSQL](https://www.allthingsdistributed.com/2025/05/just-make-it-scale-an-aurora-dsql-story.html) · SIG — [Quality of FastRender](https://www.softwareimprovementgroup.com/blog/quality-of-fastrender/)
- [Facturation électronique — impots.gouv.fr](https://www.impots.gouv.fr/facturation-electronique-et-plateformes-agreees)
