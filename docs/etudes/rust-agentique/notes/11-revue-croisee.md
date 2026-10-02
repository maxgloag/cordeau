# 11 — Revue croisée des notes 01 à 09 (contradicteur)

> Rôle : contradicteur, pour le rédacteur du rapport final. Rédigé le 2026-10-02.
>
> Matériau lu en entier : notes 01 à 09, `experience/protocole.md`, `experience/spec-totaux-devis.md` (début), `CLAUDE.md`, `ROADMAP.md` (statut, Phases 6 à 10) et ADR 0002, 0016, 0017.
>
> Cette note ne refait pas la vérification factuelle, confiée à un autre agent : les écarts chiffrés sont seulement signalés « → fact-check ». Elle n'introduit aucune source extérieure ; tout fait extérieur nécessaire est marqué « à vérifier ». Un seul fait a été relevé localement : le marketplace officiel Claude Code contient `php-lsp` (Intelephense) et `typescript-lsp` à côté de `rust-analyzer-lsp` (copie locale `~/.claude/plugins/marketplaces/claude-plugins-official`, date de la copie non vérifiée).
>
> Renvois : `[06 §4]` = note 06, section 4 ; `[01 Q2]` = note 01, question 2 ; `[09 §5.2]` = note 09, sous-section 5.2.

---

## 0. Ce que le rédacteur doit retenir

1. **Le point robuste.** Les notes s'accordent : le gain démontré vient d'un **typage statique strict sans échappatoire**, combiné à un **oracle de tests**. Il ne vient pas de Rust en propre [01 Synthèse ; 07 Q9 ; 02 §1 ; 06 §1]. La part propre à Rust (ownership, borrow, lifetimes) ne pèse qu'environ 1,7 % des diagnostics des agents en 2026 [01 Q2]. Ce chiffre vient de GitHub, qui vient justement de migrer _vers_ Rust : une donnée défavorable à sa propre thèse, donc plutôt crédible.
2. **Aucune mesure pertinente.** Aucune note ne mesure Rust contre PHP ou TS sur une application web métier ou CRUD. Toutes les preuves fortes en faveur de Rust sont des **portages dont le comportement est figé par un oracle** (Bun, runtime Copilot, Ladybird, wamedia, compilateur C). Tous étaient motivés par la mémoire, la performance ou l'ABI, trois problèmes que Cordeau n'a pas [01 Q4 ; 02 §1 ; 06 §1].
3. **La question « migrer l'API ? » est mal posée.** Rust apporte le plus sur le domaine de calcul pur (Mesures, totaux de Devis, TVA). Or cette même logique doit **aussi** tourner hors ligne sur mobile, en TS [09 §5.2 ; ROADMAP 7.3 et 8.5]. Migrer l'API seule ne fait que remplacer la duplication PHP↔TS par une duplication Rust↔TS [09 §7]. Le vrai levier est le **nombre d'implémentations** du domaine de calcul et la façon de les garder synchronisées.
4. **Le recensement des bugs est biaisé.** D'après [09 §6.2], environ 19 bugs sur 25 relèvent de la config, de l'infra, de la plateforme ou de l'UI, et aucun n'est une erreur de typage d'API passée sous PHPStan 9. Mais l'échantillon couvre surtout le CRUD et l'infra. Les phases à risque pour les types (calcul monétaire, états de Devis) n'ont pas commencé, et des montants sont déjà stockés en `float` [09 §1.3, §5.2]. Le passé dit où sont allés les bugs ; il ne réfute pas le risque à venir.
5. **Le protocole A/B est asymétrique.** Il oppose un Rust durci (clippy pedantic + lints restrictifs) à un PHP non durci (PHPStan 9, sans strict-rules ni Infection) [protocole, expérience B]. Il mesure donc « langage + durcissement » contre « langage seul ». Sans bras PHP durci, H1 ne peut pas isoler l'effet du langage (§6.1).
6. **Le coût dominant est le temps du fondateur.** Ce ne sont ni les tokens (ordre de grandeur 10³ $ pour porter environ 9 k lignes, §3.4) ni la compilation. C'est le **temps calendaire du fondateur avant validation** [06 §4], sur un projet sans commit sur `main` depuis le 2026-06-14 [09 §0].
7. **Angle mort majeur : la maintenance sans agent.** Une panne, un changement de prix ou de politique, une régression de modèle suffisent à poser la question. Pour un dev Symfony solo qui ne lit pas couramment Rust, l'agent devient un point unique de défaillance. Aucune note ne traite ce point ; 02 §5 et 06 §3 l'effleurent.
8. **« Solide » n'est défini nulle part.** Les notes l'assimilent tour à tour à « moins d'erreurs à la compilation », « moins de bugs en prod », « maintenable » ou « pérenne ». La pause de 109 jours offre pourtant une mesure gratuite, ignorée : **ce qui s'est dégradé sans intervention**. Dependabot est rouge depuis le 2026-06-15, 14 advisories sont ouvertes, et les memories et docs dérivent [09 §1.1, §1.6].
9. **Décomposition (§4).** Rust apporte une part **unique** surtout sur les types somme, l'exhaustivité et `Result`, un peu sur les frontières par crates. Sa contribution est quasi nulle sur la sûreté mémoire (déjà acquise en PHP et TS), les tests et le harness. Elle est **négative** sur la vitesse de boucle. Le premier gisement de solidité observé (environnement, config, plateforme) ne dépend pas du langage.
10. **Matrice (§5).**
    - O1 (statu quo durci) est **sans regret** : c'est le prérequis de toutes les autres options, puisqu'il construit l'oracle agnostique.
    - O5 est hors jeu en 2026 [04 Q1-Q2].
    - Un cœur Rust côté serveur seulement est **dominé** : il ajoute une troisième langue sans supprimer aucune duplication.
    - O3 et O4 sont des paris de réécriture. Leur arbitrage dépend d'une donnée que personne n'a encore : le volume des règles partagées en Phases 7 et 8, et leur taux de divergence.
11. **Attendre a une valeur d'option.** Si les agents rendent les portages sous oracle bon marché (11 jours pour Bun [01 Q4]), **migrer plus tard coûte peu** et se décide mieux informé. L'argument « migrer maintenant tant que c'est petit » s'affaiblit d'autant. Le seul actif qui ne se rattrape pas, c'est l'oracle : il faut le construire maintenant, quelle que soit l'option.
12. **Seuils go / no-go en §6.** Ils doivent être fixés **avant** de lire les résultats de l'expérience en cours.

---

## 1. Contradictions entre notes, et comment les arbitrer

