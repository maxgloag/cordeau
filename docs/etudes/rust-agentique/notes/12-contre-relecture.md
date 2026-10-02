# 12 — Contre-relecture finale du rapport (README.md + experience/resultats.md)

> Relecteur critique, 2026-10-02. Aucun autre fichier n'a été modifié.
> Numéros de ligne : ceux de `README.md` au moment de la relecture (`R:nn`) et de `experience/resultats.md` (`E:nn`).
> Vérifications faites en plus de la lecture : `git log main` (dernier commit 2026-06-14), `composer audit` (rejoué le 2026-10-02), `Dockerfile`/`fly.toml`/`.env`, `apps/web/src/lib/api.ts`, `.serena/memories/architecture.md`, lecture des journaux `journal-b-php.md` et `journal-b-rust.md`, notes 03, 07, 08, 09, relecture du billet GitHub Copilot (WebFetch) pour le point 1.

## 0. Ce qui est vérifié et correct

- **Liens relatifs** : tous les fichiers cités par le README et par `resultats.md` existent (notes 01 à 11, `experience/*`, ADR 0002, 0016, 0017) ; les deux ancres (`#5-matrice-doptions`, `#ce-qui-change-pour-la-conclusion`) correspondent à de vrais titres.
- **Note 11 §7 « ne pas citer »** : absents du rapport, les 76 %, le ratio « ~2× C/C++ », les « 70-80 % », les pourcentages 40/25/15/10 des régressions Copilot, Klabnik « 100 k lignes en 11 jours », le coût de 120 k$. Le billet Ronacher de 2026 n'est pas utilisé comme argument anti-Rust dans le texte (voir point 25 pour la ligne de sources).
- **Corrections de la note 10 appliquées** : AutoCodeBench (38,7 / 40,3 / 47,2 / 28,1), Tokenmaxxing (1,16× pour Claude Sonnet 4.6), Bun classé « éditeur », Android « contre C++ », Leptos, ubrn.
- **Chiffres de l'expérience cohérents entre README, `resultats.md` et journaux** : 302/302, 3 000 entrées, 15 sondes, 35/31 min, 22/26 boucles, 207 tests/279 assertions, 984 et 1 255 lignes, 152 mutants = 122 + 1 + 29.
- **Faits du dépôt revérifiés** : aucun commit sur `main` depuis le 2026-06-14 ; `bcmath` absent du `Dockerfile` de `main` ; `fly.toml` ne déclare que le process `app` ; `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` ; tokens web en `localStorage` (`apps/web/src/lib/api.ts`) alors que la memory Serena `architecture` parle de cookie ; 14 advisories sur 4 paquets.
- **Renvois internes** (§1, §6, §9, §11) : cohérents. Les codes Q-A à Q-I du §9 correspondent à la note 11 §6.3.

---

## 1. BLOQUANT

### 1. « Ce sont les tests qui les ont trouvées » : affirmation contredite par la source primaire (En bref 1)

