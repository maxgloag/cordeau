# Adoption industrielle de Rust : résultats mesurés, pratiques publiées, contre-cas

> Notes de recherche, rédigées le 2026-10-01. Légende des niveaux de preuve utilisée dans tout le fichier :
> **[MESURÉ]** = donnée chiffrée issue de la télémétrie/production de l'entreprise elle-même ; **[ENQUÊTE]** = déclaratif de développeurs (sondage) ; **[ANNONCE]** = affirmation d'un dirigeant/ingénieur sans méthodologie publiée, ou communication produit ; **[ANECDOTE]** = retour d'expérience d'une petite équipe ; **[SECONDAIRE]** = chiffre lu uniquement dans un agrégateur/presse, pas re-vérifié dans la source primaire.
>
> Constat transversal à garder en tête : **presque toutes les données mesurées portent sur du code système (C/C++ → Rust) ou d'infrastructure à très grande échelle (JVM/Go → Rust)**. Je n'ai trouvé **aucune donnée mesurée comparant Rust à PHP ou TypeScript pour un backend CRUD métier** (cf. Gaps de la section 2).

---

## 1. Résultats mesurés de l'adoption de Rust dans les grandes entreprises

### Takeaway

Les chiffres les plus solides viennent de Google/Android (densité de vulnérabilités mémoire ~1000× plus faible, rollbacks ~4× moins fréquents, revue de code 25 % plus courte vs C++, nov. 2025), de Cloudflare (FL2 : moins de la moitié du CPU, -10 ms de médiane) et d'AWS (Aurora DSQL : 10× le débit d'un composant Kotlin optimisé). Ces gains sont tous mesurés **contre C/C++ ou contre des runtimes à GC à très grande échelle**, jamais contre un backend web interprété de taille modeste. Et l'incident Cloudflare du 18 nov. 2025 rappelle que Rust n'empêche ni les erreurs de logique ni les crashes par `panic`.

### Cited Findings

**Google / Android**