| #   | Sujet                                               | Ce que disent les notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | Arbitrage (meilleure source)                                                                                                                                                                                                                                                                      | Suite                                                                                                                                                                                    |
| --- | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| C1  | Part des échecs « compilation » dans Rust-SWE-bench | [06 §2] : « **76,3 % des échecs** sont des erreurs de compilation (43,7 % structure, 32,6 % types) », repris en inférence (« cohérent avec les 76 % d'échecs compilation »). [01 Q2-Q3] et [05 Q3, Q5] : 43,7 % et 32,6 % **des erreurs de compilation**, environ 3 erreurs de compilation par tâche.                                                                                                                                                                                                                                                                                                                 | 76,3 = 43,7 + 32,6 : 06 a apparemment converti une **répartition des erreurs de compilation** en **part des échecs**. 01 et 05 concordent et citent le papier.                                                                                                                                    | → fact-check. Retirer l'inférence de 06 §2 du rapport.                                                                                                                                   |
| C2  | Rang de Rust sur Multi-SWE-bench                    | [06 §2] : « Rust, C et Go parmi les plus durs ; meilleur agent ~12 % sur Rust (slides) ». [01 Q1] : avec MagentLess + Claude 3.7, Rust 5,44 % devant TS 3,57 % et JS 1,97 %. [07 Q2] : meilleur score Claude 3.7 toutes méthodes, Rust 15,90 %, 2ᵉ derrière Java (23,44 %), devant TS (11,16 %), Go (7,48 %) et JS (5,06 %).                                                                                                                                                                                                                                                                                          | Les chiffres peuvent coexister (méthodes différentes), pas les interprétations : sur ce benchmark, Rust est 2ᵉ sur 7, pas « parmi les plus durs ». 07 est la meilleure source (ventilation et nombre d'instances par langage). 01 signale lui-même une extraction automatique à revérifier.       | → fact-check sur le Tableau 4 du PDF.                                                                                                                                                    |
| C3  | SWE-Bench ProMax                                    | [01 Q1] : « C++ 54,5 % (Qwen3.5) ». [05 Q5] : « GPT-5.2 54,5 % sur Rust ; Kimi-K2.5 18,2 % ».                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | Le même chiffre est attribué à deux couples (langage, modèle) différents. 05 avoue des « chiffres issus de résumés, non relus » ; 01 détaille langage par langage. Arbitrage provisoire en faveur de 01.                                                                                          | → fact-check.                                                                                                                                                                            |
| C4  | Ronacher, « A Language For Agents » (2026-02-09)    | [06 §2] en tire « agents struggle with macros (Rust repose massivement sur les macros) » et « l'agent performe much better en TypeScript ». [07 Q4] cite « the agent does much better with TypeScript **than Python** » et précise que « Rust n'est pas cité dans ce billet » ; [01 Q5] dit la même chose.                                                                                                                                                                                                                                                                                                            | 06 a ajouté une glose sur Rust à une critique générique et supprimé le terme de comparaison (Python). 01 et 07 font foi.                                                                                                                                                                          | Ne pas citer ce billet comme argument anti-Rust ; s'en tenir à la critique générique des macros (qui vaut aussi pour les attributs Symfony [01 Q5]).                                     |
| C5  | Modèle d'auth actuel de Cordeau                     | [03 Q4 et Synthèse] : « cookie de session web + token opaque mobile » ; la stack A « garde l'auth actuelle (cookie + opaque) » et prévoit un extracteur cookie + bearer. [09 §1.8] : un seul mécanisme, un token opaque Bearer pour le web et le mobile depuis `d883d9b` (2026-05-17), avec des tokens web en `localStorage`.                                                                                                                                                                                                                                                                                         | 09 fait foi (lecture du code). 03 a vraisemblablement hérité de la memory Serena périmée signalée en [09 §1.1].                                                                                                                                                                                   | Le socle auth chiffré par 03 (Q11) est surdimensionné. Plus important : la dérive de la doc a contaminé une note de recherche, ce qui illustre l'angle mort §3.8.                        |
| C6  | Maturité du pont Rust ↔ React Native                | [07 Q5] présente `uniffi-bindgen-react-native` avec « usage en prod : ChessTiles », sans réserve. [04 Q3] cite la doc officielle : « early development, should not yet be used in production » (vérifié dans le source le 2026-10-01), et des issues Expo restées ouvertes.                                                                                                                                                                                                                                                                                                                                           | 04 fait foi.                                                                                                                                                                                                                                                                                      | Toute option « cœur partagé mobile » (O2a) repose sur un maillon que son propre auteur déclare non prêt.                                                                                 |
| C7  | Stabilité de l'écosystème Rust                      | [07 Q7, Q9] note Rust ●●●●○ en « maintenabilité et stabilité », au-dessus de PHP/Symfony (●●●○○). [03] documente, pour les briques web dont Cordeau aurait besoin, l'état suivant :<br>– axum 0.9 en préparation, avec des changements breaking ;<br>– sqlx 0.9 : trait `Migrate` modifié, gouvernance transférée, 778 issues ouvertes ;<br>– utoipa 6 breaking, avec un bus factor de 1 ;<br>– apalis en RC breaking depuis des mois ;<br>– SeaORM 2 et Loco 1.0 sortis en juillet 2026 ;<br>– tower-sessions et axum-login reposent sur un seul mainteneur, et leurs stores n'ont pas eu de release depuis 21 mois. | 07 note la stabilité **du langage** (éditions) ; 03 mesure celle **de l'écosystème applicatif**. Pour Cordeau, c'est l'écosystème qui compte, et 03 s'appuie sur des données primaires (crates.io, `gh api`).                                                                                     | Abaisser la note de Rust sur ce critère, ou la scinder en deux : « langage » et « crates web ».                                                                                          |
| C8  | Poids de la facturation électronique                | [07 Q7] : la réforme « rend la brique Factur-X/PA structurante pour Cordeau ». [03 Q8 et dernière section] : « impact fort en V2, obligation légale ». [06 §2, §4] et [07 Q7 Inferences] : l'émission passe par une plateforme agréée via API, ce qui rend la brique largement agnostique du langage. ADR 0016 : V1 sans PDP ; V2 = partenariat ou intégration PDP.                                                                                                                                                                                                                                                   | 06 et l'ADR 0016 font foi. L'écart d'écosystème Factur-X est réel [03 Q8], mais il ne pèse que si Cordeau génère et valide lui-même du Factur-X, ce que la trajectoire actée exclut.                                                                                                              | Pour le rédacteur : l'ADR 0016 écrit que l'obligation s'applique « aux entreprises au 1er septembre 2026 ». 06 §4 et 07 Q7 précisent : réception en 2026, émission pour les TPE en 2027. |
| C9  | Fiabilité des agents en TS/React                    | [04 Q7] : « React/TS/Expo est le terrain où l'agent est le plus fiable (volume d'entraînement, API stables) », sans source. [01 Q1] : TS/JS sont systématiquement parmi les plus faibles sur les benchmarks de dépôts. [07 Q7] : TS a l'écosystème le plus mouvant (TS 7, Effect 4). [01 Q2] : les agents écrivent 9 fois plus de `any` que les humains.                                                                                                                                                                                                                                                              | Personne ne mesure React/Expo. 01 et 07 ont des sources, 04 une assertion.                                                                                                                                                                                                                        | Le rapport doit écrire « non mesuré », pas « le plus fiable ».                                                                                                                           |
| C10 | Le coût de migration est-il décisif ?               | [01 Q4] : « le coût de migration n'est probablement pas l'argument décisif ; le coût de maintenance l'est davantage ». [06 §4] : « l'argument le plus fort contre la migration maintenant n'est pas technique » (coût d'opportunité).                                                                                                                                                                                                                                                                                                                                                                                 | Pas de contradiction réelle : 01 parle de **tokens**, 06 de **temps du fondateur avant validation**. Pour un solo sans revenu, c'est la mesure de 06 qui compte.                                                                                                                                  | Le rapport doit nommer l'unité de coût à chaque fois.                                                                                                                                    |
| C11 | Taille de la surface migrable                       | [01 Q3-Q4] et [06 §1] raisonnent sur « 15-20 k lignes », et 06 en déduit « ~4 % de la taille de Bun ». [09 §0] : l'API PHP compte environ 5 k lignes de src, 3,7 k de tests et 0,35 k de migrations, soit environ 9 k lignes. Les 15-20 k correspondent à tout le monorepo, dont environ 9 k lignes de TS non migrables vers Rust.                                                                                                                                                                                                                                                                                    | 09 fait foi. Pour la surface réellement migrable, la proportion est d'environ 1,7 % de Bun, pas 4 %.                                                                                                                                                                                              | Calculer les ordres de grandeur de coût sur environ 9 k lignes.                                                                                                                          |
| C12 | Deptrac contre crates Cargo                         | [03 Q5] : un workspace Cargo fait appliquer la direction des dépendances par le compilateur, « ce que Deptrac vérifie a posteriori ». [05 Q3] : « un Deptrac gratuit ». [09 §2.2-2.3] : Deptrac tourne **déjà** en CI, mais son périmètre exclut Auth, Client et Photo.                                                                                                                                                                                                                                                                                                                                               | Le gain réel n'est pas « vérifié contre non vérifié ». Il tient à deux différences : vérification au build local plutôt qu'en CI, et périmètre total plutôt que partiel. Le périmètre se corrige en élargissant `deptrac.yaml`, la vérification locale par un hook. 03 et 05 surévaluent ce gain. | —                                                                                                                                                                                        |
| C13 | Durée de la panne Cloudflare du 18/11/2025          | [02 §1] : « cœur 11:20–14:30 (3 h 10), rétablissement complet 17:06 ». [05 Q2] : « ~6 h ».                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Compatible selon la définition retenue ; 02 est plus précis.                                                                                                                                                                                                                                      | Harmoniser.                                                                                                                                                                              |
| C14 | Mündler et al. (PLDI 2025)                          | [01 Q2] : −75,3 % d'erreurs de compilation (HumanEval) et −52,1 % (MBPP). [07 Q1] : « −74,8 % ».                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | Probablement deux tables différentes (synthèse vs traduction). Sans incidence sur le raisonnement.                                                                                                                                                                                                | → fact-check.                                                                                                                                                                            |
| C15 | « Rust ~2× plus d'erreurs que C/C++ »               | La synthèse de [01] parle d'« environ 2× C ou C++ en volume ». [01 Q3], dans la même note : « comptes absolus, pas des taux ».                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Contradiction interne : la synthèse transforme en ratio une limite que la note a elle-même signalée.                                                                                                                                                                                              | Ne pas reprendre ce ratio.                                                                                                                                                               |