- **Passage (R:23)** : « **Toutes** les régressions de cette migration compilaient : ce sont les tests qui les ont trouvées. »
- **Problème** : le billet GitHub dit autre chose. Les régressions ont été trouvées « _just by developing in the repo_ », en pré-release, ou après une release stable : « _some reached a stable release, often after being unnoticeable enough to make it through one or more rounds of pre-release usage undetected_ ». Elles ont toutes été fusionnées sur `main` (note 10 #11), donc elles ont **aussi traversé la suite de tests** (468 k lignes de tests unitaires + 175 k de tests e2e). La note 11 (§0, §4 d) a la même erreur et le rapport l'a héritée. C'est la première puce du rapport, et elle appuie la thèse centrale « les tests dominent ».
- **Correction (à coller)** : « **Toutes** les régressions connues de cette migration compilaient et ont été fusionnées sur `main` : le compilateur ne les a pas vues, et beaucoup ont aussi traversé la suite de tests (trouvées en développant, en pré-release, parfois après une release stable). Ni le compilateur ni les tests ne suffisent : il faut un oracle indépendant **et** de l'observabilité en production. »
- **À propager** : aucune autre occurrence dans le README, mais ne pas reprendre « attrapées par les tests » de la note 11 §4 (d).

### 2. Phrase décisive du « Pourquoi pas Rust maintenant » non étayée et contradictoire avec le §5

- **Passage (R:300-301)** : « Le portage, lui, devient **moins cher chaque trimestre** à mesure que les agents progressent, à condition d'avoir l'oracle. » / « **Faire Rust après la bêta ne coûte presque rien de plus ; le faire avant peut coûter le produit.** »
- **Problème** :
  - C'est la phrase qui porte tout le « non, pas maintenant » face à un commanditaire qui a dit « pas pour aller plus vite ». Aucune unité de coût n'est donnée (note 11 §7 : « nommer l'unité de coût à chaque fois »).
  - Elle contredit le tableau du §5 (O3 : coût calendaire « **Élevé** », réversibilité « moyenne, puis faible ») et le §2.3 du rapport lui-même (« les agents savent traduire sous oracle, ils ne disent rien de la capacité à concevoir sous une spec qui bouge »).
  - Elle suppose que les coûts d'un portage tardif sont négligeables : plus de code après les Phases 6-9, des utilisateurs payants, deux systèmes de migrations de schéma pendant le strangler fig (note 11 §3.2, absent du rapport), une auth à ré-auditer, et l'échéance e-facture TPE du 1er septembre 2027.
  - Le seul chiffre disponible (10³ $) est en **tokens** et vaut pour du « portage sous oracle » (note 11 §3.4 : « ne doit pas être cité comme une estimation »). Ce n'est pas l'unité qui compte ici (heures du fondateur, semaines calendaires).
- **Correction (à coller, remplace les deux puces)** :
  > - Le portage devient moins cher à mesure que les agents progressent, **mais seulement sous oracle** et pour un comportement figé. Le coût qui compte, en **heures du fondateur** et en **semaines calendaires**, d'un portage de l'API avec des utilisateurs payants, deux systèmes de migrations de schéma et une auth à ré-auditer n'est mesuré nulle part : O3 est classé « Élevé » au §5. Les ~10³ $ sont un coût en **tokens**, pas le bon indicateur.
  > - Ce qu'on peut dire : migrer avant la bêta fait porter le risque sur le seul actif qui ne se rattrape pas (la validation produit) ; migrer après le fait porter sur plus de code, des utilisateurs et une échéance réglementaire (1er septembre 2027). Aucun chiffre ne permet de dire que le second est « presque gratuit ». La mesure qui manque est l'expérience A (portage de la PR #105) : la lancer (Temps 1 bis, point 3) donne ce coût sur du code réel.

### 3. Le chemin vers Rust n'est pas opérationnel : pas de règle de décision, expériences non planifiées, pas de date

- **Passage (R:289-295, R:346-360)** : « Décision Rust formelle à la sortie de la Phase 9 (ADR) », « Si les données disent « types » (bugs échappés **majoritairement** attrapables par Rust, avantage net à l'expérience face à un PHP durci) », seuils Q-A à Q-I.
- **Problème** (la demande est « quoi, quand, à quelles conditions », avec un commanditaire qui **préfère** Rust) :
  1. **Pas de règle de combinaison.** La note 11 §5 (O3) exige que _toutes_ les conditions soient réunies (bêta validée, oracle, Q-A et Q-C, Q-D, Q-E). Le README ne dit pas si c'est un ET ou un OU. En lecture littérale, Rust exige cinq seuils conjoints, dont un veto.
  2. **Les expériences qui alimentent Q-A, Q-C, Q-D ne sont planifiées nulle part.** Temps 1 et Temps 2 ne planifient que la collecte de Q-E et Q-F. Q-A exige ≥ 3 runs par bras, ≥ 2 specs et un bras PHP durci qui n'existe pas ; Q-C exige l'expérience C ; Q-D exige l'expérience D (toi, sans agent). À la sortie de la Phase 9, ces trois mesures seront inconnues, donc le défaut sera « no-go » par omission. Or l'expérience B a coûté ≈ 35 min d'agent par bras : ces mesures sont peu chères.
  3. **Pas de date.** « À la sortie de la Phase 9 » : d'après le ROADMAP (Phases 6, 7, 8 de 2-3, 2-3, 3-4 semaines + 3-4 semaines d'usage bêta), une reprise en octobre 2026 donnerait une fenêtre février-avril 2027 (estimation à confirmer), soit 5 à 7 mois avant l'échéance e-facture TPE. Un O3 « Élevé » lancé alors se télescope avec cette échéance et avec V1.1.
  4. **Pas de premier pas concret** : par quel contexte, avec quelle crate, quel proxy, qui possède le schéma ?
- **Correction (à coller, nouveau sous-titre entre Temps 1 et Temps 2, plus une règle dans Temps 3)** :
  > ### Temps 1 bis — Mesurer ce qui conditionne la décision (≈ 2-3 jours calendaires, surtout du temps d'agent)
  >
  > 1. **B+** : rejouer l'expérience B avec un bras PHP durci (PHPStan 10, strict-rules, Infection), ≥ 3 runs par bras, sur 2 specs dont une plus dure que « totaux de devis » (les deux bras sont au plafond à 0 défaut). Alimente Q-A.
  > 2. **C** : un agent en contexte frais modifie la spec (arrondi par taux, nouveau taux) sur chaque implémentation ; on compte les régressions qui passent. Alimente Q-C.
  > 3. **A** : portage de la PR #105 en Rust. Donne le coût réel d'O3 (heures, tokens, friction SQLx/Postgres).
  > 4. **D** : toi, sans agent, tu corriges un bug injecté en PHP puis en Rust, et tu relis une PR de 200 lignes. Alimente Q-D et fixe le point de départ du plan d'apprentissage (point 13).
  >
  > **Règle de décision à inscrire dans l'ADR _avant_ de voir les résultats** : **O3** si Q-G **et** Q-D (pas de veto) **et** [Q-E **ou** (Q-A **et** Q-C)] ; **O4 ou O2a** si Q-F **et** Q-G ; **sinon** « O1 + fixtures ». Décision datée : à la sortie de la Phase 8 si Q-A/Q-C/Q-D sont mesurés, sans attendre la fin de la bêta pour les trois mesures qui ne dépendent pas des utilisateurs.
- **Remplacer aussi (R:293)** « bugs échappés majoritairement attrapables par Rust » par « Q-E : ≥ 30 % des bugs API échappés attrapables par Rust et pas par PHPStan 10, sur ≥ 10 bugs » (le mot « majoritairement » suggère > 50 %, le §9 dit ≥ 30 %).

---

## 2. IMPORTANT

### 4. « ≈ 660 lignes » attribué au mauvais périmètre (En bref 3)

- **Passage (R:27)** : « Ce domaine [Mesures, Devis, TVA] représente environ 660 lignes aujourd'hui »
- **Problème** : les ≈ 660 lignes de la note 09 §7 sont le domaine **Chantier/Lot/Tâche + VO + `Shared` + `Telephone`** (« ~920 l., ~660 l. code »). Le domaine de calcul Mesures/Devis/TVA **n'existe pas encore** (Phases 7-8). Le seul chiffre mesuré pour un calcul de devis est celui de l'expérience : 984 lignes utiles en PHP, 1 255 en Rust, pour les seuls totaux. L'erreur vient de la note 11 §4, que le rapport cite. Elle fait paraître la zone « Rust » plus petite qu'elle n'est.
- **Correction (à coller)** : « Ce domaine n'existe pas encore : le domaine pur actuel (Chantier/Lot/Tâche) fait environ 660 lignes de code. Un calcul de devis seul a pesé 984 lignes utiles en PHP et 1 255 en Rust dans l'expérience (§4), et devra **aussi** tourner hors ligne sur mobile, en TypeScript. »

### 5. Tableau §4 sélectif : toutes les lignes sont neutres ou favorables à Rust ; les lignes défavorables de `resultats.md` sont absentes

- **Passage (R:203-213)** : tableau §4.
- **Problème** : `resultats.md` contient trois faits défavorables à Rust ou neutres que le rapport omet :
  - **Dépendances** (E:79) : PHP 0 bibliothèque + 1 extension ; Rust 5 crates directes, 14 externes, 51 paquets au `Cargo.lock`. Le CLAUDE.md impose un ADR avec section sécurité pour toute dépendance externe, et le journal PHP écarte `brick/math` pour cette raison.
  - **Suite de tests** (E:54) : 0,04 s en PHP contre 1,8 s en Rust. Le rapport ne retient que la vérification statique (0,14 s contre 0,8 s).
  - **Cadre** : B-PHP est dans `apps/api` (architecture hexagonale, Deptrac, conventions du projet), B-Rust dans un workspace isolé. Le protocole signale aussi que les mesures de temps sont bruitées (agents en parallèle, 12 cœurs) et que la spec est « volontairement piégeuse », donc **choisie comme cas favorable à Rust**.
- **Correction (à coller : trois lignes à ajouter au tableau + une phrase)** :

  |                        | B-PHP                                                                            | B-Rust                                                     |
  | ---------------------- | -------------------------------------------------------------------------------- | ---------------------------------------------------------- |
  | Dépendances ajoutées   | 0 bibliothèque, 1 extension système (bcmath) ; `brick/math` écartée (ADR requis) | 5 crates directes, 14 externes, 51 paquets au `Cargo.lock` |
  | Suite de tests (durée) | 0,04 s (0,15 s avec les tests CLI)                                               | 1,8 s (dont tests de contrat sur le binaire)               |
  | Cadre                  | Dans `apps/api`, hexagonal, Deptrac                                              | Workspace isolé `spikes/rust-devis/`                       |

  > La spec a été choisie comme **cas favorable à Rust** (arithmétique décimale, validation exhaustive) ; les temps sont bruités (agents en parallèle sur la même machine). Une égalité à 0 défaut sur le cas le plus favorable est donc un résultat plutôt prudent pour l'argument « Rust produit du code plus juste ».

### 6. « Une garantie portée par le compilateur survit… ; une garantie portée par la vigilance, non » : raisonnement présenté comme acquis

- **Passage (R:219-221, repris E:38, E:44, En bref 8)** : « une garantie portée par le compilateur survit à un agent moins soigneux, ou à une autre session qui modifie le code ; une garantie portée par la vigilance, non. »
- **Problème** :
  - Ce n'est pas testé (`resultats.md` l'admet à E:45), mais la phrase est assertive et c'est l'argument le plus fort de la section.
  - **L'agent PHP a aussi conçu par les types** : objets-valeur (`Quantite`, `Pourcentage`, `Montant`…), `DemandeDevis`, interface `RemiseGlobale` polymorphe (journal PHP). La différence ne porte pas sur « conception par les types » contre « vigilance », mais sur ce que le langage rend **impossible** (division par zéro, erreur sans index).
  - **Les 5 pièges PHP sont matérialisés dans 207 tests** : une autre session qui les casserait aurait un test rouge. La « vigilance » est donc aussi encodée.
  - **En Rust, les 2 pièges** (`rust_decimal`, `1e400`) **ne sont pas gardés par les types** non plus.
  - « Invisibles pour PHPStan » est une affirmation de l'agent, non testée sur du code naïf ; la boucle 4 du journal montre d'ailleurs que PHPStan a réagi au constructeur `BcMath\Number` (`numeric-string`). Le vrai constat : le format accepté par le constructeur diffère de la spec, ce qu'aucun outil ne peut savoir.
  - Citer le journal de l'agent Rust (« la conception a rendu les bugs irreprésentables ») comme preuve revient à citer une auto-évaluation.
- **Correction (à coller, remplace R:221)** : « C'est l'argument « solide à long terme » le plus concret de l'étude, mais c'est un **raisonnement** que l'expérience ne teste pas : l'agent PHP a lui aussi modélisé en objets-valeur, et ses 5 pièges sont couverts par 207 tests, donc une autre session qui les casserait aurait un test rouge. Ce que l'on peut retenir : en Rust, une classe d'erreurs (division par zéro, erreur de ligne sans index) est exclue **par construction** ; en PHP, elle est exclue **par tests et discipline**. Les 2 pièges Rust relevés (`rust_decimal`, `1e400`) ne sont, eux, pas gardés par les types. L'expérience C (modification de spec par un agent frais) est faite pour trancher. »

### 7. Ligne (c) du §2.2 contredite par l'expérience et par le §4

- **Passage (R:110)** : « **Négative** sur la vitesse, positive sur la qualité des messages » / « Multiplicateur de chaque itération ».
- **Problème** : le §4 point 4 (R:224), `resultats.md` §3 et En bref 6 disent que la compilation n'est pas un frein à l'échelle mesurée ; `cargo check` incrémental (0,14 s) est plus rapide que PHPStan (0,8 s). La ligne (c) n'a pas été mise à jour après l'expérience, et elle penche vers le statu quo. L'inverse serait aussi faux : la suite de tests Rust est 45 fois plus lente (1,8 s contre 0,04 s) et rien n'est mesuré à l'échelle d'une API complète.
- **Correction (à coller, cellule « Part propre à Rust »)** : « **Dépend de l'échelle.** À 830 k lignes (GitHub) : 120 s par `cargo check`/`cargo test` contre 22 s pour `pnpm test`. À l'échelle d'un domaine (expérience B) : `cargo check` 0,14 s contre 0,8 s pour PHPStan, mais suite de tests 1,8 s contre 0,04 s. **Non mesuré** sur une API complète (Axum, SQLx, Tokio). Messages d'erreur plus précis. »

### 8. « ~45 % de bugs qu'aucun type n'attrape » : estimation personnelle sur du Python de 2023, citée comme un fait

- **Passage (R:111)** : « **Le levier le plus fort** : seul levier sur les ~45 % de bugs qu'aucun type n'attrape »
- **Problème** : ce 45 % vient de la note 07 Q1 (« estimation personnelle, sur du Python et des modèles de 2023 — à prendre comme un ordre de grandeur »), qui projette la taxonomie de Tambon sur les garanties statiques. La note 11 §2.1-2.2 la signale comme extrapolation faible, du même acabit que les « 70-80 % » qu'elle interdit de citer.
- **Correction (à coller)** : « seul levier sur les bugs fonctionnels qu'aucun système de types n'attrape (ordre de grandeur de 45 % sur la taxonomie de Tambon, mais pour du Python généré par des modèles de 2023 : à ne pas citer comme mesure) ».

### 9. « Aucun benchmark ne ventile… pour les modèles de 2026 » contredit par le tableau §2.1

- **Passage (R:26)** : « Aucun benchmark ne ventile les résultats par langage pour les modèles de 2026. » ; (R:367) : « Ceux qui sont ventilés par langage datent de modèles 2025 »
- **Problème** : le tableau §2.1 contient SWE-Bench ProMax (2026, Sonnet 4.6), Tokenmaxxing (juillet 2026, Sonnet 4.6 et GPT-5.5), Multi-LCB (juin 2026) et Endoh (mars 2026, Opus 4.6), tous ventilés par langage. Ce que dit la note 11 §3.10 est plus étroit : les classements agrégés des modèles actuels (85-94 %) ne sont plus ventilés.
- **Correction (à coller, R:26)** : « Aucun benchmark ne ventile les résultats par langage pour les modèles actuels (Opus 5.5, Sonnet 5.5 : les classements agrégés saturent à 85-94 % sans détail par langage). Les ventilations disponibles portent sur des modèles de 2025 ou du début 2026 et sur de petits échantillons. » **R:367** : remplacer « datent de modèles 2025 » par « portent sur des modèles antérieurs à Opus 5.5 / Sonnet 5.5 (2025 à début 2026) ».

### 10. Ligne « Domaine de calcul » du §3 : `rust_decimal` recommandé alors que l'expérience l'a écarté

- **Passage (R:169)** : « Crate pure + `rust_decimal`, newtypes, enums exhaustifs ».
- **Problème** : l'agent Rust a **écarté `rust_decimal`** (perte de précision silencieuse au débordement) au profit de `BigInt` (R:210, E:36). Le §7 le dit (« attention à la perte de précision silencieuse »), le §3 non. La note 03 « Stack A » recommande `rust_decimal` : l'écart n'a pas été reporté.
- **Correction (à coller)** : « Crate pure + type décimal (`rust_decimal` avec `checked_*`, ou `num-bigint` comme dans l'expérience : `rust_decimal` y a été écarté pour une perte de précision silencieuse au débordement), newtypes, enums exhaustifs ».

### 11. Sécurité « urgente » : la faille **critique** est omise

- **Passage (R:50, R:263, R:380)** : « 14 failles ouvertes (`composer audit`, dont 1 haute sur Guzzle) » ; §11 : « 14 failles `composer audit` sur 4 paquets (api-platform/core ×2, guzzlehttp/guzzle dont 1 **haute** CVE-2026-69246…) ».
- **Problème** : `composer audit` (rejoué le 2026-10-02) donne bien 14 advisories sur 4 paquets, mais la plus grave n'est pas citée : **`mtdowling/jmespath.php`, CVE-2026-54133, sévérité critique**, dépendance transitive d'`aws/aws-sdk-php` (le SDK de R2). Répartition réelle : 1 critique, 1 haute (Guzzle), 12 moyennes (Guzzle ×8, psr7 ×2, api-platform/core ×2). La section est intitulée « urgent » ; le lecteur croit que le pire est une « haute ».
- **Correction (à coller, R:50)** : « 14 failles ouvertes sur 4 paquets (`composer audit`, 2026-10-02) : **1 critique** (`mtdowling/jmespath.php`, CVE-2026-54133, dépendance transitive d'`aws/aws-sdk-php` ; exposition réelle à qualifier), 1 haute (Guzzle, CVE-2026-69246) et 12 moyennes. Dependabot rouge depuis le 15 juin. » ; **R:263** idem ; **R:380** remplacer « dont 1 **haute** CVE-2026-69246… » par la même répartition.
- **Mise à jour utile de la colonne « Vérifié » (R:383)** : le `localStorage` du token web (et du refresh token) est confirmé dans `apps/web/src/lib/api.ts` ; remplacer « Selon la note 09 » par « Oui ».

### 12. Temps 1 : « ≈ 1-2 semaines » sous-estimé et non priorisé, au risque de recréer la « procrastination productive » que le rapport dénonce

- **Passage (R:258)** : « Temps 1 — Maintenant (≈ 1-2 semaines) : O1 » ; sept chantiers (sécurité, `Montant` en PHP et TS, oracle, PHPStan 10 + Deptrac + lints TS, Infection + propriétés, Stop-hook, job natif iOS + smoke test + E2E).
- **Problème** :
  - L'estimation vient de la note 11 (« plausible, à valider avec le fondateur ») ; ici elle est posée sans réserve.
  - Le périmètre est large : passer un code existant de PHPStan 9 à 10 avec strict-rules peut faire sortir des centaines d'erreurs, un type `Montant` touche la persistance (colonne `NUMERIC(12,2)`, casts) et deux clients TS, le job de build natif iOS (#87) traîne depuis la Phase 5, et la note 11 relève qu'un agent est peu fiable sur les builds natifs.
  - Le CLAUDE.md impose, avant le code : une issue par chantier, un ADR pour les choix non triviaux (type décimal, `bcmath` ou `brick/math` : le journal PHP note qu'une dépendance externe exige un ADR avec section sécurité ; Infection et Schemathesis en dépendances de dev), une entrée `THESAURUS` pour un terme nouveau (`Montant`, `TauxTva` contre `TauxTVA` déjà prévu), et pas de PR « drive-by ». Rien de cela n'apparaît.
- **Correction (à coller)** : « **Temps 1 — Maintenant : budget plafonné à 2 semaines calendaires, puis reprise de la Phase 6.2 ; le reste du socle se fait en continu, une issue par chantier.** _Avant la 6.2 (≈ 3-5 jours, estimation à valider)_ : sécurité et reprise (1), `Montant` décimal avec ADR + `THESAURUS` + fixtures (2 et 3, partie fixtures), Stop-hook (6). _En continu, sans bloquer la 6.2_ : PHPStan 10 et Deptrac étendu (sans nouvelle baseline), Infection sur le diff, Schemathesis et contrôle de dérive OpenAPI, job natif iOS (#87), smoke test d'image, E2E. Chaque dépendance de dev nouvelle suit le workflow ADR du CLAUDE.md. »

### 13. Q-D (« veto en 60 min ») est atteint par construction : pas de plan d'apprentissage ; le critère « sans agent » est étranger à l'objectif « agentique »

- **Passage (R:67, R:71, R:355)** : « Autonomie … PHP > TS > Rust à court terme » ; « Q-D … Échec en 60 min (**veto**) ».
- **Problème** :
  - Le rapport pose un fait supposé (« ne lit pas encore couramment Rust ») puis en fait un veto sans offrir de voie pour le lever. Sans apprentissage, Q-D échoue forcément, et le « ce n'est pas un non à Rust » (R:44) devient faux en pratique.
  - Le veto ne s'applique qu'à Rust, alors que « le bus factor, c'est l'agent » vaut pour toute stack dans un projet voulu agentique.
  - Pour un développement agentique, la compétence décisive est **relire et valider** du code produit par l'agent, plus qu'écrire seul.
  - Le rapport reprend l'estimation de Google (2-4 mois) en la déclarant non transposable (note 11 §3.1) mais ne propose rien d'autre.
- **Correction (à coller, sous le tableau §9 ou dans Temps 1 bis)** : « **Plan d'autonomie Rust (parallèle, optionnel).** 2 h par semaine pendant 6 semaines : lire le crate de l'expérience B avec l'agent comme tuteur, corriger 3 bugs injectés, réécrire seul un module de 100 lignes. Refaire l'expérience D à la fin. **Q-D devient deux mesures** : D1, corriger seul un bug injecté (veto s'il échoue après le plan d'apprentissage) ; D2, relire et justifier une PR de 200 lignes écrite par l'agent (la compétence que l'usage agentique exige réellement). Sans plan, le veto est atteint par construction. »

### 14. Q-E : taux de base déjà connu et classification qui exclut la classe que l'expérience dit favorable à Rust

- **Passage (R:356)** : « ≥ 30 % des bugs API échappés attrapables par Rust et pas par PHPStan 10, sur ≥ 10 bugs » / no-go « < 10 % ».
- **Problème** :
  - Les seuils sont « à calibrer **avant** de lire les données » (R:348), mais le taux de base est déjà connu : note 09 §6.2, 2-3 bugs d'API sur ~25 (≈ 8-12 %) relèvent de la « magie runtime Symfony/Doctrine ». Il tombe pile sur la frontière du no-go, avec un échantillon dominé par le CRUD et l'infra (R:157).
  - La classe « attrapable par Rust » est ambiguë. Le bug #81 (extension absente en prod) est classé « config-infra » dans la grille Q-E (note 11 §6.3), alors que l'expérience (§4 point 3, E:46) dit que cette classe est précisément celle qu'un binaire statique supprime pour les dépendances en Rust pur.
- **Correction (à coller)** : dans la cellule « Mesure » de Q-E : « Classes comptées comme Rust-favorables : (i) erreurs de type/état attrapées par le compilateur et pas par PHPStan 10, (ii) dépendance système absente en prod, pour une dépendance Rust pur (cf. #81). Taux de base observé en Phases 1-5 : 2-3 bugs d'API sur ~25 (≈ 8-12 %), hors calcul monétaire. **En dessous de 10 bugs d'API échappés : indécidable, pas « no-go ».** »

### 15. Couverture de la demande (c) : les « meilleures pratiques des plus grandes entreprises » ne couvrent que le cas Rust

- **Passage (R:118-147)** : §2.3.
- **Problème** : tous les cas sont des adoptions de Rust ou des abandons de Rust. Il manque le **précédent direct d'O1** : des grandes entreprises qui ont **durci une base existante plutôt que la réécrire** (typage graduel de PHP chez Meta avec Hack, Sorbet chez Stripe, mypy chez Dropbox), plus les pratiques non Rust de solidité à grande échelle (tests, mutation testing, revue) et le guide d'Anthropic sur Claude Code (donner à l'agent un moyen de vérifier son travail). Un grep (`Hack`, `Sorbet`, `mypy` hors le benchmark d'Endoh) dans les notes 02, 05, 06 et 07 ne trouve rien de cela, alors que l'objet de l'étude est justement « PHP durci contre Rust ».
- **Correction (à coller, fin du §2.3)** : « **Les grandes entreprises durcissent aussi leur base existante au lieu de la réécrire** : typage graduel de PHP (Hack, Meta), de Ruby (Sorbet, Stripe), de Python (mypy, Dropbox). _Ces cas n'ont pas été vérifiés dans cette étude (les notes 02, 05, 06 et 07 ne les couvrent pas) ; à vérifier avant d'en tirer une conclusion._ Ils indiquent que « O1 » est une pratique d'industrie, pas un statu quo. »

### 16. La réponse n'est pas en tête et la sensibilité à la définition de « solide » manque

- **Passage (R:19-55, R:57-71)** : « En bref » en 9 points, recommandation au point 7 ; §1 « à trancher en premier » ne demande rien.
- **Problème** : le commanditaire lit ce matin pour décider ; la réponse à « faut-il passer en Rust ? » arrive après six points d'analyse. La note 11 §7 demande d'ouvrir sur la définition de « solide », avec la conclusion qui change selon la pondération. Le §1 dit « à trancher en premier » mais ne formule aucune question à Maxime, et le rapport ne dit pas comment la conclusion change selon sa réponse.
- **Correction (à coller, juste avant « En bref »)** :
  > ## Réponse en cinq lignes
  >
  > 1. **« Tout en Rust » : non**, en 2026 (UI, mobile, jobs, Factur-X, auth : pas d'équivalent prêt).
  > 2. **API en Rust maintenant : non**, mais **Rust reste une option datée et conditionnée**, pas un refus (Temps 1 bis et Temps 3).
  > 3. **À faire maintenant, quel que soit le langage** : socle durci, `Montant` décimal, fixtures partagées, oracle, harness (Temps 1).
  > 4. **Le seul avantage propre à Rust démontré ici** : types qui rendent des erreurs irreprésentables sur le domaine de calcul. Il est qualitatif, sur n = 1.
  > 5. **La réponse dépend de ce que « solide » veut dire pour toi** (tableau ci-dessous).
  >
  > | Si ta priorité est…                 | Alors…                                                                                                                  |
  > | ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
  > | Correction du calcul monétaire      | O1, puis un spike Rust sur le domaine Devis en Phase 8 (O2a/O3 partiel) : la seule zone à avantage structurel documenté |
  > | Autonomie et reprise après pause    | O1 ; Rust seulement après le plan d'autonomie (point 13)                                                                |
  > | Pérennité du langage et performance | Rust crédible à terme, pas pour la bêta                                                                                 |
  > | Source unique du métier             | O4 ou O2a, conditionnés à Q-F                                                                                           |

### 17. Nomenclature des expériences confuse : « A/B » contre « expérience A » contre « expérience B »

- **Passage (R:14, R:45, R:192, R:224, R:370)** : « Expérience A/B en aveugle », « L'expérience A/B confirme sans trancher », « ce que devait faire l'expérience A, non menée ». `resultats.md` s'intitule « Expérience B ».
- **Problème** : « A/B » désigne la comparaison PHP/Rust, et « expérience A » (portage de la PR #105) est une autre expérience, non menée, que le lecteur ne rencontre définie qu'au §10 (R:370). Les expériences C, D, E, F (note 11 §6.2) ne sont jamais définies dans le rapport : elles sont citées (R:64, R:222, R:372). « Confirme » (R:45) est ambigu : il confirme quoi ?
- **Correction** : R:14 → « **Expérience B (comparaison PHP/Rust en aveugle)** ». R:192 → « 4. Résultats de l'expérience B ». R:45 → « **L'expérience B éclaire sans trancher** (§4) ». Ajouter au §9, sous le tableau : « **A** : porter la PR #105 (Lot/Tâche, avec Postgres) en Rust, non menée. **B** : cette comparaison, n = 1. **C** : un agent frais modifie la spec, on compte les régressions. **D** : le fondateur corrige, sans agent, un bug injecté. **E** : coût de boucle sur le code réel. **F** : comptage de la duplication aux Phases 7-8. »

### 18. Q-A « n'est pas franchi » : c'est « non évaluable »

- **Passage (R:217, repris E:26)** : « Le seuil Q-A n'est pas franchi (0 contre 0) » ; §10 (R:366) : « c'est en partie compensé par le passage du code PHP final à PHPStan niveau max ».
- **Problème** : Q-A (R:352) exige ≥ 3 runs par bras, ≥ 2 specs et un bras PHP durci. L'expérience a 1 run, 1 spec, PHPStan 9. Avec 0 défaut des deux côtés, l'écart (0 %) tomberait formellement dans le « no-go » (< 25 %), ce qui laisse croire à un résultat alors que le test n'a aucun pouvoir discriminant (effet plafond). Par ailleurs, passer PHPStan max sur le code final ne « compense » pas l'absence des strict-rules, de l'interdiction d'ignores et d'Infection.
- **Correction (à coller)** : R:217 → « Q-A n'est **pas évaluable** : il exige ≥ 3 runs par bras, ≥ 2 specs et un bras PHP durci, l'expérience en a un, une et PHPStan 9 ; et les deux bras sont au plafond (0 défaut), ce qui ne discrimine pas. Aucune preuve, ici non plus, que Rust produise du code plus juste. » R:366 → « Le bras PHP n'avait pas les mêmes règles strictes ; le passage final à PHPStan niveau max (0 erreur) vérifie le code livré mais **ne remplace pas** un bras PHP durci (strict-rules, interdiction d'ignores, Infection). »

---

## 3. MINEUR

### 19. Les branches de l'expérience ne sont pas poussées : l'expérience n'est rejouable que localement

- **Passage (R:385, E:102)** : « correctif prêt sur `worktree-agent-ab6ecd713ce85860f` » ; « Rien n'a été poussé ».
- **Problème** : B-PHP et B-Rust n'existent que dans des worktrees locaux sous `.claude/worktrees/` (untracked) ; `differentiel.py` et `sondes.py` ont ces chemins codés en dur. Un nettoyage de worktrees (déjà arrivé pour l'expérience A, E:9) supprimerait le code évalué et la correction `ext-bcmath`.
- **Correction** : ajouter à Temps 1 : « Pousser (ou archiver) les deux branches d'expérience avant tout nettoyage de worktree, et cherry-picker la correction `ext-bcmath` dans une PR `fix(api)` dédiée. »

### 20. FastRender présenté comme « contre-exemple » d'un portage

- **Passage (R:133)** : « Le seul contre-exemple documenté est **FastRender (Cursor)** : 3 M de lignes, note de maintenabilité 1,3/5 attribuée par SIG. »
- **Problème** : ce n'est pas un portage sous oracle mais un projet créé de zéro, présenté par Cursor comme « _an experiment … not a production browser_ » (note 10 #23) ; « cargo check never passed » est relayé comme constat d'un tiers à une date (note 10 #51). La critique d'Andrew Kelley sur Bun (« unreviewed slop », note 10 #43, confirmée) n'apparaît pas alors qu'elle vise un portage sous oracle.
- **Correction (à coller)** : « Contre-exemples documentés : **FastRender (Cursor)**, projet de zéro présenté comme une expérience (3 M de lignes, maintenabilité 1,3/5 selon SIG), et la critique du créateur de Zig sur le port de Bun (« un million de lignes non relues »). Aucun n'est un échec chiffré d'un portage sous oracle. »

### 21. Cloudflare : « causée par un `.unwrap()` »

- **Passage (R:144)** : « après sa panne du 18 novembre 2025 causée par un `.unwrap()` »
- **Problème** : d'après le post-mortem, la chaîne causale commence par un changement de permissions ClickHouse qui double le fichier de features ; le `unwrap()` est le point où le service plante.
- **Correction** : « après sa panne du 18 novembre 2025 (un fichier de configuration doublé a fait paniquer un `.unwrap()`) : »

### 22. « Enquêtes Rust » rangées sous « Les pratiques à copier »

- **Passage (R:142-147)** : la puce sur 55 % et 41,6 % est une donnée, pas une pratique.
- **Correction** : la déplacer sous « Ce que dit le code de Cordeau » ou la retitrer « Coûts perçus de Rust (enquêtes) », en ajoutant l'unité : « 55 % des répondants attendent plus de 10 s par rebuild (enquête 2025 sur le compilateur, > 3 700 réponses) ».

### 23. Précisions manquantes sur les études ETH et MSR

- **Passage (R:98-99)** : « Le décodage contraint par les types réduit ces erreurs de 74,8 %… » ; « 9 fois plus de `any` ».
- **Problème** : note 10 #8 : 74,8 % vaut pour HumanEval, 56,0 % pour MBPP, sur des modèles open-weight de 2B à 34B ; note 10 #9 : corpus filtré sur les PR liées aux types.
- **Correction** : « réduit ces erreurs de 74,8 % (HumanEval) et 56,0 % (MBPP), sur des modèles open-weight de 2B à 34B » ; « dans les PR TypeScript liées aux types (MSR 2026, effet petit) ».

### 24. axum 0.9 présenté comme existant

- **Passage (R:66, R:170)** : « crates web mouvantes (axum 0.9, sqlx 0.9, utoipa 6 en rupture) » ; « axum 0.9 et sqlx 0.9 apportent des ruptures ».
- **Problème** : au 2026-10-01 la dernière version d'axum est la 0.8.9 ; la 0.9 est « en préparation », sans date (note 03). sqlx 0.9 est sorti (2026-05-21).
- **Correction** : « axum 0.9 (en préparation, ruptures annoncées), sqlx 0.9 et utoipa 6 (ruptures publiées) ».

### 25. Ronacher 2026 et rollback ×4 de Google : lignes de sources à resserrer

- **Passage (R:140, R:422, R:124)** : « Ronacher et Hashimoto recommandent Go pour les agents » avec, dans les sources, le billet 2026 « A Language for Agents » ; « 4 fois moins de rollbacks ».
- **Problème** : le billet de 2026 ne contient aucun verdict sur Go ni sur Rust (note 10 #35, note 11 C4). La note 11 §7 interdit de citer le rollback ×4 comme transposable ; il est ici correctement daté « contre C++ » mais présenté dans une liste de « succès ».
- **Correction** : R:422 → retirer « · A Language for Agents » ou le renommer « (critique générique des macros, sans verdict sur Rust) ». R:124 → ajouter « (chiffres non transposables à un SaaS memory-safe) ».

### 26. Dates et décompte

- **Passage (R:299, R:36)** : « La pause de 109 jours » ; « Aucun commit sur `main` depuis le 2026-06-14 ».
- **Problème** : au 2026-10-02, du 2026-06-14 au 2026-10-02 il y a 110 jours (109 depuis le 15 juin).
- **Correction** : « La pause de 110 jours (depuis le dernier commit sur `main`, le 2026-06-14) ».

### 27. En bref 4 : deux raccourcis

- **Passage (R:30-31)** : « **Aucun** n'est une erreur de type que PHPStan 9 aurait laissée passer. » ; « Les montants sont aujourd'hui des `float`. »
- **Problème** : la note 09 §6.2 dit « 0 **bug d'API** » (les bugs mobiles en TypeScript n'ont pas été évalués contre un TS strict). Les « montants » sont les estimations de lot (`float` PHP et `number` JS, colonne SQL `NUMERIC(12,2)`), seules valeurs monétaires existantes avant la Phase 8.
- **Correction** : « **Aucun bug d'API** n'est une erreur de type que PHPStan 9 aurait laissée passer. » ; « Les seules valeurs monétaires existantes (estimations de lot) sont des `float` PHP et `number` JS, avec une colonne SQL décimale. »

### 28. Interprétation excessive des « mutants non viables » (`resultats.md`)

- **Passage (E:71, R:213)** : « **19 % des mutants ne compilent même pas**, le système de types élimine une partie de l'espace d'erreurs » ; « **29 rejetés par le compilateur** ».
- **Problème** : un mutant non viable est écarté du score, pas une erreur évitée. En PHP, un mutant qui produit une erreur de type est simplement « tué » à l'exécution. Le chiffre ne distingue pas les deux langages sur la solidité.
- **Correction** : « 29 mutants non viables (écartés du score par cargo-mutants) ; ce chiffre décrit l'outil, pas un avantage de solidité démontré. » La ligne « Mutation testing » du §4 doit aussi préciser que l'absence de mesure PHP vient d'un **outillage local** (ni pcov ni xdebug), pas d'une propriété du langage.

### 29. Lisibilité : jargon et renvois

- **Problèmes** :
  - Jargon non défini à la première occurrence : _oracle_ (R:129, défini seulement R:200), _strangler fig_, _harness_, _Stop-hook_, _newtype/typestate_, _FFI_, _WASM_, _UniFFI_, _Schemathesis_, _bus factor_, _mutation testing_, _Deptrac_. Public : un développeur Symfony qui ne lit pas encore Rust.
  - Collision de notation : « [10 #30] » (ligne n° 30 du tableau de la note 10) et « #81 », « #87 » (issues GitHub).
  - Les codes Q-E, Q-F sont employés (R:280, R:287) avant la définition au §9.
- **Correction (à coller, après « En bref »)** :
  > **Mini-glossaire.** _Oracle_ : jeu de tests et implémentation de référence indépendants du code testé. _Strangler fig_ : remplacer un système morceau par morceau derrière un proxy. _Harness_ : l'ensemble hooks + lints + CLAUDE.md qui contraint l'agent. _Stop-hook_ : script exécuté à la fin d'un tour de l'agent, qui bloque tant que tests et analyse statique ne passent pas. _Newtype_ : type enveloppe (ex. `Montant`) qui interdit les mélanges d'unités. _FFI/WASM_ : deux façons d'appeler du Rust depuis un autre langage. _Bus factor_ : nombre de personnes (ou d'outils) dont la perte bloque le projet.
  >
  > Renvois : « [10, n° 30] » désigne la ligne 30 du tableau de la note 10 ; « #81 » désigne l'issue GitHub.

### 30. « Notre précédente discussion » dans un document versionné

- **Passage (R:15)** : « Aucune trace de notre précédente discussion sur Rust n'a été retrouvée (Notion, memories, ADRs) : l'étude repart de zéro. »
- **Problème** : « notre » n'a pas de référent pour un lecteur futur (ni pour l'équipe, si le repo est partagé).
- **Correction** : « Aucune trace d'une étude ou d'une discussion antérieure sur Rust n'a été retrouvée (Notion, memories Serena, ADRs) : l'étude repart de zéro. »

### 31. §8 : l'étude ne dit pas quels outils elle a réellement utilisés

- **Passage (R:329-342)** : tableau des outils recommandés.
- **Problème** : la demande (d) est « chercher les meilleurs outils pour faire ces recherches ». Le §8 recommande, mais la section « Méthode » ne dit pas ce qui a été utilisé (d'après l'en-tête de la note 08 : `gh api`, sondes d'API crates.io/deps.dev/OSV/Context7 lancées en direct, agents en parallèle ; Context7 et le MCP GitHub non authentifiés dans la session). La note 08 cite un benchmark indépendant (AIMultiple, décembre 2025) où Brave est premier, Firecrawl deuxième et Exa troisième, avec des écarts que les auteurs jugent peu significatifs ; le rapport recommande Exa en premier sans le dire (choix fondé sur la friction d'installation, ce qui est légitime).
- **Correction (à coller)** : dans « Méthode » : « **Outils réellement utilisés** : lecture web, `gh api`, API crates.io/deps.dev/OSV, agents en parallèle (liste exacte à confirmer d'après la note 08) ; Context7 et le MCP GitHub n'étaient pas authentifiés. » Dans le §8, pour Exa : « choisi pour sa faible friction (plugin officiel, sans clé) ; le seul benchmark indépendant trouvé classe Brave, Firecrawl et Exa à égalité statistique. »

### 32. E-facture : consigne de la note 11 (C8) non relayée

- **Passage (R:37)** : « Émission de factures électroniques obligatoire pour les TPE au 1er septembre 2027. »
- **Problème** : exact, mais la note 11 C8 demandait de signaler que l'ADR 0016 écrit « obligatoire pour les entreprises au 1er septembre 2026 », ce qui est faux pour l'émission des TPE (2027), alors que la **réception** est déjà obligatoire pour tous depuis le 1er septembre 2026 (note 10 #39). Cela touche l'hypothèse « V1 sans PDP » (ADR 0016) et mérite une ligne dans §11.
- **Correction (à ajouter au §11)** : « ADR 0016 (« obligatoire pour les entreprises au 1er septembre 2026 ») à corriger : réception obligatoire pour tous depuis le 2026-09-01, émission et e-reporting des TPE au 2027-09-01. À confirmer que la V1 n'a pas besoin de gérer la réception. »

### 33. Pilote Rust à faible risque non nommé

- **Passage (R:173, R:295)** : « PDF devis/facture : Typst… Un point fort de Rust » ; « Rust reste un outil ponctuel (PDF Typst, services isolés) » seulement dans le cas « Sinon ».
- **Problème** : pour un commanditaire qui veut Rust, le chemin le plus doux n'est pas présenté comme une option dès la Phase 8 : un pilote Rust à faible risque (génération PDF Typst) appelé depuis PHP, sans toucher à l'auth ni aux données. Le rapport mentionne l'ajout éventuel au `fly.toml` (256 Mo, commentaire sur la génération PDF) sans le relier.
- **Correction (à ajouter au Temps 2)** : « **Pilote Rust à faible risque (Phase 8)** : générer les PDF de devis avec Typst, appelé comme binaire ou crate isolé depuis PHP (à confirmer : intégration, polices, mémoire sur 256 Mo). Il donne une expérience Rust réelle, indépendante de l'auth et des données, et nourrit le plan d'autonomie (point 13). »

---

## 4. Verdict global

1. **Le fond est honnête et solide** : chiffres vérifiés, corrections de la note 10 appliquées, consignes de la note 11 §7 largement respectées, expérience correctement qualifiée « n = 1 » dans l'ensemble.
2. **Deux erreurs de fait à corriger avant lecture** : la phrase sur les tests de la migration Copilot (point 1) et la faille critique omise dans la section sécurité (point 11) ; plus l'attribution erronée des « 660 lignes » (point 4).
3. **Biais résiduel vers le statu quo** : la phrase « presque rien de plus » (point 2), la ligne (c) non mise à jour (point 7), et surtout un chemin vers Rust sans règle de décision, sans expériences planifiées, sans date et avec un veto sans plan d'apprentissage (points 3, 13, 14). Le rapport dit « ce n'est pas un non » mais ne donne pas encore les moyens que ce soit vrai.
4. **Biais inverse, plus léger, dans §4** : le tableau de l'expérience omet les lignes défavorables à Rust et l'argument « garantie portée par le compilateur » est présenté comme acquis (points 5 et 6).
5. **Publiable après correction des points 1 à 3 et 11 ; les autres points IMPORTANTS améliorent la décision, pas son sens.**
