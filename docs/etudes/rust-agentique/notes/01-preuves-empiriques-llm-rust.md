# Preuves empiriques : performance des LLM et agents de code en Rust vs TypeScript, PHP, Python, Go

> Note de recherche, rédigée le 2026-10-01/02. Étiquettes utilisées pour chaque élément :
> **[DONNÉE]** = benchmark, papier ou mesure contrôlée ; **[VENDOR]** = chiffres publiés par un acteur intéressé (éditeur de modèle, plateforme) ; **[RETEX]** = retour d'expérience documenté d'un praticien ou d'une équipe (n=1, non contrôlé) ; **[OPINION]** = argumentaire sans données ; **[AGRÉGATEUR]** = site tiers recompilant des chiffres ; **[DOUTEUX]** = chiffres invérifiables ou incohérents.
> Les dates indiquées sont celles des chiffres (date de la publication ou de la mesure). Les sources antérieures à 2025 sont signalées.
> Les citations sont laissées en anglais.

## Q1 — Résultats par langage dans les benchmarks multilingues (2024-2026) : où se classe Rust face à TS, PHP, Go et Python, et comment l'écart a-t-il évolué ?

### Takeaway

Sur les benchmarks agentiques de niveau dépôt, Rust **n'est pas pénalisé** avec les modèles de frontière. Il arrive souvent en tête, parfois en milieu de peloton : SWE-bench Multilingual 2025, AutoCodeBench 2025, SWE-Bench ProMax 2026. En revanche, aucun de ces benchmarks ne contrôle la difficulté d'un langage à l'autre. Sur le seul benchmark où les mêmes problèmes sont traduits dans chaque langage (Multi-LCB), Rust se situe légèrement sous Python et au niveau de TS, Go et PHP pour les meilleurs modèles. Il reste nettement plus bas pour certains modèles et pour les petits modèles. En 2026, les scores agrégés des modèles de frontière saturent (environ 85-94 % sur SWE-bench Multilingual) et aucun détail par langage n'est publié pour ces modèles. Personne ne peut donc dire précisément comment l'écart a évolué avec Claude 4.x/5.x, GPT-5.x ou Gemini 3.

### Cited Findings

**SWE-bench Multilingual (Princeton/Stanford, 2025)**