---

## 2. Raisonnements faibles

### 2.1 Extrapolations hors du régime observé

- **Du portage sous oracle au développement produit.**
  - Bun, Copilot, Ladybird, le compilateur C, wamedia et giflib partagent tous un comportement figé, un oracle massif indépendant du langage et une motivation mémoire, performance ou ABI [01 Q4 ; 02 §1, §3 ; 06 §1]. Toutes les notes le disent.
  - Pourtant, la conclusion « la compétence des agents en Rust n'est plus en question » [01 Q4, Inferences] glisse ensuite vers « Rust est viable pour Cordeau ».
  - Savoir _traduire sous oracle_ ne dit rien de la capacité à _concevoir sous une spec qui bouge_. Or c'est le régime de Cordeau : la modélisation des Phases 6 à 9 est encore ouverte, et l'ADR 0024 vient d'affiner Lot et Tâche.
  - Le protocole le reconnaît pour l'expérience A, mais les notes ne le répercutent pas sur leurs synthèses.
- **De l'infrastructure au SaaS CRUD.**
  - Les gains de Google/Android (rollbacks 4 fois moins fréquents, revue 25 % plus courte) sont mesurés **contre C++** [02 §1]. Ceux de Cloudflare FL2 le sont contre NGINX + LuaJIT, ceux de DSQL contre une JVM sous charge GC.
  - 02 le signale, mais retient quand même le rollback ×4 comme « le chiffre le plus transposable » [02 §1, Inferences]. Il ne l'est pas : il n'y a pas de comparateur à la fois memory-safe et analysé statiquement.
  - Même problème pour la panne Cloudflare du 5/12 (Lua, `nil`), présentée comme « prévenue par un typage fort » [02 §1]. PHPStan attrape aussi un accès sur `null`. C'est un argument pour le typage, pas pour Rust.
- **Des modèles de 2023 aux agents de 2026.**
  - La projection 30 / 25 / 45 % [07 Q1] s'appuie sur Tambon, c'est-à-dire du Python généré par CodeGen, PanGu et Codex. 07 la présente comme un ordre de grandeur, mais la matrice de Q9 en hérite.
  - Avec des agents qui itèrent contre un type-checker, la tranche d'environ 30 % « attrapable par tout typage » est résorbée avant le commit **dans toutes les options typées** ; 07 Q1 le reconnaît lui-même.
  - Restent discriminantes la tranche partielle (environ 25 %) et surtout les quelque 45 % de bugs non typables. Ce constat renforce l'argument des tests, pas celui de Rust.
- **Des benchmarks de génération aux agents de dépôt.** AutoCodeBench (problèmes générés par LLM, différents d'un langage à l'autre) figure dans les matrices [07 Q2, Q9]. Pourtant, 01 Q1 et 07 Q2 reconnaissent tous deux qu'il compare des jeux de problèmes différents.
- **De « pas de bug de typage dans l'API » à « Rust est inutile ».**
  - Le recensement de [09 §6.2] a un biais d'échantillon (§0, point 4) : 5 mois, du CRUD et de l'infra, aucun calcul monétaire, aucune machine à états exposée. Les transitions `passerEnCours` et `terminer`, par exemple, ne sont exposées par aucun endpoint [09 §5.1].
  - Conclusion licite : « jusqu'ici, le système de types n'était pas le goulot ».
  - Conclusion non licite : « il ne le sera pas en Phase 8 ».

### 2.2 Chiffres non sourcés qui risquent d'être cités comme des faits

- **« 70 à 80 % ».** [07 Q8] écrit que le statu quo durci « capte peut-être 70 à 80 % du bénéfice compile-time ». Il n'y a ni source ni calcul. À présenter comme une intuition, ou à supprimer.
- **Deux estimations non vérifiées, une de chaque camp.** [06 §5] estime le durcissement de PHP à « des jours, pas des mois » ; c'est plausible mais non chiffré. [03 Q11] estime le socle Rust à « ≈ 500-700 lignes », sans prototype ; 03 le dit lui-même dans ses Gaps. Le rapport ne doit pas comparer ces deux devinettes comme si c'étaient des mesures.
- **Une incohérence interne dans la matrice de 07.** [07 Q9] donne Rust ●●●●○ et PHP ●●●○○ en « compétence LLM ». Or [07 Q2] juge non significatif l'écart Rust 58 % / PHP 49 % (±15 points sur environ 43 tâches). Il faut égaliser les deux notes ou justifier l'écart autrement.

### 2.3 Biais de sélection

