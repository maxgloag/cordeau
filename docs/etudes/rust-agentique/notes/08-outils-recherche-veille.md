# Outils de recherche technique et de veille pour un agent Claude Code (focus écosystème Rust)

> Notes de recherche, 2026-10-01. Toutes les métriques GitHub (`pushed_at`, étoiles) viennent de `gh api repos/OWNER/REPO` exécuté le 2026-10-01. Les sondes d'API (crates.io, deps.dev, Scorecard, OSV, Context7, registre MCP) ont été lancées en direct le même jour avec `curl` (lecture seule, rien n'a été installé).
>
> État observé de l'environnement pendant cette session (messages système du harness, pas une source externe) : les connecteurs `claude.ai Context7` et `plugin:context7` demandent une authentification non faite ; le plugin `plugin:github:github` **échoue à se connecter** (« Authorization header is badly formatted »). Le `gh` CLI, lui, fonctionne.

## 1. Serveurs MCP / API de recherche web pour agents (Exa, Perplexity, Tavily, Brave, Firecrawl, Jina, Kagi, SearXNG)

### Takeaway

Tous les grands fournisseurs ont désormais un serveur MCP **officiel, hébergé (HTTP distant) et actif** (dépôts poussés en septembre 2026). Pour un dev solo, le meilleur rapport qualité/coût/friction est **Exa** (plugin dans le marketplace officiel Anthropic, utilisable sans clé, 10 $ de crédits gratuits par mois), complété par **Firecrawl** (palier hébergé sans clé pour scrape/search) ou **Brave** (index indépendant, 5 $ de crédits par mois). Perplexity et Kagi n'ont pas de palier gratuit. Les benchmarks « indépendants » ne désignent pas de vainqueur net, et plusieurs sont publiés par des concurrents.

### Cited Findings

**Exa**