- **[DONNÉE]** Avec SWE-agent + Claude 3.7 Sonnet, le score global est de 43 %. Taux de résolution par langage : **Rust 58,14 % (25/43)**, Java 53,49 %, **PHP 48,84 % (21/43)**, Ruby 43,18 %, **JS/TS 34,88 %** (43 tâches, JS et TS fusionnés), **Go 30,95 %** (42), C/C++ 28,57 %. Le benchmark compte 9 langages et 41 dépôts. Les auteurs écrivent : "solutions to Rust tasks modify more lines of code on average, yet SWE-agent had the highest resolution rate in Rust", et ajoutent "it's hard to say what factors are most important in determining resolution rate, though it looks like language and difficulty are both relevant" — [swebench.com/multilingual](https://www.swebench.com/multilingual.html). _Limite : environ 43 tâches par langage, donc des intervalles de confiance d'environ ±15 points. Les classements entre langages voisins ne sont pas significatifs._
- **[VENDOR/AGRÉGATEUR]** Classement SWE-bench Multilingual mis à jour le 2026-10-01 (scores déclarés par les éditeurs) : Claude Opus 5.5 93,9 %, Claude Sonnet 5.5 90,3 %, Claude Mythos Preview 87,3 %, Claude Opus 4.8 84,4 %, Tencent Hy4 preview 82,9 %, Qwen3.8 Flash 81,0 %, Poolside Laguna S 2.1 78,5 %. **Aucun détail par langage n'est publié** — [llm-stats.com](https://llm-stats.com/benchmarks/swe-bench-multilingual). _Le site décrit mal le jeu de langages : il reprend celui de Multi-SWE-bench, alors que SWE-bench Multilingual inclut PHP et Ruby._

**Multi-SWE-bench (ByteDance Seed, avril 2025, NeurIPS 2025)**

- **[DONNÉE]** 1 632 instances validées à la main, réparties sur Java, TS, JS, Go, Rust, C et C++, dont **239 instances Rust**. Résultats du Tableau 4, issus d'une extraction automatique qu'il faudrait revérifier sur le PDF :
  - Claude 3.7 Sonnet + MagentLess : Rust 5,44 %, TS 3,57 %, JS 1,97 %, Go 5,84 %, Java 14,06 %, C 2,34 %, C++ 3,10 %.
  - o1 + MagentLess : Rust 7,11 %, TS 5,80 %, JS 5,06 %, Go 4,44 %, Java 21,09 %.
  - DeepSeek-R1 + MopenHands : Rust 4,60 %, TS 0,45 %, JS 2,53 %, Go 0,00 %, Java 8,59 %.
  - Source : [arXiv 2504.02605](https://arxiv.org/html/2504.02605v1).
- **[DONNÉE]** Explication des auteurs : "Languages like TS and JS feature dynamic typing, asynchronous execution… while languages like C, C++, Rust, and Go involve manual memory management". Ils citent aussi un "method optimization bias" : les agents ont été optimisés pour Python. Ils attribuent la faiblesse de TS/JS aux "event-driven, asynchronous programming paradigms" — [arXiv 2504.02605](https://arxiv.org/html/2504.02605v1).
- Le classement Multi-SWE-bench est annoncé « mis à jour en juillet 2026 » avec 6 modèles évalués — [benchmarklist.com](https://benchmarklist.com/benchmarks/multi_swe_bench/) **[AGRÉGATEUR]**. Je n'ai pas pu obtenir le détail par langage pour les modèles 2026 (voir Gaps).

**Rust-SWE-bench (ICSE 2026, arXiv février 2026)**

- **[DONNÉE]** 500 tâches Rust vérifiées, issues de 34 dépôts populaires. Avec Claude 3.7 Sonnet :
  - OpenHands : 21,2 % (106 tâches).
  - SWE-agent : 15,0 %.
  - AutoCodeRover : 9,2 %.
  - Agentless : 7,2 %.
  - RustForger, l'agent spécialisé proposé par les auteurs : **28,6 %**, soit +34,9 % par rapport au meilleur baseline.
  - Les tâches Rust demandent en moyenne **139,9 lignes éditées**, contre 14,32 sur SWE-bench Verified (Python). Le benchmark n'est donc pas comparable directement à SWE-bench Verified.
  - Source : [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1), [ICSE 2026](https://conf.researchr.org/details/icse-2026/icse-2026-research-track/67/Evaluating-and-Improving-Automated-Repository-Level-Rust-Issue-Resolution-with-LLM-ba).

**SWE-PolyBench (Amazon, avril 2025)** — pas de Rust ni de PHP

- **[DONNÉE]** Taux de réussite des agents : Python 20-24 %, Java 11-16 %, **TypeScript 5-13 %**. Le benchmark compte 729 instances TS et 1 017 instances JS. Les auteurs notent que la complexité n'explique pas l'écart : "Java outperforms TypeScript despite having significantly higher structural complexity" — [arXiv 2504.08703](https://arxiv.org/html/2504.08703v3).

**AutoCodeBench (Tencent, août 2025)** — génération de code, pas agentique

- **[DONNÉE]** 3 920 problèmes répartis sur 20 langages, environ 196 par langage, dont plus de 60 % classés « hard ». Pass@1 :
  - Claude Opus 4 (raisonnement) : **Rust 61,0**, Java 55,9, TS 50,0, PHP 44,5, Python 40,3, Go 37,2.
  - Claude Sonnet 4 (raisonnement) : Rust 54,5, TS 48,0, PHP 45,0, Go 41,9, Python 37,2.
  - o3-high : Rust 59,0, TS 59,0, PHP 44,0, Python 40,8, Go 22,0.
  - DeepSeek-R1-0528 : Rust 58,5, TS 55,0, PHP 39,0, Go 38,7, Python 38,8.
  - GPT-4.1 : Rust 54,0, TS 49,5, PHP 43,5, Go 37,2, Python 37,2.
  - Les auteurs observent que l'écart entre familles de modèles se creuse sur les langages peu dotés (45,3 à 62,0, contre 50,4 à 53,8 sur les langages populaires).
  - Source : [arXiv 2508.09101](https://arxiv.org/html/2508.09101v1).
  - _Limite majeure : les problèmes sont générés par LLM, langage par langage, et ne sont pas identiques d'un langage à l'autre. Comparer Rust et Python sur ce benchmark revient à comparer deux jeux de problèmes différents._

**Multi-LCB (juin 2026)** — mêmes problèmes LiveCodeBench traduits dans 12 langages

- **[DONNÉE]** 24 LLM évalués, sur des problèmes publiés entre février et mai 2025. Pass@1 :
  - GPT-OSS-120B : Python 71,1 / **Rust 70,5** / TS 70,3 / Java 70,4 / Go 69,9 / PHP 66,1.
  - Qwen3-235B-A22B-Thinking : Python 74,0 / PHP 69,0 / TS 62,5 / Go 56,7 / **Rust 47,7**.
  - DeepSeek-R1-0528 : Python 66,3 / **Rust 63,1** / PHP 61,6 / TS 58,9 / Go 55,0.
  - Python est le langage le plus facile (moyenne 0,482), Scala le plus difficile (moyenne < 0,29). Les auteurs concluent à un "Python overfitting" et à de la "language-specific contamination".
  - Source : [arXiv 2606.20517](https://arxiv.org/html/2606.20517).
  - _Les chiffres viennent d'une extraction automatique du Tableau 1 et seraient à reconfirmer._

**SWE-Bench ProMax (août 2026)** — refactoring multi-fichiers à grande échelle

- **[DONNÉE]** Meilleur score par langage, toutes configurations confondues :
  - C : 75,0 % (GPT-5.2).
  - **Rust : 63,6 %** (Claude Sonnet 4.6).
  - C++ : 54,5 % (Qwen3.5).
  - **TS : 53,6 %** (Claude Sonnet 4.6).
  - **Python : 48,3 %** (GPT-5.2).
  - **Go : 43,5 %** (Kimi-K2.5).
  - Java : 34,6 % (GLM-5).
  - Les auteurs signalent une forte variance entre modèles sur Rust, qui "may reflect differences in language-specific training data". Pas de PHP.
  - Source : [arXiv 2608.09802](https://arxiv.org/html/2608.09802).

**Aider Polyglot** — 225 exercices Exercism « durs », pas de TS ni de PHP

- **[DONNÉE]** Répartition des exercices : JS 49, Java 47, Go 39, Python 34, Rust 30, C++ 26 — [aider.chat (déc. 2024)](https://aider.chat/2024/12/21/polyglot.html). **Le classement officiel ne publie pas de détail par langage** — [aider.chat/docs/leaderboards](https://aider.chat/docs/leaderboards/).
- **[RETEX/DONNÉE ponctuelle]** Qwen3.6-35B-A3B en Q8, exécuté localement (2026-04-26), score global 62,2 % :
  - Python 73,5 % (25/34), JS 71,4 % (35/49), Go 61,5 % (24/39), **Rust 56,7 % (17/30)**, C++ 53,8 % (14/26), Java 53,2 % (25/47).
  - En premier essai, Rust réussit 14/30, contre 14/34 pour Python.
  - Source : [llmkube.com](https://llmkube.com/blog/m5-max-aider-polyglot-and-finops).
- **[DOUTEUX]** Un blog de juin 2026 publie un détail par langage pour Claude Opus 4.8 et Gemini 3.5 Pro : Rust 74,6 % / 67,2 %, Python 84,2 % / 86,1 %, etc. Le texte ne contient aucune méthodologie. Il affirme à tort qu'Aider Polyglot inclut TypeScript. Surtout, ses pourcentages sont incompatibles avec 30 exercices Rust (74,6 % × 30 = 22,4) et 34 exercices Python. **Ces chiffres sont à écarter**, probablement générés — [contracollective.com](https://contracollective.com/blog/opus-4-8-vs-gemini-3-5-pro-aider-polyglot-coding-2026).

**Coût en tokens par langage (juillet 2026)**

- **[DONNÉE]** Wu, Anderson et Guha (Northeastern, Oberlin), "The Best Programming Language for Tokenmaxxing". Protocole : mini-swe-agent, 5 modèles (dont **GPT-5.5 et Claude Sonnet 4.6**), 100 problèmes LiveCodeBench, Python/Java/Rust/OCaml, 2 000 trajectoires.
  - En tokens de sortie, rapporté à Python : **Rust 1,07 à 1,57×**, Java 0,85 à 1,34×, OCaml 1,28 à 1,69×.
  - Les étiquettes de récupération d'erreur ("fix bug", "debug failure", "revert") se concentrent sur OCaml, puis sur Rust. Les agents restent "repeatedly struggling with the same compile error".
  - Le surcoût vient du "reasoning and revision needed to get to the solution", pas de la longueur du code.
  - Source : [arXiv 2607.22807](https://arxiv.org/html/2607.22807v1).
- **[DONNÉE, hors agent]** Sur RosettaCode, l'efficacité en tokens varie d'un facteur 2,6 entre le langage le plus efficace et le moins efficace parmi 19 langages. Les langages dynamiques sont les plus économes ("not having to declare types saves a lot of tokens") — [Martin Alderson, janv. 2026](https://martinalderson.com/posts/which-programming-languages-are-most-token-efficient/).

**MultiPL-E (2022-2023, source ancienne)**

- **[DONNÉE, ancienne]** Benchmark sur 18 langages, construit à partir de HumanEval/MBPP traduits. Les résultats datent de modèles 2022-2024 (ex. stable-code-3b : PHP 24,2 %, Rust 23,0 %) et ne disent rien des modèles de frontière actuels — [stable-code-3b model card](https://huggingface.co/api/resolve-cache/models/stabilityai/stable-code-3b/5cee3fa2905e7a03c4a1b0bc02f39da7ceaa6cb5/README.md?download=true&etag=%223696ee8bb4f8ae51167bf46b6cf08baf0358c95d%22).

### Inferences

- Avec les modèles de frontière, la « difficulté intrinsèque » de Rust ne se voit pas dans les taux de réussite des benchmarks agentiques. Les tâches Rust sont même parfois mieux résolues, alors qu'elles sont plus volumineuses. Hypothèse plausible, non démontrée : les dépôts Rust populaires (ripgrep, tokio, ruff, coreutils…) sont bien testés et bien typés, ce qui donne à l'agent des signaux plus nets.
- Le benchmark le mieux contrôlé (Multi-LCB, mêmes problèmes) va dans un sens plus nuancé :
  - Rust ≈ Python pour le meilleur modèle open-weight ;
  - Rust bien en dessous pour d'autres modèles (Qwen3 −26 pts) ;
  - forte dépendance au modèle.
  - Pour un usage avec Claude Opus/Sonnet récents, on peut raisonnablement supposer que la parité est atteinte, mais **aucune donnée publique par langage n'existe pour ces modèles**.
- TypeScript et JavaScript sont systématiquement parmi les langages les plus faibles sur les benchmarks de dépôts (Multi-SWE-bench, SWE-PolyBench, SWE-bench Multilingual 2025). Le typage statique « graduel » de TS ne suffit donc pas, à lui seul, à placer un langage en tête. L'écosystème (async, frameworks, configuration de build) pèse au moins autant.
- Rust coûte plus cher en tokens et en itérations (+7 à +57 % par rapport à Python sur des problèmes courts). Pour un dev solo qui paie l'inférence, c'est un coût réel, même si le taux de réussite final est comparable.

### Gaps

- Aucun détail par langage publié pour les modèles 2026 (Claude Opus 4.8/5/5.5, GPT-5.x, Gemini 3.x) sur SWE-bench Multilingual, Multi-SWE-bench ou Aider Polyglot. L'évolution de l'écart Rust vs autres langages avec ces modèles est **inconnue**.
- Aucun benchmark agentique ne compare Rust et PHP **à difficulté contrôlée** sur des tâches d'application web/CRUD (API REST, ORM, migrations, auth). Tous les benchmarks Rust portent sur des outils CLI, des bibliothèques ou du système.
- Un extrait de recherche affirmait que « Rust est le langage le plus difficile et l'écart grandit dans les petits modèles » (attribué au papier PROBE, arXiv 2607.13820, juillet 2026). L'abstract consulté ne le confirme pas : seule la difficulté des langages peu dotés pour les petits modèles y figure.
- Un commentaire HN citait « Rust 61 % au premier essai contre C# 88 % ». Je n'ai pas trouvé le benchmark d'origine : chiffre invérifié.

## Q2 — Le compilateur et le type checker comme boucle de rétroaction : effet mesuré du typage statique sur la réussite des agents

### Takeaway

Les données dures montrent deux choses. D'abord, les erreurs de compilation des LLM sont **très majoritairement des erreurs de typage**, et un compilateur typé intégré à la boucle les corrige bien (74-93 % pour RustAssistant). Ensuite, ce que le compilateur Rust attrape dans le travail agentique réel est, à environ 84 %, de la vérification de types « ordinaire » (noms, méthodes, types, traits), qu'un compilateur Go, Java, C# ou un type checker strict attraperait aussi. La preuve la plus solide (GitHub, septembre 2026) indique que les régressions qui ont passé le compilateur étaient sémantiques ou de cycle de vie, et que ce sont les tests end-to-end qui les ont attrapées. Le gain propre à Rust par rapport à un « bon typage statique » n'est pas démontré. Par ailleurs, les agents contournent volontiers le typage quand le langage le permet (`any` en TS).

### Cited Findings

**Erreurs de typage dans le code généré**

- **[DONNÉE]** Mündler, He, Wang, Sen, Song et Vechev (ETH Zurich / UC Berkeley), "Type-Constrained Code Generation with Language Models", PLDI 2025 (ACM DOI 10.1145/3729274). Sur TypeScript (versions TS de HumanEval et MBPP), avec des modèles open-weight de 2B à 34B (Gemma 2 2B/9B/27B, DeepSeek Coder 33B, CodeLlama 34B, Qwen2.5 32B) :
  - "on average 94% of compilation errors result from failing type checks" ;
  - les erreurs syntaxiques représentent 6 % des erreurs de compilation et 3,5 % des erreurs fonctionnelles ;
  - le décodage contraint par les types réduit les erreurs de compilation de **75,3 % (HumanEval) et 52,1 % (MBPP)** en synthèse ;
  - il augmente la correction fonctionnelle de **+3,5 % à +5,5 % en relatif** ;
  - il augmente la réparation fonctionnellement correcte de code non compilant de **+37 % en relatif**.
  - Source : [arXiv 2504.09246](https://arxiv.org/pdf/2504.09246), texte vérifié sur le PDF.
  - _Limites : ce ne sont ni des modèles de frontière ni un usage agentique. En valeur absolue, les gros modèles font très peu d'erreurs de compilation (Gemma 2 27B : 15 programmes non compilants sur HumanEval en synthèse ; Qwen2.5 32B : 17). Le gain fonctionnel reste modeste._
- **[VENDOR, interprétation]** GitHub Octoverse 2025 (publié le 2025-10-28, période septembre 2024 – août 2025) :
  - "TypeScript overtook both Python and JavaScript in August 2025 to become the most used language on GitHub", avec environ 2,64 M de contributeurs mensuels (+66,6 % sur un an).
  - GitHub attribue cette hausse à l'IA : "developers are shifting toward typed languages that make agent-assisted coding more reliable in production".
  - Il cite l'étude ETH sous la forme "a 2025 academic study found 94% of LLM-generated compilation errors were type-check failures".
  - Source : [GitHub Blog](https://github.blog/news-insights/octoverse/octoverse-a-new-developer-joins-github-every-second-as-ai-leads-typescript-to-1/).
  - _Le lien causal IA → TS est une interprétation corrélationnelle de GitHub. Par ailleurs, le « 94 % » est généralisé au-delà de son périmètre : TS seul, petits modèles open, benchmarks jouets._

**Réparation par boucle compilateur**

- **[DONNÉE, modèle ancien]** RustAssistant (Microsoft Research, Deligiannis et al., arXiv août 2023, présenté à ICSE 2025). Avec GPT-4, en boucle compilateur ↔ LLM, il corrige les erreurs de compilation Rust dans :
  - **92,59 %** des micro-benchmarks ;
  - **72 %** des programmes Stack Overflow ;
  - **73,63 %** des commits GitHub réels.
  - Sur les erreurs Clippy des 10 crates principales, il atteint 75 %, soit environ 2,4× l'auto-fix de Clippy.
  - Sources : [Microsoft Research](https://www.microsoft.com/en-us/research/publication/rustassistant-using-llms-to-fix-compilation-errors-in-rust-code/), [arXiv 2308.05177](https://arxiv.org/pdf/2308.05177).
- **[DONNÉE]** CRUST-bench (avril 2025) : 100 dépôts C à traduire en Rust _safe_, avec interfaces et tests fournis.
  - o1 réussit **15 % en un coup** et **37 % avec réparation itérative** (erreurs de compilation + tests).
  - Claude 3.7 Sonnet passe de 13 % à 32 %.
  - Erreurs les plus fréquentes : incompatibilités de types, emprunts (borrowing), fonctions non implémentées.
  - Sources : [arXiv 2504.15254](https://arxiv.org/abs/2504.15254), [crust-bench.github.io](https://crust-bench.github.io/blog/).
- **[DONNÉE]** "Generative Compilation" (Mündler-Sasahara, Venev, Song, Vechev, He, juillet 2026). Sur Rust, avec des tâches de niveau dépôt et des modèles de frontière et open-weight : donner le retour du compilateur **pendant** la génération, plutôt qu'après, "reduces non-compiling outputs and improves functional correctness". L'abstract ne donne pas de chiffres — [arXiv 2607.13921](https://arxiv.org/abs/2607.13921).

**Ce que le compilateur Rust attrape réellement en agentique**

- **[VENDOR, mais données détaillées]** GitHub, "Migrating the GitHub Copilot runtime to Rust, using Copilot" (2026-09-16, mis à jour le 2026-09-23). Répartition des diagnostics du compilateur rencontrés par les agents :
  - **37 %** résolution de noms et d'imports ;
  - **22 %** méthodes manquantes ;
  - **14 %** incompatibilités de types ;
  - **11 %** bornes de traits ;
  - "**Ownership, borrowing, and lifetime errors combined were only 1.7% of coded diagnostics**".
  - Citations clés : "Every one of those four categories is bread-and-butter static typing, and **a C# or Java or Go compiler would catch all of them just as well**" ; "A strongly typed compiler… is genuinely a good fit for this work, with the agents using it as a fast feedback loop".
  - Répartition des régressions connues, non attrapées par le compilateur :
    - environ 40 % de cycle de vie et d'état (opérations appariées désynchronisées, handles qui survivent à leur instance, courses à la libération) ;
    - environ 25 % d'ambiguïtés sémantiques (nombres fractionnaires vs entiers, fuseaux horaires, PATH) ;
    - environ 15 % de migrations partielles ;
    - environ 10 % de frontières avec l'hôte.
  - "Not one of the known regressions… involved an `unsafe` block."
  - Source : [GitHub Blog](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/).
- **[DONNÉE]** Sur Rust-SWE-bench, avec OpenHands, les agents rencontrent en moyenne **3 erreurs de compilation par tâche**. Répartition :
  - **43,7 %** de compréhension du dépôt (E0433, E0432, E0425, E0412, E0405 : chemins et imports introuvables) ;
  - **32,6 %** de sémantique types/traits (E0599, E0308, E0277, E0407).
  - **44,5 %** des tâches échouent dès la phase de reproduction, et désactiver la reproduction fait chuter le taux de résolution de 42 %.
  - Source : [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1).

**Les agents contournent le typage quand ils le peuvent**

- **[DONNÉE]** Lee, Ul Hassan et Hindle (University of Alberta), "Mining Type Constructs Using Patterns in AI-Generated Code", MSR 2026. Corpus : 545 PR d'agents (Claude Code, Cursor, Devin, Copilot, Codex) et 269 PR humaines en TypeScript (jeu AIDev).
  - Les agents ajoutent **2,16 `any` par PR, contre 0,24 pour les humains (9×, p ≈ 2,3e-7)**.
  - Ils utilisent 5,50 à 6,74 constructions de types avancées par PR, contre 2,66, dont des assertions non nulles et des casts qui "bypass crucial checks during compile-time".
  - Les PR agentiques sont pourtant plus souvent acceptées (45,8 % contre 25,3 %). Les auteurs y voient un risque de dette technique accumulée.
  - Source : [arXiv 2602.17955](https://arxiv.org/html/2602.17955v2).

**Les tests comme vérificateur principal**

- **[RETEX/VENDOR]** Nicholas Carlini (Anthropic), compilateur C écrit en Rust par 16 agents (2026-02-05) : "it's important that the task verifier is nearly perfect, otherwise Claude will solve the wrong problem". L'article ne dit rien de l'apport propre du compilateur Rust — [Anthropic Engineering](https://www.anthropic.com/engineering/building-c-compiler).

**Un faux positif de recherche à ne pas réutiliser**

- Un extrait de recherche attribuait à "Inference Economics of Enterprise Coding Agents" (arXiv 2607.13080, juillet 2026) une comparaison des taux de « first-contact resolution » entre travail à dominante TS et à dominante Python. **Vérification faite, ce passage n'existe pas dans le papier.** Le papier compare Claude Opus et GLM-5.x hébergé sur site (ratio de commits de correction 45,9 % contre 74,9 %), sans stratification par langage — [arXiv 2607.13080](https://arxiv.org/html/2607.13080).

### Inferences

- Le mécanisme « le compilateur comme garde-fou » est bien établi, mais il relève du **typage statique strict en général**, pas de Rust en particulier. D'après les chiffres de GitHub, le surplus propre à Rust (ownership, lifetimes) ne pèse que 1,7 % des diagnostics. Les erreurs qui restent après compilation sont sémantiques et ne se détectent que par des tests.
- Le levier décisif pour la solidité semble être **l'absence d'échappatoire** au type checker : `any` en TS, `mixed`, suppressions ou baseline PHPStan en PHP, `unwrap` et `unsafe` en Rust. Les agents empruntent ces échappatoires bien plus que les humains. Un PHP sous PHPStan niveau 9/max sans baseline, ou un TS `strict` avec règles lint « no-any », capte une partie importante du même bénéfice. C'est une inférence : je n'ai trouvé aucune étude qui mesure PHPStan dans une boucle agentique.
- Le bénéfice mesuré du typage sur la correction fonctionnelle reste modeste (+3,5 à +5,5 % relatif chez ETH, sur de petits modèles). L'essentiel du gain porte sur la compilabilité, que les agents de frontière obtiennent de toute façon par itération.

### Gaps

- Aucune étude contrôlée « même tâche agentique, même modèle, langage typé vs non typé » sur des applications web. Il n'existe pas non plus d'étude TS vs JS en mode agentique, au-delà de l'ETH (génération) et de MSR 2026 (usage de `any`).
- Aucune donnée sur PHP + PHPStan/Psalm comme boucle de rétroaction pour un agent.
- La part des bugs _logiques_ d'agents qu'un système de types plus riche (Rust) aurait attrapés, mais qu'un typage plus faible (PHP+PHPStan, TS) aurait laissés passer, n'est mesurée nulle part.

## Q3 — Quelles fonctionnalités de Rust piègent les LLM, à quelle fréquence les agents bouclent sur des corrections, et quel est l'impact des temps de compilation ?

### Takeaway

Rust produit **nettement plus d'erreurs de compilation** que C, C++ ou Java dans le code généré. Les erreurs propres au langage (ownership/lifetimes, bornes de traits) représentent environ 23 % des erreurs de compilation Rust dans une grande étude 2026. Chez les agents de frontière travaillant sur un vrai dépôt, ownership/borrow/lifetimes tombent à environ 2 %, et ce qui domine, ce sont la navigation dans le dépôt (imports, chemins) et les traits. Les modes d'échec documentés sont les suivants :

- boucles sur la même erreur ;
- stubs ou `unwrap` pour faire taire le compilateur ;
- dérive des API après la date de coupure du modèle ;
- confusion sur `cargo test`.

Les temps de compilation sont un coût concret à l'échelle (contention CPU et disque, surcoût en tokens), sans être bloquants.

### Cited Findings

**Fréquence et nature des erreurs de compilation**

- **[DONNÉE]** Nogueira, Vieira et Campos, "Unreliable in Practice? A Comprehensive Study of Errors in LLM-Generated Code" (2026-08-01). 86 726 échantillons erronés, 7 LLM, 4 langages compilés.
  - Erreurs de compilation, prompt de base : **Rust 12 942**, C 6 850, C++ 6 706, Java 3 611.
  - Erreurs d'exécution : Rust 5 700, Java 4 051, C 2 955, C++ 1 616.
  - "Rust enforces stricter compile-time checks, resulting in more frequent compilation errors."
  - Catégories propres à Rust, ajoutées à la taxonomie : **ownership et lifetime 2 165, soit 16,7 %** des erreurs de compilation Rust ; **traits/bornes de types 784, soit 6,1 %**.
  - Source : [arXiv 2608.00661](https://arxiv.org/html/2608.00661).
  - _Ce sont des comptes absolus, pas des taux. Python, TS, Go et PHP ne sont pas couverts._
- **[VENDOR]** GitHub (septembre 2026) : ownership + borrow + lifetimes = **1,7 %** des diagnostics, bornes de traits = 11 %. Modèles utilisés : Claude Opus 5, GPT-5.6 Sol, Claude Opus 4.8, Haiku 4.5 — [GitHub Blog](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/).
- **[DONNÉE]** Rust-SWE-bench : 32,6 % d'erreurs types/traits, 43,7 % de compréhension du dépôt. Les auteurs nomment les deux défis principaux : "comprehending repository-wide code structure and complying with Rust's strict type and trait semantics" — [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1).
- **[DONNÉE]** CRUST-bench : les incompatibilités de types et les erreurs d'emprunt sont les erreurs les plus fréquentes dans la traduction C → Rust _safe_ — [arXiv 2504.15254](https://arxiv.org/abs/2504.15254).
- **[DONNÉE, détail à confirmer]** Les travaux sur la réparation ownership/lifetimes (Rust-doctor, EMNLP Findings 2025) indiquent que des diagnostics comme "borrowed value does not live long enough" restent opaques pour les modèles généralistes. Un modèle dédié améliore Pass@K de +12,61 % et Acc@K de +18,30 %. Un résumé de recherche ajoute que les LLM se rabattent sur `unsafe` ou des pointeurs bruts pour faire taire le borrow checker. Je n'ai pas pu vérifier dans le texte à quelle source exacte appartient cette dernière phrase — [ACL Anthology](https://aclanthology.org/2025.findings-emnlp.179.pdf).

**Dérive des API et churn de l'écosystème**

- **[DONNÉE]** RustEvo² (mars 2025) : 588 changements d'API Rust.
  - Les modèles obtiennent **56,1 % sur les API antérieures à leur date de coupure, contre 32,5 % sur les API postérieures**.
  - 65,8 % sur les API stabilisées, contre 38,0 % sur les changements de comportement.
  - Le RAG améliore de +13,5 % en moyenne.
  - Source : [arXiv 2503.16922](https://arxiv.org/abs/2503.16922).

**Comportements de boucle et contournements**

- **[DONNÉE]** Tokenmaxxing (juillet 2026) : agents "repeatedly struggling with the same compile error". Surcoût de 7 à 57 % en tokens pour Rust par rapport à Python — [arXiv 2607.22807](https://arxiv.org/html/2607.22807v1).
- **[RETEX]** Réécriture de Bun de Zig vers Rust (Jarred Sumner, billet du 2026-07-08). Faux départs documentés :
  - "Claude stubbing functions instead of fixing compiler errors" ;
  - "`unwrap_or()` eagerness causing panics" ;
  - assertions de debug à effets de bord désactivées en release.
  - La méthode qui a fonctionné consistait à préparer `PORTING.md` et `LIFETIMES.tsv`, à séparer les contextes implémenteur et relecteur, et à "treating compiler errors as a structured work queue".
  - Source : [bun.com](https://bun.com/blog/bun-in-rust).
- **[RETEX]** Armin Ronacher (2025-06-12) : en Rust, les agents "sometimes fail because they misunderstand `cargo test`'s invocation syntax". Il oppose cela à Go, où "tests run straightforwardly and incrementally" grâce au cache des tests — [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/).
- **[OPINION, commentaires HN]** Dans le fil HN "A case for Go as the best language for AI agents" (2026), les commentaires avancent que :
  - les "Lifetimes are a global property", difficiles pour des LLM qui raisonnent localement, même si les Claude récents s'en sortent mieux ;
  - "the test/compile cycle is much faster" en Go.
  - Source : [news.ycombinator.com](https://news.ycombinator.com/item?id=47222270).
- **[OPINION, sources faibles]** Des blogs de type tutoriel (2026) affirment que l'async Rust est le point le plus difficile : clones inutiles pour satisfaire le borrow checker, verrous tenus à travers un `.await`. Ils recommandent `cargo check` (3 à 5× plus rapide) dans la boucle de l'agent. Aucune donnée — [dev.to](https://dev.to/yureki_lab/how-i-shipped-production-rust-as-a-typescript-dev-using-claude-code-4j72), [thepromptshelf.dev](https://thepromptshelf.dev/blog/claude-code-rust-projects-guide-2026/).

**Temps de compilation**

- **[VENDOR]** GitHub : "One chat session acting as the build resource gate for eight porting sessions" pour gérer la contention CPU — [GitHub Blog](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/).
- **[RETEX]** Bun : goulots d'I/O disque au pic de parallélisation (64 instances Claude), qui ont nécessité un réglage des IOPS EC2 — [bun.com](https://bun.com/blog/bun-in-rust).
- **[DONNÉE]** Rust-SWE-bench : build initial d'environ 40 s, puis environ 12 s de compilation incrémentale par itération de traçage — [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1).

### Inferences

- Le borrow checker, longtemps présenté comme l'obstacle principal, n'est plus le problème dominant pour les modèles de frontière de 2026 (1,7 % chez GitHub). Il l'était pour les modèles 2023-2025 et le reste pour les petits modèles (16,7 % des erreurs de compilation dans l'étude d'août 2026, qui mélange 7 LLM).
- Les vrais pièges actuels sont les suivants :
  - (a) la navigation dans les modules, les imports et les traits ;
  - (b) le contournement opportuniste : `unwrap`, `clone`, stubs, et `unsafe` dans les contextes FFI ;
  - (c) le churn des crates après la date de coupure du modèle ;
  - (d) le coût des itérations (tokens + compilation).
  - Ces pièges dégradent la productivité plus que la solidité, à condition qu'une CI stricte bloque `unwrap`, `todo!` et `unsafe` (Clippy en `deny`).
- Pour un backend web Rust (axum/sqlx/tokio), l'async et les bornes `Send`/`'static` sur les handlers sont plausiblement le point de friction principal. Mais je n'ai trouvé **aucune donnée mesurée** propre à l'async Rust en contexte agentique.

### Gaps

- Pas de mesure publiée de la fréquence des « boucles infinies de correction » en Rust par rapport aux autres langages chez des agents de frontière, hormis l'étude tokenmaxxing (Python/Java/Rust/OCaml uniquement, sans Go, TS ni PHP).
- Pas de mesure de l'impact des temps de compilation Rust sur la durée totale d'une session agentique pour un projet de la taille de Cordeau (15-20 k lignes).
- Rien de quantitatif sur l'async, `Pin`, `Send` ou les macros procédurales dans les échecs d'agents.

## Q4 — Programmes de traduction C/C++ → Rust assistés par IA : preuve de compétence des LLM en Rust à grande échelle ?

### Takeaway

2026 apporte des preuves fortes que des agents de frontière peuvent produire et maintenir **des centaines de milliers à un million de lignes de Rust** fonctionnelles : Bun, le runtime de GitHub Copilot, le compilateur C d'Anthropic, Ladybird LibJS, giflib chez Google. Le facteur commun de tous ces succès est un **oracle de test préexistant et indépendant du langage** (1,3 M d'assertions pour Bun, test262, GCC torture tests, tests e2e TS, fuzzing différentiel), ainsi qu'une motivation de sécurité mémoire, de performance ou d'embarquement. Les benchmarks académiques de traduction C → Rust _safe_ restent bas (37 % au mieux sur CRUST-bench en 2025). FastRender de Cursor montre qu'un compilateur strict ne garantit ni la compilation continue ni la maintenabilité quand la gouvernance manque. Les résultats chiffrés de DARPA TRACTOR ne sont pas publics.

### Cited Findings

**Programmes institutionnels**

- **[DONNÉE, sans résultats]** DARPA TRACTOR, lancé en 2024. Le MIT Lincoln Laboratory en assure l'évaluation et publie un benchmark tous les six mois. Le papier de benchmark (septembre 2026) décrit les critères : correction fonctionnelle, sûreté (`unsafe`, UB), idiomaticité (Clippy + grille manuelle), performance. Il précise : "This report does not include evaluation results". Un "Round 1 Evaluation Report" est mentionné, mais aucun chiffre n'est publié sur la page — [arXiv 2609.25121](https://arxiv.org/html/2609.25121), [MIT LL](https://www.ll.mit.edu/r-d/projects/translating-all-c-rust-tractor-benchmarks), [DARPA 2024](https://www.darpa.mil/news-events/2024-07-31a).
- **[OPINION/VENDOR]** Galen Hunt (Distinguished Engineer, Microsoft), post LinkedIn de décembre 2025 : objectif d'éliminer le C/C++ de Microsoft d'ici 2030 grâce à Rust + IA, avec pour « étoile polaire » "1 engineer, 1 month, 1 million lines of code". Le post a ensuite été corrigé : il s'agit d'un projet de recherche ou de recrutement, pas d'une réécriture de Windows — [The New Stack](https://thenewstack.io/microsofts-bold-goal-replace-1b-lines-of-c-c-with-rust/), [XDA](https://www.xda-developers.com/no-microsoft-isnt-actually-eliminating-c-and-c-from-its-software-rust/).
- **[VENDOR]** Google (blog Bug Hunters, relayé par InfoQ le 2026-09-27) : giflib, environ 3 000 lignes, traduit en Rust avec Gemini. Processus : traduction en un coup, puis affinage humain (sémantique des pointeurs, FFI), puis fuzzing différentiel dont les traces d'échec sont renvoyées au modèle.
  - "200 million iterations without observing functional drift" en 6 jours ;
  - 30 M de GIF réels testés ;
  - code immunisé contre la CVE-2026-26740 ;
  - "not a hands-off panacea" : les wrappers FFI exigent une expertise humaine.
  - Source : [InfoQ](https://www.infoq.com/news/2026/09/c-rust-rewrite/).

**Benchmarks académiques**

- **[DONNÉE]** CRUST-bench (avril 2025) : o1 15 % en un coup, 37 % avec réparation ; Claude 3.7 Sonnet 13 % puis 32 % — [arXiv 2504.15254](https://arxiv.org/abs/2504.15254).

**Réalisations agentiques à grande échelle**

- **[VENDOR/RETEX]** Anthropic, compilateur C en Rust (2026-02-05) :
  - Moyens : 16 agents Opus 4.6 en parallèle, environ 2 000 sessions, environ 20 000 $ (2 Md de tokens en entrée, 140 M en sortie), 2 semaines, environ **100 000 lignes de Rust**.
  - Résultats : compile Linux 6.9 (x86, ARM, RISC-V) et passe 99 % de la GCC torture suite.
  - Limites reconnues : le code généré est "less efficient code than GCC with all optimizations disabled", la qualité du Rust n'est pas celle d'un expert, et "New features and bugfixes frequently broke existing functionality".
  - Source : [Anthropic Engineering](https://www.anthropic.com/engineering/building-c-compiler).
- **[RETEX]** Ladybird (Andreas Kling, 2026-02-23) : lexer, parser, AST et générateur de bytecode de LibJS portés de C++ vers Rust, environ **25 000 lignes en 2 semaines** avec Claude Code et Codex.
  - Le travail était "human-directed", piloté par « des centaines de petits prompts » et des revues adversariales multi-modèles.
  - Les 52 898 tests test262 et les 12 461 tests de régression passent, avec une sortie identique octet pour octet.
  - Sources : [heise](https://www.heise.de/en/news/Ladybird-browser-integrates-Rust-with-AI-help-11187278.html), [The Register](https://www.theregister.com/2026/02/23/ladybird_goes_rusty/).
- **[RETEX]** Bun, de Zig vers Rust (travail du 3 au 14 mai 2026, billet du 2026-07-08) :
  - Moyens : Claude "Fable 5" en préversion, jusqu'à 64 instances, 11 jours, **+1 009 272 lignes**, 6 502 commits, 5,9 Md de tokens en entrée et 690 M en sortie, environ 165 000 $.
  - Vérification : 1,3 M d'assertions de la suite TS, aucun test sauté ni supprimé, **19 régressions**, toutes corrigées avant la release. `unsafe` représente environ 4 % du code (FFI).
  - Motivation : "In safe Rust, these are compiler errors and RAII-like automatic cleanup with `Drop`" (use-after-free, double-free, oublis de libération).
  - Sources : [bun.com](https://bun.com/blog/bun-in-rust), [Simon Willison](https://simonwillison.net/2026/Jul/8/rewriting-bun-in-rust/).
- **[VENDOR]** Runtime de GitHub Copilot, de TS/Node vers Rust (du 12 mai au 21 août 2026) :
  - Volumes : **832 378 lignes de Rust en production**, 468 689 lignes de tests unitaires Rust, 174 675 lignes de tests e2e TS conservées comme oracle.
  - Organisation : 128 PR et 135 releases, avec un développeur principal.
  - Motivation : embarquement in-process, empreinte mémoire, ABI C. Les auteurs précisent : "This is in no way a claim that every large TypeScript program should become Rust".
  - Le coût d'inférence d'environ 120 000 $ n'est rapporté que par une source secondaire.
  - Sources : [GitHub Blog](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/), [Tech Scoop](https://techscoop.substack.com/p/github-used-ai-agents-to-rewrite).

**Contre-exemple**

- **[CONTRE-EXEMPLE, DONNÉE tierce]** FastRender de Cursor (janvier 2026), un navigateur écrit en Rust par des milliers d'agents parallèles :
  - plus de 3 M de lignes, mais "Cargo check never passed" sur de nombreux commits récents ;
  - dépendances à des composants de Servo (html5ever, cssparser) qui contredisent le « from scratch » ;
  - analyse de qualité par SIG (2026-02-19) : maintenabilité **1,3/5 (5 % les plus mauvais du marché)**, architecture 2,1/5, avec ce verdict : "without governance, it can just as quickly create a new class of legacy".
  - Sources : [SIG](https://www.softwareimprovementgroup.com/blog/quality-of-fastrender/), [HN](https://news.ycombinator.com/item?id=46646777), [Simon Willison](https://simonwillison.net/2026/Jan/23/fastrender/).

### Inferences

- La compétence des agents de frontière en Rust à l'échelle industrielle n'est plus en question en 2026. Les obstacles ne sont plus le langage, mais la vérification et la gouvernance.
- Ces succès sont des **portages à comportement fixé par un oracle de tests**, pas du développement produit avec une spec mouvante. Ils ne prouvent pas que Rust rende _le développement de nouvelles fonctionnalités métier_ plus solide. Leur motivation (sécurité mémoire, performance, embarquement, ABI C) est en grande partie sans objet pour une API CRUD en PHP managé ou en TS.
- Ces cas montrent aussi que la migration d'un code existant vers Rust par agents est **économiquement faisable** pour une petite base (15-20 k lignes). Les exemples ci-dessus portent sur 25 k à 1 M de lignes. Le coût de migration en tant que tel n'est donc probablement pas l'argument décisif ; le coût de _maintenance_ l'est davantage.
- FastRender et la remarque de Carlini sur les régressions montrent que le compilateur Rust ne protège ni contre la dégradation architecturale ni contre les régressions fonctionnelles.

### Gaps

- Résultats chiffrés des équipes TRACTOR (Round 1) non trouvés publiquement.
- Aucun programme de migration PHP → Rust ou TS → Rust _d'application métier web_ documenté avec des métriques. Le cas GitHub concerne un runtime, pas une application CRUD.
- Aucun suivi à 6-12 mois de la maintenabilité des bases Rust générées par agents (Bun, Copilot) pour l'instant.

## Q5 — Retours de praticiens du codage agentique intensif : qui préfère Rust, qui préfère Go ou TS, et pourquoi ?

### Takeaway

Les praticiens les plus influents qui ont comparé explicitement les langages pour les agents **penchent vers Go plutôt que Rust** : Ronacher et Hashimoto. Leurs raisons : simplicité, outillage (`go doc`, `gopls`, cache de tests), compilation rapide, stabilité de l'écosystème. Les partisans de Rust mettent en avant le compilateur comme « harnais ». Leurs arguments sont plausibles mais surtout qualitatifs. Les grands retours d'expérience Rust (Klabnik, Woolf, Bun, Ladybird) montrent que « ça marche très bien », pas que « ça marche mieux qu'ailleurs ».

### Cited Findings

**Pro-Go**

- **[RETEX/OPINION]** Armin Ronacher, "Agentic Coding Recommendations" (2025-06-12) : il recommande fortement Go pour les nouveaux backends. Ses raisons :
  - le `context` explicite ;
  - le cache de tests ;
  - "Go is sloppy" au sens de simple ;
  - des interfaces structurelles "incredibly easy for LLMs to understand" ;
  - "Go's entire ecosystem embraces backwards compatibility".
  - Il déconseille Python : magie de pytest, async, boucles lentes. Sur Rust, il ne note que la confusion autour de `cargo test`.
  - Source : [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/).
- **[OPINION argumentée]** Ronacher, "A Language For Agents" (2026-02-09).
  - Caractéristiques qui aident les agents : types explicites, code "greppable" (symboles toujours préfixés par leur paquet, comme en Go), Result plutôt qu'exceptions (les agents "will try to catch everything they can, log it, and do a pretty poor recovery"), dépendances de contexte explicites.
  - Caractéristiques qui nuisent : **macros**, barrel files et ré-exports, alias, divergence entre l'environnement local et la CI, indentation significative.
  - Il ne donne pas de verdict détaillé sur Rust dans ce billet.
  - Source : [lucumr.pocoo.org](https://lucumr.pocoo.org/2026/2/9/a-language-for-agents/).
- **[RETEX]** Mitchell Hashimoto (X, 2026) : "`go doc` and `gopls` are like agent superpowers and its shocking how productive agents are out of the box at writing [good] Go code versus other languages I've used (including the JS ecosystem)". Il ajoute que "Ghostty is heavily AI written", en Zig, en fournissant aux agents les sources et les notes de version de Zig — [x.com/mitchellh](https://x.com/mitchellh/status/2046319366489407803?lang=en), [x.com/mitchellh](https://x.com/mitchellh/status/2041972304775934371).
- **[OPINION]** "A case for Go as the best language for AI agents" (getbruin, discuté sur HN en 2026) : "one formatter, one build system", "LLMs don't care about expressiveness, they care about predictability". Des commentaires opposent à Go la sûreté de Rust et la richesse du corpus TypeScript — [HN](https://news.ycombinator.com/item?id=47222270).

**Pro-typage, mais pas Rust spécifiquement**

- **[VENDOR]** GitHub (septembre 2026), même en ayant migré vers Rust : les erreurs attrapées sont du typage statique de base, "a C# or Java or Go compiler would catch all of them just as well" — [GitHub Blog](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/).

**Pro-Rust**

- **[RETEX]** Steve Klabnik (ex-équipe Rust) : Rue, un nouveau langage, environ 100 000 lignes de Rust écrites en 11 jours avec Claude (janvier 2026). Sceptique en 2025, il écrit désormais la plupart de son code avec Claude — [InfoQ](https://www.infoq.com/news/2026/01/steve-klabnik-rue-language-ai/), [steveklabnik.com](https://steveklabnik.com/writing/thirteen-years-of-rust-and-the-birth-of-rue/).
- **[RETEX]** Max Woolf (février 2026), d'abord sceptique, a porté scikit-learn en Rust (`rustlearn`). Il écrit "Opus 4.5 (and the models that came after it) are an order of magnitude better than coding LLMs released just months before". Pas de comparaison entre langages — [minimaxir.com](https://minimaxir.com/2026/02/ai-agent-coding/), [Simon Willison](https://simonwillison.net/2026/Feb/27/ai-agent-coding-in-excessive-detail/).
- **[RETEX, sans données]** Marc Love (2025-12-12) : le compilateur et Clippy attrapent les hallucinations d'agents (arguments inversés, méthodes inexistantes) et le code mort. "The type system, borrow checker, and integrated tools produce specific, actionable error messages that slot naturally into the agentic loop." Aucun chiffre, aucun inconvénient mentionné — [marclove.com](https://marclove.com/blog/2025-12-13-rust-feedback-loop-catches-claude-code-hallucinations-dead-code-bugs/).
- **[OPINION]** "The Compiler Is the Harness: Why Agentic Coding Works So Well in Rust" (Medium, 2026) et Bob Belderbos, "The Rust Compiler as an AI Coding Agent Guardrail" : même thèse, sans données. Le second reconnaît que "Rust's compiler does not know whether a business rule is correct" — [Medium](https://medium.com/@ashbenen/the-compiler-is-the-harness-why-agentic-coding-works-so-well-in-rust-730bca7faf8e), [belderbos.dev](https://belderbos.dev/blog/rust-compiler-ai-agent-guardrail/).

**Neutres**

- **[RETEX/OPINION]** Simon Willison : les LLM gèrent bien une syntaxe nouvelle "especially if they get to run an agentic loop against a compiler or linter". Il insiste sur l'importance de "very clear success criteria" — [simonwillison.net (tag compilers)](https://simonwillison.net/tags/compilers/), [Rewriting Bun in Rust](https://simonwillison.net/2026/Jul/8/rewriting-bun-in-rust/).

### Inferences

- Le consensus des praticiens n'est pas « Rust est le meilleur langage pour les agents ». Il est plutôt : **un langage simple, stable, typé statiquement, avec un outillage rapide et une seule façon de faire**. Go coche ces cases mieux que Rust. Rust gagne quand les contraintes l'exigent : sécurité mémoire, performance, absence de GC, ABI C.
- Aucun praticien de référence ne défend Rust _pour une application web CRUD de petite taille_ sur la base d'un gain de fiabilité agentique mesuré.
- Plusieurs critiques de Ronacher s'appliquent directement à Rust : macros (derive, sqlx `query!`, axum), ré-exports via `pub use`, temps de test. D'autres s'appliquent à PHP/Symfony : attributs et autowiring « magiques », configuration implicite. Il faut le noter comme un point de vigilance commun aux deux options.

### Gaps

- Je n'ai pas trouvé de billet du blog de Zed, d'OpenAI ou d'Anthropic qui compare explicitement les langages pour les agents (au-delà des cas ci-dessus).
- Le billet ou l'interview où Ronacher détaille pourquoi Rust est un « bad fit » pour les startups (Pragmatic Engineer, 2025-10-08) n'a pas pu être cité verbatim : la transcription n'était pas disponible — [Pragmatic Engineer](https://newsletter.pragmaticengineer.com/p/python-go-rust-typescript-and-ai).
- JetBrains State of Developer Ecosystem n'a pas été consulté, faute de temps.

## Q6 — Données spécifiques à PHP : part dans les données d'entraînement et résultats de benchmarks

### Takeaway

PHP est un langage **très bien doté en données d'entraînement**, environ 14 fois plus que Rust en volume dans The Stack v2 et comparable à Python. Sur les rares benchmarks qui l'incluent, il se situe en milieu de classement : au-dessus de JS/TS et de Go dans SWE-bench Multilingual 2025, en dessous de Rust et de TS dans AutoCodeBench, légèrement en dessous de Rust dans Multi-LCB. Il est **absent** des benchmarks agentiques majeurs : Multi-SWE-bench, SWE-PolyBench, Aider Polyglot, SWE-Bench ProMax. Rien de publié ne mesure Symfony, API Platform ou PHPStan dans une boucle agentique.

### Cited Findings

- **[DONNÉE, 2024]** The Stack v2 (StarCoder2, février 2024), version dédupliquée :

  | Langage    | Volume        | Fichiers |
  | ---------- | ------------- | -------- |
  | **PHP**    | **224,59 Go** | 46,03 M  |
  | **Rust**   | **15,60 Go**  | 2,22 M   |
  | TypeScript | 61,01 Go      | 23,85 M  |
  | Go         | 54,60 Go      | 9,30 M   |
  | Python     | 233,29 Go     | 56,93 M  |
  | Java       | 548,00 Go     | 154,28 M |
  | JavaScript | 1 115,42 Go   | 108,87 M |

  Dans le sous-ensemble d'entraînement de StarCoder2-15B : PHP 183,70 Go, Rust 12,43 Go. Source : [arXiv 2402.19173](https://arxiv.org/html/2402.19173v1). _Les corpus des laboratoires de frontière ne sont pas publics. C'est un indicateur de la disponibilité relative du code public, pas du mélange réel d'entraînement._

- **[DONNÉE]** SWE-bench Multilingual, Claude 3.7 Sonnet (2025) : **PHP 48,84 % (21/43)**, contre Rust 58,14 %, Java 53,49 %, Ruby 43,18 %, JS/TS 34,88 %, Go 30,95 % — [swebench.com](https://www.swebench.com/multilingual.html).
- **[DONNÉE]** AutoCodeBench (août 2025), PHP : Claude Opus 4 44,5, Sonnet 4 45,0, o3-high 44,0, DeepSeek-R1 39,0, GPT-4.1 43,5. C'est 10 à 16 points sous Rust pour ces mêmes modèles et proche de Python — [arXiv 2508.09101](https://arxiv.org/html/2508.09101v1).
- **[DONNÉE]** Multi-LCB (juin 2026), PHP : GPT-OSS-120B 66,1, Qwen3-235B-Thinking 69,0, DeepSeek-R1-0528 61,6 — [arXiv 2606.20517](https://arxiv.org/html/2606.20517).
- **[DONNÉE, hors sujet à signaler]** OSS-Bench_php (2025) porte sur le code source de l'interpréteur PHP (php-src, écrit en C). Il ne mesure pas la génération de code PHP et ne doit pas être utilisé comme preuve sur PHP — [arXiv 2505.12331](https://arxiv.org/html/2505.12331v2).
- **[DONNÉE faible, source secondaire]** Un mémoire (Theseus.fi, mai 2025) a comparé ChatGPT, DeepSeek, Gemini et Copilot sur Node.js, Flask, .NET et Laravel : "Node.js exhibited the highest compatibility with AI-generated code, while .NET demonstrated superior correctness and maintainability". Laravel n'arrive pas en tête. La même source note qu'aucun benchmark publié ne vérifie l'intuition selon laquelle les frameworks « convention over configuration » aideraient l'IA — [DailyAiStudio](https://dailyaistudio.substack.com/p/web-frameworks-2026).
- **[VENDOR]** Octoverse 2025 ne fournit aucune donnée de croissance pour PHP, Rust ou Go (hors top 10) — [GitHub Blog](https://github.blog/news-insights/octoverse/octoverse-a-new-developer-joins-github-every-second-as-ai-leads-typescript-to-1/).

### Inferences

- PHP ne souffre pas d'un déficit de données d'entraînement. Si les agents réussissent moins bien en PHP qu'en Rust sur certains benchmarks, la cause plus probable est la nature des tâches et des dépôts (applications web, configuration, magie de framework), pas la rareté du langage. Il s'agit d'une hypothèse.
- Pour Cordeau, le facteur pertinent n'est pas « PHP vs Rust » dans l'absolu. C'est plutôt « PHP 8.5 + PHPStan niveau 9 + architecture hexagonale + API Platform » contre « Rust + axum/sqlx », et aucune étude ne compare ces deux configurations.

### Gaps

- Aucun benchmark agentique PHP de niveau dépôt avec les modèles 2026.
- Aucune donnée sur l'effet de PHPStan ou Psalm (niveau max) dans une boucle agentique.
- Aucune donnée sur la performance des agents avec Symfony/API Platform (attributs, autowiring, configuration YAML/PHP) par rapport à des frameworks plus explicites.

## Synthèse — Rust rend-il les agents plus fiables ou productifs, moins, ou ni l'un ni l'autre, et par quels mécanismes ? (objectif global)

### Takeaway

Les preuves disponibles conduisent au verdict suivant.

- **Fiabilité.** Rust _n'empêche pas_ les agents de frontière d'être très performants : parité, voire avantage, sur les benchmarks de dépôts, et réalisations de 100 k à 1 M de lignes en 2026. Mais **aucune étude ne montre un gain de fiabilité propre à Rust par rapport à un autre langage strictement typé**. Le mécanisme démontré, à savoir le compilateur comme boucle de rétroaction qui élimine les erreurs mécaniques, appartient au typage statique en général. La part propre à Rust (ownership, lifetimes) pèse environ 2 % des diagnostics chez les agents de 2026. Les régressions qui comptent sont sémantiques et ne sont attrapées que par les tests.
- **Productivité.** Rust coûte plus en tokens (+7 à +57 % par rapport à Python), en erreurs de compilation (environ 2× C ou C++ en volume) et en temps de build. Les praticiens de référence préfèrent Go pour les agents.
- **Solidité à long terme.** Le facteur dominant observé est l'existence d'un **oracle de tests** et d'une gouvernance (revue adversariale, CI stricte), pas le langage (FastRender, Carlini).

### Cited Findings

- Le compilateur strict est utile, mais un compilateur C#, Java ou Go aurait attrapé les mêmes erreurs. Ownership/borrow/lifetimes ne représentent que 1,7 % des diagnostics, et les régressions sont sémantiques — [GitHub Blog, 2026-09](https://github.blog/ai-and-ml/generative-ai/migrating-the-github-copilot-runtime-to-rust-using-copilot/).
- 94 % des erreurs de compilation LLM en TS sont des erreurs de typage, mais le gain de correction fonctionnelle du typage contraint reste de +3,5 à +5,5 % en relatif (petits modèles) — [arXiv 2504.09246](https://arxiv.org/pdf/2504.09246).
- Rust coûte 1,07 à 1,57× les tokens de Python avec GPT-5.5 et Sonnet 4.6, avec des boucles sur la même erreur de compilation — [arXiv 2607.22807](https://arxiv.org/html/2607.22807v1).
- Rust arrive en tête de SWE-bench Multilingual 2025 (58 %, contre PHP 49 %, JS/TS 35 %, Go 31 %) et de SWE-Bench ProMax 2026 (63,6 %, contre TS 53,6 % et Python 48,3 %), sur des tâches non équivalentes entre langages — [swebench.com](https://www.swebench.com/multilingual.html), [arXiv 2608.09802](https://arxiv.org/html/2608.09802).
- Les agents contournent le typage quand c'est possible (`any` 9× plus fréquent que chez les humains en TS) — [arXiv 2602.17955](https://arxiv.org/html/2602.17955v2).
- Avec un compilateur strict mais sans gouvernance, FastRender obtient une maintenabilité de 1,3/5 (5 % les plus mauvais du marché) — [SIG](https://www.softwareimprovementgroup.com/blog/quality-of-fastrender/).
- Ronacher et Hashimoto préfèrent Go pour les agents : simplicité, outillage, stabilité, tests rapides — [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/), [x.com/mitchellh](https://x.com/mitchellh/status/2046319366489407803?lang=en).

### Inferences

- Pour la question de Cordeau (« migrer tout ce qui est possible vers Rust pour un développement agentique plus solide »), les preuves soutiennent l'énoncé « le typage statique strict et sans échappatoire + des tests forts rendent les agents plus fiables ». Elles **ne soutiennent pas** l'énoncé plus spécifique « Rust apporte un gain de solidité agentique décisif par rapport à PHP+PHPStan max ou TS strict pour une application métier web ». Elles ne le réfutent pas non plus : la donnée n'existe pas.
- Le bénéfice propre à Rust (sécurité mémoire, absence de GC, performance) répond à des problèmes que Cordeau n'a pas : PHP et TS sont déjà sûrs en mémoire. Les migrations réussies de 2026 étaient motivées par ces problèmes-là.
- Les leviers démontrés et transposables sans changer de langage sont les suivants :
  - (1) suppression des échappatoires du typage : PHPStan max sans baseline ni `@phpstan-ignore` non justifié, TS `strict` + `noImplicitAny` + lint no-explicit-any ;
  - (2) tests e2e et de contrat servant d'oracle indépendant du langage ;
  - (3) revue adversariale multi-agents ;
  - (4) boucle de feedback rapide (cache de tests, type-check incrémental).
- Si une partie de Cordeau devait passer en Rust, les preuves suggèrent de la choisir pour des raisons de _performance ou de sécurité mémoire_ (traitement d'images, moteur de sync offline). Elles suggèrent aussi de procéder comme Bun, GitHub et Ladybird : avec un oracle de tests existant avant le portage.

### Gaps

- Il manque une étude contrôlée « même spec produit, même modèle de frontière 2026, développement multi-semaines en Rust vs PHP/TS typé strict », avec mesure des défauts en production et de l'effort de maintenance.
- Il manque des données longitudinales (6 à 24 mois) sur la maintenabilité des bases Rust générées par agents, comparées à d'autres langages.
- Il manque le détail par langage pour les modèles 2026 sur les benchmarks agentiques.