- **Les retours pro-Rust** viennent de contextes système ou outillage (Klabnik, Woolf, Bun, zackoverflow) [01 Q5 ; 07 Q4]. Aucun ne concerne un SaaS métier développé en solo.
- **Les enquêtes Rust** (82 % disent que Rust « a aidé l'entreprise », 85 % sont confiants dans leur code) interrogent des gens qui ont déjà choisi Rust, et la comparaison implicite est C/C++ [02 §5 le note].
- **L'échec documenté est rare.** FastRender est le seul [01 Q4] : les projets abandonnés ne publient pas de post-mortem. L'absence de retour « SaaS CRUD réécrit en Rust » [06 §1 Gaps] ne prouve ni un succès ni un échec.
- **Les notes 03 et 05 partent du principe que Rust est adopté** (« stack recommandée », harness « must-have »). Elles répondent à « si oui, comment ? », mais leur ton prescriptif peut se lire comme une recommandation d'adoption. Le rapport doit le dire.

### 2.4 Arguments d'autorité et de principe

- **Les deux camps de praticiens.** Ronacher et Hashimoto, pro-Go [01 Q5 ; 06 §2-3 ; 07 Q4], donnent des opinions éminentes mais isolées et non mesurées. Elles sont utiles comme hypothèses (cache de tests, simplicité), pas comme verdict. Même statut, de l'autre côté, pour « The Compiler Is the Harness », Belderbos et HAMY [01 Q5] : une thèse sans données.
- **Microsoft M-DESIGN-FOR-AI** (« le compilateur compense ») énonce un principe, pas une mesure ; [02 §3] le dit.
- **La doctrine startup.** Spolsky, Brooks, McKinley et YC [06 §1, §4] relèvent de la doctrine. 06 en fait un steelman honnête : l'argument de Spolsky est faible pour un code de 5 mois. Leur vraie valeur ici est de nommer le risque du second système. L'ADR 0017 a déjà identifié ce risque pour l'IA, et la même logique « valider avant la sophistication invisible » s'applique à Rust ; aucune note ne fait ce rapprochement.

### 2.5 Confusions de mesure

- **Où l'erreur est attrapée n'est pas la même chose que si elle l'est.** H2 et une partie de 03 et 05 valorisent le fait d'attraper une erreur à la compilation plutôt qu'aux tests. Si les tests l'auraient attrapée de toute façon, c'est un gain de **vitesse**, pas de **solidité**. La métrique de solidité est le **défaut résiduel après la boucle complète** : vecteurs cachés, mutants survivants, bugs en prod.
- **« Tests rendus inutiles par les types »** (protocole, expérience A). Un test qui vérifie `Surface > 0` ne disparaît pas en Rust : il devient le test du constructeur `TryFrom`. Seuls les tests de forme (types d'arguments, nullabilité) disparaissent, et ils sont rares dans le PHP actuel. Il faut définir cette métrique avant le comptage, sinon elle avantage Rust à tort.
- **Le score de mutation n'est pas comparable d'un outil à l'autre.** Infection et cargo-mutants n'appliquent pas les mêmes opérateurs. Les mutants servent de diagnostic au sein d'un langage ; entre langages, la métrique doit être les vecteurs cachés.
- **La vitesse de boucle n'est mesurée nulle part sur Cordeau.**
  - [07 Q3] classe PHP ●●●●○ (« pas de build ») sans compter un PHPStan 9 complet ni un PHPUnit sur Postgres réel.
  - [05 Q4] raisonne sur un `cargo check` incrémental.
  - Aucune mesure n'existe pour Cordeau, d'un côté comme de l'autre [05 Q4 Gaps ; 07 Q3 Gaps]. Seul repère : le job CI `api`, qui dure 56 à 67 s [09 §2.3].

---

## 3. Angles morts qui comptent pour la décision

### 3.1 Maintenance par un humain seul : le bus factor, c'est l'agent

- **Ce qui différencie les options.** Plusieurs ruptures sont possibles : hausse de prix ou de quotas, indisponibilité, régression de modèle, changement de politique, ou simplement un débogage en prod à 23 h. Toutes les options dépendent de l'agent. Elles diffèrent par **ce que le fondateur peut faire sans lui** :
  - Symfony est son langage de compétence ;
  - TS est déjà pratiqué sur le web et le mobile ;
  - Rust est à apprendre. Google mesure 2 à 4 mois de montée en compétence, mais pour des ingénieurs encadrés venant de C++, Java ou Go [02 §5] : ce n'est pas transposable.
- **Les échappatoires reposent sur la relecture humaine.** En Rust, on les ferme par une justification relue (`#[expect(..., reason = "...")]`) [05 Q2 ; 07 Q8]. La solidité du dispositif dépend donc de la capacité du relecteur à juger cette raison. C'est la même chose pour les ignores PHPStan, mais là, le relecteur est compétent.
- **Effet de second ordre.** [04 Q1] rapporte que Leptos passe en maintenance légère en partie à cause des agents : moins d'échanges humains sur les issues. Un écosystème plus petit y est plus exposé (n = 1, à vérifier).
- Mesure proposée : expérience D (§6.2) et seuil Q-D (§6.3).

### 3.2 Réversibilité

- Aucune note ne note les options sur leur **coût de sortie**. Ordre proposé, de la plus à la moins réversible :
  1. O1 : rien à défaire.
  2. O2a, si des fixtures existent : on remplace le cœur par une implémentation TS validée sur les mêmes vecteurs.
  3. O3 pendant le strangler fig : on peut router en sens inverse au proxy tant que le PHP n'est pas supprimé.
  4. O3 après suppression du PHP, et O4 : il faudrait une nouvelle réécriture.
  5. O5 : la perte d'Expo et de React est irréversible en pratique.
- **Corollaire : l'oracle agnostique est l'actif qui rend tout réversible.** Il se compose de tests HTTP boîte noire écrits en TS ou en Python, de Schemathesis sur l'OpenAPI et de fixtures JSON pour les règles métier. [06 §6] le présente comme une mitigation « si migration ». C'est en fait la première étape de toutes les options, O0 excepté.
- **Non traité : deux systèmes de migrations de schéma sur une même base** pendant un strangler fig (Doctrine Migrations et `sqlx migrate`). Qui possède le schéma ? Dans quel ordre appliquer ? Comment revenir en arrière ? [06 §6] dit seulement « même schéma Postgres ».

### 3.3 Sécurité et RGPD

- **La dette de sécurité actuelle ne dépend pas du langage**, et personne ne l'a traitée pendant la pause :
  - 14 advisories `composer audit`, Dependabot rouge depuis le 2026-06-15 [09 §1.6] ;
  - tokens web en `localStorage` [09 §1.8], donc exposés en cas de XSS (à vérifier au regard de la CSP) ;
  - pas de rate limiter (#63) ;
  - pas de Sentry côté API ;
  - CodeQL ne couvre pas PHP [09 §2.2] ;
  - le consommateur Messenger en prod n'est pas identifié [09 §1.9].
- **Une réécriture (O3 ou O4) touche l'auth et des données personnelles.** Il faut donc un ADR avec section sécurité et un `/security-review` obligatoire [CLAUDE.md ; 09 §7, point 7]. [03 Q4] montre une pile d'auth Rust à assembler soi-même, avec des briques mono-mainteneur sans audit public ; [07 Q7] en fait un contre-argument au « solide ». Aucune note ne chiffre ce ré-audit.
- **Chaîne d'approvisionnement.** 03 évalue le bus factor des crates, mais aucune note ne compare la surface npm (qu'O4 étendrait au serveur) à celle de Composer. À vérifier.
- **Modèle de défaillance.**
  - PHP ne partage rien entre requêtes : une exception fait échouer une requête.
  - Un processus Rust vit longtemps. Selon le profil `panic`, la présence d'une couche de capture des paniques et l'endroit où survient le panic, celui-ci peut tuer un worker ou le processus (à vérifier). Cloudflare en est le cas extrême [02 §1 ; 05 Q2].
  - Sur cette dimension, Rust ne gagne pas par défaut. Une politique explicite (lints anti-panic, isolation) règle le problème, mais elle doit entrer dans la comparaison.
- **RGPD.**
  - La localisation des données chez des sous-traitants comme PowerSync ou Electric Cloud n'est pas vérifiée [04 Q5 Gaps].
  - Point positif jamais relevé : O3 n'implique aucune migration de données personnelles, puisque la base Postgres reste la même.

### 3.4 Coût d'usage des agents

- **Récurrent.**
  - Rust consomme 1,07 à 1,57 fois plus de tokens que Python sur des problèmes courts [01 Q1].
  - Chez Endoh, une tâche coûte 0,54 $ en Rust, 0,38 $ en Python (×1,4) et 0,62 $ en TS [07 Q2].
  - PHP est absent des deux études.
  - Avec un abonnement, la contrainte porte plutôt sur le quota et le temps que sur les dollars (à vérifier selon le plan du fondateur).
- **Ponctuel (portage).**
  - Les grands portages ont coûté environ 0,14 à 0,20 $ par ligne produite : Bun environ 165 k$ pour 1,0 M de lignes, Copilot environ 120 k$ pour 0,83 M (source secondaire), le compilateur C environ 20 k$ pour 100 k lignes [01 Q4].
  - Extrapolation grossière, valable seulement en régime « portage sous oracle » : environ 9 k lignes d'API représentent de l'ordre de 10³ $. Même multiplié par 5 pour une refonte non mécanique, c'est négligeable devant le coût d'opportunité.
  - **Le coût en tokens n'est donc un argument ni pour ni contre.** Ce calcul ne doit pas être cité comme une estimation, seulement comme un ordre de grandeur.

### 3.5 Ce que « solide » veut dire pour le fondateur

Aucune note ne pose la question. Voici une grille à faire trancher par le fondateur en ouverture du rapport, avec une métrique par dimension :

| Dimension            | Métrique proposée                                                                   | Avantage a priori selon les notes                                                                           |
| -------------------- | ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Correction           | Défauts échappés en prod par mois ; défaut résiduel sur des vecteurs cachés         | Rust partiellement, sur le domaine de calcul (§4 b) ; les tests dominent (§4 d)                             |
| Évolutivité          | Coût d'une story à 6-12 mois ; régressions introduites par une modification de spec | Inconnu : non mesuré (expérience C, §6.2)                                                                   |
| Reprise              | Temps pour retrouver une CI verte et une première PR utile après N mois de pause    | Processus (mises à jour automatisées) plutôt que langage ; la pause actuelle donne la mesure (§3.6)         |
| Pérennité            | Churn des dépendances clés, bus factor, support LTS                                 | Langage Rust stable, mais crates web mouvantes [03] ; Symfony avec LTS (à vérifier)                         |
| Autonomie            | Ce que le fondateur sait faire sans agent                                           | PHP > TS > Rust à court terme (§3.1)                                                                        |
| Sécurité, conformité | Advisories ouvertes, couverture d'audit, surface d'auth                             | Neutre à défavorable pour une réécriture (§3.3)                                                             |
| Exploitation         | Disponibilité, détection, MTTR                                                      | Rust léger à l'exécution [03 Q10] ; l'isolation des pannes est à vérifier (§3.3) ; Sentry est absent (§3.9) |

### 3.6 Le projet est à l'arrêt depuis juin 2026

- **Faits.** Le dernier commit sur `main` date du 2026-06-14. Les étapes 6.2 à 6.4 n'ont pas démarré. La bêta prévue en septembre 2026 n'est pas lancée [09 §6.1 ; 06 §4]. 06 §4 évoque un risque de procrastination productive, en l'affichant comme une inférence (la cause de la pause n'est pas documentée).
- **Ce que la pause mesure, et que personne ne lit : la solidité de la stack actuelle dans le temps.**
  - La CI Dependabot est rouge et les advisories s'accumulent.
  - Expo SDK 57 est sorti alors que Cordeau est en 56 [04 Q4].
  - Les docs et les memories dérivent [09 §1.1].
  - Une stack Rust aurait subi ses propres ruptures (axum 0.9, sqlx 0.9, utoipa 6, apalis en RC [03]).
  - La réponse à « solide dans le temps » est donc d'abord **processuelle** (mises à jour automatisées et vertes, veille, budget de maintenance) plutôt que linguistique.
- **Séquencement.** Toute option qui repousse la Phase 9 au-delà de mi-2027, moment où les TPE choisiront leur outillage de facturation électronique [06 §4], a un coût produit que les notes techniques ne voient pas.
- **Cohérence avec la doctrine du projet.** L'ADR 0017 diffère l'IA tant que le concept manuel n'est pas validé. Le même raisonnement s'applique à une sophistication technique invisible pour l'artisan : aucun client n'achète « une API en Rust » [06 §4].

### 3.7 La duplication métier PHP ↔ TS, et son vrai traitement

- **Les faits établis par [09 §5].**
  - 10 règles sont déjà dupliquées, avec 3 divergences constatées : code postal à 5 chiffres quel que soit le pays, surface sans maximum côté clients, email validé par regex d'un côté et `Assert\Email` de l'autre.
  - 8 familles de règles sont à venir, dont `Mesure::calculer` (en PHP et en TS hors ligne) et les totaux de Devis. Ces derniers auront 3 implémentations d'un même calcul monétaire : serveur (8.4), mobile (8.5) et web (8.6).
- **Les notes divergent sur le poids de cette duplication.**
  - [07 Q5, Q9] en fait « le critère le plus discriminant », en faveur de TS.
  - [04 Q3, Q7] la minimise : « arithmétique simple, quelques centaines de lignes, des fixtures suffisent ».
  - [02 §1] rappelle qu'AWS DSQL a réécrit son control plane **pour supprimer** une duplication entre deux langages.
  - Aucune note ne fait le lien avec l'option API-Rust, alors que migrer l'API ne supprime aucune implémentation [09 §7].
- **Ce que 04 sous-estime.** « Arithmétique simple » ne veut pas dire « simple à garder cohérent ». La spec d'expérience (ventilation d'une remise globale par taux, arrondis, franchise) est exactement le type de règle où deux implémentations divergent au centime, et le `float` actuel aggrave le risque [09 §1.3].
- **Trois leviers que les notes n'ont pas mis côte à côte :**
  1. **Inventorier les règles qui doivent vraiment tourner hors ligne.** C'est une décision produit, qui peut réduire la duplication sans changer de langage. Par exemple : des totaux affichés comme provisoires hors ligne, puis recalculés par le serveur, si l'UX le permet.
  2. **Des fixtures de conformité partagées**, exécutées par PHPUnit, Vitest et Jest [07 Q5 ; 04 Q3]. Elles réduisent la _divergence_, pas la _duplication_.
  3. **Une source unique** : cœur Rust (O2a) ou TS de bout en bout (O4).