- [MESURÉ] 13 nov. 2025 (Jeff Vander Stoep, Android Security) : les vulnérabilités de sûreté mémoire passent **sous 20 % du total** des vulnérabilités Android pour la première fois en 2025 — [Google, « Rust in Android: move fast and fix things »](https://blog.google/security/rust-in-android-move-fast-fix-things/)
- [MESURÉ] Même source : densité ≈ **0,2 vulnérabilité / million de lignes (MLOC) en Rust vs ≈ 1 000 / MLOC en C/C++**, soit « a more than 1000x reduction » — [Google, nov. 2025](https://blog.google/security/rust-in-android-move-fast-fix-things/)
- [MESURÉ] Même source, cadre DORA : les changements Rust passent **25 % de temps en moins en revue de code**, nécessitent **~20 % de révisions en moins** que des changements C++ de taille similaire ; pour les changements moyens et gros, **taux de rollback ~4× plus faible** que C++. Méthodo déclarée : changements first-party et third-party en C, C++, Java, Kotlin, Rust ; contrôle par taille de changement, pools de développeurs similaires, suivi temporel — [Google, nov. 2025](https://blog.google/security/rust-in-android-move-fast-fix-things/)
- [MESURÉ] Même source : ~**5 millions de lignes de Rust** dans Android ; au T3 2025 le code net Rust ajouté dépasse légèrement celui de C++ ; **~4 % du code Rust est dans des blocs `unsafe{}`** ; un seul « near-miss » mémoire (CVE-2025-48530, buffer overflow dans CrabbyAVIF, jamais publié, rendu non exploitable par l'allocateur durci Scudo) — [Google, nov. 2025](https://blog.google/security/rust-in-android-move-fast-fix-things/)
- [MESURÉ] 25 sept. 2024 (Vander Stoep & Rebert) : part des vulnérabilités mémoire dans Android passée de **76 % (2019) à 24 % (2024)** ; nombre absolu de 223 (2019) à < 50 (2024). Stratégie « Safe Coding » : langages memory-safe **pour le nouveau code**, l'ancien code C/C++ ne recevant que des correctifs ; les vulnérabilités « décroissent exponentiellement » avec l'âge du code — [Google Security Blog, « Eliminating Memory Safety Vulnerabilities at the Source »](https://security.googleblog.com/2024/09/eliminating-memory-safety-vulnerabilities-Android.html) (corps de l'article non récupéré par l'outil ; chiffres confirmés via [Cybersecurity Dive](https://www.cybersecuritydive.com/news/google-reduces-memory-safety-defects/728260/) et [The Hacker News](https://thehackernews.com/2024/09/googles-shift-to-rust-programming-cuts.html)) [SECONDAIRE pour le détail]
- [ENQUÊTE] 27 juin 2023 (Lars Bergstrom & Kathy Brennan) : sondage de **plus de 1 000 développeurs Google** ayant commité du Rust en 2022. Plus de 2/3 confiants pour contribuer à une codebase Rust en ≤ 2 mois ; 1/3 aussi productifs qu'avec leurs autres langages en ≤ 2 mois, >50 % en ≤ 4 mois ; **85 % confiants que leur code Rust est correct** ; 77 % satisfaits de la qualité du code ; >50 % trouvent le code Rust « incredibly easy to review ». **Lenteur de build = défi n°1 « by far »** (seulement 40 % la jugent acceptable) ; autres défis : macros, ownership/borrowing, async — [Google Open Source Blog, « Rust fact vs. fiction »](https://opensource.googleblog.com/2023/06/rust-fact-vs-fiction-5-insights-from-googles-rust-journey-2022.html)
- [ANNONCE] Mars 2024, Rust Nation UK (Lars Bergstrom) : « In every case we've seen a decrease by more than 2x in the amount of effort required to both build the services in Rust as well as maintain and update those services » (vs C++) ; « When we've rewritten systems from Go into Rust, we've found that it takes about the same size team about the same amount of time to build it » + moins de mémoire et moins de défauts. Pas de méthodologie publiée — [The Register, 31 mars 2024](https://www.theregister.com/2024/03/31/rust_google_c)

**Microsoft**

- [ANNONCE] RustConf 2025 (sept. 2025), Mark Russinovich (CTO Azure) : ~**70 % des CVE Windows sont des problèmes mémoire** ; « no matter how much we really want to make C and C++ better, we can't make it as good as what Rust starts with » ; Rust en production dans Azure Boost, Caliptra, le VMM Hyper-V ; dans Windows : DirectWriteCore et du code Win32k/GDI (`win32kbase_rs.sys`) — [The Register, 11 sept. 2026](https://www.theregister.com/devops/2026/09/11/microsoft-anoints-rust-as-a-tier-1-internal-language/5295732) ; [GeekWire 2025](https://www.geekwire.com/2025/orange-crabs-in-the-machine-how-rust-is-rewriting-the-rules-of-modern-software/)
- [SECONDAIRE] DirectWriteCore réécrit de ~152 000–154 000 lignes C++ en Rust (chiffres divergents selon les sources), « en ~6 mois », sans régression de performance — [résumé de recherche citant SoftwareSeni/ParagMali](https://paragmali.com/blog/rust-in-the-windows-kernel-a-field-guide-to-the-2024-2026-me/) ; non vérifié en source primaire
- [ANNONCE] 11 sept. 2026, RustConf Montréal (Victor Ciura, équipe outillage Rust) : Rust devient **langage « Tier 1 »** chez Microsoft, au même rang que C++, C# et TypeScript ; présent dans **plus de 100 dépôts** ; utilisé dans les **services cœur de Microsoft 365 (Outlook, Word, Excel, OneDrive, SharePoint)** et dans la stack Copilot ; création d'« Oxidizer » (crates pour services scalables) et de `rustc_codegen_utc` (backend rustc vers la toolchain MSVC). Limite reconnue : la gestion de l'`unsafe` à la frontière Rust/C++ reste difficile — [The Register, 11 sept. 2026](https://www.theregister.com/devops/2026/09/11/microsoft-anoints-rust-as-a-tier-1-internal-language/5295732)
- [ANNONCE, puis démentie] Déc. 2025, Galen Hunt (Distinguished Engineer) sur LinkedIn : « My goal is to eliminate every line of C and C++ from Microsoft by 2030 », stratégie « AI _and_ Algorithms », North Star « 1 engineer, 1 month, 1 million lines of code ». Mise à jour du 29 déc. 2025 : « Windows is \*NOT\* being rewritten in Rust with AI » — c'est un projet de recherche (offre d'emploi), pas une stratégie produit — [The Register, 24 déc. 2025](https://www.theregister.com/2025/12/24/microsoft_rust_codebase_migration/) ; [Windows Latest](https://www.windowslatest.com/2025/12/24/microsoft-denies-rewriting-windows-11-using-ai-after-an-employees-one-engineer-one-month-one-million-code-post-on-linkedin-causes-outrage/)

**AWS**

- [MESURÉ] 27 mai 2025 (Werner Vogels, avec Niko Matsakis et Marc Bowes) — Aurora DSQL : en simulation, la « crossbar » sur JVM n'atteignait que **6 000 TPS au lieu du million attendu** à 40 hôtes, latence de queue passant de 1 s à 10 s à cause des pauses GC (« nearly every transaction would be affected by the worst-case latency of any single host »). Options étudiées : optimiser la JVM, C/C++, Rust. Premier composant porté (l'Adjudicator) : **30 000 TPS en Rust vs 3 000 TPS pour la version Kotlin soigneusement optimisée**, « written by Java developers who were new to the language » — [All Things Distributed, « Just make it scale »](https://www.allthingsdistributed.com/2025/05/just-make-it-scale-an-aurora-dsql-story.html)
- [ANNONCE] Même source : le **control plane était resté en Kotlin**, puis a été réécrit en Rust parce que de la **logique métier critique était dupliquée entre les deux langages**, posant des problèmes de test et de maintenance ; « There was definitely a learning curve, but once the team was ramped up, they moved just as fast as they ever had. » Garde-fou explicite des auteurs : il est faux de dire que Rust convient à tous les projets ; cela dépend des besoins, des capacités de l'équipe et de l'environnement opérationnel — [All Things Distributed, mai 2025](https://www.allthingsdistributed.com/2025/05/just-make-it-scale-an-aurora-dsql-story.html)
- [MESURÉ, ancien] S3 ShardStore : backend de stockage de ~40 000 lignes de Rust, validé par « lightweight formal methods » / property-based testing (SOSP 2021, best paper) — [The Stack](https://www.thestack.technology/aws-shardstore-s3/) ; [The Register, oct. 2021](https://www.theregister.com/2021/10/28/aws_s3_shardstore_upgrade/)
- [SECONDAIRE] 14 nov. 2025 : runtime Rust pour AWS Lambda passé en GA — [greenchapel.dev](https://greenchapel.dev/2025/11/15/aws-lambda-rust-officially-supported-and-ready-for-production-%F0%9F%9A%80/)

**Cloudflare — gains, puis incident**

- [MESURÉ] 2025 : FL2 (nouveau proxy cœur, Rust, sur le framework Oxy) remplace FL1 (NGINX + LuaJIT, 15 ans de code) ; **moins de la moitié du CPU de FL1 et « much less than half » de la mémoire**, médiane de réponse réduite de **10 ms**, +25 % de performance sur des tests CDN tiers ; FL1 perdait du temps à convertir les données entre représentations de langages différents — [Cloudflare, « Cloudflare just got faster and more secure, powered by Rust »](https://blog.cloudflare.com/20-percent-internet-upgrade/) ; [InfoQ, oct. 2025](https://www.infoq.com/news/2025/10/cloudflare-rust-proxy)
- [MESURÉ] **18 nov. 2025** : panne mondiale. Chaîne causale : changement de permissions ClickHouse à 11:05 UTC → la requête générant le fichier de features Bot Management (sans filtre de base) renvoie des doublons → fichier doublé, > **200 features** (limite codée en dur, pré-allocation pour la performance ; ~60 en usage normal) → `thread fl2_worker_thread panicked: called Result::unwrap() on an Err value` → HTTP 5xx. Cœur de panne 11:20–14:30 UTC (3 h 10), rétablissement complet 17:06 UTC. L'ancien FL1 ne crashait pas mais mettait les scores bot à zéro (faux positifs) — [Cloudflare, postmortem 18 nov. 2025](https://blog.cloudflare.com/18-november-2025-outage/) ; [Hackaday](https://hackaday.com/2025/11/20/how-one-uncaught-rust-exception-took-out-cloudflare/)
- [MESURÉ] Engagements du postmortem : « Hardening ingestion of Cloudflare-generated configuration files in the same way we would for user-generated input » ; « Enabling more global kill switches for features » ; « Eliminating the ability for core dumps or other error reports to overwhelm system resources » ; « Reviewing failure modes for error conditions across all core proxy modules » — [Cloudflare, nov. 2025](https://blog.cloudflare.com/18-november-2025-outage/)
- [MESURÉ] **5 déc. 2025** : seconde panne (~25 min, ~28 % du trafic HTTP), cette fois dans du **Lua** (FL1) : `attempt to index field 'execute' (a nil value)`. Cloudflare : « This type of code error is prevented by languages with strong type systems. In our replacement for this code in our new FL2 proxy, which is written in Rust, the error did not occur. » — [Cloudflare, postmortem 5 déc. 2025](https://blog.cloudflare.com/5-december-2025-outage/)
- [MESURÉ] **1er mai 2026** — clôture de « Code Orange: Fail Small » : la règle de l'Engineering Codex devient **« Do not use `.unwrap()` outside of tests and `build.rs`. »** ; « Services MUST validate that upstream dependencies are in an expected state before processing » ; les changements de configuration internes sont désormais déployés progressivement avec monitoring de santé (outil Snapstone) ; stratégie « fail stale » (garder la dernière config valide) sinon « fail open »/« fail close » décidé cas par cas — [Cloudflare, « Code Orange: Fail Small is complete »](https://blog.cloudflare.com/code-orange-fail-small-complete/) ; plan initial : [Cloudflare, « Fail Small » resilience plan](https://blog.cloudflare.com/fail-small-resilience-plan/)

**Meta / WhatsApp**

- [MESURÉ] 27 janv. 2026 : la bibliothèque média « wamedia » réécrite : **160 000 lignes de C++ (hors tests) → 90 000 lignes de Rust (tests inclus)** ; développement **en parallèle** de la version C++ avec **fuzzing différentiel** et tests d'intégration/unitaires pour garantir la compatibilité ; obstacles : taille du binaire (stdlib Rust) et support du build system sur toutes les plateformes ; déployé sur Android, iOS, macOS, Web, wearables — [Engineering at Meta, « Rust at Scale: An Added Layer of Security for WhatsApp »](https://engineering.fb.com/2026/01/27/security/rust-at-scale-security-whatsapp/) ; surcoût binaire ~200 Ko selon [Rust Trends #74](https://rust-trends.com/newsletter/when-meta-and-anthropic-choose-rust/) [SECONDAIRE]

**Discord**

- [MESURÉ] 6 mars 2023 : migration Cassandra → ScyllaDB avec une couche de « data services » écrite en Rust (coalescence des requêtes identiques) ; **177 nœuds Cassandra → 72 nœuds ScyllaDB**, p99 lecture historique ~15 ms, insertion ~5 ms p99. Citations : « It gave us fast C/C++ speeds without having to sacrifice safety » ; « once it compiled, it generally works » — [Discord, « How Discord Stores Trillions of Messages »](https://discord.com/blog/how-discord-stores-trillions-of-messages). Attention : la réduction de nœuds est surtout imputable au passage à ScyllaDB (C++, sans GC JVM), pas à Rust seul.

**1Password**

- [ANNONCE] Janv. 2021 (interview Michael Fey, VP Engineering) : ~70 % de 1Password 7 pour Windows en Rust ; « Brain » (logique de remplissage navigateur) porté de Go vers Rust fin 2019 ; cœur « headless » partagé en Rust + UI native par plateforme ; difficultés : courbe ownership, temps de compilation (« our CPUs and fans are definitely getting a workout ») — [Serokell, « Rust in Production: 1Password »](https://serokell.io/blog/rust-in-production-1password) ; ~63 % du cœur en Rust selon [Rust Foundation, Member Spotlight](https://rustfoundation.org/media/member-spotlight-1password/) [SECONDAIRE]

**Outils développeur (Vercel, OpenAI, Astral, Anthropic, Ladybird)**

- [ANNONCE] 21 oct. 2025, Next.js 16 : Turbopack (Rust) devient le bundler par défaut ; « 2–5× faster production builds », « Up to 10× faster Fast Refresh » ; >50 % des sessions dev et 20 % des builds prod sur Next.js 15.3+ l'utilisaient déjà — [Next.js 16](https://nextjs.org/blog/next-16)
- [ANNONCE] 30 mai 2025, OpenAI Codex CLI « going native » : 4 objectifs — « Zero-dependency Install » (Node v22+ freinait l'adoption), « Native Security Bindings » (sandboxing Rust déjà en place : Landlock/seccomp sous Linux, `sandbox-exec` sous macOS), « Optimized Performance » (pas de GC, moins de mémoire), « Extensible Protocol » (wire protocol pour extensions multi-langages). La version TS continue de recevoir des correctifs jusqu'à parité — [GitHub openai/codex, Discussion #1174](https://github.com/openai/codex/discussions/1174)
- [SECONDAIRE] 19 mars 2026 : OpenAI annonce l'acquisition d'Astral (uv, Ruff, ty, outils Python écrits en Rust), l'équipe rejoignant Codex — [pydevtools](https://pydevtools.com/blog/openai-acquires-astral/)
- [ANNONCE] Févr. 2026, Anthropic : 16 agents Claude en parallèle, ~2 000 sessions Claude Code, ~20 000 $ d'API, produisent un **compilateur C de ~100 000 lignes écrit en Rust** capable de compiler Linux 6.9 (x86, ARM, RISC-V) — [Anthropic Engineering, « Building a C compiler with a team of parallel Claudes »](https://www.anthropic.com/engineering/building-c-compiler) (chiffres lus via résumé de recherche ; article non relu intégralement) ; [InfoQ, févr. 2026](https://www.infoq.com/news/2026/02/claude-built-c-compiler/)
- [MESURÉ] 23 févr. 2026, Ladybird : LibJS (lexer, parser, AST, générateur de bytecode, ~25 000 lignes) porté de C++ vers Rust **en deux semaines** avec Claude Code et Codex, « human-directed » (« hundreds of small prompts ») ; exigence : **sortie byte-for-byte identique** entre les deux pipelines, vérifiée sur **52 898 tests test262 + 12 461 tests de régression, zéro régression** ; le Rust produit est volontairement « traduit du C++ », non idiomatique — [Ladybird, « Adopting Rust »](https://ladybird.org/posts/adopting-rust/)

### Inferences

- Les gains mesurés à grande échelle tiennent à deux mécanismes : (1) **suppression d'une classe de bugs (mémoire, data races)** face à C/C++, et (2) **absence de GC / contrôle de la mémoire** face à JVM/Go à très haute charge (DSQL, Discord). Aucun de ces deux mécanismes n'est le problème dominant d'une API Symfony CRUD de quelques milliers d'utilisateurs : PHP est déjà memory-safe et le GC n'y est pas un goulot de latence de queue.
- Le chiffre le plus transposable à l'objectif « solide à long terme » est le **taux de rollback ~4× plus faible et la revue 25 % plus courte** (Android). Mais la comparaison est Rust vs C++, pas Rust vs un PHP typé statiquement à phpstan niveau 9 ; l'écart serait vraisemblablement bien plus faible face à PHP strict ou TypeScript strict (non mesuré).
- La stratégie de Google (« Safe Coding » sur le **nouveau** code, l'ancien code qui vieillit devient moins buggé) va **contre** l'idée de « migrer tout ce qui est possible » : même Google ne réécrit pas massivement l'existant, il change le langage par défaut du code neuf et cible les surfaces à fort rayon d'impact.
- L'incident Cloudflare montre que **Rust déplace le risque sans le supprimer** : un `.unwrap()` transforme une erreur de donnée/config en crash de processus. La réponse de Cloudflare est autant processuelle (déploiement progressif des configs, fail-stale) que linguistique (interdiction d'`unwrap`). À l'inverse, la panne du 5 déc. 2025 (Lua, `nil`) est exactement le type d'erreur que le typage fort empêche.
- Les ports assistés par IA qui ont réussi (Ladybird, wamedia en différentiel, compilateur C d'Anthropic) ont tous un **oracle de test massif et automatique** (test262 byte-identique, fuzzing différentiel, suite de compilation Linux). La qualité de la suite de tests existante conditionne la faisabilité d'une migration agentique plus que le langage cible.
- Le cas DSQL révèle un argument souvent oublié : la **duplication de logique métier entre deux langages** est elle-même un coût de solidité (Cordeau a déjà PHP côté API et TypeScript côté web/mobile).

### Gaps

- Pas de source primaire récupérée pour les chiffres Russinovich sur DirectWriteCore (152k vs 154k lignes, « 6 mois ») — divergence entre secondaires.
- Je n'ai pas trouvé de données **chiffrées publiques et récentes** pour Dropbox, Figma, Shopify, Zed, Oxide, Datadog (taux d'incident, onboarding) ; seules existent des annonces/talks anciens (2018-2021) non re-vérifiés ici.
- Aucune entreprise n'a publié de taux d'incidents de production « Rust vs langage précédent » pour un service web métier ; les chiffres Bergstrom (2× vs C++, parité vs Go) n'ont pas de méthodologie publiée.
- Le corps du billet Google de sept. 2024 n'a pas pu être lu directement (seuls les chiffres relayés par la presse sont disponibles ici).

---

## 2. Rust pour backends métier / SaaS CRUD (hors système et infra)

### Takeaway

Il existe des backends métier en Rust en production (TrueLayer pour des paiements, Svix pour des webhooks, le control plane d'Oxide, des services cœur de Microsoft 365), mais **aucune source publique mesurée ne documente vélocité, coût de recrutement ou taux de défauts d'un SaaS CRUD Rust comparé à PHP/TypeScript**. Les signaux disponibles sont qualitatifs, et le seul acteur « Rust backend-as-a-service » grand public (Shuttle) a fermé sa plateforme début 2026.

### Cited Findings

- [ENQUÊTE] State of Rust 2024 (publié le 13 févr. 2025) : les **backends serveur, services web/réseau et cloud restent les principaux domaines** d'usage (pourcentages exacts non lisibles dans le texte) ; 38 % utilisent Rust pour la majorité de leur code au travail ; **45 % des organisations** en font un usage non trivial ; **82 %** disent que Rust a aidé leur entreprise à atteindre ses objectifs ; raison n°1 d'adoption en entreprise : « build relatively correct and bug-free software », n°2 : performance — [Rust Blog, 2024 State of Rust Survey Results](https://blog.rust-lang.org/2025/02/13/2024-State-Of-Rust-Survey-results/)
- [ANNONCE] TrueLayer (fintech, Open Banking, Londres) : le produit Payouts API est entièrement en Rust, « tens of Rust production services » pour la plateforme de paiement ; Luca Palmieri (auteur de _Zero To Production in Rust_) y utilise Rust en production depuis 2018 — [lpalmieri.com/about](https://www.lpalmieri.com/about/) ; [podcast Building with Rust](https://podtail.com/podcast/building-with-rust/luca-palmieri-on-truelayer-and-zero-to-production/) [SECONDAIRE via résumé]
- [ANNONCE] Svix (service de webhooks) : backend en Rust/Axum ; raisons données : rapidité, écriture de code sûr et correct plus facile, déploiement simple (un binaire statique), « more fun » ; Axum réutilise l'écosystème Tokio/Hyper/Tower/Serde — [Show HN: Svix](https://news.ycombinator.com/item?id=30347858) ; [guides Svix Axum](https://www.svix.com/guides/sending/send-webhooks-with-rust-axum/) [SECONDAIRE via résumé]
- [ANNONCE] Oxide Computer : tout le control plane (Omicron, service Nexus qui expose l'API externe) est en Rust ; chaque service est un serveur HTTP **Dropshot** dont l'API est décrite en **OpenAPI**, d'où la génération de clients — [GitHub oxidecomputer/omicron](https://github.com/oxidecomputer/omicron) ; [architecture du control plane](https://github.com/oxidecomputer/omicron/blob/main/docs/control-plane-architecture.adoc). Oxide publie des RFD sur les pièges rencontrés, p. ex. [RFD 400 « Dealing with cancel safety in async Rust »](https://rfd.shared.oxide.computer/rfd/0400) et [RFD 619 « Managing types across Dropshot API versions »](https://rfd.shared.oxide.computer/rfd/0619) (titres seulement, contenu non relu)
- [ANNONCE] Microsoft, 11 sept. 2026 : Rust utilisé dans les **services cœur de Microsoft 365** (Outlook, Word, Excel, OneDrive, SharePoint) et Oxidizer = « a set of crates for scalable services » — [The Register](https://www.theregister.com/devops/2026/09/11/microsoft-anoints-rust-as-a-tier-1-internal-language/5295732)
- [MESURÉ] AWS DSQL : le **control plane** (typiquement du code « métier/orchestration », pas du chemin critique) a été réécrit de Kotlin vers Rust ; motif : duplication de logique métier critique entre deux langages ; productivité équivalente à Java après montée en compétence — [All Things Distributed, mai 2025](https://www.allthingsdistributed.com/2025/05/just-make-it-scale-an-aurora-dsql-story.html)
- [MESURÉ] Discord : Rust n'est utilisé que pour une **couche de data services** entre « l'API monolith » et ScyllaDB ; l'article ne précise pas le langage du monolithe — [Discord, mars 2023](https://discord.com/blog/how-discord-stores-trillions-of-messages)
- [MESURÉ] Shuttle (plateforme d'hébergement de backends Rust) : arrêt annoncé — tier Pro coupé le **16 janv. 2026**, Community début 2026 ; **aucune raison donnée** dans la doc — [Shuttle Docs, « Shuttle Shutdown »](https://docs.shuttle.dev/docs/shuttle-shutdown). [SECONDAIRE] une levée de 6 M$ en oct. 2025 pour construire « the AI platform engineer » et s'étendre au-delà de Rust est mentionnée dans un résumé de recherche, non vérifiée
- [ANECDOTE, non fiable] Des billets dev.to/Medium (2025) décrivent des réécritures Go→Rust de services web avec chute de vélocité (« -65 % de vélocité de sprint pendant un trimestre ») puis gains mémoire/CPU — [dev.to, « We rewrote a Go service in Rust and our velocity tanked for a quarter »](https://dev.to/adioof/we-rewrote-a-go-service-in-rust-and-our-velocity-tanked-for-a-quarter-27al). Auteurs et entreprises non identifiables : à ne pas utiliser comme preuve

### Inferences

- Les backends métier Rust cités ont un point commun : un **besoin non fonctionnel fort** (paiements régulés, délivrance de webhooks à haut débit, control plane d'un produit matériel dont tout le reste est en Rust, services M365 à l'échelle de centaines de millions d'utilisateurs). Aucun ne ressemble à « un dev solo + un SaaS CRUD pour artisans ».
- Le motif récurrent est **Rust pour une couche spécifique, pas pour tout** (Discord : data services ; 1Password : cœur partagé client, UI native ; Cloudflare : proxy). C'est l'inverse d'un « migrer tout ce qui est possible ».
- Le pattern Oxide (Dropshot → OpenAPI → clients générés) est conceptuellement proche de ce que Cordeau fait déjà avec API Platform → OpenAPI → `openapi-typescript` : le contrat typé de bout en bout est atteignable sans changer de langage serveur.
- La fermeture de Shuttle est un signal faible (pas de raison donnée) mais cohérent avec un marché « Rust pour backends web génériques » étroit.

### Gaps

- Aucune donnée publique trouvée sur : temps de recrutement/salaire de développeurs Rust backend en France ; vélocité mesurée d'un SaaS CRUD en Rust vs PHP/TS ; taux d'incidents en production d'un backend métier Rust.
- Pas de source primaire sur les raisons de l'arrêt de Shuttle.
- Le langage du monolithe API de Discord n'est pas confirmé par la source consultée.
- Pas de retour d'expérience « Rust + ORM/migrations/admin back-office » comparable à l'écosystème Symfony/API Platform (Doctrine, Messenger, Security, EasyAdmin) trouvé dans le temps imparti.

---

## 3. Pratiques et guidelines publiées, copiables par une petite équipe

### Takeaway

Microsoft publie depuis juillet 2025 les **Pragmatic Rust Guidelines**, qui incluent une section **IA** (M-DESIGN-FOR-AI, M-TAUTOLOGICAL-TESTS, M-RUST-SHAPED…) et une version condensée pour agents ; s'y ajoutent une politique de panique claire (« panic = stop », bugs = panics, erreurs attendues = `Result`), une configuration de lints Clippy/rustc explicite, et les pratiques de Google/Chromium (revue obligatoire de tout `unsafe`, `cargo vet`). Après sa panne, Cloudflare a codifié l'interdiction de `.unwrap()` hors tests.

### Cited Findings

**Microsoft Pragmatic Rust Guidelines**

- [MESURÉ, métadonnées GitHub] Dépôt `microsoft/rust-guidelines` créé le **2025-07-15** ; section IA transformée en guideline M-DESIGN-FOR-AI le **2025-09-05** ; « 2026 update (#54) » du **2026-06-23** ajoutant « 40+ more guidelines », un changelog et un « claude file with ground rules » ; dernier push 2026-09-30 — [GitHub microsoft/rust-guidelines](https://github.com/microsoft/rust-guidelines) (via `gh api`)
- Section **IA** (verbatim des identifiants) — [Pragmatic Rust Guidelines, AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/) :
  - **M-DESIGN-FOR-AI** « Design with AI use in mind » : API idiomatiques (« the more your APIs look and feel like the majority of Rust code in the world, the better it is for AI »), documentation exhaustive avec exemples, types forts, API testables, bonne couverture ; le système de types de Rust aide à compenser le manque de compréhension réelle des agents via les vérifications du compilateur
  - **M-SINGLE-ITEM-PATH** : chaque item public accessible par un seul chemin (les agents violent souvent cette règle en refactorant, via des ré-exports multiples)
  - **M-NO-META-DESIGN-DOCUMENTATION** : pas de documentation « du parcours » (récits de conception, tableaux d'auto-évaluation) que les agents produisent souvent ; documenter l'état final utile à l'utilisateur
  - **M-TAUTOLOGICAL-TESTS** : interdire les tests qui passent par construction (qui réénoncent la valeur attendue ou recopient les branches de l'implémentation), fréquents chez les agents
  - **M-RUST-SHAPED** : lors d'un portage depuis un autre langage, adapter les constructions techniques aux idiomes Rust au lieu de copier 1-pour-1 ; la logique métier peut être transposée, pas les patterns propres au langage source
- Version pour LLM : `src/agents/all.txt` (texte complet) et `all.meta` (taille/tokens) à injecter dans les instructions d'agents — [Medium, « Implementing Microsoft's Rust Guidelines for AI Coding Agents »](https://medium.com/rustaceans/implementing-microsofts-rust-guidelines-for-ai-coding-agents-bf985dd60fc6) [SECONDAIRE]
- Checklist (extraits pertinents) — [Pragmatic Rust Guidelines, checklist](https://microsoft.github.io/rust-guidelines/guidelines/checklist/) :
  - Correctness : **M-PANIC-IS-STOP** (« Panic means 'stop the program' »), **M-PANIC-ON-BUG** (« Detected programming bugs are panics, not errors »), M-PANIC-CONTINUATION (« Panic continuation is last resort »), M-PANIC-MESSAGE ; **M-UNSAFE** (« Unsafe needs reason, should be avoided »), M-UNSAFE-IMPLIES-UB, **M-UNSOUND** (« All code must be sound »)
  - Erreurs : **M-ERRORS-CANONICAL-STRUCTS** (erreurs = structs canoniques dans les bibliothèques), M-FROM-ERROR (conversion via `From`, pas `map_err`), **M-APP-ERROR** (« Applications may use Anyhow or derivatives »)
  - Types/robustesse : **M-STRONG-TYPES**, **M-STRONG-TYPES-GUARD** (« Newtypes guard their invariants »), M-BUILD-RESULT (validation dans `.build()`), M-AVOID-STATICS, M-MOCKABLE-SYSCALLS, M-INTEGRATION-TESTS (sous `tests/`), M-LOG-NOT-PRINT, M-LOG-STRUCTURED
  - Projet : **M-CARGO-WORKSPACE** (réglages communs dans le `Cargo.toml` de workspace), M-CRATES-IN-WORKSPACE, M-CRATES-FLAT-FOLDER (« All crates are siblings in one folder »), **M-SMALLER-CRATES** (« If in doubt, split the crate »), M-LATEST-EDITION, M-MSRV
  - Macros : **M-MACRO-LAST-RESORT**, M-EXAMPLE-OVER-PROC
  - Applications : M-MIMALLOC-APPS
- **M-STATIC-VERIFICATION** : lints rustc + Clippy + rustfmt + `cargo-audit` (vulnérabilités des dépendances) + `cargo-hack` (combinaisons de features) + `cargo-udeps` (dépendances inutilisées) + **Miri** (validation de l'`unsafe`). Lints rustc recommandés en `warn` : `ambiguous_negative_literals`, `missing_debug_implementations`, `redundant_imports`, `redundant_lifetimes`, `trivial_numeric_casts`, `unsafe_op_in_unsafe_fn`, `unused_lifetimes`. Clippy : groupes `cargo`, `complexity`, `correctness`, `pedantic`, `perf`, `style`, `suspicious` + lints « restriction » ciblés (`allow_attributes_without_reason`, `assertions_on_result_states`, `map_err_ignore`, `undocumented_unsafe_blocks`, `unused_result_ok`…). **M-LINT-OVERRIDE-EXPECT** : surcharger un lint via `#[expect(..., reason = "...")]` plutôt que `#[allow]`, pour qu'une exception devenue inutile déclenche un avertissement — [Pragmatic Rust Guidelines, universal](https://microsoft.github.io/rust-guidelines/guidelines/universal/)
- Note : la liste Clippy recommandée par Microsoft **n'inclut pas** `clippy::unwrap_used` (cohérent avec M-PANIC-ON-BUG qui accepte les panics pour les bugs) ; Cloudflare, après sa panne, va plus loin en l'interdisant hors tests — [Microsoft universal](https://microsoft.github.io/rust-guidelines/guidelines/universal/) ; [Cloudflare, mai 2026](https://blog.cloudflare.com/code-orange-fail-small-complete/) ; lint correspondant : [Clippy `unwrap_used`](https://rust-lang.github.io/rust-clippy/master/#unwrap_used)

**Google**

- Comprehensive Rust : cours gratuit de l'équipe Android (fondamentaux, idiomes, `unsafe`, modules Android/AOSP, Chromium, bare-metal, concurrence) ; ajout d'un approfondissement sur l'`unsafe` (soundness, UB, commentaires `SAFETY`, encapsulation dans des abstractions sûres) — [google/comprehensive-rust](https://github.com/google/comprehensive-rust) ; [site du cours](https://google.github.io/comprehensive-rust/)
- Chromium : « All `unsafe` Rust code in Chromium needs to be reviewed and LGTM-ed by a reviewer from `//third_party/rust/UNSAFE_RUST_OWNERS` » ; chaque bloc `unsafe` nouveau/modifié reçoit un commentaire « TODO: `unsafe` review » ; les crates tierces exigent des audits **`cargo vet`** dont le critère dépend de l'usage (safe, sandbox, test) — [Chromium docs, rust-unsafe.md](https://chromium.googlesource.com/chromium/src/+/refs/tags/135.0.7040.0/docs/rust-unsafe.md)
- Mai 2023 : Google publie ses audits de crates (compatibles `cargo vet`), alimentés notamment par ChromeOS et Fuchsia — [Google Open Source Blog, « Open sourcing our Rust crate audits »](https://opensource.googleblog.com/2023/05/open-sourcing-our-rust-crate-audits.html) ; Mozilla agrège ses audits dans [mozilla/supply-chain](https://github.com/mozilla/supply-chain)
- [SECONDAIRE] En mars 2025, Google, Mozilla, Zulip, Embark Studios, Fermyon et ISRG maintenaient conjointement des audits couvrant 14 140 crates distinctes / 58 900 versions — chiffre attribué à un papier ESOP 2026 (UC Davis) [lien PDF](https://web.cs.ucdavis.edu/~cdstanford/doc/2026/ESOP26.pdf), non relu

**Cloudflare (post-incident)**

- Règles codifiées : pas d'`.unwrap()` hors tests et `build.rs` ; validation de l'état des dépendances amont avant traitement ; fichiers de configuration générés en interne traités comme des entrées utilisateur ; déploiement progressif + kill switches ; « fail stale » — [Cloudflare, mai 2026](https://blog.cloudflare.com/code-orange-fail-small-complete/) ; [Cloudflare, nov. 2025](https://blog.cloudflare.com/18-november-2025-outage/)

**Meta / WhatsApp, Ladybird (méthode de migration)**

- Réécriture **en parallèle** de l'existant + **fuzzing différentiel** + tests d'intégration comme critère d'équivalence — [Engineering at Meta, janv. 2026](https://engineering.fb.com/2026/01/27/security/rust-at-scale-security-whatsapp/)
- Exigence de sortie **byte-identique** entre ancien et nouveau pipeline, agents IA pilotés par petits prompts, passes de revue multiples, simplification idiomatique reportée après le retrait de l'ancien code — [Ladybird, févr. 2026](https://ladybird.org/posts/adopting-rust/)

**Compilation (mitigations recommandées par le projet Rust)**

- Découper les gros crates, réduire dépendances et features activées, utiliser LLD ou mold ; LLD devient le linker par défaut sur `x86_64-unknown-linux-gnu` — [Rust Blog, Compiler performance survey 2025 results](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)

### Inferences

- Plusieurs règles de Microsoft sont **directement transposables à Cordeau, quel que soit le langage** : M-TAUTOLOGICAL-TESTS (tests qui passent par construction), M-NO-META-DESIGN-DOCUMENTATION, M-STRONG-TYPES-GUARD (Value Objects qui gardent leurs invariants — déjà le cas en hexagonal PHP), M-LINT-OVERRIDE-EXPECT (équivalent : baseline phpstan sans `@phpstan-ignore` non justifié). Elles visent des défauts typiques des agents, pas des défauts de PHP.
- La justification de M-DESIGN-FOR-AI (« le compilateur compense le manque de compréhension des agents ») est un **argument de principe, pas une mesure** : Microsoft ne publie aucune donnée comparative sur la qualité du code produit par agents en Rust vs autres langages.
- Une petite équipe qui adopterait Rust devrait au minimum copier : workspace + crates frères, lints rustc/Clippy en config de workspace, `#[expect]` avec raison, `unsafe` interdit par défaut (`#![forbid(unsafe_code)]` dans les crates applicatifs), `cargo-audit`/`cargo-deny`, politique de panique explicite (au minimum `clippy::unwrap_used` en `deny` hors tests, à la Cloudflare), erreurs `thiserror` dans le domaine et `anyhow` en bordure applicative (M-APP-ERROR).
- Le cas Cloudflare montre qu'une politique de panique doit être couplée à une politique de **validation des entrées internes** (configs, données d'autres services) : pertinent pour l'outbox offline-first de Cordeau, quel que soit le langage.

### Gaps

- Je n'ai pas pu lire en détail le texte des guidelines M-PANIC-IS-STOP / M-PANIC-ON-BUG (seuls les titres sont confirmés) ni le contenu du « claude file » ajouté au dépôt Microsoft en juin 2026.
- Les Android Rust guidelines (source.android.com) et les Rust API Guidelines (référencées par M-UPSTREAM-GUIDELINES) n'ont pas été relues dans ce lot.
- Pas trouvé de politique « Clippy officielle » publiée par Google pour Android au-delà de l'`unsafe` review Chromium.

---

## 4. Contre-cas : où des équipes ont choisi de ne PAS utiliser Rust, ou en sont sorties

### Takeaway

Les contre-cas les mieux documentés ne disent pas « Rust est mauvais » mais « Rust est mauvais **pour cette forme de problème** » : graphes cycliques et portage fidèle d'une base existante (TypeScript → Go), frontière inter-langages coûteuse et contributeurs TS (Prisma → TypeScript), itération rapide et prototypage (LogLog Games), modèle objet à héritage profond (Ladybird en 2024 — décision inversée en 2026 grâce à l'IA). La raison commune : **adéquation entre la structure du code existant/du domaine et le modèle d'ownership**, et coût de la frontière entre langages.

### Cited Findings

- [ANNONCE] **TypeScript → Go, pas Rust** (5 mars 2025, Ryan Cavanaugh, FAQ officielle) : « Idiomatic Go strongly resembles the existing coding patterns of the TypeScript codebase » ; Go offre « excellent control of memory layout and allocation [...] without requiring that the entire codebase continually concern itself with memory management » ; les allocations (AST…) vivent toute la durée du programme et l'équipe sait quand déclencher le GC ; le traitement de graphes est ergonomique en Go ; c'est **un portage** qui préserve le comportement existant, pas une réécriture — [GitHub microsoft/typescript-go, Discussion #411](https://github.com/microsoft/typescript-go/discussions/411)
- [ANNONCE] Anders Hejlsberg : les structures cycliques (AST avec pointeurs parent/enfant, symboles ↔ nœuds) se heurtent au borrow checker ; « trying to unravel all of that would just make the job of moving to native insurmountably larger » ; Go = « the lowest-level language we can get to that gives us full, optimized, native-code support on all platforms, great control over data layout, the ability to have cyclic data structures » — [The New Stack](https://thenewstack.io/microsoft-typescript-devs-explain-why-they-chose-go-over-rust-c/) [SECONDAIRE]
- [MESURÉ] Résultat du portage Go (11 mars 2025, Hejlsberg) : « reduce most build times by 10x » (9,1× à 13,5× selon les codebases), chargement de projet dans VS Code 8× plus rapide, mémoire ~moitié — [TypeScript devblog, « A 10x Faster TypeScript »](https://devblogs.microsoft.com/typescript/typescript-native-port/)
- [MESURÉ] **Prisma : Rust → TypeScript** (30 janv. 2025 ; Rust-free par défaut dans Prisma ORM 7) : raisons citées — « Contributing to the query engine requires a combination of Rust and TypeScript proficiency, reducing the opportunity for community involvement » ; « Each operating system and OpenSSL library version needs its own binary, complicating deployments » ; incompatibilité des gros binaires Rust avec les runtimes serverless/edge ; **surcoût de sérialisation JS → Rust → JS**. Résultats : bundle ~14 Mo → 1,6 Mo (-85-90 %), jusqu'à **3,4× plus rapide** sur gros jeux (findMany 25 000 lignes : 185 ms → 55 ms) — [Prisma, « From Rust to TypeScript »](https://www.prisma.io/blog/from-rust-to-typescript-a-new-chapter-for-prisma-orm) ; [Prisma 7 release](https://www.prisma.io/blog/announcing-prisma-orm-7-0-0) ; [InfoQ, janv. 2026](https://www.infoq.com/news/2026/01/prisma-7-performance)
- [ANECDOTE] **LogLog Games** (avril 2024, studio indé, 3 ans de Rust à plein temps) : compilation et itérations lentes ; « Rust's values are everything but rapid prototyping and iteration » ; lutte avec lifetimes/borrow checker lors des refactorings ; hot reloading crucial et mal servi ; écosystème GUI/gamedev « lives on hype » ; ECS qui résout le mauvais problème ; borrow checking dynamique causant des crashs après refactor — [LogLog Games, « Leaving Rust gamedev after 3 years »](https://loglog.games/blog/leaving-rust-gamedev/) ; discussion [HN](https://news.ycombinator.com/item?id=40172033)
- [ANNONCE] **Ladybird** : Rust écarté en 2024 car « Rust is not great at C++ style OOP » (modèle objet du web avec GC et hiérarchies d'héritage profondes) ; décision inversée le 23 févr. 2026 par pragmatisme (écosystème, garanties de sûreté, Firefox et Chromium intègrent déjà Rust) avec un portage assisté par IA — [Ladybird, « Adopting Rust »](https://ladybird.org/posts/adopting-rust/)
- [ANNONCE] **AWS DSQL** — garde-fou explicite des auteurs eux-mêmes : « Rust is right for every project » est présenté comme faux — [All Things Distributed, mai 2025](https://www.allthingsdistributed.com/2025/05/just-make-it-scale-an-aurora-dsql-story.html)
- [ENQUÊTE] Environ **45 % des anciens utilisateurs de Rust** citent les temps de compilation parmi les raisons d'arrêt (sept. 2025) — [Rust Blog, Compiler performance survey 2025](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)
- [MESURÉ] Microsoft garde C#, TypeScript et C++ comme langages Tier 1 **à côté** de Rust (sept. 2026) ; la promesse « zéro C/C++ en 2030 » a été requalifiée en projet de recherche (déc. 2025) — [The Register, sept. 2026](https://www.theregister.com/devops/2026/09/11/microsoft-anoints-rust-as-a-tier-1-internal-language/5295732) ; [The Register, déc. 2025](https://www.theregister.com/2025/12/24/microsoft_rust_codebase_migration/)

### Inferences

- **Leçon TS→Go directement applicable** : pour une migration (surtout assistée par IA), un langage cible **structurellement proche** du code existant permet un _port_ au lieu d'une _réécriture_. Une base Symfony hexagonale riche en objets, entités Doctrine bidirectionnelles et graphes d'agrégats est structurellement plus éloignée de Rust que de… PHP. Ladybird contourne ce problème en acceptant un Rust « traduit » non idiomatique — ce que M-RUST-SHAPED de Microsoft déconseille.
- **Leçon Prisma directement applicable** : chaque frontière inter-langages (FFI, sérialisation, binaires par plateforme) est un coût permanent de performance, de déploiement et de contribution. Introduire Rust « partout où c'est possible » dans un monorepo PHP + TS + Expo **multiplie les frontières** plutôt que de les réduire, sauf migration totale d'une couche.
- **Leçon LogLog** : l'itération rapide (prototypage UX, découverte produit) est le point faible déclaré de Rust ; Cordeau est en phase de validation produit (bêta septembre 2026 selon le contexte), précisément la phase où l'itération compte le plus.
- Le revirement de Ladybird montre que **l'IA réduit le coût de traduction**, mais pas le coût de l'oracle : sans tests byte-identiques, le portage n'aurait pas été vérifiable.

### Gaps

- Je n'ai pas trouvé de récit **vérifiable et nominatif** d'une entreprise ayant quitté Rust pour un backend web métier (les billets « Rust → Go » trouvés sont anonymes/non vérifiables).
- La date exacte de sortie de Prisma ORM 7 et le détail du choix Swift de Ladybird en 2024 n'ont pas été re-vérifiés en source primaire.

---

## 5. Données d'enquêtes : productivité perçue, compilation, courbe d'apprentissage, IA

### Takeaway

Les enquêtes convergent : les utilisateurs de Rust au travail l'adoptent d'abord pour produire du logiciel **correct** ; la compilation lente reste la plainte n°1 depuis des années (55 % attendent > 10 s une recompilation incrémentale en 2025) ; la montée en compétence est de l'ordre de 2 à 4 mois chez Google ; et la communauté bascule visiblement ses questions vers les LLM.

### Cited Findings

- [ENQUÊTE] State of Rust 2024 (publié le 13 févr. 2025) : 38 % utilisent Rust pour la majorité de leur code au travail (34 % en 2023) ; 45 % des organisations en usage non trivial (+7 pts) ; 82 % disent que Rust a aidé l'entreprise à atteindre ses objectifs ; 53 % se jugent productifs (47 % en 2023) ; 20 % ne savent écrire que des programmes simples ; principaux freins : compilation lente, débogage, usage disque ; inquiétude principale : complexité croissante du langage — [Rust Blog, 2024 results](https://blog.rust-lang.org/2025/02/13/2024-State-Of-Rust-Survey-results/)
- [ENQUÊTE] State of Rust 2025 (publié le 2 mars 2026) : **7 156 réponses** (7 310 en 2024) ; « resource usage (slow compile times and storage usage) is still up there » ; le débogage recule de la 2e à la 4e place ; préoccupation persistante sur une complexité croissante ; baisse (~3 pts) de la fréquentation des communautés, « This hints at some people moving their questions to LLM tooling » ; confirmation d'une tendance d'embauche de développeurs Rust — [Rust Blog, 2025 results](https://blog.rust-lang.org/2026/03/02/2025-State-Of-Rust-Survey-results)
- [SECONDAIRE, non vérifié] Chiffres 2025 relayés par des résumés de recherche mais **absents du texte de l'article officiel** (probablement dans les graphiques) : 48,8 % d'organisations en usage non trivial ; complexité 45,2 % (2024) → 41,6 % (2025) ; 78 % des développeurs Rust utilisent des assistants IA, 25 % prévoient d'essayer des agents autonomes. À ne citer qu'avec réserve.
- [ENQUÊTE] Compiler performance survey (publié le 10 sept. 2025, 3 700+ réponses) : satisfaction moyenne **6/10** ; **55 % attendent plus de 10 s** une recompilation incrémentale ; ~33 % jugent l'attente des annotations IDE (type-checking) bloquante ; 20-25 % gênés par les builds propres/CI ; ~45 % des anciens utilisateurs citent la compilation ; causes techniques : recompilation inutile des crates dépendants dans un workspace, expansion des proc macros derive non mise en cache, édition de liens toujours refaite de zéro — [Rust Blog, compiler performance survey 2025](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)
- [ENQUÊTE] Google 2023 (>1 000 devs) : productivité équivalente pour 1/3 en ≤ 2 mois, >50 % en ≤ 4 mois ; aucune pénalité de productivité constatée vs langages précédents ; seulement 9 % insatisfaits des messages d'erreur du compilateur — [Google Open Source Blog, juin 2023](https://opensource.googleblog.com/2023/06/rust-fact-vs-fiction-5-insights-from-googles-rust-journey-2022.html)
- [ANNONCE] Google 2024 : ~4 mois pour que la moitié des ingénieurs se déclarent aussi productifs dans le nouveau langage (Rust ou Kotlin) — [The Register, mars 2024](https://www.theregister.com/2024/03/31/rust_google_c)

### Inferences

- Pour un développement agentique, la **latence de la boucle compile/test** est un multiplicateur direct du coût par itération d'agent : 55 % des devs Rust attendent > 10 s par recompilation incrémentale, quand PHP n'a pas d'étape de compilation et que phpstan/tsc sont typiquement plus rapides sur 15-20 k lignes (non mesuré pour Cordeau — à benchmarker si l'option est retenue).
- Les mesures de montée en compétence (2-4 mois) portent sur des ingénieurs Google déjà expérimentés en C++/Java/Go, encadrés et formés (Comprehensive Rust) ; elles ne se transposent pas directement à un dev solo venant de Symfony dont l'agent écrit l'essentiel du code — mais c'est précisément le dev qui devra relire et arbitrer le code Rust produit.
- Le motif d'adoption n°1 (« relatively correct and bug-free software ») correspond à l'objectif de Cordeau ; mais il est déclaré par des gens qui ont déjà choisi Rust (biais de sélection), et la question « correct par rapport à quoi » (C/C++ le plus souvent) n'est pas posée par l'enquête.

### Gaps

- Pas de répartition par domaine (part « backend web ») lisible dans le texte des enquêtes 2024/2025.
- Aucune enquête trouvée mesurant la **qualité du code Rust produit par des agents IA** vs d'autres langages (relève d'une autre note de l'étude).
- Pas de données spécifiques France (marché de l'emploi Rust, freelances).
