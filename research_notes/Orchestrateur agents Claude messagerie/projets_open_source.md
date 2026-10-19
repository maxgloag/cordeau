# Projets open source : orchestration d'une flotte d'agents Claude Code pilotée par messagerie (état au 2026-10-04)

Méthode : métadonnées via `gh api repos/<owner>/<repo>` (étoiles, pushed_at, licence, issues ouvertes) relevées le 2026-10-04 ; READMEs et docs lus ; issues cherchées via `gh search issues`. Ce qui n'a pas pu être vérifié est dans les sections Gaps.

## 1. Quels projets permettent de piloter des agents depuis une messagerie, et comment ?

### Takeaway
Trois mécanismes existent : (a) le plugin officiel Anthropic « Channels » (Telegram, Discord, iMessage ; research preview) qui pousse les messages dans une session `claude` interactive ; (b) des bots maison (Telegram, Slack, Discord, WhatsApp) qui enveloppent le CLI ou l'Agent SDK ; (c) des intégrations dans des orchestrateurs plus larges (omnigent, cc-haha, Superset, oh-my-claudecode pour les notifications). Seul `claude-agent-fleet` combine Channels + orchestrateur-agent + tmux, mais c'est un squelette d'un seul commit, sans traction.

### Cited Findings

**Mécanisme officiel : Channels**
- Un channel est un serveur MCP qui pousse des événements dans une session Claude Code déjà ouverte ; il peut être bidirectionnel. Telegram, Discord et iMessage sont fournis en research preview. Les événements n'arrivent que tant que la session est ouverte ; pour un usage always-on, il faut lancer Claude dans un process d'arrière-plan ou un terminal persistant — [docs Channels](https://code.claude.com/docs/en/channels)
- Requiert une authentification claude.ai ou une clé Console (pas Bedrock/Vertex/Foundry). Les utilisateurs Pro/Max sans organisation sautent les contrôles d'organisation et activent par session avec `--channels plugin:telegram@claude-plugins-official` — [docs Channels](https://code.claude.com/docs/en/channels)
- `--channels` n'accepte que les plugins d'une allowlist Anthropic pendant la preview ; le flag n'apparaît pas dans `claude --help` ; la syntaxe peut changer — [docs Channels](https://code.claude.com/docs/en/channels)
- Sécurité : allowlist d'expéditeurs par appairage ; avec le relais de permissions, toute personne pouvant répondre dans le channel peut approuver/refuser des outils — [docs Channels](https://code.claude.com/docs/en/channels)
- Les prompts de permission bloquent la session si personne ne répond ; `--dangerously-skip-permissions` les contourne en partie ; en mode `-p`, les outils nécessitant une saisie (questions à choix, plan mode) sont désactivés — [docs Channels](https://code.claude.com/docs/en/channels)
- iMessage : lit `~/Library/Messages/chat.db` et répond via AppleScript, macOS uniquement, sans token ; nécessite Full Disk Access — [docs Channels](https://code.claude.com/docs/en/channels)
- Dépôt des plugins officiels (37 387 étoiles, Apache-2.0, poussé le 2026-10-04) : `external_plugins/` contient notamment `discord`, `telegram`, `imessage`, `fakechat`, `github`, `linear` — [anthropics/claude-plugins-official](https://github.com/anthropics/claude-plugins-official)
- Les channels sont distincts de « Claude in Slack » (mention @Claude qui lance une session cloud) et de Remote Control (piloter une session locale depuis claude.ai/app mobile) — [docs Channels](https://code.claude.com/docs/en/channels)

**Aitensity/claude-agent-fleet**
- Architecture : un agent orchestrateur (instance Claude Code dans tmux, `CLAUDE.md` du repo comme cerveau) qui crée d'autres agents via `scripts/new-agent.sh` et `scripts/fleet.sh start`. Chaque agent a son `CLAUDE_CONFIG_DIR` isolé, sa propre boucle tmux `while true; claude; sleep 5` avec `--continue`, et son propre canal Discord via le plugin Channels. Token Discord dans le Keychain macOS — [README](https://github.com/Aitensity/claude-agent-fleet)
- Prérequis : macOS, abonnement Claude, tmux ; ne pas cloner sous `~/Documents` (TCC macOS bloque les process headless) — [README](https://github.com/Aitensity/claude-agent-fleet)
- Pourquoi pas launchd : `claude --channels` se fige à l'init quand il est lancé en headless par launchd ; le README cite anthropics/claude-code#37878 et #9026 comme « no fix planned » — [README](https://github.com/Aitensity/claude-agent-fleet) ; mais #37878 (titre « Claude CLI v2.1.81 crashes … launchd ») est à l'état `closed` au 2026-10-04 — [issue](https://github.com/anthropics/claude-code/issues/37878). Le lien exact avec le gel de `--channels` n'est pas vérifié.
- Maturité : 0 étoile, 0 fork, 0 issue, licence MIT, un seul commit (« Initial skeleton », 2026-06-12), aucun push depuis ; Co-Authored-By Claude — [API GitHub](https://api.github.com/repos/Aitensity/claude-agent-fleet), [commits](https://github.com/Aitensity/claude-agent-fleet/commits)
- Pas de file d'attente ni de plafond de concurrence : la concurrence est le nombre d'agents lancés par `fleet.sh`. Pas d'intégration GitHub issues/PR, pas de worktrees (chaque agent a son `workspace/`) — [README](https://github.com/Aitensity/claude-agent-fleet)

**Bots Telegram/Slack/Discord/WhatsApp (cherchés via `gh search repos`)**
- `overwirehq/claude-code-telegram` (anciennement RichardAtCT ; 2 798 étoiles, poussé 2026-10-01, 36 issues ouvertes, licence non déclarée par GitHub) : bot Telegram, « SDK (primary) and CLI (fallback) », `ANTHROPIC_API_KEY` optionnel si auth CLI, rate limiting token-bucket, webhooks GitHub (HMAC) pour résumer push/PR/issues, installation recommandée depuis un tag — [README](https://github.com/overwirehq/claude-code-telegram)
- `zebbern/claude-code-discord` : 233 étoiles, poussé 2026-08-01 ; `mpociot/claude-code-slack-bot` : 180 étoiles, poussé 2025-06-27 (abandonné) ; `Rich627/whatsapp-claude-plugin` : 100 étoiles, poussé 2026-09-22 ; `timoconnellaus/claude-code-discord-bot` : 74 étoiles, 2025-06-16 — [recherche gh](https://github.com/search?q=claude+code+discord&type=repositories)
- `slopus/happy` (Happy Coder) : 24 009 étoiles, MIT, poussé 2026-10-04, 1 067 issues ouvertes. Client mobile/web (Expo) + CLI `happy` qui remplace `claude` ; chiffrement de bout en bout, notifications push, bascule bureau/téléphone, application iOS ; contient un `happy-agent` (CLI de contrôle à distance : créer/envoyer/surveiller des sessions). Pas une messagerie (app dédiée) — [README](https://github.com/slopus/happy)
- `siteboon/claudecodeui` (CloudCLI) : 13 936 étoiles, AGPL-3.0, poussé 2026-10-04, 286 issues ouvertes ; interface web responsive pour Claude Code/Codex/Cursor/OpenCode, shell intégré, git explorer, gestion de sessions ; modèle commercial « CloudCLI Cloud » ; pas de messagerie — [README](https://github.com/siteboon/claudecodeui)
- `openclaw/openclaw` : 391 313 étoiles, MIT, poussé 2026-10-04, 9 306 issues ouvertes, assistant multi-canal généraliste ; son mode de pilotage de Claude n'a pas été examiné — [API GitHub](https://github.com/openclaw/openclaw)

**Orchestrateurs avec messagerie intégrée**
- `NanmiCoder/cc-haha` : app de bureau (macOS/Windows/Linux) ; « IM » : Telegram, Feishu, WeChat, DingTalk, WhatsApp, WeCom, QQ, Slack, en messages privés uniquement ; appairage par code à 6 chiffres ; commandes `/projects`, `/resume`, `/sessions`, `/status`, `/stop`, `/new` ; approbations par boutons (Telegram) ; l'app de bureau doit rester ouverte, « l'IM n'est qu'une télécommande » ; WhatsApp passe par une session « appareil lié » d'un compte perso, pas par l'API Cloud officielle — [docs IM](https://github.com/NanmiCoder/cc-haha/blob/main/docs/im/index.md), [Telegram](https://github.com/NanmiCoder/cc-haha/blob/main/docs/im/telegram.md)
- `omnigent-ai/omnigent` : bots Slack et Discord (issues ouvertes : les bots ignorent les pièces jointes #5808 ; pas de contexte de thread Slack #6839 ; mode identité utilisateur #4425) — [issues](https://github.com/omnigent-ai/omnigent/issues/5808)
- `superset-sh/superset` : README annonce « spin up workspaces from Slack messages or Linear issues » ; bug ouvert : images jointes dans un thread Slack perdues (#7097) — [README](https://github.com/superset-sh/superset), [issue](https://github.com/superset-sh/superset/issues/7097)
- `Yeachan-Heo/oh-my-claudecode` : notifications sortantes Telegram/Discord/Slack (`omc config-stop-callback telegram|discord|slack`), variable `OPENCLAW_REPLY_CHANNEL` ; pas de pilotage entrant avéré dans le README lu — [README](https://github.com/Yeachan-Heo/oh-my-claudecode)
- `Untrivial-ai/agent-orchestrator` (AO) : notifiers vers Slack/Discord ; issue fermée « Two-Way Interaction — Reply to Agent Notifications from Discord/Slack » (#973) ; issues liées à Hermes/OpenClaw comme agents contrôleurs externes (#6101 ouvert) — [issues](https://github.com/Untrivial-ai/agent-orchestrator/issues/973)
- `getpaseo/paseo`, `johannesjo/parallel-code` : pas de messagerie ; clients mobile dédiés (Paseo : iOS/Android/web/CLI, relais chiffré ; parallel-code : QR code + Wi-Fi/Tailscale) — [Paseo README](https://github.com/getpaseo/paseo), [parallel-code README](https://github.com/johannesjo/parallel-code)
- `ccpm`, `worktrunk` : aucune messagerie.

### Inferences
- Pour le cas « je parle à un orchestrateur par Discord/Telegram sur un Mac dédié sous abonnement », le chemin à la fois officiel et le plus mince est : session Claude interactive en tmux + plugin Channels, comme dans claude-agent-fleet. Le dépôt vaut comme patron d'architecture (CLAUDE_CONFIG_DIR par agent, tmux + boucle de respawn, Keychain), pas comme dépendance (un commit, 0 étoile).
- Aucun outil examiné ne combine nativement messagerie entrante officielle + file d'attente plafonnée + issues GitHub ; l'assemblage reste à faire (soit script maison autour de tmux, soit AO/paseo comme exécuteur).

### Gaps
- Aucun test d'exécution réel n'a été fait ; tout vient des READMEs/docs/issues.
- La doc Slack de Superset n'a pas pu être lue (la page `use-with-linear` ne détaille pas Slack) : où tourne l'agent (cloud ou local) est inconnu.
- Le mode de pilotage de Claude par openclaw et par les bots Slack/Discord d'omnigent n'est pas vérifié.
- Les bots Discord/WhatsApp tiers n'ont été jugés que sur étoiles/date de push, pas sur le code.

## 2. Quels projets offrent une file de tâches avec concurrence maximale configurable ?

### Takeaway
Quasiment aucun. AO (Untrivial-ai) a un plafond `maxConcurrentSessions` dans l'ancienne base TypeScript, mais la PR de portage vers la réécriture Go a été fermée sans fusion ; une issue d'août 2026 rapporte des crashs à 40-45 workers sans plafond. Les autres outils gèrent l'isolation et la visibilité, pas la limitation. Le plafond « 2 agents simultanés (3 pour tâches légères) » devra être imposé par l'orchestrateur lui-même (CLAUDE.md + script) ou par un wrapper.

### Cited Findings
- AO : l'issue #3921 (2026-08-13) décrit un orchestrateur mort après 40-45 workers sur un Mac 8 Go ; analyse interne : « no concurrency cap in the Go rewrite » ; la PR #918 « enforce maxConcurrentSessions for worker spawns » est CLOSED, non fusionnée (2026-07-06) ; #2344 (« tracker intake hardening: spawn cap, rate-limit budget ») est ouvert — [issue #3921](https://github.com/Untrivial-ai/agent-orchestrator/issues/3921)
- AO : « Intake is opt-in and read-only toward the issue tracker » (GitHub/GitLab) ; issue #5700 (filtre par label en plus de l'assigné) ouverte — [docs AO](https://docs.orchestrator.inc), [issue](https://github.com/Untrivial-ai/agent-orchestrator/issues/5700)
- oh-my-claudecode : `omc team N:claude "..."` lance N panneaux tmux de CLI ; « Workers spawn on-demand and die when their task completes » ; N est choisi à l'appel, pas un plafond global configurable documenté ; `omc wait --start` relance les sessions après reset de rate limit — [README](https://github.com/Yeachan-Heo/oh-my-claudecode)
- omnigent : file de messages côté client (édition/suppression/steer/réordonnancement) pour un message dans une même session, pas une file de tâches entre agents ; « automations » planifiées (rrule) avec `max_cost_usd` par exécution — [QUEUE_STEER_DESIGN](https://github.com/omnigent-ai/omnigent/blob/main/docs/QUEUE_STEER_DESIGN.md), [AUTOMATIONS](https://github.com/omnigent-ai/omnigent/blob/main/docs/AUTOMATIONS.md)
- worktrunk : gestionnaire de worktrees (« 5-10+ in parallel »), hooks, `wt switch -c -x claude feat` ; aucune file ni plafond — [README](https://github.com/max-sixty/worktrunk)
- ccpm : parallélisme par « streams » analysés dans une issue, plusieurs agents dans le même worktree ; pas de plafond configurable relevé — [README](https://github.com/automazeio/ccpm)
- claude-agent-fleet : pas de file ; la limite est le nombre d'agents démarrés — [README](https://github.com/Aitensity/claude-agent-fleet)
- cc-haha : « Workflow » dynamique avec sous-agents « concurrents ou en pipeline », créés par le modèle ; pas de plafond documenté dans le README — [README](https://github.com/NanmiCoder/cc-haha)
- Superset : « 100+ agents in parallel », aucune limite documentée ; issue ouverte sur le service hôte qui ne répond plus sous charge de sessions (#7426) — [README](https://github.com/superset-sh/superset), [issue](https://github.com/superset-sh/superset/issues/7426)

### Inferences
- Le plafond 2/3 est un invariant de règle d'architecture à écrire soi-même (sémaphore dans un script de dispatch, ou instruction dans le CLAUDE.md de l'orchestrateur + vérification via `tmux ls`/`fleet.sh status`). AO montre que même un outil dédié ne l'offre pas de façon fiable actuellement.

### Gaps
- Je n'ai pas vérifié le code de Superset, Paseo, cc-haha ni omnigent pour un paramètre caché de concurrence maximale.
- Les docs de configuration d'AO (`/configuration`) n'ont pas été lues en détail.

## 3. Pilotage interactif (couvert abonnement) vs `claude -p` / Agent SDK

### Takeaway
Important : la facturation séparée du 15 juin 2026 a été suspendue le jour même et ne s'applique pas à ce jour. Le README de claude-agent-fleet, qui présente le pilotage programmatique comme facturé à part, est donc périmé sur ce point (il date du 2026-06-12, avant la pause). Le pilotage interactif reste néanmoins le plus sûr face à un éventuel retour du changement, et le plus conforme à l'usage prévu.

### Cited Findings
- Annonce (14 mai) : à partir du 15 juin 2026, Agent SDK, `claude -p`, GitHub Actions et apps tierces sortent des pools d'abonnement vers un crédit mensuel séparé (Pro 20 $, Max 5x 100 $, Max 20x 200 $) facturé au tarif API, sans report ; TUI interactif, Cowork et claude.ai explicitement non concernés — [digitalapplied](https://www.digitalapplied.com/blog/anthropic-claude-credit-overhaul-june-15-2026), [devtoolpicks](https://devtoolpicks.com/blog/anthropic-splits-claude-subscriptions-agent-sdk-credit-june-2026)
- Pause : Anthropic a confirmé le 15 juin dans son Help Center : « the planned move of Agent SDK, claude -p, and third-party app usage to a separate monthly credit is not taking effect » ; usage inchangé sur les pools d'abonnement ; Anthropic dit retravailler le plan et prévenir à l'avance — [digitalapplied](https://www.digitalapplied.com/blog/anthropic-claude-credit-overhaul-june-15-2026), [The New Stack](https://thenewstack.io/anthropic-pauses-claude-agent-sdk-subscription-change/), [The Decoder](https://the-decoder.com/anthropic-backs-off-unpopular-billing-overhaul-as-price-war-with-openai-looms/)
- Statut en septembre 2026 : Agent SDK, `claude -p` et apps tierces tirent toujours sur les limites d'abonnement, aucune nouvelle date annoncée — [koromo.io](https://koromo.io/en/blog/claude-agent-sdk-credit-guide/) (blog secondaire ; les sources primaires lues sont l'annonce reprise par digitalapplied/The New Stack, non le Help Center directement)
- Mode de pilotage par projet :

| Projet | Comment il lance Claude |
|---|---|
| claude-agent-fleet | TUI interactif natif dans tmux + Channels (explicitement sans SDK ni `-p`) — [README](https://github.com/Aitensity/claude-agent-fleet) |
| Channels officiels | Session interactive ; `-p` possible mais outils interactifs désactivés — [docs](https://code.claude.com/docs/en/channels) |
| oh-my-claudecode | Plugin dans Claude Code + `omc team` = vrais processus `claude` en panneaux tmux ; helpers `createOmcSession()` basés sur `@anthropic-ai/claude-agent-sdk` pour programmes Node ; CI headless : clé API conseillée — [README](https://github.com/Yeachan-Heo/oh-my-claudecode) |
| Paseo | `@anthropic-ai/claude-agent-sdk` 0.3.246 dans `packages/server/package.json` (donc Agent SDK, pas TUI) — [package.json](https://github.com/getpaseo/paseo/blob/main/packages/server/package.json) |
| AO | Deux interfaces : « Chat » (structuré, via `claude-agent-acp` avec l'exécutable Claude de l'utilisateur) et « Terminal UI » natif ; la doc lue ne précise pas la facturation — [docs AO](https://docs.orchestrator.inc/plugins/agents/) |
| omnigent | Harnais `claude-sdk` (SDK) et `claude-native` (terminal tmux/PTY) ; abonnement Pro/Max supporté « via the official claude CLI » ; tmux requis pour les wrappers natifs — [README](https://github.com/omnigent-ai/omnigent) |
| Superset | « Keep your existing agent subscriptions », agents CLI lancés dans des terminaux de l'app — [README](https://github.com/superset-sh/superset) |
| parallel-code | CLI dans des terminaux, « no extra subscription required » — [README](https://github.com/johannesjo/parallel-code) |
| Happy | Wrapper `happy claude` qui remplace `claude` ; bascule en « remote mode » depuis le téléphone (détail non lu) — [README](https://github.com/slopus/happy) |
| Bot Telegram overwirehq | SDK (primaire) + CLI (repli) — [README](https://github.com/overwirehq/claude-code-telegram) |
| cc-haha | App de bureau « Claude Code workbench » ; mécanisme d'invocation non vérifié |
| ccpm | Skills/commandes exécutés dans une session Claude Code ordinaire ; sous-agents lancés par la session — [README](https://github.com/automazeio/ccpm) |
| worktrunk | Simple lanceur : `wt switch -c -x claude feat` lance le `claude` interactif — [README](https://github.com/max-sixty/worktrunk) |

- Note ToS dans claude-agent-fleet : l'authentification d'abonnement est destinée à un usage local et personnel ; ne pas expédier ses identifiants à un VPS ; sur serveur distant, utiliser une clé API — [README](https://github.com/Aitensity/claude-agent-fleet). Le projet précise ne pas prétendre à l'aval d'Anthropic.

### Inferences
- Pour un Mac dédié local sous abonnement, la voie « TUI interactif + Channels » reste conforme à tout scénario de facturation. Paseo (Agent SDK) et les modes SDK d'omnigent/AO « Chat » ne seraient exposés qu'en cas de reprise du plan de facturation annoncé (risque à surveiller, non daté).
- Les workers lancés par l'orchestrateur via tmux (`claude` interactif) conservent la même garantie ; un `claude -p` ponctuel est aujourd'hui couvert mais dépend d'une politique susceptible de changer.

### Gaps
- Le Help Center d'Anthropic n'a pas été lu directement ; confirmation de la pause via presse et blogs (The New Stack, The Decoder, DevOps.com listés par la recherche, digitalapplied lu).
- Mécanisme exact de cc-haha, Happy en « remote mode » et AO Chat (ACP) non vérifié dans le code.

## 4. Intégration GitHub issues → branche → PR

### Takeaway
Les meilleurs candidats sont AO (suivi PR/CI/revue, intake d'issues en lecture seule) et ccpm (issues comme source de vérité, mais commandes propres et workflow PRD→epic lourd). Aucun ne reproduit nativement `feat/<n>-<slug>` ni la règle « Claude ne fusionne jamais » ; AO affiche un état « Ready to merge » mais l'action de fusion n'a pas été vérifiée.

### Cited Findings
- AO : chaque worker Git a sa branche et son worktree ; suivi du PR, de la CI, des revues et conflits de merge ; Kanban Working / Needs you / In review / Ready to merge ; le feedback CI/revue est renvoyé au même agent ; intake GitHub/GitLab opt-in en lecture seule — [README AO](https://github.com/Untrivial-ai/agent-orchestrator)
- AO santé : 12 737 étoiles, Apache-2.0, poussé le 2026-10-04, 755 issues ouvertes, application de bureau (macOS arm64/x64, Windows, Linux) avec daemon Go ; bugs ouverts : worktrees orphelins (#2811), nettoyage non idempotent (#3402), crash du daemon laissant des workers orphelins (#3537), `--reviewer` répétable ignoré au-delà du premier (#5465) — [API GitHub](https://github.com/Untrivial-ai/agent-orchestrator), [issue #3921](https://github.com/Untrivial-ai/agent-orchestrator/issues/3921)
- ccpm : PRD → epic → issues GitHub + sous-issues + worktree ; commentaires d'issues comme piste d'audit ; « start working on issue 42 » lance plusieurs agents en parallèle ; 8 399 étoiles, MIT, dernier push 2026-03-18 (≈ 6,5 mois), 4 issues ouvertes — [README](https://github.com/automazeio/ccpm)
- Superset : onglet Pull requests avec envoi de commentaires de diff à un agent ; intégration Slack/Linear — [README](https://github.com/superset-sh/superset)
- parallel-code : worktree par tâche, observateur de CI de PR avec notification de bureau — [README](https://github.com/johannesjo/parallel-code)
- omnigent : GitHub App `omnigent-ci[bot]` pour les revues automatisées du dépôt lui-même ; utilisation pour les utilisateurs non documentée dans ce qui a été lu — [OMNIGENT_BOT_SETUP](https://github.com/omnigent-ai/omnigent/blob/main/docs/OMNIGENT_BOT_SETUP.md)
- worktrunk : CI status et aperçus PR dans le sélecteur, hooks pre-/post-merge, commits rédigés par LLM — [README](https://github.com/max-sixty/worktrunk)
- Bot Telegram overwirehq : webhooks GitHub (push, PR, issues) routés vers Claude ; `gh` utilisé conversationnellement — [README](https://github.com/overwirehq/claude-code-telegram)

### Inferences
- Le flux de Cordeau (issue → `feat/<n>-<slug>` → PR → CI → reviewer auto-merge) est déjà couvert par le repo (`gh`, CI, PR Review). Ce qu'un outil externe apporterait : tableau de bord (AO) et gestion de worktrees (worktrunk). Un orchestrateur maison écrit dans un CLAUDE.md et un script de dispatch semble suffisant ; AO est surtout une option de supervision.

### Gaps
- Je n'ai pas vérifié si AO ou ccpm ouvrent eux-mêmes les PR avec `gh` ni s'ils peuvent fusionner (risque vs règle « Claude ne fusionne jamais »).
- Nommage de branche configurable non vérifié pour AO.

## 5. Tableau comparatif et maturité

### Takeaway
Les gros projets (omc, AO, Paseo, Superset, omnigent, cc-haha, Happy) sont très actifs (pushs le 2026-10-04) mais ont des centaines à des milliers d'issues ouvertes : ce sont des plateformes larges, pas des briques minimalistes. Les deux outils les plus proches du besoin (claude-agent-fleet, bots Telegram) sont soit sans traction, soit sans orchestration.

### Cited Findings (métadonnées au 2026-10-04, via API GitHub)

| Projet | Étoiles | Dernier push | Licence | Issues ouvertes | Plateformes |
|---|---|---|---|---|---|
| Aitensity/claude-agent-fleet | 0 | 2026-06-12 (1 commit) | MIT | 0 | macOS |
| automazeio/ccpm | 8 399 | 2026-03-18 | MIT | 4 | tout (shell) |
| Yeachan-Heo/oh-my-claudecode | 39 579 | 2026-10-04 | MIT | 1 | tmux requis pour `omc team` |
| Untrivial-ai/agent-orchestrator | 12 737 | 2026-10-04 | Apache-2.0 | 755 | macOS/Windows/Linux (app de bureau) |
| getpaseo/paseo | 19 488 | 2026-10-04 | Apache-2.0 (GitHub affiche NOASSERTION ; texte du fichier LICENSE lu) | 1 031 | desktop, iOS, Android, web, CLI, Docker |
| superset-sh/superset | 14 865 | 2026-10-04 | Elastic License 2.0 (source-available, pas OSI) | 872 | macOS (téléchargement annoncé) |
| omnigent-ai/omnigent | 10 492 | 2026-10-04 | Apache-2.0 | 1 734 | macOS/Linux ; Windows partiel |
| NanmiCoder/cc-haha | 14 862 | 2026-10-04 | MIT | 185 | macOS/Windows/Linux |
| max-sixty/worktrunk | 8 790 | 2026-10-04 | MIT ou Apache-2.0 | 56 | CLI Rust |
| stravu/crystal | 3 123 | 2026-02-26 | MIT | 68 | **déprécié en février 2026**, remplacé par Nimbalyst (nimbalyst.com) — [README](https://github.com/stravu/crystal) |
| johannesjo/parallel-code | 1 034 | 2026-09-29 | MIT | 53 | app de bureau + accès téléphone |
| slopus/happy | 24 009 | 2026-10-04 | MIT | 1 067 | iOS/web/macOS + CLI |
| siteboon/claudecodeui | 13 936 | 2026-10-04 | AGPL-3.0 | 286 | web |
| overwirehq/claude-code-telegram | 2 798 | 2026-10-01 | non déclarée | 36 | Python |

Sources : [API GitHub](https://docs.github.com/rest) pour chaque dépôt (`gh api repos/<owner>/<repo>`), licences de Paseo, Superset, worktrunk vérifiées dans les fichiers LICENSE/README.

**Plaintes et signaux notables**
- AO : crash/OOM de l'orchestrateur à 40+ workers, session non récupérable, worktrees orphelins — [issue #3921](https://github.com/Untrivial-ai/agent-orchestrator/issues/3921)
- Paseo : le fournisseur Claude ne répond jamais aux demandes d'élicitation MCP, les appels d'outils MCP soumis à approbation sont refusés silencieusement (#3941, ouverte) ; échecs de démarrage concurrent (Codex) — [issue #3941](https://github.com/getpaseo/paseo/issues/3941)
- Superset : le service hôte cesse de répondre sous charge, WAL SQLite jamais checkpointé (#7426) ; suppression d'un worktree lourd en `node_modules` qui expire (#6887) — [issue #7426](https://github.com/superset-sh/superset/issues/7426)
- omnigent : conditions de concurrence sur écritures d'état de session (#3402), « upgrade » laissant un ancien service en marche (#8868) — [issues](https://github.com/omnigent-ai/omnigent/issues)
- claude-agent-fleet : repo d'un seul commit ; le gel de `claude --channels` sous launchd est présenté comme la raison d'être de tmux — [README](https://github.com/Aitensity/claude-agent-fleet)
- Worktrunk (README) se présente comme « the most popular git worktree manager » et a été publié début 2026 — [README](https://github.com/max-sixty/worktrunk) (auto-déclaration)

### Inferences
- Les étoiles de ces projets (surtout oh-my-claudecode à 39 k avec 1 issue ouverte) sont à interpréter avec prudence : un faible nombre d'issues ouvertes peut refléter une fermeture automatique ou un suivi hors GitHub ; non vérifié.
- Les projets Python/TS à milliers d'issues ouvertes montrent un rythme de développement très rapide, avec les risques de régression correspondants.

### Gaps
- Pas d'analyse de discussions Hacker News/Reddit sur ces projets en dehors d'un lien HN (« We're pausing the Agent SDK credit change », item 48545980) non lu.
- Pas de mesure de qualité de code ni de test d'installation.
- Support Windows/Linux de plusieurs projets non vérifié.