### 3.8 Le harness lui-même se dégrade

- **La doc lue par l'agent est fausse par endroits** [09 §1.1] :
  - la memory Serena `architecture` décrit mal la structure et l'auth ;
  - `docs/architecture.md` est daté ;
  - `apps/mobile/CLAUDE.md` contredit le code (il dit « pas de polling » alors que le mobile poll).
- **Un agent qui lit une doc fausse produit du code faux.** La note 03 en est l'illustration directe (contradiction C5).
- C'est un défaut de solidité agentique **indépendant du langage**, probablement plus coûteux que le choix du compilateur. Aucune note ne propose de le tester, par exemple par une vérification automatique de quelques assertions de la doc, ou par l'application stricte de l'étape 9 du protocole de phase.
- **Le LSP n'est pas un avantage propre à Rust.** [05 Q1] présente les diagnostics après chaque édition comme un atout du harness Rust. Or le marketplace officiel propose aussi `php-lsp` et `typescript-lsp` (relevé local). La vraie différence : les diagnostics d'Intelephense ne sont pas ceux de PHPStan 9 ou 10. L'équivalent PHP serait un hook PostToolUse lançant PHPStan sur le fichier édité, dont la latence reste à mesurer.

### 3.9 Observabilité et parité des environnements

- **Les bugs coûteux de Cordeau viennent de l'environnement** [09 §6.2] :
  - une extension absente de l'image de prod, masquée par un fallback (#81) ;
  - un build natif non couvert par la CI (#84, #87) ;
  - une erreur de CORS (#83).
- **Aucune option de langage ne les traite.** Ce qui les traite :
  - un smoke test de l'image de prod au démarrage (extensions, config) ;
  - un job de build natif ;
  - un E2E minimal ;
  - Sentry côté API et côté mobile, absent des deux [09 §1.6, §4].
- Rust n'aide qu'à la marge : une bibliothèque liée échoue au build plutôt qu'à l'exécution [09 §6.2].

### 3.10 Trajectoire des modèles et valeur d'option

- [01 Q1] : les scores agrégés saturent (85-94 %) et ne sont plus ventilés par langage. Si l'écart entre langages se réduit à mesure que les modèles progressent, le critère « le langage aide-t-il l'agent ? » perd du poids, au profit des critères d'écosystème et de capacité humaine.
- Symétriquement, plus les agents progressent, moins un portage futur coûte. **Attendre la validation produit avant toute réécriture a donc une valeur d'option**, à condition d'avoir l'oracle. Aucune note ne formule cet argument.

---

## 4. Décomposer la « solidité » : ce que Rust apporte en propre

Comparateur : la stack actuelle **durcie** (PHP 8.5 + PHPStan 10 strict, TS strict avec lints d'exhaustivité et interdiction d'`any`), pas la stack actuelle telle quelle.

| Composante                                                                              | Ce que disent les notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | Part propre à Rust                                                                                                     | Équivalent hors Rust                                                                                                                                                               | Pertinence pour Cordeau                                                                                                                                                                     |
| --------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **(a) Sûreté mémoire, data races**                                                      | PHP et TS sont déjà memory-safe ; tous les gains mesurés de Rust le sont contre C/C++ [02 §1 ; 01 Synthèse ; 07 Q1]. Les courses de Cordeau sont logiques (outbox async mobile) ou transactionnelles, pas des data races [09 §6.2 ; 07 Q1]. Le décodage HEIC reste en C++ (libheif) dans les deux stacks [03 Q7].                                                                                                                                                                                                                      | **≈ 0.** Gain partiel sur les décodeurs d'image non HEIC.                                                              | Déjà acquis. Isolation du décodage dans un processus séparé [03 Q7].                                                                                                               | Faible                                                                                                                                                                                      |
| **(b) Types expressifs** (types somme, exhaustivité, pas de `null`, `Result`, newtypes) | Rust : ADT avec données, `match` exhaustif natif, `Result` `#[must_use]` [07 Q1, Q8 ; 05 Q3]. PHP durci : null-safety à peu près équivalente, exhaustivité sur les enums, mais pas d'ADT avec données, pas de `Result` idiomatique, et des annotations qui peuvent mentir [07 Q8]. TS strict : unions discriminées et `never`, mais système non sûr, et `any` 9 fois plus fréquent chez les agents [01 Q2]. Selon GitHub, 84 % de ce que le compilateur Rust attrape relève du typage « ordinaire » [01 Q2].                           | **Moyenne face à PHP** (ADT, `Result`) ; **faible face à TS strict + lints**.                                          | Value objects `final readonly` (déjà la pratique [09 §1.3]), hiérarchies scellées vérifiées par PHPStan, `Result` maison ; types décimaux en PHP et TS (bibliothèques à vérifier). | **Moyenne à forte** en Phases 7-8 (états Devis/Facture, validations combinées, montants) ; faible sur le CRUD.                                                                              |
| **(c) Boucle de feedback**                                                              | Rust est plus lent en incrémental (55 % des répondants attendent plus de 10 s [02 §5 ; 07 Q3]), mais ses messages d'erreur sont précis, avec des `help:` [05 Q5]. `cargo check` et le LSP atténuent [05 Q4]. Temps total de l'agent sur une petite tâche : Rust ≈ Java < TS [07 Q3]. Tokens : +7 à +57 % par rapport à Python [01 Q1].                                                                                                                                                                                                 | **Négative sur la vitesse, positive sur la qualité des messages.** Non mesuré sur Cordeau.                             | Cache PHPStan, PHPUnit filtré, hook Stop ciblé, TS 7 [07 Q3].                                                                                                                      | Forte : c'est un multiplicateur de chaque itération (H2 et H3 de l'expérience).                                                                                                             |
| **(d) Tests** (mutation, propriétés, contrats)                                          | Les régressions qui comptent dans les grands portages ont été attrapées par les tests, pas par le compilateur (GitHub ; Bun, 19 régressions) [01 Q2, Synthèse]. Les tests tautologiques écrits par les agents sont un défaut transverse [02 §3 ; 05 Q2]. Outils : Infection, cargo-mutants, Stryker ; proptest (équivalents PHP et TS à vérifier) ; Schemathesis et Pact, agnostiques du langage [05 Q2 ; 06 §5-6 ; 07 Q8].                                                                                                            | **≈ 0.** Confort légèrement supérieur avec proptest et insta, non mesuré.                                              | Tous ces outils existent pour PHP et TS.                                                                                                                                           | **La plus forte** : c'est le seul levier sur les quelque 45 % de bugs non typables [07 Q1]. Cordeau n'a aujourd'hui ni mutation, ni propriétés, ni contrat OpenAPI en CI, ni E2E [09 §2.1]. |
| **(e) Architecture** (frontières, une seule source de vérité métier)                    | Frontières : crates Cargo (vérifiées à la compilation) contre Deptrac (en CI, périmètre partiel) [03 Q5 ; 05 Q3 ; 09 §2.2]. Source unique : Rust n'aide que s'il est embarqué dans les clients via FFI ou WASM, maillon immature côté React Native [04 Q3-Q4] ; TS de bout en bout la donne directement [07 Q5]. Le SQL vérifié à la compilation n'est pas propre à Rust (sqlc, jOOQ, Kysely) [07 Q7]. Contrat : API Platform **génère** l'OpenAPI depuis le code, utoipa le **déclare** par annotations, qui peuvent dériver [03 Q3]. | **Faible** (frontières vérifiées au build). **Négative** sur le contrat, sauf test de dérive ajouté.                   | Deptrac étendu à tous les contextes + hook local ; fixtures partagées ; TS pour une source unique.                                                                                 | **Forte** pour la source unique (duplication, [09 §5]).                                                                                                                                     |
| **(f) Harness agentique** (hooks, CLAUDE.md, skills, LSP)                               | Hook Stop comme gate, PostToolUse, CLAUDE.md court, reviewer frais, liste de features [05 Q1, Q6]. Les guidelines Microsoft sont transposables à d'autres langages [02 §3]. Des plugins LSP officiels existent pour PHP, TS et Rust (relevé local).                                                                                                                                                                                                                                                                                    | **≈ 0.** La valeur vient de la conversion des règles en outils (lints, bans, hooks), possible dans les trois langages. | Hook Stop (PHPStan + PHPUnit ciblé + `tsc` + tests), `php-lsp`, interdiction des nouvelles baselines.                                                                              | Forte et immédiate : Cordeau n'a pas de hook Stop de vérification, et ses docs et memories dérivent [09 §1.1].                                                                              |
| **(g) Ajout : environnement, config, plateforme**                                       | Environ 19 bugs sur 25 [09 §6.2].                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | **Marginale** (échec au link plutôt qu'à l'exécution pour les bibliothèques natives).                                  | Smoke test de l'image de prod, CI de build natif, E2E, Sentry.                                                                                                                     | **Forte** : c'est là que sont allés les bugs coûteux.                                                                                                                                       |

**Lecture.**

- Ce que la solidité recherchée doit à Rust **en propre** se concentre sur (b), et un peu sur (e), dans un périmètre précis : le **domaine de calcul pur** (Mesures, Devis, TVA).
- Ce périmètre est petit (environ 660 lignes de domaine aujourd'hui [09 §7]), c'est le mieux testé, et c'est aussi celui qui doit exister côté mobile.
- Tout le reste de la solidité visée s'obtient sans changer de langage, et la composante (c) est un coût.

C'est cohérent avec l'ADR 0002 : rigueur maximale sur les contextes complexes, légèreté sur le CRUD. Les éventuels bénéfices de Rust ne se situent que là où l'ADR place déjà la rigueur.

---

## 5. Matrice d'options

### O0 — Statu quo

- **Définition** : reprendre la Phase 6 sans toucher à l'outillage.
- **Solidité** : en baisse, et mesurable. Pendant la pause, la CI Dependabot est passée au rouge, les advisories se sont accumulées et la doc a dérivé [09 §1.1, §1.6].
- **Coûts** : nuls à court terme, puis une dette qui se compose.
- **Risques** : arriver en Phase 8 avec des montants en `float` et trois implémentations des totaux non synchronisées [09 §5.2] ; des tests tautologiques qui passent inaperçus.
- **Réversibilité** : totale.
- **Pour** : la vélocité démontrée des Phases 1 à 5 [09 §6.1].
- **Contre** : aucune note ne le défend tel quel ; [06 §5] et [07 Q8] listent tout ce qui n'est pas activé.
- **Ce qui ferait changer d'avis** : rien. O0 n'est pas une option stable, c'est O1 laissé en friche.

### O1 — Statu quo durci (PHPStan max, mutation, contrats, harness)

- **Contenu, tiré des notes, à prioriser :**
  - **PHP** : PHPStan 10 + strict-rules + loaders Symfony/Doctrine + interdiction de toute nouvelle entrée de baseline ou d'ignore non justifiée [06 §5 ; 07 Q8] ; Deptrac étendu à Auth, Client et Photo [09 §2.2] ; Infection sur le domaine, idéalement limitée au diff (option à vérifier) [07 Q8].
  - **Contrat** : Schemathesis et contrôle de dérive de `openapi.json` en CI [06 §6 ; 09 §2.1].
  - **Montants** : un type `Montant` décimal, en PHP et en TS, **avant** la Phase 8 [09 §5.2 ; 05 Q2]. L'usage actuel du `float` est une décision de modélisation, pas une fatalité du langage : un agent Rust peut tout aussi bien écrire `f64`.
  - **Fixtures de conformité partagées** PHP/TS pour chaque règle dupliquée [07 Q5 ; 04 Q3].
  - **TS** : statuts en unions littérales, `no-explicit-any`, `switch-exhaustiveness-check`, et ESLint sur le mobile, qui n'a aujourd'hui que `tsc` [09 §2.2 ; 07 Q1].
  - **Harness** : hook Stop (PHPStan, PHPUnit ciblé, `tsc`, tests), plugin `php-lsp`, reviewer en contexte frais, memories corrigées [05 Q1, Q6 ; 09 §1.1].
  - **Socle ops** : job de build natif iOS (#87), smoke test de l'image de prod, Sentry API et mobile, Dependabot remis au vert [09].
- **Bénéfices de solidité** : couvre en partie (b), entièrement (d) et (f), en partie (e). **C'est la seule option qui traite (g).** Elle construit l'oracle agnostique.
- **Coûts** : « des jours » selon [06 §5], non chiffré ; ordre de grandeur plausible de 1 à 2 semaines, à valider avec le fondateur.
- **Risques** : pas d'ADT avec données ni de `Result` natifs [07 Q8] ; PHPStan peut être trompé par des `@var` mensongers [07 Q8] ; tout repose sur la discipline.
- **Réversibilité** : totale. Et elle rend O2 à O4 réversibles.
- **Pour** : [01 Synthèse] (leviers transposables), [06 §5], [07 Q8-Q9], [09 §6.2].
- **Contre** : aucune donnée sur PHPStan dans une boucle agentique [01 Q2 Gaps] ; le « 70-80 % » de 07 n'est pas sourcé.
- **Ce qui ferait changer d'avis** : si, une fois O1 en place, les bugs échappés en Phases 7-8 relèvent majoritairement de classes que les types Rust attrapent et que PHPStan 10 laisse passer (seuil Q-E).

### O2 — Rust ciblé

Trois variantes à séparer, car leurs conclusions diffèrent.

- **O2a — Cœur de calcul Rust unique, consommé partout** : par le serveur, par le web (WASM), par le mobile (pont JSI via ubrn ; un usage de WASM sous Hermes est à vérifier) et par PHP (`ext-php-rs`, encore pré-1.0, ou un sidecar/CLI) [04 Q3].
  - **Bénéfices** : (b) sur le périmètre critique, et (e) une source unique. C'est la seule option Rust qui **réduit** la duplication.
  - **Coûts** :
    - 3 nouvelles chaînes de build [04 Q7] ;
    - Rust absent des images EAS : hook pre-install, cibles iOS et Android, symbolisation des crashs [04 Q4] ;
    - la CI de build natif iOS n'existe même pas encore (#87) ;
    - débogage à travers la frontière JSI ;
    - un agent peu fiable sur les builds natifs [04 Q4].
  - **Risques** : le maillon React Native est déclaré non prêt pour la prod par son auteur [04 Q3] ; chaque montée de version d'Expo impose un triple test [04 Q4].
  - **Réversibilité** : bonne s'il existe des fixtures, puisqu'on peut remplacer le cœur par une implémentation TS validée sur les mêmes vecteurs.
  - **Pour** : le pattern est prouvé chez 1Password, Bitwarden ou Proton, mais avec des UI natives et des équipes nombreuses [04 Q3] ; cas DSQL [02 §1].
  - **Contre** : [04 Q3, Q7] ; [06 §2] ; Prisma, qui illustre le coût des frontières entre langages [02 §4 ; 06 §3].
  - **Ce qui ferait changer d'avis** : ubrn qui sort de l'« early development » avec un support Expo documenté ; ou un calcul client qui devient lourd, comme la géométrie AR en V2 [04 Q7].
- **O2b — Cœur de calcul Rust côté serveur seulement, clients en TS** : option **dominée**. Elle ajoute une troisième langue, ne supprime aucune implémentation et ajoute une frontière (`ext-php-rs` ou sidecar). Son seul intérêt est de servir de laboratoire pour mesurer (b) en conditions réelles : à garder comme spike jetable.
- **O2c — Service isolé (vignettes)** : le décodage HEIC reste en C++ via libheif [03 Q7]. Le gain memory-safe ne porte que sur les autres décodeurs. En contrepartie, il faut un déploiement et une file de jobs de plus, alors que le mode d'exécution actuel des jobs est inconnu [09 §1.9]. Valeur faible ; à rouvrir en cas de CVE ImageMagick ou de besoin de performance.

### O3 — API entièrement en Rust par strangler fig, clients TS conservés

- **Bénéfices de solidité** : (b) et les frontières de (e) côté serveur ; disparition des classes de bugs liées à la magie Symfony/Doctrine (2 à 3 bugs observés [09 §6.2]) ; SQLx ; binaire léger [03 Q10].
- **Coûts** :
  - porter environ 9 k lignes de PHP, dont 3,7 k de tests [09 §0] ;
  - réécrire le socle d'API Platform (environ 500-700 lignes, estimation non prototypée [03 Q11]) ;
  - réassembler et ré-auditer l'auth [03 Q4 ; 09 §7] ;
  - remplacer les jobs, qui n'ont pas de standard stable en Rust [03 Q6] ;
  - absorber le churn d'axum, sqlx et utoipa [03] ;
  - mettre en place un proxy de routage et un double déploiement, non chiffrés [06 §6 Gaps] ;
  - gérer deux systèmes de migrations de schéma (§3.2) ;
  - assurer la montée en compétence du fondateur.
- **Risques** :
  - une refonte déguisée, ou effet second système [06 §1, §6] ;
  - une dérive du contrat OpenAPI, puisqu'utoipa fonctionne par annotations [03 Q3] ;
  - la duplication n'est pas réduite [09 §7] ;
  - le bus factor de l'agent (§3.1).
- **Réversibilité** : moyenne pendant le strangler fig, faible une fois le PHP supprimé.
- **Pour** : la compétence des agents en Rust est établie [01 Q4] ; une stack existe et est documentée [03] ; le harness Rust est riche [05] ; le contrat typé peut être conservé [03 Q3].
- **Contre** :
  - aucun gain démontré face à un typage strict [01 Synthèse] ;
  - un écosystème plus mince pour un SaaS français [07 Q7 ; 03, dernière section] ;
  - le coût d'opportunité [06 §4] ;
  - aucun bug de typage d'API à ce jour [09 §6.2] ;
  - aucun retour d'expérience de SaaS CRUD [02 §2 ; 06 §1 Gaps].
- **Ce qui ferait changer d'avis** (toutes les conditions sont requises) :
  - la bêta est validée selon le critère de l'ADR 0017 ;
  - un oracle agnostique couvre 100 % des endpoints et passe sur le PHP ;
  - l'expérience montre un avantage net face à un PHP **durci** (seuils Q-A et Q-C) ;
  - le fondateur sait corriger un bug Rust sans agent (Q-D) ;
  - les bugs des Phases 7-8 relèvent de classes que Rust attrape (Q-E).

### O4 — TypeScript de bout en bout

- **Définition** : l'API est réécrite en TS et un package de domaine est partagé entre serveur, web et mobile. **Aucune note ne compare les frameworks, ORM, briques d'auth ou files de jobs en TS côté serveur** : c'est un trou de l'étude.
- **Bénéfices de solidité** :
  - (e) une source unique directe pour les règles qui tournent hors ligne [07 Q5] ;
  - une seule langue pour le fondateur et pour l'agent ;
  - des types de contrat partagés sans étape de génération.
- **Coûts** : une réécriture d'API comparable à O3, le même ré-audit de sécurité, et un écosystème serveur non évalué.
- **Risques** :
  - un système de types non sûr, et des agents qui écrivent 9 fois plus de `any` [01 Q2 ; 07 Q1] ;
  - le churn de l'écosystème (TS 7, Effect 4) [07 Q6-Q7] ;
  - TS faible sur les benchmarks de dépôts [01 Q1] ;
  - la surface d'approvisionnement npm étendue au serveur (à vérifier) ;
  - la perte de l'expertise Symfony.
- **Réversibilité** : faible à moyenne. Un strangler fig est possible, comme pour O3.
- **Pour** : [07 Q5, Q9] (« le critère le plus discriminant ») ; [09 §5] (duplication réelle et déjà divergente) ; DSQL a réécrit un composant pour supprimer une duplication [02 §1].
- **Contre** : [01 Q1-Q2] ; [07 Q7] ; aucune donnée sur un backend TS dans ce contexte.
- **Variante intermédiaire, à évaluer avant O4** : un domaine TS partagé par le web et le mobile, avec l'API PHP conservée et vérifiée par des fixtures. Elle capte une partie du bénéfice sans réécrire l'API, mais garde deux implémentations côté serveur et côté clients.
- **Ce qui ferait changer d'avis** : un inventaire des Phases 7-8 montrant que la majorité des règles métier doit tourner hors ligne, **et** des divergences constatées malgré les fixtures (seuil Q-F).

### O5 — Tout en Rust, y compris l'UI

- **Bénéfices théoriques** : une seule langue et des types partout.
- **Coûts et risques** :
  - Leptos est en maintenance légère et le port Rust de shadcn est archivé ;
  - Dioxus mobile tourne dans une WebView, sans API natives (la caméra et le stockage sécurisé sont seulement prévus pour la 0.8) ;
  - Tauri 3 est en alpha ;
  - aucune synchro en arrière-plan n'est documentée [04 Q1-Q2, Q7] ;
  - on perdrait Expo, EAS et les mises à jour OTA, expo-sqlite et l'écosystème de tests.
- **Réversibilité** : quasi nulle.
- **Pour** : aucun élément probant pour une app mobile-first métier [04 Q2 Gaps].
- **Ce qui ferait changer d'avis** : Dioxus en version 1.0 avec des API natives livrées, et des apps métier mobiles documentées en production. Pas avant 2027 au mieux (inférence).

### Synthèse comparative

| Option                      | (b) Types          | (d) Tests | (e) Source unique                  | (g) Environnement | Coût calendaire avant bêta | Risque principal                              | Réversibilité        | Statut                                   |
| --------------------------- | ------------------ | --------- | ---------------------------------- | ----------------- | -------------------------- | --------------------------------------------- | -------------------- | ---------------------------------------- |
| O0 Statu quo                | =                  | −         | −                                  | −                 | 0                          | Dette composée, montants en `float`           | Totale               | Pas une option stable                    |
| **O1 Durci**                | + (partiel)        | ++        | + (fixtures : moins de divergence) | **++**            | Faible                     | Discipline ; pas d'ADT                        | Totale               | **Sans regret, prérequis**               |
| O2a Cœur Rust partagé       | ++ (sur le calcul) | =         | ++                                 | −                 | Moyen à élevé              | Maillon RN pas prêt, 3 chaînes de build       | Bonne si fixtures    | Différée (déclencheurs écosystème)       |
| O2b Cœur Rust serveur seul  | + (sur le calcul)  | =         | −− (3ᵉ langue)                     | =                 | Moyen                      | Complexité sans réduction de la duplication   | Bonne                | **Dominée** (spike jetable au mieux)     |
| O2c Service vignettes       | =                  | =         | =                                  | −                 | Moyen                      | Ops supplémentaires, HEIC toujours en C++     | Bonne                | Sur incident uniquement                  |
| O3 API Rust (strangler fig) | ++ (serveur)       | =         | = (Rust↔TS)                        | =                 | **Élevé**                  | Bus factor de l'agent, refonte déguisée, auth | Moyenne, puis faible | Conditionnée à Q-A…Q-G, après la bêta    |
| O4 TS de bout en bout       | + / −              | =         | **++**                             | =                 | **Élevé**                  | Non-sûreté de TS, churn, backend non étudié   | Moyenne, puis faible | Conditionnée à Q-F et Q-G, après la bêta |
| O5 Tout Rust                | ++                 | =         | ++                                 | −−                | Prohibitif                 | Écosystème UI mobile immature                 | Quasi nulle          | **Exclue en 2026**                       |

**Relations de dominance que la matrice établit :**

- O1 domine O0.
- O2b est dominée par O1.
- O5 est exclue.
- Le choix reste ouvert entre trois trajectoires : « O1 + fixtures », O4, et O2a/O3. Il dépend de données qui n'existeront qu'après les Phases 7-8 et l'expérience. Comme O1 construit l'oracle qui rend ces trajectoires réversibles, l'ordre logique est le suivant : O1 d'abord, la bêta ensuite, puis la décision, prise sur les seuils de la §6.

---

## 6. Questions décisives, expériences et seuils

### 6.1 Corrections à apporter au protocole en cours, avant de lire les résultats

1. **Asymétrie de durcissement.** B-Rust a clippy pedantic et des lints restrictifs ; B-PHP n'a que PHPStan 9. Il faut ajouter un bras **B-PHP+** : PHPStan 10, strict-rules, interdiction des ignores, Infection. Sinon, H1 mesure l'effet du durcissement autant que celui du langage.
2. **n = 1 par cellule.** Prévoir au moins 3 runs par bras (même prompt, sessions fraîches) pour estimer la variance. Ne conclure que sur des écarts supérieurs à la dispersion observée au sein d'un même bras.
3. **Hiérarchie des métriques.**
   - Primaire : le défaut résiduel mesuré sur les vecteurs cachés.
   - Diagnostic au sein d'un langage : les mutants survivants (les scores Infection et cargo-mutants ne sont pas comparables entre eux).
   - Métrique de **vitesse**, pas de solidité : l'endroit où une erreur est attrapée.
4. **Juge LLM.** On ne peut pas masquer le langage d'une implémentation au juge. Privilégier les métriques mécaniques et, si possible, faire relire par deux modèles différents.
5. **Expérience A.** Définir « test rendu inutile par les types » avant le comptage (§2.5).
6. **Environnement.** Docker n'est pas disponible et Postgres sera embarqué : la friction mesurée pour H3 ne sera pas représentative de la CI. Le noter dans les résultats.
7. **Ce que le protocole ne mesure pas** : l'évolution du code et la maintenance humaine. Or « solide à long terme » porte justement là-dessus (expériences C et D ci-dessous).

### 6.2 Expériences complémentaires proposées

- **C — Évolution.** Sur les implémentations B, un agent en contexte frais applique une modification de spec : par exemple, un arrondi par taux plutôt que par ligne, un nouveau taux, ou un nouveau type de remise. On mesure :
  - les régressions sur les vecteurs cachés ;
  - la répartition des régressions entre celles attrapées par le compilateur, celles attrapées par les tests et celles qui passent ;
  - les tokens et la durée.
- **D — Maintenance humaine.** Le fondateur, **sans agent**, localise et corrige un bug injecté dans chaque implémentation. On mesure la durée et le succès. Il explique ensuite à voix haute une fonction tirée au sort.
- **E — Coût de boucle sur le code réel de Cordeau.** On compare un `cargo check` incrémental (crate domaine de l'expérience A) avec un `phpstan analyse` ciblé suivi d'un PHPUnit filtré, en 3 mesures chacun. Le protocole prévoit déjà en partie cette mesure.
- **F — Comptage de la duplication.** À la fin des Phases 7 et 8, compter les règles qui ont au moins deux implémentations et les divergences détectées par les fixtures.

### 6.3 Questions décisives et seuils

Les seuils sont des **propositions** que le fondateur doit calibrer, mais **avant** de voir les résultats.

| #   | Question                                                               | Mesure                                                                                                                                                                                                                                  | Seuil « go » (vers plus de Rust ou de TS)                                                                                                                                                                                        | Seuil « no-go »                                                                                                                                                                        | Options concernées |
| --- | ---------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| Q-A | À effort égal, Rust produit-il moins de défauts qu'un PHP **durci** ?  | Taux d'échec sur les vecteurs cachés, au moins 3 runs par bras, sur au moins 2 specs                                                                                                                                                    | Échecs Rust ≤ 50 % de ceux de B-PHP+, **et** écart supérieur à la dispersion intra-bras                                                                                                                                          | Écart < 25 %, ou dans le bruit                                                                                                                                                         | O2a, O3            |
| Q-B | Le coût de boucle est-il acceptable ?                                  | Tokens et temps mur par story vérifiée                                                                                                                                                                                                  | Rust ≤ 1,5 × PHP (cohérent avec la borne haute de 1,57 mesurée par tokenmaxxing [01 Q1])                                                                                                                                         | Rust > 2 × PHP                                                                                                                                                                         | O3                 |
| Q-C | Le code reste-t-il solide quand la spec change ?                       | Régressions qui passent après l'expérience C                                                                                                                                                                                            | Rust en laisse passer ≤ 50 % de ce que PHP+ laisse passer                                                                                                                                                                        | Égalité                                                                                                                                                                                | O2a, O3            |
| Q-D | Le fondateur est-il autonome sans agent ?                              | Expérience D                                                                                                                                                                                                                            | Rust ≤ 2 × le temps PHP, avec succès                                                                                                                                                                                             | Échec en 60 min (**veto**)                                                                                                                                                             | O3, O5             |
| Q-E | Quelle est la classe des bugs échappés ? (protocole double-fix étendu) | Chaque bug sorti du dev local en Phases 6-8, classé en : attrapable par les types Rust mais pas par PHPStan 10 / tests / config-infra-plateforme / sync                                                                                 | ≥ 30 % des bugs API échappés dans la première classe, sur au moins 10 bugs                                                                                                                                                       | < 10 %                                                                                                                                                                                 | O3                 |
| Q-F | Quel est le poids réel de la duplication ?                             | Expérience F                                                                                                                                                                                                                            | ≥ 10 règles à plusieurs implémentations **et** ≥ 3 divergences en recette ou en prod malgré les fixtures → source unique (O4 ou O2a)                                                                                             | Moins que cela → « O1 + fixtures » suffit                                                                                                                                              | O4, O2a            |
| Q-G | Les préconditions de toute réécriture sont-elles réunies ?             | Toutes requises : (i) critère de l'ADR 0017 levé ; (ii) oracle agnostique couvrant les quelque 25 endpoints, avec Schemathesis en mode stateful vert sur PHP ; (iii) CI verte (Dependabot, advisories) ; (iv) ADR avec section sécurité | Toutes réunies                                                                                                                                                                                                                   | Une seule manque : **no-go automatique**                                                                                                                                               | O3, O4             |
| Q-H | Quand arrêter une réécriture déjà lancée ?                             | Critères repris de [06 §6] : premier contexte porté > 2 × l'estimation, vélocité par story < 50 % de celle de la Phase 2, date butoir de la bêta dépassée                                                                               | —                                                                                                                                                                                                                                | Un seul critère atteint → retour au PHP. Ajout : arrêt si un contexte Rust cohabite plus de 8 semaines avec son équivalent PHP (éviter la double stack permanente, cas Prisma [06 §3]) | O3, O4             |
| Q-I | Quels signaux de veille doivent rouvrir le dossier ?                   | Veille, par exemple via une Routine [08 §5]                                                                                                                                                                                             | ubrn sans l'avertissement « not for production » et avec une recette Expo officielle → rouvrir O2a ; Dioxus 1.0 avec API natives → réexaminer O5 ; ventilation par langage publiée pour les modèles 2026 → mettre à jour [01 Q1] | —                                                                                                                                                                                      | O2a, O5            |

---

## 7. Consignes de rédaction pour le rapport final

- **Ne pas écrire** « Rust est le meilleur langage pour les agents », ni l'inverse. Écrire plutôt : « aucune donnée ne sépare Rust d'un typage strict et durci sur une application métier ; la donnée qui le ferait n'existe pas encore (§6) ».
- **Ne pas citer :**
  - les 76 % de [06 §2] (C1) ;
  - le ratio « ~2× C/C++ » de la synthèse de [01] (C15) ;
  - les « 70-80 % » de [07 Q8] ;
  - le billet de Ronacher de 2026 comme argument anti-Rust (C4) ;
  - le rollback ×4 de Google comme chiffre transposable (§2.1).
- **Nommer l'unité de coût** à chaque fois : tokens, heures du fondateur, ou semaines calendaires.
- **Présenter 03 et 05 comme des réponses à « si oui, comment ? »**, pas comme des recommandations.
- **Distinguer partout** le « portage sous oracle » du « développement produit ».
- **Ouvrir le rapport sur la définition de « solide »** (§3.5), à faire trancher par le fondateur. La conclusion change selon la pondération retenue entre correction, autonomie et reprise.
- **Rappeler que la note 09 rend la question « API en Rust ? » secondaire** face à celle du nombre d'implémentations du domaine de calcul (§3.7, §4).