- Serveur hébergé `https://mcp.exa.ai/mcp`. Il « fonctionne anonymement avec des limites de débit ». OAuth est préféré pour lever les limites ; on peut aussi passer une clé via l'en-tête `Authorization: Bearer` ou `x-api-key` (ou `?exaApiKey=`). — [exa-labs/exa-mcp-server README](https://github.com/exa-labs/exa-mcp-server)
- Installation Claude Code documentée : `claude plugin install exa@claude-plugins-official`. — [exa-labs/exa-mcp-server](https://github.com/exa-labs/exa-mcp-server)
- Outils par défaut : `web_search_exa` et `web_fetch_exa`. Outils optionnels activables par URL : `web_search_advanced_exa` (filtres domaine et date, sous-pages) et `agent_run` (Exa Agent, recherche multi-étapes, qui demande une authentification). Exemple : `https://mcp.exa.ai/mcp?tools=web_search_exa,web_fetch_exa,agent_run`. — [README](https://github.com/exa-labs/exa-mcp-server)
- Le plugin `exa` du marketplace officiel est décrit comme « Exa AI web search, deep research, and content extraction. Provides MCP tools and research skills ». — [anthropics/claude-plugins-official marketplace.json](https://github.com/anthropics/claude-plugins-official)
- Dépôt : 5 070 ★, dernier push le 2026-09-30. Présent dans le registre MCP officiel sous `ai.exa/exa`. — [GitHub](https://github.com/exa-labs/exa-mcp-server), [registry.modelcontextprotocol.io](https://registry.modelcontextprotocol.io/v0/servers?search=exa)
- Tarifs : 10 $ de crédits gratuits à l'inscription, **remis à 10 $ le 1er de chaque mois**, sans carte. Recherche Instant à 4 $/1k, Fast/auto à 7 $/1k, contenus à 1 $/1k pages, Deep à 12 $/1k, Deep-reasoning à 15 $/1k, Agent de 0,012 $ à 1,00 $ par requête. La Zero Data Retention (ZDR) est réservée à l'offre Enterprise. — [exa.ai/pricing](https://exa.ai/pricing)

**Perplexity**

- Serveur distant officiel : `claude mcp add --transport http perplexity https://api.perplexity.ai/mcp --header "Authorization: Bearer YOUR_API_KEY"`. En local : `claude mcp add perplexity --env PERPLEXITY_API_KEY="..." -- npx -y @perplexity-ai/mcp-server`. — [perplexityai/modelcontextprotocol](https://github.com/perplexityai/modelcontextprotocol)
- Outils : `perplexity_search` (Search API, filtres de récence et de domaine), `perplexity_ask` (preset Agent API « fast »), `perplexity_research` (preset « high », des runs de plusieurs minutes) et `perplexity_reason` (« medium »). Les anciens modèles `sonar-pro` et `sonar-deep-research` ne sont plus appelés par le serveur MCP. — [README](https://github.com/perplexityai/modelcontextprotocol)
- Tarifs : Search API à 5 $/1k (Fast Search à 1 $/1k). Sonar Pro à 3 $/15 $ par million de tokens + 6 à 14 $/1k requêtes. Sonar Deep Research facture tokens et citations, plus 5 $/1k recherches. **Aucun palier gratuit mentionné.** — [docs.perplexity.ai pricing](https://docs.perplexity.ai/getting-started/pricing)
- Les presets de l'Agent API s'appuient sur des modèles OpenAI (« openai/gpt-5.6-luna » ou « gpt-5.6-sol », selon la page de tarifs). — [docs.perplexity.ai pricing](https://docs.perplexity.ai/getting-started/pricing)
- Dépôt : 2 550 ★, push le 2026-09-25. Registre : `ai.perplexity/mcp-server` et `io.github.perplexityai/mcp-server`. — [GitHub](https://github.com/perplexityai/modelcontextprotocol)

**Tavily**

- `claude mcp add --transport http tavily https://mcp.tavily.com/mcp` (flux OAuth), ou avec la clé dans l'URL `...?tavilyApiKey=<key>`. Portée utilisateur avec `--scope user`. — [tavily-ai/tavily-mcp](https://github.com/tavily-ai/tavily-mcp)
- Tarifs : 1 000 crédits gratuits par mois sans carte, puis 0,008 $ par crédit. Recherche basic = 1 crédit, advanced = 2. Extract : 1 crédit pour 5 URL. Research : de 4 à 250 crédits par requête. — [docs.tavily.com](https://docs.tavily.com/documentation/api-credits)
- Un plugin `tavily` (dépôt `tavily-ai/skills`) existe dans le marketplace officiel. — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)
- Dépôt : 2 415 ★, push le 2026-09-29. Registre : `io.github.tavily-ai/tavily-mcp`.

**Brave Search**

- Serveur MCP officiel en stdio (`npx -y @brave/brave-search-mcp-server`) ou en Docker, avec `BRAVE_API_KEY` obligatoire. Outil `brave_web_search` avec filtre `freshness` (pd/pw/pm/py), `goggles` (re-ranking personnalisé) et pagination. — [brave/brave-search-mcp-server](https://github.com/brave/brave-search-mcp-server)
- Tarifs : Search à 5 $/1k requêtes **avec 5 $ de crédits gratuits chaque mois** (50 req/s). Answers à 4 $/1k + 5 $/M tokens. ZDR en Enterprise. Brave revendique un index indépendant de plus de 40 milliards de pages, « not a scraper » de Google ou Bing. — [brave.com/search/api](https://brave.com/search/api/)
- Dépôt : 1 479 ★, push le 2026-09-30. Registre : `io.github.brave/brave-search-mcp-server`.

**Firecrawl**

- **Palier hébergé sans clé** : `https://mcp.firecrawl.dev/v2/mcp` expose `firecrawl_scrape`, `firecrawl_search` et `firecrawl_parse`, avec limitation de débit. OAuth via `https://mcp.firecrawl.dev/v2/mcp-oauth`. Un endpoint « search-only » (`/v2/mcp-search`) propose 8 outils dont `firecrawl_developer_search` et `firecrawl_research_*`. Le profil complet compte 26 outils. — [firecrawl/firecrawl-mcp-server](https://github.com/firecrawl/firecrawl-mcp-server)
- Consigne explicite du README : « Never put an API key in the server URL. Never put an API key in an agent chat. » — [README](https://github.com/firecrawl/firecrawl-mcp-server)
- Tarifs : gratuit à 1 000 crédits par mois. Hobby à 16 $/mois pour 5 000 crédits, Standard à 83 $/mois pour 100 000. Scrape = 1 crédit par page, Search = 2 crédits pour 10 résultats. ZDR en Enterprise seulement. — [firecrawl.dev/pricing](https://www.firecrawl.dev/pricing)
- Plugin `firecrawl` (dépôt `firecrawl/firecrawl-claude-plugin`) dans le marketplace officiel. Dépôt MCP : 7 538 ★, push le 2026-10-01. Registre : `io.github.firecrawl/firecrawl-mcp-server`.

**Jina AI**

- `claude mcp add -s user --transport http jina https://mcp.jina.ai/v1 --header "Authorization: Bearer ${JINA_API_KEY}"`. La clé est optionnelle pour `read_url` (page ou PDF vers markdown), `capture_screenshot_url`, `guess_datetime_url` et `primer`. Elle est requise pour `search_web`, `search_arxiv`, `search_ssrn`, `extract_pdf` et le reranking. Les clés gratuites s'obtiennent sur jina.ai. Filtrage possible par `?include_tags=search,read`. — [jina-ai/MCP](https://github.com/jina-ai/MCP)
- Dépôt : 869 ★, push le 2026-09-18. **Absent du registre MCP officiel** : seul un tiers, `io.github.pipeworx-io/jina-reader`, y figure. — [registre](https://registry.modelcontextprotocol.io/v0/servers?search=jina)

**Kagi**

- Serveur hébergé `https://mcp.kagi.com/mcp`, sans OAuth pour l'instant : `claude mcp add kagi https://mcp.kagi.com/mcp --transport http --header "Authorization: Bearer $(read -sp 'API key: ' k; echo $k)" --scope user`. Outils : `kagi_search_fetch` et `kagi_extract`. FastGPT et le summarizer ont été retirés. — [kagisearch/kagimcp](https://github.com/kagisearch/kagimcp)
- Tarifs : Search API à 12 $/1k, Extract à 4 $/1k pages, facturation à l'usage. Aucun palier gratuit API trouvé. — [kagi.com/api/pricing](https://kagi.com/api/pricing)
- Dépôt : 527 ★, push le 2026-07-07 (moins actif que les autres). Absent du registre MCP officiel.

**SearXNG (auto-hébergé)**

- `ihor-sokoliuk/mcp-searxng` (communautaire, 1 269 ★, push le 2026-09-29) : seule option « free / no API key » et auto-hébergeable du comparatif publié dans son README. Il faut opérer ou choisir soi-même une instance SearXNG (`searxng/searxng`, 37 861 ★). — [mcp-searxng](https://github.com/ihor-sokoliuk/mcp-searxng)

**Benchmarks de qualité (à lire avec prudence)**

- AIMultiple : 100 requêtes réelles, environ 4 000 résultats jugés par un LLM, dont 10 % vérifiés à la main, instantané de décembre 2025. Agent score : Brave 14,89, Firecrawl 14,58, Exa 14,39, Parallel Pro 14,21, Tavily 13,67, Perplexity 12,96, SerpAPI 12,28. Les auteurs écrivent que « Top 4 APIs perform equally well » (pas de différence significative). Latence : Brave 669 ms, Tavily 998 ms, Exa ~1,2 s, Perplexity plus de 11 s. Conflit d'intérêts déclaré : SerpApi est abonné à leur service. — [aimultiple.com/agentic-search](https://aimultiple.com/agentic-search)
- D'autres comparatifs (Parallel.ai, éditeur concurrent, et openbenchmarks.com, d'origine non vérifiée) donnent des classements différents selon le mode : Perplexity en tête sur des tâches multi-hop limitées aux snippets, Exa sur la recherche factuelle à requête unique. — [parallel.ai](https://parallel.ai/articles/best-ai-search-for-agents), [openbenchmarks.com](https://openbenchmarks.com/web-search)

### Inferences

- **Classement pour Cordeau (recherche technique en ingénierie, budget de dev solo)** :
  1. **Exa** : plugin officiel, sans clé au départ, 10 $ gratuits par mois, recherche sémantique bonne pour trouver des articles d'ingénierie, plus des « research skills » fournies.
  2. **Firecrawl keyless** : lecture des pages JS-lourdes et des docs que WebFetch rend mal.
  3. **Brave** : index indépendant, donc une diversité de sources par rapport à Exa, avec 5 $ gratuits par mois. Il faut une clé et un `npx` local.
  4. **Tavily** : 1 000 crédits par mois, résultats corrects, plugin officiel.
  5. **Jina** : `read_url` sans clé, utile pour les PDF et la détection de date de publication (vérifier la fraîcheur d'un article).
  6. **Perplexity** (`perplexity_research`) : à réserver aux synthèses ponctuelles payantes. Pas de palier gratuit, et la synthèse est faite par un LLM tiers, ce qui ajoute une couche à vérifier.
  7. **Kagi** : qualité réputée mais le plus cher (12 $/1k), sans palier gratuit.
  8. **SearXNG** : seulement si la souveraineté et la confidentialité priment sur la qualité.
- Le WebSearch natif de Claude Code est « US-only ». Un second moteur (Exa ou Brave) donne une diversité de sources utile pour recouper.
- Les benchmarks ne départagent pas clairement les quatre premiers. Le choix doit se faire sur le coût, la friction d'installation et la confiance, pas sur la « qualité ».

### Gaps

- Aucun benchmark trouvé spécifique à la **recherche technique ou de code** (docs de crates, issues GitHub, RFC). Les benchmarks portent sur des requêtes généralistes ou IA.
- Politiques de rétention des requêtes sur les offres non-Enterprise (Exa, Brave, Tavily, Firecrawl) : non vérifiées. Seule la ZDR Enterprise est documentée.
- Disponibilité générale de l'API Kagi (ouverte ou sur invitation) : non précisée par les pages consultées.

## 2. Outils de connaissance code et bibliothèques (Context7, DeepWiki, docs.rs/crates.io MCP, GitHub MCP, Sourcegraph, grep.app, GitMCP, lib.rs)

### Takeaway

Pour Rust, le trio le plus fiable est **Context7** (couverture Rust réelle vérifiée, free tier de 1 000 appels par mois, auth à finaliser), **DeepWiki MCP** (gratuit, sans auth, questions sur n'importe quel dépôt GitHub public) et **grep.app MCP** (recherche de code sur plus d'un million de dépôts, gratuit). Les MCP « docs.rs / crates.io » sont **tous communautaires**, avec peu d'étoiles, et l'un se déclare « entirely vibe coded ». Mieux vaut interroger directement les API publiques (crates.io, docs.rs, deps.dev) avec `curl`/WebFetch ou via un petit skill maison. Le GitHub MCP officiel est redondant avec `gh` CLI, déjà authentifié (et le plugin GitHub est en échec de connexion dans cette session).

### Cited Findings

**Context7 (Upstash)**

- Serveur distant `https://mcp.context7.com/mcp`, clé « recommended but not required », passée en `Authorization: Bearer`. Outils : `resolve-library-id` et `query-docs`. CLI : `npx ctx7 setup`, `ctx7 library <name> <query>`, `ctx7 docs <libraryId> <query>`. Le backend, le parseur et le crawler sont privés. — [upstash/context7](https://github.com/upstash/context7)
- Plans : gratuit à 1 000 appels par mois (+20 appels bonus par jour une fois bloqué), sans dépôts privés. Pro à 10 $/siège/mois. Côté vie privée : « Only the documentation lookup query (...) and library name are sent to our servers — not your code, conversation history, or sensitive data. » — [context7.com/plans](https://context7.com/plans)
- Plugin `context7` présent dans le marketplace officiel Anthropic (`external_plugins/context7`, « Connects to Context7's hosted remote MCP server »). — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)
- **Couverture Rust sondée le 2026-10-01** (API publique `context7.com/api/v1/search`, sans clé, HTTP 200) :
  - axum : `/websites/rs_axum`, trustScore 9.7, 7 092 snippets, mis à jour le 2026-08-12 ;
  - sqlx : `/websites/rs_sqlx`, 14 268 snippets, 2026-09-11 ;
  - tokio : `/websites/rs_tokio`, 2026-08-26 ;
  - utoipa : `/websites/rs_utoipa`, 2026-09-19 ;
  - diesel : `/diesel-rs/diesel`, 2026-09-27 ;
  - leptos : `/websites/rs_leptos`, 121 054 snippets ;
  - tauri : `/websites/tauri_app` ;
  - loco : `/loco-rs/loco`, 2026-08-22 ;
  - **sea-orm : premier résultat hors sujet** (`/shanbay/sea`, 2025-05-05, trustScore 6.6). — [API Context7](https://context7.com/api/v1/search?query=axum)
- Dépôt : 62 586 ★, push le 2026-10-01. Registre : `io.github.upstash/context7`, avec au moins un fork tiers au nom trompeur (`ai.smithery/renCosta2025-context7fork`). — [registre](https://registry.modelcontextprotocol.io/v0/servers?search=context7)

**DeepWiki MCP (Cognition / Devin)**

- Endpoint `https://mcp.deepwiki.com/mcp` (Streamable HTTP ; le SSE `/sse` est en dépréciation). Service gratuit, **sans authentification pour les dépôts publics**. Les dépôts privés exigent un compte Devin et le Devin MCP. Outils : `read_wiki_structure`, `read_wiki_contents` et `ask_question`. — [docs.devin.ai DeepWiki MCP](https://docs.devin.ai/work-with-devin/deepwiki-mcp)
- Commande Claude Code documentée : `claude mcp add -s user -t http deepwiki https://mcp.deepwiki.com/mcp`. — [docs.devin.ai](https://docs.devin.ai/work-with-devin/deepwiki-mcp)
- Absent du registre MCP officiel (aucun résultat pour « deepwiki »). — [registre](https://registry.modelcontextprotocol.io/v0/servers?search=deepwiki)

**grep.app MCP (Vercel)**

- Serveur officiel Vercel, recherche par motifs et regex dans plus d'un million de dépôts GitHub publics, filtres langage / dépôt / chemin. Installation : `claude mcp add --transport http grep https://mcp.grep.app`. — [Vercel blog](https://vercel.com/blog/grep-a-million-github-repositories-via-mcp), [PulseMCP](https://www.pulsemcp.com/servers/grep-app)

**Sourcegraph MCP**

- Le serveur MCP officiel expose la recherche par mots-clés, regex et sémantique, la navigation SCIP (go-to-definition, find-references), la recherche de commits et de diffs, Deep Search et « Code Finder ». — [sourcegraph.com/mcp](https://sourcegraph.com/mcp)
- OAuth avec Dynamic Client Registration depuis le 2026-01-12. L'URL prend la forme `https://<instance>/.api/mcp`. — [Sourcegraph changelog](https://sourcegraph.com/changelog/mcp-dcr)
- Le plugin `sourcegraph` du marketplace officiel pointe vers **`sourcegraph-community/sourcegraph-claudecode-plugin`**, une organisation « community » et non `sourcegraph`. — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)

**GitHub MCP officiel**

- Distant : `claude mcp add --transport http github https://api.githubcopilot.com/mcp -H "Authorization: Bearer YOUR_GITHUB_PAT"`. Local en Docker : `claude mcp add github -e GITHUB_PERSONAL_ACCESS_TOKEN=... -- docker run -i --rm -e GITHUB_PERSONAL_ACCESS_TOKEN ghcr.io/github/github-mcp-server`. Toolsets configurables. — [github/github-mcp-server install-claude.md](https://github.com/github/github-mcp-server/blob/main/docs/installation-guides/install-claude.md)
- Dépôt : 33 314 ★, push le 2026-10-01. Registre : `io.github.github/github-mcp-server`.

**GitMCP**

- `https://gitmcp.io/{owner}/{repo}` transforme n'importe quel dépôt GitHub en serveur MCP de doc (`llms.txt`, README, recherche de code). Le README se dit « free, open-source » et « doesn't collect personal information or store queries ». — [idosal/git-mcp](https://github.com/idosal/git-mcp)
- Dépôt : 8 446 ★ mais **dernier push le 2026-05-08** (moins de 5 mois).

**MCP Rust docs.rs / crates.io (tous communautaires)**

| Dépôt                                                                             | ★   | Dernier push | Remarque                                                                                                                                                                                                                                                       |
| --------------------------------------------------------------------------------- | --- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [snowmead/rust-docs-mcp](https://github.com/snowmead/rust-docs-mcp)               | 135 | 2026-09-21   | Cache local de crates (crates.io, GitHub ou local), analyse des sources, arbres de dépendances. README : « This is an entirely vibe coded project and was created as an experiment to see if LLMs can build a fully functional tool without human oversight. » |
| [Govcraft/rust-docs-mcp-server](https://github.com/Govcraft/rust-docs-mcp-server) | 297 | 2025-11-24   | Nécessite **une clé OpenAI** pour les embeddings et les résumés.                                                                                                                                                                                               |
| [joshrotenberg/cratesio-mcp](https://github.com/joshrotenberg/cratesio-mcp)       | 17  | 2026-08-05   | —                                                                                                                                                                                                                                                              |
| [mmgeorge/docs-mcp](https://github.com/mmgeorge/docs-mcp)                         | 4   | 2026-09-27   | Utilise « the crates.io API and docs.rs published rustdoc JSON ».                                                                                                                                                                                              |
| [pato/crates-mcp](https://github.com/pato/crates-mcp)                             | 14  | 2025-06-28   | Inactif.                                                                                                                                                                                                                                                       |

- Le registre MCP officiel liste aussi des serveurs crates tiers (`io.github.mrfentmen/crates-io-mcp`, `io.github.pipeworx-io/crates`). — [registre](https://registry.modelcontextprotocol.io/v0/servers?search=crates)
- Plugin `rust-analyzer-lsp` (« Rust language server for code intelligence ») présent dans le marketplace officiel. Il sert à analyser du code local, pas à faire de la recherche. — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)

**lib.rs**

- Classement par « overall quality score » qui combine : téléchargements (« only as a small fraction of the score », filtrés du spam), dépendances inverses directes et indirectes, présence et qualité de la doc, des exemples et du README, et « reputation of maintainers (...) page-rank-like based on co-owning relationships ». Le score s'inspire de la RFC 1824, de SourceRank et de la QualityIndex de CocoaPods. — [lib.rs/about](https://lib.rs/about)

### Inferences

- **Context7** (après auth OAuth du connecteur claude.ai ou de la clé gratuite) est l'outil de premier recours pour « comment utiliser la crate X à la version Y ». Pour les crates de niche ou mal indexées (cas sea-orm), basculer sur **DeepWiki** (question en langage naturel sur le dépôt) puis sur **docs.rs** (WebFetch direct).
- **DeepWiki** est le meilleur outil pour évaluer l'architecture d'un framework candidat (Loco, Axum, SeaORM) sans cloner. Ses réponses sont générées par un LLM : les vérifier contre le code via grep.app ou `gh`.
- Inutile d'installer le GitHub MCP pour la recherche : `gh api` / `gh search code` couvrent le besoin avec moins de surface d'attaque et pas de PAT de plus. Le plugin GitHub est d'ailleurs déjà en échec de connexion dans cette session.
- Pour les MCP Rust communautaires, leur fiabilité (faible adoption, « vibe coded », dépendance OpenAI) ne justifie pas l'exécution de code tiers en local. Un skill maison qui appelle `index.crates.io` (sparse index, sans limite de débit), `crates.io/api/v1` et `api.deps.dev` coûte peu à écrire et reste auditable.
- Sourcegraph n'a d'intérêt que si l'on a accès à une instance. Pour du code public, grep.app suffit.

### Gaps

- Je n'ai pas pu vérifier la couverture DeepWiki des dépôts Rust : les requêtes `curl` vers deepwiki.com ont renvoyé HTTP 429 (rate limit).
- Sourcegraph MCP sur sourcegraph.com (code public, gratuit) ou seulement sur une instance Enterprise : non clarifié par les pages consultées.
- Politique de confidentialité de DeepWiki (journalisation des questions) : non vérifiée.
- Requête Context7 pour « seaorm » / « SeaQL » non testée : le trou de couverture sea-orm peut venir du nommage.

## 3. Outils de recherche académique (arXiv, Semantic Scholar, successeur de Papers with Code, Connected Papers, Elicit, Consensus)

### Takeaway

Pour des preuves d'ingénierie logicielle (cs.SE, cs.PL), le duo **arxiv-mcp-server** (communautaire mais très actif, avec citation graph via Semantic Scholar) + **API Semantic Scholar** (gratuite) est le plus utile. **paper-search-mcp** élargit à OpenAlex, CORE et DOAJ. Consensus a un MCP officiel mais il est orienté études empiriques peer-reviewed (biais biomédical). Papers with Code est fermé depuis juillet 2025. Pour Elicit et Connected Papers, aucun MCP officiel n'a été trouvé.

### Cited Findings

- **arxiv-mcp-server** (blazickjp) : `claude mcp add --transport stdio --scope user arxiv -- uvx arxiv-mcp-server`. Outils : `search_papers` (catégorie, date, tri), `get_paper_latex`, `citation_graph` (références et citants via l'API Semantic Scholar, cache disque). La variable `SEMANTIC_SCHOLAR_API_KEY` est optionnelle (« Free Semantic Scholar API key »). Packagé aussi en plugin Claude Code. — [blazickjp/arxiv-mcp-server](https://github.com/blazickjp/arxiv-mcp-server)
- Dépôt : 3 185 ★, push le 2026-09-30. Registre : `io.github.blazickjp/arxiv-mcp-server`. — [registre](https://registry.modelcontextprotocol.io/v0/servers?search=arxiv)
- **paper-search-mcp** (openags, 2 728 ★, push le 2026-10-01) : stratégie « free-first ». Sources : arXiv, Semantic Scholar, OpenAlex (clé gratuite qui multiplie le budget quotidien par 10), CORE, DOAJ, Zenodo, IEEE (clé requise). Il existe un mode « Claude Code Skill » en CLI. Le README avertit de ne pas exposer le backend non authentifié sur Internet. — [openags/paper-search-mcp](https://github.com/openags/paper-search-mcp)
- **Semantic Scholar API** : accès public sans clé, partagé à « 1000 requests per second shared among all unauthenticated users » et throttlé en période chargée. Avec clé gratuite (demande par formulaire), « 1 RPS on all endpoints » au départ. Services : Academic Graph, Recommendations, Datasets. Corpus : 214 M d'articles, 2,49 Md de citations. — [semanticscholar.org/product/api](https://www.semanticscholar.org/product/api)
- MCP Semantic Scholar communautaires : tous à moins de 170 ★ (par exemple `zongmin-yu/semantic-scholar-fastmcp-mcp-server`, 165 ★, push 2026-03). — [GitHub](https://github.com/zongmin-yu/semantic-scholar-fastmcp-mcp-server)
- **Jina MCP** inclut `search_arxiv` et `search_ssrn` (clé requise). — [jina-ai/MCP](https://github.com/jina-ai/MCP)
- **Consensus** : MCP officiel hébergé `https://mcp.consensus.app/mcp`, OAuth, un seul outil `search` sur plus de 220 M d'articles peer-reviewed. Filtres : type d'étude, quartile SJR, taille d'échantillon, « medical mode ». — [docs.consensus.app](https://docs.consensus.app/docs/mcp), [Consensus-NLP/consensus-mcp](https://github.com/Consensus-NLP/consensus-mcp)
- **Papers with Code** : fermé le 24 juillet 2025, avec redirection vers Hugging Face Trending Papers. Les leaderboards SOTA par tâche n'ont pas été repris intégralement. — [Coursera](https://www.coursera.org/articles/papers-with-code), [HF blog](https://huggingface.co/blog/AdinaY/a-guide-to-hugging-faces-papers-page)
- **Elicit** : pas de dépôt `elicit/elicit-mcp` (HTTP 404 sur `gh api`). Aucun MCP officiel trouvé.

### Inferences

- Pour la migration Rust, la littérature académique pèse moins que les retours d'expérience d'ingénierie (blogs d'équipes, RFC, post-mortems). Exa (recherche sémantique) et le blog Rust ou This Week in Rust sont plus rentables qu'un MCP académique. arxiv-mcp-server reste utile pour des questions comme « productivité ou sûreté mémoire de Rust, études empiriques » ou « LLM et génération de code Rust ».
- Consensus est peu pertinent pour l'ingénierie logicielle (orientation santé et sciences sociales).

### Gaps

- Connected Papers : je n'ai pas cherché d'API ni de MCP. Aucune donnée.
- Elicit : pas de MCP officiel trouvé. Existence d'une API publique non vérifiée.
- Qualité comparée des MCP académiques sur des requêtes cs.SE : aucune évaluation trouvée.

## 4. Signaux de santé de l'écosystème Rust (choisir une crate)

### Takeaway

Les signaux les plus fiables et automatisables sans MCP sont des **API publiques gratuites** :

- crates.io : `recent_downloads` sur 90 jours, owners, reverse deps (1 req/s, User-Agent obligatoire) ;
- deps.dev : sans auth, Cargo supporté, Scorecard intégré ;
- OpenSSF Scorecard API ;
- OSV (qui agrège RustSec) ;
- le score qualité de lib.rs ;
- les audits cargo-vet de Google, Mozilla, Bytecode Alliance et d'autres, à vérifier sur le registre.

Le score Scorecard d'une crate de référence comme axum (5,5/10) montre qu'il ne faut **jamais** l'utiliser seul comme seuil.

### Cited Findings

**crates.io**

- Politique d'accès : passer de préférence par le **sparse index** `index.crates.io` (« No rate limits are required »), les **dumps DB quotidiens** `https://static.crates.io/db-dump.tar.gz` (« updated every 24 hours ») et les **flux RSS** (`static.crates.io/rss/crates.xml`, `/rss/updates.xml`, `/rss/crates/<nom>.xml`). L'API n'arrive qu'en dernier recours, avec « A maximum of 1 request per second » et un « user-agent header that identifies your application ». OpenAPI expérimental sur `crates.io/api/openapi.json`. — [crates.io data-access (source)](https://github.com/rust-lang/crates.io/blob/main/svelte/src/routes/data-access/%2Bpage.svelte)
- `recent_downloads` = « The total number of downloads for this crate in the last 90 days ». Endpoints utiles : `/crates/{name}/owners`, `/reverse_dependencies`, `/{version}/downloads`. — [crates.io openapi.json](https://crates.io/api/openapi.json)
- Sonde axum (2026-10-01) : 496,9 M de téléchargements, dont 125,0 M sur 90 jours. max_stable 0.8.9, mise à jour le 2026-04-14, création le 2021-07-22. Owners : 2 utilisateurs (carllerche, davidpdrsn) + 2 équipes GitHub (`tokio-rs:core`, `tokio-rs:axum-release`). 9 172 dépendances inverses. — [crates.io API](https://crates.io/api/v1/crates/axum)

**lib.rs et blessed.rs**

- lib.rs affiche pour axum « 42,616,606 downloads per month » et « Used in 12,664 crates », un chiffre **différent** des 9 172 reverse deps de crates.io (méthodes de comptage différentes, sans doute des dépendances indirectes). — [lib.rs/crates/axum](https://lib.rs/crates/axum)
- blessed.rs : guide communautaire curaté des crates recommandées (`nicoburns/blessed-rs`, 1 613 ★, dernier commit le 2026-08-03 : « Replase paste with pastey »). La curation est maintenue. — [blessed.rs](https://blessed.rs/crates), [GitHub](https://github.com/nicoburns/blessed-rs)

**deps.dev (Google)**

- API v3 sans authentification, base `https://api.deps.dev/`, écosystème **Cargo supporté**. Endpoints : GetPackage, GetVersion (licences et advisories), `:dependencies` (graphe résolu, Cargo inclus), GetProject (« An OpenSSF Scorecard for the project, if one is available »), GetAdvisory (OSV), Query. Mise en cache explicitement autorisée. Dataset BigQuery disponible. — [docs.deps.dev/api/v3](https://docs.deps.dev/api/v3/)
- Sonde : `/v3/systems/cargo/packages/axum` renvoie 94 versions (défaut 0.8.9). `/v3/projects/github.com%2Ftokio-rs%2Faxum` renvoie 27 308 ★, 78 issues ouvertes, licence MIT, Scorecard 5,5 au 2026-08-24. — [deps.dev API](https://api.deps.dev/v3/systems/cargo/packages/axum)

**OpenSSF Scorecard**

- API `https://api.securityscorecards.dev/projects/github.com/<org>/<repo>`. Pour axum (2026-09-28) : score 5,5, avec Maintained 10, Code-Review 10, Dangerous-Workflow 10, License 10, Pinned-Dependencies 1, Token-Permissions 0, Security-Policy 0, Fuzzing 0, SAST 0, CII-Best-Practices 0. — [Scorecard API](https://api.securityscorecards.dev/projects/github.com/tokio-rs/axum)
- `ossf/scorecard` : 5 732 ★, push le 2026-10-01.

**Vulnérabilités (RustSec et OSV)**

- La base `rustsec/advisory-db` contient 946 répertoires de crates (sondé le 2026-10-01), avec un flux RSS `rustsec.org/feed.xml` (HTTP 200). — [rustsec/advisory-db](https://github.com/rustsec/advisory-db), [rustsec.org](https://rustsec.org/feed.xml)
- L'API OSV accepte l'écosystème `crates.io` : `POST https://api.osv.dev/v1/query` avec `{"package":{"name":"hyper","ecosystem":"crates.io"}}` renvoie 14 vulnérabilités historiques (identifiants GHSA). — [api.osv.dev](https://api.osv.dev/v1/query)
- `EmbarkStudios/cargo-deny` (2 436 ★, push le 2026-10-01) pour les contrôles locaux (advisories, licences, sources). `google/osv-scanner` : 11 131 ★, actif. — [cargo-deny](https://github.com/EmbarkStudios/cargo-deny), [osv-scanner](https://github.com/google/osv-scanner)

**Audits humains (cargo-vet et cargo-crev)**

- Le `registry.toml` de cargo-vet référence des sets d'audits importables : actix, ariel-os, **bytecode-alliance** (wasmtime), embark-studios, fermyon, **google**, isrg, **mozilla**, zcash. — [mozilla/cargo-vet registry.toml](https://github.com/mozilla/cargo-vet/blob/main/registry.toml)
- Activité : dernière release de l'outil cargo-vet v0.10.0 (2024-10-03), push du dépôt le 2026-04-19. Les sets d'audits restent actifs : `mozilla/supply-chain` (push le 2026-09-09), `google/rust-crate-audits` (push le 2026-09-17). — [mozilla/cargo-vet](https://github.com/mozilla/cargo-vet), [google/rust-crate-audits](https://github.com/google/rust-crate-audits)
- cargo-crev : 2 337 ★, derniers commits en juin-juillet 2026, à faible cadence (« uglify code », « JSON verify »). — [crev-dev/cargo-crev](https://github.com/crev-dev/cargo-crev)

### Inferences

- **Grille de décision proposée** pour une crate candidate, automatisable par un skill de type `curl` + `gh`, sans MCP :
  1. **Adoption** : `recent_downloads` (90 j) et reverse deps (crates.io), « Used in » (lib.rs), présence sur blessed.rs.
  2. **Maintenance** : date de dernière version (crates.io `updated_at`), cadence de commits et de releases, issues ouvertes (`gh api`), Scorecard « Maintained ».
  3. **Bus factor** : nombre d'owners crates.io. Une **équipe GitHub owner** (comme `tokio-rs:*`) vaut mieux qu'un seul individu. Nombre de contributeurs actifs sur 12 mois via `gh api repos/X/stats/contributors`.
  4. **Sécurité** : advisories OSV/RustSec (historique et réactivité), Scorecard (en signal faible), présence dans les audits cargo-vet de Google, Mozilla ou Bytecode Alliance (signal fort mais couverture partielle).
  5. **Doc** : docs.rs, couverture Context7 et DeepWiki (un proxy de l'« agent-friendliness », pertinent puisque Claude est le principal développeur).
- Le critère 5 est spécifique à Cordeau : une crate bien indexée par Context7 sera mieux utilisée par Claude. C'est un argument réel dans le choix (par exemple sqlx et diesel bien couverts, sea-orm moins dans la sonde).
- Pour la veille continue : les flux RSS crates.io par crate (`/rss/crates/<nom>.xml`) pour les dépendances clés, RustSec, This Week in Rust et le blog Rust. Les quatre URL répondent en HTTP 200 (sondé), ce qui permet une routine hebdomadaire.

### Gaps

- Disponibilité d'une API lib.rs : rien trouvé. lib.rs semble consultable uniquement en HTML.
- Nombre réel d'utilisateurs actifs de cargo-crev et volume de revues : non mesuré.
- Couverture des sets cargo-vet sur les crates candidates de Cordeau (axum, sqlx, sea-orm, etc.) : non vérifiée. Il faudrait parser les `audits.toml`.
- Support explicite de `Cargo.lock` par osv-scanner : non vérifié dans cette session.

## 5. Fonctionnalités Claude Code utiles pour la recherche longue et la veille

### Takeaway

Claude Code couvre nativement la recherche longue :

- **subagents** : contexte isolé, synthèse renvoyée ;
- **dynamic workflows** : beaucoup de subagents avec résultats recoupés, explicitement cités pour la « cross-checked research » ;
- **agent view** : sessions en arrière-plan ;
- **`/loop`** : polling en session, expiration à 7 jours ;
- **Routines** : cloud, cron d'1 h minimum, déclencheurs API et GitHub, machine éteinte.

Pour la veille hebdomadaire, une **Routine** est l'outil adapté. Côté skills, les sources fiables sont le marketplace officiel Anthropic (plugins Exa, Tavily, Firecrawl et Context7, qui embarquent des skills de recherche) et le skill `deep-research` déjà présent. Les skills de recherche « communautaires » trouvés sur GitHub ont une adoption quasi nulle.

### Cited Findings

- Cinq modes de parallélisme :

  | Mode                         | Statut                             | Usage                                                                |
  | ---------------------------- | ---------------------------------- | -------------------------------------------------------------------- |
  | subagents                    | —                                  | « side task would flood your main conversation with search results » |
  | agent view (`claude agents`) | research preview                   | sessions en arrière-plan                                             |
  | agent teams                  | expérimental, désactivé par défaut | équipe coordonnée par un lead                                        |
  | dynamic workflows            | —                                  | « a codebase-wide audit (...), cross-checked research »              |
  | projects                     | bêta publique Pro/Max              | travail long sur plusieurs jours                                     |

  La doc ajoute que « Running several sessions or subagents at once multiplies token usage ». — [code.claude.com/docs/en/agents](https://code.claude.com/docs/en/agents)

- Planification :

  |                    | Routines (cloud)                       | Desktop scheduled tasks   | `/loop`                                         |
  | ------------------ | -------------------------------------- | ------------------------- | ----------------------------------------------- |
  | Intervalle minimum | 1 h                                    | —                         | 1 min                                           |
  | Persistance        | sans machine allumée                   | —                         | session uniquement                              |
  | Fichiers           | sans fichiers locaux (« fresh clone ») | accès aux fichiers locaux | —                                               |
  | Limites            | —                                      | —                         | 7 jours d'expiration, 50 tâches max par session |

  — [code.claude.com/docs/en/scheduled-tasks](https://code.claude.com/docs/en/scheduled-tasks)

- `/loop` sans intervalle laisse Claude choisir un délai entre 1 min et 1 h. Un `loop.md` (`.claude/loop.md` ou `~/.claude/loop.md`) remplace le prompt par défaut. La doc suggère l'outil Monitor comme alternative plus économe en tokens au polling. — [scheduled-tasks](https://code.claude.com/docs/en/scheduled-tasks)
- Routines : « a prompt, one or more repositories, and a set of connectors », exécutées sur l'infra Anthropic. Déclencheurs : planifié, API (POST avec bearer) ou événements GitHub. Création via claude.ai/code/routines, Desktop ou `/schedule`. Plans Pro, Max, Team et Enterprise. Statut « research preview ». — [code.claude.com/docs/en/routines](https://code.claude.com/docs/en/routines)
- Sorties MCP : avertissement à 10 000 tokens, limite par défaut à 25 000 tokens. Au-delà, le résultat est sauvegardé dans un fichier. Configurable via `MAX_MCP_OUTPUT_TOKENS`. Le tool search (chargement différé des outils MCP) est activé par défaut. — [code.claude.com/docs/en/mcp](https://code.claude.com/docs/en/mcp)
- Le marketplace officiel `anthropics/claude-plugins-official` (315 plugins, push le 2026-10-01) inclut des plugins de recherche :
  - exa (« MCP tools and research skills ») ;
  - tavily ;
  - firecrawl ;
  - context7 ;
  - github ;
  - sourcegraph (organisation community) ;
  - youdotcom-agent-skills (« research with citations ») ;
  - brightdata-plugin ;
  - playwright ;
  - rust-analyzer-lsp.
    — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)
- `anthropics/skills` (179 315 ★) ne contient **aucun** skill de recherche web. Liste : academy-guide, algorithmic-art, brand-guidelines, canvas-design, claude-api, discernment-nudge, doc-coauthoring, docx, frontend-design, internal-comms, mcp-builder, pdf, pptx, skill-creator, slack-gif-creator, theme-factory, web-artifacts-builder, webapp-testing, xlsx. — [anthropics/skills](https://github.com/anthropics/skills)
- Recherche GitHub « deep research skill claude » : seul résultat pertinent, `azagreev/DResearch-Skill`, à 0 ★. — [GitHub search](https://github.com/azagreev/DResearch-Skill)
- `obra/superpowers` (293 918 ★, push le 2026-09-27) fournit déjà `dispatching-parallel-agents`, utilisé par Cordeau. — [obra/superpowers](https://github.com/obra/superpowers)

### Inferences

- **Recette de veille Rust recommandée** : une Routine hebdomadaire (cloud, sans fichiers locaux, donc sans risque pour le repo) qui lit les flux RSS (This Week in Rust, blog Rust, RustSec, crates.io par crate clé), interroge deps.dev, Scorecard et OSV pour les crates de la stack, puis publie un digest (issue GitHub ou page Notion). Les connecteurs se configurent par routine.
- Pour une étude ponctuelle profonde (comme cette étude Rust), le skill `deep-research` existant + subagents en parallèle + Exa/DeepWiki/Context7 suffit. Les dynamic workflows valent l'essai pour le recoupement automatique des sources.
- Fixer `MAX_MCP_OUTPUT_TOKENS` et restreindre les outils (`?tools=` chez Exa, `include_tags` chez Jina, toolsets chez GitHub) limite la consommation de contexte.

### Gaps

- Quotas et coûts exacts des Routines (nombre d'exécutions incluses par plan) : non vérifiés.
- Je n'ai pas trouvé de skill communautaire de « veille technologique » ou de « crate evaluation » ayant une adoption significative.

## 6. Confiance et sécurité (MCP officiels vs communautaires, prompt injection)

### Takeaway

Anthropic **n'audite aucun serveur MCP**. Il y a eu des incidents réels :

- **injection via issues GitHub** sur le GitHub MCP officiel (Invariant Labs, mai 2025) ;
- **premier serveur MCP malveillant** publié sur npm (postmark-mcp, septembre 2025).

Règle pour Cordeau : privilégier les **serveurs distants hébergés par l'éditeur** (aucun code tiers exécuté localement) et présents sous le namespace de l'éditeur dans le registre officiel, avec des scopes et tokens minimaux, et traiter tout contenu web comme non fiable.

### Cited Findings

- « Verify you trust each server before connecting it. Servers that fetch external content can expose you to prompt injection risk. » — [code.claude.com/docs/en/mcp](https://code.claude.com/docs/en/mcp)
- « Anthropic reviews connectors against its listing criteria before adding them to the Anthropic Directory, but does not security-audit or manage any MCP server. » Recommandation : « either writing your own MCP servers or using MCP servers from providers that you trust ». — [code.claude.com/docs/en/security](https://code.claude.com/docs/en/security)
- Protections de Claude Code :
  - « Isolated context windows: Web fetch uses a separate context window to avoid injecting potentially malicious prompts » ;
  - vérification de confiance pour les nouveaux serveurs MCP, désactivée avec `-p` en non-interactif ;
  - `curl` et `wget` non auto-approuvés ;
  - sandbox réseau et fichiers disponible.
    — [security](https://code.claude.com/docs/en/security)
- **GitHub MCP « Toxic Agent Flow »** (Invariant Labs, mai 2025) : une issue malveillante dans un dépôt public pousse l'agent à lire des dépôts privés et à les divulguer dans une PR publique. La cause est un token unique avec accès multi-dépôts, pas un bug de code. — [Docker blog](https://www.docker.com/blog/mcp-horror-stories-github-prompt-injection/), [securityonline.info](https://securityonline.info/toxic-agent-flow-github-mcp-vulnerability-exposes-private-repositories/)
- **postmark-mcp** : paquet npm imitant le serveur officiel Postmark. À partir de la v1.0.16 (17 septembre 2025), il ajoutait un BCC vers un domaine de l'attaquant ; 1 643 téléchargements. Décrit comme le « first-ever instance of a malicious MCP server spotted in the wild ». — [The Hacker News](https://thehackernews.com/2025/09/first-malicious-mcp-server-found.html), [The Register](https://www.theregister.com/2025/09/29/postmark_mcp_server_code_hijacked/)
- Registre MCP officiel :
  - **présents sous le namespace de l'éditeur** : `ai.exa/exa`, `ai.perplexity/mcp-server`, `io.github.perplexityai/mcp-server`, `io.github.tavily-ai/tavily-mcp`, `io.github.firecrawl/firecrawl-mcp-server`, `io.github.brave/brave-search-mcp-server`, `io.github.github/github-mcp-server`, `io.github.upstash/context7`, `io.github.sourcegraph/mcp`, `io.github.blazickjp/arxiv-mcp-server` ;
  - **absents** : Kagi, Jina (seulement un tiers `pipeworx-io/jina-reader`), DeepWiki ;
  - **sosies tiers** : par exemple `com.mcparmory/perplexity`, `com.mcparmory/firecrawl`, `ai.smithery/renCosta2025-context7fork`, `io.github.Ansarii/firecrawl-stealth-mcp`.
    — [registry.modelcontextprotocol.io](https://registry.modelcontextprotocol.io/v0/servers?search=perplexity)
- Firecrawl recommande de ne jamais mettre de clé API dans l'URL ni dans le chat. Exa et Tavily acceptent pourtant la clé en paramètre d'URL (`?exaApiKey=`, `?tavilyApiKey=`). — [Firecrawl README](https://github.com/firecrawl/firecrawl-mcp-server), [Exa README](https://github.com/exa-labs/exa-mcp-server), [Tavily README](https://github.com/tavily-ai/tavily-mcp)
- Dans le marketplace officiel d'Anthropic, les plugins pointent parfois vers des dépôts non officiels de l'éditeur (par exemple sourcegraph vers `sourcegraph-community/...`). — [claude-plugins-official](https://github.com/anthropics/claude-plugins-official)

### Inferences

- **Classement global de confiance et d'utilité pour Cordeau** :

  | Tier                                                         | Outils                                                                                                                                                                             | Pourquoi                                         |
  | ------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
  | 1 (à activer maintenant, distant, éditeur officiel, gratuit) | **Context7** (finaliser l'auth du connecteur existant), **DeepWiki MCP**, **grep.app MCP**, **Exa** (plugin officiel)                                                              | —                                                |
  | 1 (sans MCP)                                                 | API publiques **crates.io / deps.dev / Scorecard / OSV** via `curl`, et `gh` CLI                                                                                                   | zéro dépendance tierce                           |
  | 2 (au besoin)                                                | **Firecrawl** keyless, **Brave** (5 $/mois gratuits, mais `npx` local), **Tavily**, **Jina** `read_url`, **arxiv-mcp-server** (`uvx` local, communautaire mais populaire et actif) | —                                                |
  | 3 (payant ou niche)                                          | **Perplexity** (pas de free tier), **Kagi** (12 $/1k), **Consensus** (biais santé), **Sourcegraph** (instance requise)                                                             | —                                                |
  | À éviter                                                     | MCP Rust communautaires à faible adoption (exécution locale de code non audité), forks et sosies Smithery/MCPArmory, GitHub MCP avec PAT large                                     | redondant avec `gh` et vecteur d'injection connu |

- **Mesures concrètes** :
  - distant HTTP plutôt que `npx`/`uvx` (pas d'exécution de code tiers local, pas de risque de typosquat npm) ;
  - auth en en-tête ou OAuth plutôt que clé dans l'URL ;
  - scope `user` pour les outils de recherche, pas `project`, pour ne pas committer de config dans `.mcp.json` ;
  - aucun PAT GitHub à portée privée dans un agent qui lit des issues publiques ;
  - ne jamais envoyer de données clients (RGPD : adresses, noms d'artisans) dans des requêtes de recherche vers des API US sans ZDR, puisque la ZDR est réservée à l'Enterprise chez Exa, Brave et Firecrawl.
- Les contenus récupérés (pages web, issues, réponses DeepWiki ou Perplexity) sont des **données, pas des instructions**, et les synthèses LLM tierces (DeepWiki, Perplexity research, Exa Agent) doivent être recoupées avec une source primaire avant de figurer dans un ADR.

### Gaps

- Mécanisme exact de vérification des namespaces du registre MCP (`io.github.<org>` lié à l'auth GitHub, domaines `ai.exa` via DNS) : non vérifié dans cette session. L'inférence repose sur la cohérence des noms observés.
- Aucun audit de sécurité indépendant trouvé pour les MCP hébergés Exa, Tavily, Firecrawl, Context7 ou DeepWiki.
