# Red team : contre-arguments et risques d'une réécriture de Cordeau en Rust (état au 2026-10-01)

> Rôle : avocat du diable, sans homme de paille. Chaque argument est daté et sourcé ; les contre-preuves trouvées (qui affaiblissent la thèse « ne pas réécrire ») sont signalées explicitement. Les faits internes au repo viennent de `ROADMAP.md`, `git log` et des fichiers de config, relevés le 2026-10-01.

## 1. Littérature sur les réécritures : que disent les classiques, et l'IA change-t-elle le calcul ?

### Takeaway

Le canon (Spolsky 2000, Brooks 1975, Fowler 2004/2024) condamne la réécriture big-bang et recommande le remplacement progressif. Les grands succès de migration assistée par IA en 2025-2026 (Bun, TypeScript→Go, Airbnb, Google) ont **tous** un point commun : un **portage mécanique à comportement constant**, piloté par une **suite de tests indépendante du langage** ou une validation automatisée, et non une refonte. Cordeau n'a aujourd'hui ni l'un ni l'autre pour l'API (tests PHPUnit couplés au langage, et un passage PHP OO/Doctrine → Rust est structurellement une refonte, pas un portage).

### Cited Findings

**Canon anti-réécriture**

- Joel Spolsky (6 avril 2000) qualifie la réécriture from scratch de Netscape de « the single worst strategic mistake that any software company can make » ; arguments : « It's harder to read code than to write it », « Old code has been used. It has been tested. Lots of bugs have been found, and they've been fixed », et « You are giving a gift of two or three years to your competitors » (Netscape : ~3 ans entre la 4.0 et la 6.0 bêta) ; « There is absolutely no reason to believe that you are going to do a better job than you did the first time » — [Joel on Software, 2000-04-06](https://www.joelonsoftware.com/2000/04/06/things-you-should-never-do-part-i/)
- Effet second système (Fred Brooks, _The Mythical Man-Month_, 1975) : le second système est « the most dangerous » qu'un architecte conçoive, car il est tenté d'y mettre toutes les améliorations, options et généralisations différées dans le premier (exemple : OS/360) — [Wikipedia, Second-system effect](https://en.wikipedia.org/wiki/Second-system_effect)
- Martin Fowler (Strangler Fig, révisé le 22 août 2024) : « Replacing a serious IT system takes a long time, and the users can't wait for new features. Replacements seem easy to specify, but often it's hard to figure out the details of existing behavior. » Le remplacement progressif permet que « investment and returns occur gradually and visibly » et « earlier return on the investment » — [martinfowler.com](https://martinfowler.com/bliki/StranglerFigApplication.html)

**Réécritures réussies : ce qui les a fait réussir**

- Basecamp a été réécrit deux fois (Basecamp 2 en 2012, Basecamp 3 en 2015) ; la percée selon DHH a été de **ne pas forcer la migration** : les anciennes versions restent en ligne « until the end of the internet ». Le nouveau produit a été vendu aux nouveaux clients pendant que l'ancien continuait de tourner — [Business of Software, recap AMA DHH 2015](https://businessofsoftware.org/2015/11/should-you-rewrite-your-software-yes-david-heinemeier-hansson-ama-recap-basecamp-3/) ; [DHH, « Until the end of the internet »](https://world.hey.com/dhh/until-the-end-of-the-internet-439ccfce)
- Microsoft a porté le compilateur TypeScript en Go (annonce mars 2025, GA de TypeScript 7.0 le 8 juillet 2026, gains 8x-12x sur les builds complets). Le choix revendiqué est un **portage**, pas une réécriture : « we're undertaking this more as a _port_ that maintains the existing behavior and critical optimizations » et « Idiomatic Go strongly resembles the existing coding patterns of the TypeScript codebase, which makes this porting effort much more tractable » (Ryan Cavanaugh, 5 mars 2025) — [GitHub discussion #411](https://github.com/microsoft/typescript-go/discussions/411) ; [The Register, 2026-07-09](https://www.theregister.com/devops/2026/07/09/speedier-type-checks-in-typescript-70-as-first-stable-go-release-ships/5268828) ; [InfoQ, août 2026](https://www.infoq.com/news/2026/08/typescript-7-released/)

**Migrations assistées par IA (2025-2026) : la meilleure preuve « pour », et ses conditions**

- **Bun (Zig → Rust, mai 2026)** : 535 496 lignes de Zig (1 448 fichiers) portées en 11 jours (3-14 mai 2026), 6 502 commits, +1 009 272 lignes nettes, jusqu'à 64 instances Claude en parallèle, ~165 000 $ de tokens au tarif API. Préconditions décrites : une suite de tests **écrite en TypeScript, indépendante du langage d'implémentation** (~1,4 million d'`expect()` sur 60 000+ tests), un « mechanical port from Zig to Rust, with the minimal number of behavioral changes, using the exact same test suite », un guide de portage (`PORTING.md`, `LIFETIMES.tsv`) rédigé avant la génération, des boucles de revue adverse, un ingénieur à plein temps qui supervise, et l'accès à un modèle pré-release. Résultats : 19 régressions documentées malgré tout (sémantique de `debug_assert!`, bounds checks en release, macros de format…), ~4 % du code en `unsafe`, livré en production dans Claude Code v2.1.181 le 17 juin 2026 — [Bun blog, « Bun in Rust »](https://bun.com/blog/bun-in-rust) ; [Simon Willison, 2026-07-08](https://simonwillison.net/2026/Jul/8/rewriting-bun-in-rust/)
- Critique de la même opération par Andrew Kelley (créateur de Zig) : il doute qu'une suite de tests qui laissait déjà passer des bugs dans le code Zig puisse attraper ceux d'« a million lines of unreviewed slop » — [DevClass, 2026-07-15](https://www.devclass.com/ai-ml/2026/07/15/zig-creator-calls-buns-claude-rust-rewrite-unreviewed-slop/5271749). Un média secondaire avance 13 044 blocs `unsafe` contre 73 dans du Rust écrit à la main comparable (chiffre non vérifié dans la source primaire) — [AI Weekly](https://aiweekly.co/alerts/bun-rewrites-960k-lines-of-zig-to-rust-using-claude)
- Airbnb (mars 2025) : 3 500 fichiers de tests migrés d'Enzyme vers React Testing Library en 6 semaines au lieu des 18 mois estimés ; 97 % automatisé, 3 % manuel ; les fichiers de longue traîne ont demandé 50 à 100 relances — [InfoQ, 2025-03](https://www.infoq.com/news/2025/03/airbnb-llm-test-migration/)
- Google (janvier 2025, rapport d'expérience, pas une étude comparative) : pipeline « découverte par analyse statique → édits LLM → validation automatisée → revue humaine », réduction du temps de migration d'au moins 50 % ; migrations ciblées (JUnit3→4, Joda→java.time) intra-langage — [arXiv 2501.06972](https://arxiv.org/html/2501.06972v1) ; [ITPro](https://www.itpro.com/software/development/Ai-code-migration-Google)
- Microsoft / Galen Hunt (décembre 2025) : l'objectif « 1 engineer, 1 month, 1 million lines of code » pour traduire C/C++ en Rust d'ici 2030 a été **requalifié en projet de recherche**, pas en stratégie produit (« Windows is not being rewritten in Rust with AI ») — [XDA](https://www.xda-developers.com/no-microsoft-isnt-actually-eliminating-c-and-c-from-its-software-rust/) ; [Techzine](https://www.techzine.eu/news/infrastructure/137484/microsoft-sets-2030-target-to-replace-c-and-c-code-with-rust/)

### Inferences

- **Steelman honnête** : l'argument Spolsky est _faible_ pour Cordeau. Il vise des bases anciennes et massives chargées de corrections terrain ; Cordeau a 5 mois, ~15-20 k lignes, 127 commits (premier commit 2026-05-03) et **aucun utilisateur payant** : il y a peu de « bugs fixes cachés » à perdre et pas de part de marché à céder. Le vrai argument Spolsky qui tient ici est le **temps sans livraison** (« gift to your competitors »), pas la perte de savoir.
- **L'effet second système est le risque le plus probable** : une réécriture motivée par « faire plus solide » invite à réintroduire d'emblée les généralisations repoussées (offline sync plus générique, modélisation Lot/Tâche/Mesure « parfaite », IA) — exactement ce que l'ADR 0017 cherche à empêcher.
- **L'IA change le coût de la _frappe_, pas le coût de la _spécification_**. Toutes les réussites citées reposent sur un oracle de comportement exécutable (suite de tests agnostique, ou validation automatique) et sur un portage quasi ligne-à-ligne. Le TS team a choisi Go justement parce que la structure du code se transposait ; PHP OO + Doctrine (identity map, lazy loading, héritage, mutabilité partagée) → Rust (ownership, pas d'héritage, ORM explicites) est une **refonte de conception**, donc hors du régime où l'IA a fait ses preuves.
- Bun montre que même dans les conditions idéales, un portage IA produit des régressions sémantiques subtiles (19) et une dette `unsafe` ; à l'échelle de Cordeau (~4 % de la taille de Bun) le coût en tokens serait marginal, mais la supervision humaine et la construction de l'oracle ne le sont pas.

### Gaps

- Je n'ai pas trouvé de post-mortem public (2025-2026) d'une réécriture **d'un SaaS métier CRUD** (pas un runtime/compilateur) en Rust assistée par agents, ni réussie ni ratée. Toutes les preuves IA disponibles portent sur de l'outillage système ou des migrations intra-langage.
- Le billet de Herb Caudill « Lessons from 6 software rewrite stories » (Netscape, Basecamp, VS Code, Gmail/Inbox, FogBugz/Trello, FreshBooks) n'a pas pu être lu (HTTP 403) ; non cité.

## 2. Coûts spécifiques à Rust pour un SaaS métier (compilation, async, écosystème, UI, recrutement, FFI)

### Takeaway

Les coûts Rust les mieux documentés sont la **boucle de compilation** (premier motif d'abandon cité), la **complexité** (inquiétude n°2 de la communauté elle-même) et un écosystème « business » plus mince et plus jeune (pas de SDK Stripe officiel, crates Factur-X récentes). La preuve que les agents sont _moins bons_ en Rust est **contradictoire** selon les benchmarks : ne pas l'utiliser comme argument décisif.

### Cited Findings

**Compilation et complexité (données de la communauté Rust)**

- Enquête performance du compilateur Rust (3 700+ réponses, publiée le 10 septembre 2025) : satisfaction moyenne 6/10 ; 55 % attendent plus de 10 s pour un rebuild après modification ; « Around 45% respondents who answered that they are no longer using Rust said that at least one of the reasons why they stopped were long compile times » ; douleurs principales : rebuilds incrémentaux, édition de liens, rebuilds de workspace, expansion des proc-macros derive non cachée — [Rust Blog, 2025-09-10](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)
- State of Rust 2025 (7 156 réponses, enquête du 17 nov. au 17 déc. 2025, publiée le 2 mars 2026) : 41,6 % craignent que Rust devienne trop complexe ; 42,1 % citent « pas assez d'usage industriel » ; 27,9 % jugent la lenteur de compilation « un gros problème » ; 22,2 % l'usage disque, 19,9 % l'expérience de débogage — [Rust Blog, 2026-03-02](https://blog.rust-lang.org/2026/03/02/2025-State-Of-Rust-Survey-results) ; [InfoWorld](https://www.infoworld.com/article/4139528/rust-developers-have-three-big-worries-survey.html)

**Agents et Rust : preuves contradictoires**

- Rust-SWE-bench (arXiv 2602.22764, v1 février 2026) : 500 tâches issues de 34 dépôts Rust ; meilleur agent de référence OpenHands + Claude Sonnet 3.7 = 21,2 % ; l'approche proposée RustForger = 28,6 % ; **76,3 % des échecs sont des erreurs de compilation** (43,7 % structure du dépôt/imports/portée — E0433, E0432, E0425 ; 32,6 % système de types/traits — E0599, E0308, E0277) ; la reproduction d'issue échoue dans 44,5 % des cas, dont 52,5 % pour des problèmes de dépendances — [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1)
- Multi-SWE-bench (ByteDance, avril 2025, NeurIPS 2025) : 1 632 tâches sur 7 langages ; Rust, C et Go parmi les plus durs ; meilleur agent ~12 % sur Rust selon les slides de présentation — [arXiv 2504.02605](https://arxiv.org/pdf/2504.02605) ; [slides NeurIPS](https://neurips.cc/media/neurips-2025/Slides/121692.pdf)
- **Contredit par** SWE-bench Multilingual (300 tâches, ~43 par langage) : Claude 3.7 Sonnet résout **58,1 % en Rust contre 48,8 % en PHP** (Java 53,5 %, JS/TS 34,9 %, Go 31,0 %) — [swebench.com/multilingual](https://www.swebench.com/multilingual.html)
- Armin Ronacher (12 juin 2025) recommande Go, pas Rust, pour le backend en agentique : « In Rust, agents sometimes fail because they misunderstand `cargo test`'s invocation syntax. In Go, tests run straightforwardly and incrementally » ; « Rob Pike famously described Go as suitable for developers who aren't equipped to handle a complex language. Substitute 'developers' with 'agents' » ; écosystème Go rétro-compatible → moins de code obsolète généré — [lucumr.pocoo.org, 2025-06-12](https://lucumr.pocoo.org/2025/6/12/agentic-coding/)
- Ronacher (9 février 2026, « A Language For Agents ») : « Agents often struggle with macros » (Rust repose massivement sur les macros : serde, sqlx, derive) ; les agents aiment le « local reasoning », la greppabilité, l'absence de dépendances circulaires et le cache des tests ; il rapporte que l'agent performe « much better » en TypeScript — [lucumr.pocoo.org, 2026-02-09](https://lucumr.pocoo.org/2026/2/9/a-language-for-agents/)

**Écosystème « business »**

- Stripe : pas de SDK Rust officiel ; `async-stripe` est listé parmi les SDK communautaires, et Stripe précise ne pas supporter ni relire ces SDK communautaires — [Stripe Docs, Community SDKs](https://docs.stripe.com/sdks/community) ; [async-stripe](https://github.com/arlyon/async-stripe)
- Facturation électronique (Factur-X / EN 16931) : il **existe** des crates Rust (`en16931`, `en16931-formats` avec les 1 339 règles syntaxiques et le support ZUGFeRD/Factur-X, `pdf-invoice` pour l'embarquement en PDF/A-3) — [docs.rs en16931](https://docs.rs/en16931/latest/en16931/) ; [docs.rs en16931-formats](https://docs.rs/en16931-formats/latest/en16931_formats/) ; [docs.rs pdf-invoice](https://docs.rs/pdf-invoice/latest/pdf_invoice/). Une chaîne Typst (Rust) → PDF/A-3 + XML embarqué est documentée par Oicana (30 juin 2026) — [Oicana](https://oicana.com/news/2026-06-30-zugferd-factur-x-e-invoices/). Côté PHP, la bibliothèque de référence `horstoeko/zugferd` existe de longue date — [GitHub horstoeko/zugferd](https://github.com/horstoeko/zugferd)
- FFI : Prisma liste parmi ses raisons de quitter son moteur Rust le coût de sérialisation JS↔Rust, le besoin d'un binaire par OS et par version d'OpenSSL, et l'incompatibilité des binaires Rust avec certains runtimes serverless/edge (30 janvier 2025) — [Prisma blog](https://www.prisma.io/blog/from-rust-to-typescript-a-new-chapter-for-prisma-orm)

**Itération rapide / UI**

- LogLog Games (26 avril 2024, 3+ ans et 100 000+ lignes de Rust) : « The borrow checker forces a refactor at the most inconvenient times » ; « Rust is also a language that will force the user to refactor much more often than other languages » ; « By the time the Rust developer is finished with their refactoring, the C++/C#/Java developer has implemented many different gameplay features » — [LogLog Games](https://loglog.games/blog/leaving-rust-gamedev/)

**Marché du travail (France)**

- TJM freelance 2026 (agrégateurs/simulateurs, fiabilité faible) : Rust confirmé (3-7 ans) 550-750 €/j, junior 400-500 €/j — [travail-industrie.com](https://travail-industrie.com/simulateur-tjm/developpement-it/rust-developer/confirme) ; Symfony confirmé ~510-640 €/j, médiane nationale ~490 €/j — [TJMetre (Nantes)](https://tjmetre.fr/tjm/developpeur-symfony/nantes) ; [Freelance Solution](https://www.freelance-solution.fr/tarif-freelance/tjm-developpeur-php/). Codeur.com (août 2026) affiche des tarifs bien plus bas pour les deux, ce qui illustre la dispersion — [Codeur Rust](https://www.codeur.com/developpeur/rust/tarif) ; [Codeur PHP](https://www.codeur.com/developpeur/php/tarif)
- Tendance (Stack Overflow Survey 2025) : Rust en hausse (+3 pts), PHP en baisse (−3 pts) — [Stack Overflow, communiqué 2025](https://stackoverflow.co/company/press/archive/stack-overflow-2025-developer-survey/) _(contre-signal pro-Rust, à mentionner)_

### Inferences

- **Boucle agentique** : un agent passe son temps à compiler/tester. Rebuilds incrémentaux > 10 s pour une majorité de devs Rust (et proc-macros non cachées) contre un PHP sans compilation et PHPUnit ciblé : à volume d'itérations égal, Rust consomme plus de temps mur par boucle. C'est l'argument Ronacher, et il est cohérent avec les 76 % d'échecs « compilation » de Rust-SWE-bench.
- **Mais** le compilateur Rust _est_ aussi un oracle gratuit pour l'agent (l'argument pro-Rust), et SWE-bench Multilingual le place devant PHP. Conclusion honnête : la littérature ne permet pas d'affirmer que Claude est significativement meilleur ou pire en Rust qu'en PHP sur des tâches réelles en 2026 ; les échantillons (43 tâches/langage) et les modèles évalués (Sonnet 3.7) sont datés. Il faut **mesurer sur Cordeau** (l'expérience A/B `docs/etudes/rust-agentique/experience/spec-totaux-devis.md` est le bon format).
- **Ce que Rust ne peut pas remplacer** : le mobile Expo/React Native et le web React n'ont pas d'équivalent Rust mûr côté UI ; « migrer tout ce qui est possible » se réduit en pratique à l'API (et éventuellement à des modules natifs via FFI, qui réintroduisent exactement les coûts décrits par Prisma : binaires par plateforme, sérialisation, double compétence).
- **Écosystème Factur-X** : l'écart est plus faible qu'on pourrait le croire (des crates existent), mais elles sont jeunes et à mainteneur unique probable ; surtout, l'émission passe obligatoirement par une **plateforme agréée** via API (voir §4), donc l'intégration e-invoicing est largement indépendante du langage — ce n'est ni un argument fort pour, ni contre.
- **API Platform** fournit aujourd'hui gratuitement OpenAPI, pagination, filtres, sérialisation, validation, sécurité par voters : en Rust (axum + utoipa + sqlx/SeaORM…), chacune de ces briques est à assembler et à maintenir. C'est du code que l'agent doit écrire, tester et garder cohérent.

### Gaps

- Pas de donnée fiable sur le **volume** d'offres Rust vs PHP en France (Free-Work/Malt n'ont pas été trouvés en source primaire) ; les TJM ci-dessus viennent de simulateurs.
- Maturité réelle (téléchargements, mainteneurs, date de première release) des crates `en16931*` et `pdf-invoice` non vérifiée.
- Pas de benchmark 2026 avec des modèles de génération actuelle (Claude 4.x/5.x) comparant PHP et Rust par langage.

## 3. Mouvements documentés hors de Rust ou choix contre Rust

### Takeaway

Les deux décisions les plus citées (Prisma 7 → TypeScript, compilateur TypeScript → Go) n'ont **pas** été prises parce que Rust serait mauvais, mais parce que Rust imposait une frontière FFI, une barrière de contribution et une refonte structurelle là où un langage plus proche du code existant permettait un portage. Ce sont exactement les forces en jeu pour Cordeau.

### Cited Findings

- **Prisma** (blog du 30 janvier 2025) : quatre raisons — barrière de compétence (« Contributing to the query engine requires a combination of Rust and TypeScript proficiency, reducing the opportunity for community involvement »), sérialisation JS→Rust→JS, un binaire par OS/version d'OpenSSL, incompatibilité avec serverless/edge — [Prisma blog](https://www.prisma.io/blog/from-rust-to-typescript-a-new-chapter-for-prisma-orm). Prisma 7.0.0 (19 novembre 2025) rend le Query Compiler TypeScript/WASM par défaut ; Prisma annonce ~90 % de bundle en moins et des requêtes jusqu'à 3x plus rapides — [Prisma blog, mise à jour perf](https://www.prisma.io/blog/rust-to-typescript-update-boosting-prisma-orm-performance) ; [InfoQ, janvier 2026](https://www.infoq.com/news/2026/01/prisma-7-performance)
  - _Ironie à noter_ : le runtime Bun, désormais en Rust, sert de base à « Prisma Compute » — Prisma a quitté Rust **dans sa bibliothèque** (frontière FFI) mais l'utilise **dans un runtime** — [Bun blog](https://bun.com/blog/bun-in-rust)
- **TypeScript → Go, pas Rust** (Ryan Cavanaugh, 5 mars 2025) : portage à comportement constant ; Go ressemble au code existant ; GC disponible sans que « the entire codebase continually concern itself with memory management » ; beaucoup de traitements de graphes avec parcours montants/descendants de nœuds polymorphes (structures cycliques, pénibles sous ownership Rust) — [GitHub discussion #411](https://github.com/microsoft/typescript-go/discussions/411)
- **Armin Ronacher** (créateur de Flask, utilisateur Rust de longue date) recommande Go pour les nouveaux backends en agentique (juin 2025) — [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/)
- **LogLog Games** (avril 2024) quitte Rust après 3 ans parce que le produit (un jeu) exige du prototypage rapide : « I want to make a game in 3-12 months maximum and release it so that people can play it » — [LogLog Games](https://loglog.games/blog/leaving-rust-gamedev/)
- Enquête compilateur 2025 : ~45 % des répondants ayant arrêté Rust citent les temps de compilation parmi les raisons — [Rust Blog](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)
- **Contre-exemple pro-Rust** : Bun est allé _vers_ Rust (mai 2026) pour éliminer use-after-free / double-free / fuites dans un runtime mêlant valeurs GC et mémoire manuelle : « In safe Rust, these are compiler errors » ; fuites de bundling réduites de 3,5 Go à 609 Mo sur 1 000 itérations — [Bun blog](https://bun.com/blog/bun-in-rust)

### Inferences

- Le profil des gagnants Rust (Bun : runtime, mémoire manuelle, performance) est l'**opposé** du profil Cordeau (CRUD métier, GC/PHP sans gestion mémoire, perf non limitante). Le profil des « sortants » (Prisma : frontière FFI et barrière de contribution ; LogLog : besoin d'itérer vite sur un produit non validé) est **proche** de celui de Cordeau.
- La barrière de contribution de Prisma a un équivalent direct : si Cordeau grandit (freelance, associé, reprise), une API Rust réduit le vivier de contributeurs par rapport à Symfony, sans même parler du mobile/web qui resteront en TS.

### Gaps

- Je n'ai pas trouvé de billet primaire crédible d'une **startup SaaS** ayant documenté un retour de Rust vers TS/Go/Elixir avec métriques ; les articles trouvés (amazingcto, bswen) sont de qualité incertaine et n'ont pas été retenus.

## 4. Coût d'opportunité pour un fondateur solo pré-revenu

### Takeaway

L'argument le plus fort contre la migration **maintenant** n'est pas technique : la bêta payante prévue en septembre 2026 n'est pas lancée, le repo n'a plus de commit sur `main` depuis le 14 juin 2026, et la fenêtre de marché ouverte par la facturation électronique (émission obligatoire pour les TPE au 1er septembre 2027) est déjà occupée par des concurrents conformes. Une réécriture consomme le seul actif rare d'un solo pré-PMF : le temps avant la validation.

### Cited Findings

**Doctrine startup**

- Dan McKinley, « Choose Boring Technology » (30 mars 2015) : « every company gets about three innovation tokens » ; coût de toute techno ajoutée : « You have to monitor the thing. You have to figure out unit tests. You need to know the first thing about it to hack on it » ; « PHP is boring » (au sens « good enough ») ; et surtout « Consider how you would solve your immediate problem without adding anything new… The answer to this question in practice is almost never 'we can't do it.' » — [mcfunley.com](https://mcfunley.com/choose-boring-technology)
- Y Combinator (Diana Hu, « Tips for technical startup founders ») : choisir la stack que l'équipe connaît et optimiser pour la vitesse d'itération ; s'inquiéter de la stack est un piège de procrastination avant le PMF _(résumé de recherche, citation verbatim non récupérée)_ — [YC Startup Library](https://www.ycombinator.com/library/Is-tips-for-technical-startup-founders)
- CB Insights (analyse de post-mortems, version classique) : « no market need » est la première cause d'échec (42 %), devant le manque de trésorerie (29 %) — [CB Insights, The 20 Reasons Startups Fail (PDF)](https://s3-us-west-2.amazonaws.com/cbi-content/research-reports/The-20-Reasons-Startups-Fail.pdf) _(des agrégateurs citent une version ultérieure où « ran out of cash » passe en tête ; pourcentages à prendre comme ordre de grandeur)_

**Calendrier facturation électronique (source officielle)**

- Depuis le 1er septembre 2026 : toutes les entreprises assujetties à la TVA doivent pouvoir **recevoir** des factures électroniques via une plateforme agréée ; grandes entreprises et ETI doivent **émettre**. Le 1er septembre 2027 : obligation d'émission (et e-reporting) pour les PME et micro-entreprises. L'émission passe par une plateforme agréée (art. 289 bis CGI), qui transmet les données à l'administration — [impots.gouv.fr, Facturation électronique et plateformes agréées](https://www.impots.gouv.fr/facturation-electronique-et-plateformes-agreees) ; [Guide pratique de démarrage au 1er septembre 2026 (PDF)](https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/guide_pratique_facturation_electronique.pdf) ; [Fiche 3 TPE (PDF)](https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/fiche-3_tpe_a-partir-de-quand-mon-entreprise-doit-etre-prete.pdf)

**Concurrence déjà positionnée (BTP)**

- Obat annonce devis/factures prêts pour la réforme via la plateforme agréée Iopole — [Obat](https://www.obat.fr/devis-factures/facturation-electronique/)
- Tolteck propose depuis juillet 2026 la réception des factures fournisseurs via une plateforme agréée, incluse dans l'abonnement — [Tolteck](https://www.tolteck.com/fr-fr/facturation-electronique/)

**Faits internes au repo (relevés le 2026-10-01)**

- Premier commit 2026-05-03 ; 127 commits ; Phases 0 à 5 (Chantiers, Clients, Offline-first, OAuth Google, Photos + R2) livrées en ~6 semaines ; vélocité « ×5 vs Phase 1 » mesurée en Phase 2 ; dernier commit sur `main` le 2026-06-14 (6.1 Lot/Tâche) — [ROADMAP.md](/Users/maxime/cordeau/ROADMAP.md), `git log`
- La roadmap prévoyait Phase 8 (Devis + Facture brouillon, 3-4 semaines) en juillet-août 2026 et la bêta payante (Phase 9, 5 artisans, 20-30 €/mois HT) en septembre 2026 ; la V1 exclut explicitement la conformité PDP (ADR 0016) — [ROADMAP.md](/Users/maxime/cordeau/ROADMAP.md)

### Inferences

- **Le retard existe déjà** : la bêta de septembre 2026 a glissé. Les phases restantes (6 à 8) étaient estimées à ~8-10 semaines dans la stack actuelle, à une vélocité démontrée. Une réécriture de l'API (même accélérée par IA) repousse mécaniquement la première validation marché de plusieurs mois, sans aucun apprentissage client en échange.
- **Fenêtre marché** : les artisans choisissent leur outil de devis/facture entre la réception obligatoire (sept. 2026) et l'émission obligatoire (sept. 2027). Si Cordeau veut un jour porter la facture conforme, arriver après mi-2027 signifie arriver après la décision d'équipement de la plupart des TPE, face à Obat/Tolteck déjà conformes. Si au contraire Cordeau reste un outil de _suivi_ (ADR 0016), la réforme ne le presse pas — mais alors la valeur se joue sur l'UX terrain (mobile/offline, en TypeScript), que Rust ne touche pas.
- **Innovation tokens** : Cordeau a déjà dépensé des jetons (offline-first, monorepo Expo/pnpm, R2, HEIC). Rust en ajoute un gros pour un gain qui ne se voit pas côté client (aucun artisan n'achète « API en Rust »).
- **Risque de motivation/procrastination** : une réécriture est un travail gratifiant et mesurable qui évite la tâche anxiogène (vendre, recruter 5 bêta-testeurs). Le gel de `main` depuis le 14 juin est compatible avec ce schéma (inférence, la cause réelle de la pause n'est pas documentée dans le repo).

### Gaps

- Pas de donnée chiffrée sur le taux d'équipement actuel des artisans en logiciel de devis/facture ni sur le rythme de souscription autour de la réforme.
- La raison de l'absence de commits entre le 14 juin et le 1er octobre 2026 n'est pas documentée.

## 5. Bénéfices revendiqués pour Rust obtenables moins cher dans la stack actuelle

### Takeaway

L'essentiel de la « solidité » attendue de Rust (types stricts, pas de `null` implicite, erreurs à la compilation, frontières d'architecture vérifiées) a des équivalents outillés dans PHP/TS, dont plusieurs ne sont **pas encore activés** dans le repo. Liste seulement ; l'analyse détaillée est couverte par un autre agent.

### Cited Findings

- **PHPStan niveau 10** (PHPStan 2.0, 11 novembre 2024) : « level 9 on steroids », traite strictement tous les `mixed`, y compris implicites — [Phoronix](https://www.phoronix.com/news/PHPStan-2.0-Released) ; [Release 2.0.0](https://github.com/phpstan/phpstan/releases/tag/2.0.0). Le repo est au **niveau 9**, sans `phpstan-strict-rules`, et les chargeurs `containerXmlPath` / `objectManagerLoader` des extensions Symfony/Doctrine sont encore commentés — `apps/api/phpstan.dist.neon` (relevé local)
- **Tests de mutation (Infection)** pour mesurer la force réelle des assertions — [infection.github.io](https://infection.github.io/) ; non présent dans `apps/api/composer.json` (relevé local)
- **Tests d'architecture** : `deptrac` est **déjà** présent dans le repo (relevé local) ; alternative PHPat — [deptrac](https://github.com/deptrac/deptrac) ; [PHPat](https://github.com/carlosas/phpat)
- **Psalm** (analyse de flux, taint analysis) — [psalm.dev](https://psalm.dev/)
- **TypeScript strict** (déjà activé sur web et mobile, relevé local) ; **Zod 4** déjà présent sur web et mobile pour valider aux frontières — [TS strict](https://www.typescriptlang.org/tsconfig#strict) ; [zod.dev](https://zod.dev/) ; **Effect** pour erreurs typées / `Result`-like en TS — [effect.website](https://effect.website/)
- **Tests de contrat / propriétés depuis l'OpenAPI** (Schemathesis, voir §6) — [Schemathesis](https://schemathesis.readthedocs.io/)

### Inferences

- Passer PHPStan au niveau 10 + strict-rules + chargeurs Symfony/Doctrine, ajouter Infection sur le domaine et Schemathesis sur l'API représente des jours, pas des mois, et donne à l'agent le même type de retour « le compilateur refuse » que Rust pour une grande partie des classes de bugs d'un CRUD (nullabilité, types, mauvais mapping).
- Ce que ces outils **ne** donnent **pas** : garanties mémoire/concurrence (sans objet pour un PHP request-scoped), types somme exhaustifs natifs (partiellement couverts par les enums PHP 8.1+ et `match`), performance brute (non limitante à l'échelle d'une bêta à 5 artisans).

### Gaps

- Pas de mesure, sur Cordeau, du nombre de bugs passés en CI/démo qui auraient été évités par Rust plutôt que par PHPStan 10 / Infection — c'est la donnée qui trancherait (le protocole double-fix du `CLAUDE.md` permettrait de la collecter).

## 6. Si la migration a lieu quand même : patterns de mitigation

### Takeaway

Si la décision est prise, elle doit ressembler aux migrations qui ont réussi : **strangler fig au niveau HTTP**, un bounded context à la fois, un **oracle de comportement indépendant du langage** construit _avant_ la première ligne de Rust, une comparaison en ombre, et des critères d'arrêt écrits à l'avance. Et jamais en bloquant la bêta.

### Cited Findings

- **Strangler fig** : remplacement progressif, retours visibles et anticipés, éviter de spécifier d'un coup un comportement existant mal connu — [Fowler, 2024-08-22](https://martinfowler.com/bliki/StranglerFigApplication.html)
- **Oracle agnostique** : la condition n°1 du succès de Bun était une suite de tests écrite dans un autre langage que l'implémentation (TypeScript), réutilisée telle quelle comme suite de conformité — [Bun blog](https://bun.com/blog/bun-in-rust)
- **Schemathesis** : génère des cas de test (valides et invalides) à partir d'un schéma OpenAPI, détecte crashs, violations de schéma de réponse, contournements de validation, et fait du test « stateful » multi-étapes ; minimise automatiquement les entrées fautives — [Schemathesis docs](https://schemathesis.readthedocs.io/) ; [schemathesis.io](https://schemathesis.io/)
- **Pact** (tests de contrat pilotés par le consommateur : web et mobile définissent ce qu'ils attendent de l'API) — [docs.pact.io](https://docs.pact.io/)
- **Comparaison en ombre (Scientist)** : exécuter l'ancien chemin (« control ») et le nouveau (« candidate ») en production, ne renvoyer que le résultat de l'ancien, comparer résultats et durées, avaler et journaliser les erreurs du candidat — [github/scientist](https://github.com/github/scientist/tree/v1.6.4) (portages JS, Python, Rust existent : [scientisto](https://github.com/Teebor-Choka/scientisto))
- **Pas de migration forcée** : Basecamp a fait tourner les anciennes versions en parallèle plutôt que de migrer de force — [Business of Software, 2015](https://businessofsoftware.org/2015/11/should-you-rewrite-your-software-yes-david-heinemeier-hansson-ama-recap-basecamp-3/)
- **Pipeline type Google** : découverte par analyse statique → édits LLM → validation automatique → revue humaine, ≥ 50 % de gain — [arXiv 2501.06972](https://arxiv.org/html/2501.06972v1)
- **« Sample, tune, sweep »** (Airbnb) : échantillonner, corriger prompt/scripts sur chaque échec, relancer — [InfoQ](https://www.infoq.com/news/2025/03/airbnb-llm-test-migration/)

### Inferences (risque → mitigation)

- **Risque : big-bang et bêta bloquée** → Mitigation : la bêta sort sur Symfony ; Rust n'entre qu'en strangler derrière le même domaine HTTP (reverse proxy qui route endpoint par endpoint), jamais sur le chemin critique de la Phase 9.
- **Risque : divergence de comportement silencieuse (cf. les 19 régressions de Bun)** → Mitigation : avant tout code Rust, figer l'oracle : export OpenAPI d'API Platform + suite Schemathesis (stateful) + tests HTTP boîte noire écrits en TS/Python (agnostiques) qui passent sur Symfony ; la même suite doit passer sur Rust. Puis Scientist/shadow sur les lectures.
- **Risque : refonte déguisée (effet second système)** → Mitigation : règle « portage à comportement constant » écrite dans un ADR (même contrat OpenAPI, même schéma Postgres, mêmes UUID client offline) ; toute amélioration fonctionnelle interdite pendant le portage d'un contexte.
- **Risque : mauvais premier contexte** → Mitigation : commencer par un **calcul pur sans I/O** (totaux de devis/TVA — déjà spécifié dans `docs/etudes/rust-agentique/experience/spec-totaux-devis.md`), exposé en CLI ou service, comparé à l'implémentation PHP sur des milliers d'entrées générées (property-based). Ensuite seulement un contexte CRUD simple (Clients), jamais l'offline sync ou l'auth en premier.
- **Risque : double stack permanente (coût FFI/ops type Prisma)** → Mitigation : frontière HTTP uniquement (pas d'extension PHP en Rust, pas de module natif mobile), un seul Postgres, et une date butoir à laquelle soit le contexte Rust remplace entièrement le PHP, soit il est supprimé.
- **Risque : enlisement** → Mitigation : time-box et **critères d'arrêt écrits avant de commencer**, par exemple (à calibrer) : si le premier contexte porté dépasse 2x l'estimation, ou si la vélocité story/semaine en Rust est < 50 % de celle mesurée en Phase 2, ou si la bêta n'a pas d'utilisateurs payants à la date X → retour à PHP sans regret. Mesurer avec la même grille que la vélocité des phases précédentes (ROADMAP).

### Gaps

- Pas de retour d'expérience public chiffré d'un strangler fig Symfony → Rust ; les patterns ci-dessus sont transposés de contextes Ruby/Java/TS.
- Coût exact d'un proxy de routage + double déploiement sur l'infra actuelle de Cordeau non évalué ici (hors périmètre).
