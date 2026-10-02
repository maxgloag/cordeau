# Outillage, harness et pratiques de code pour le développement agentique en Rust (robustesse maximale)

> Notes de recherche, 2026-10-01. Activité des dépôts relevée via `gh api repos/<owner>/<repo>` le 2026-10-01 (champ `pushed_at`, étoiles). Les références marquées « (référence canonique, non re-fetchée) » sont des docs officielles stables citées sans relecture pendant cette session. Les priorités utilisent trois niveaux : **must-have**, **nice-to-have** et **overkill**, pour un dev solo sur un backend SaaS métier (pas une lib publiée, pas du système bas niveau).

## Q1 — Harness Claude Code pour Rust : LSP, Serena, MCP docs/crates, skills/CLAUDE.md, hooks, guides Anthropic

### Takeaway

Le socle **must-have** est petit et officiel. D'abord le plugin officiel `rust-analyzer-lsp` de Claude Code : diagnostics automatiques après chaque édition et outil `LSP` de navigation. Ensuite, des hooks déterministes (rustfmt en PostToolUse, plus un gate clippy + tests en Stop hook) et un CLAUDE.md court qui délègue les règles aux lints. L'écosystème MCP « docs Rust » est fragmenté, jeune et souvent vibe-codé : aucun serveur n'est indispensable, car le code source exact des dépendances est déjà sur disque et rust-analyzer y navigue. Les gros packs de skills communautaires (265 règles) vont contre la recommandation Anthropic de garder le contexte léger.

### Cited Findings

**Code intelligence native dans Claude Code (LSP)**

- Anthropic publie un plugin officiel `rust-analyzer-lsp` dans le marketplace `claude-plugins-official`. Il s'installe avec `/plugin install rust-analyzer-lsp@claude-plugins-official` et requiert le binaire `rust-analyzer` sur le PATH (recommandé : `rustup component add rust-analyzer`). — [Claude Code docs: Code intelligence plugins](https://code.claude.com/docs/en/plugins/code-intelligence) ; [plugin README](https://github.com/anthropics/claude-plugins-official/tree/main/plugins/rust-analyzer-lsp)
- Le plugin apporte deux choses. Des « Diagnostics after edits » : « each time Claude edits or writes a file the server handles, Claude gets the errors and warnings the server reports. It sees a type error, missing import, or syntax error it introduced without running a compiler. » Et un outil `LSP` en lecture seule pour la navigation par symbole. — [Claude Code docs](https://code.claude.com/docs/en/plugins/code-intelligence)
- Limites documentées : pas de serveur LSP dans les sessions cloud (« Claude Code doesn't start plugin language servers »). La page troubleshooting mentionne aussi une « High memory use » pendant l'indexation et des « False positive diagnostics in a monorepo ». — [Claude Code docs](https://code.claude.com/docs/en/plugins/code-intelligence)
- Le guide best practices recommande explicitement : « If you work with a typed language, install a code intelligence plugin to give Claude precise symbol navigation and automatic error detection after edits. » — [Claude Code best practices](https://code.claude.com/docs/en/best-practices)
- `anthropics/claude-plugins-official` est actif (push 2026-10-01, ~37,3k ★). `rust-lang/rust-analyzer` est actif (push 2026-10-01, ~16,9k ★). — gh api, [repo plugins](https://github.com/anthropics/claude-plugins-official), [rust-analyzer](https://github.com/rust-lang/rust-analyzer)

**Serena (déjà utilisé par Cordeau)**

- Serena (`oraios/serena`, push 2026-09-30, ~29,9k ★, release v1.7.0 du 2026-08-09) supporte Rust parmi « 40+ programming languages » via son abstraction LSP. La page README ne nomme pas explicitement rust-analyzer. — [oraios/serena](https://github.com/oraios/serena)
- Le README de Serena note que dans un harness comme Claude Code, ses outils fichiers/shell redondants « are typically disabled by default, since the surrounding harness already provides overlapping file, search, and shell capabilities ». — [oraios/serena](https://github.com/oraios/serena)

**MCP « docs Rust / crates / cargo » (statut vérifié 2026-10-01)**

| Serveur                                                                           | Dernier push | ★      | Ce qu'il fait                                                                                                                                                                                                                                                                                                                                         | Verdict                                          |
| --------------------------------------------------------------------------------- | ------------ | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| [snowmead/rust-docs-mcp](https://github.com/snowmead/rust-docs-mcp)               | 2026-09-21   | 135    | Génère le rustdoc JSON localement, met en cache les sources de crates (`~/.rust-docs-mcp/cache/`) et expose ~15 outils (`search_items`, `get_item_docs`, `get_item_source`, `get_dependencies`, `structure`…). Requiert **nightly** (`nightly-2026-05-22`). Disclaimer : « This is an entirely vibe coded project and was created as an experiment… » | nice-to-have, expérimental                       |
| [Govcraft/rust-docs-mcp-server](https://github.com/Govcraft/rust-docs-mcp-server) | 2025-11-24   | 297    | Docs de crates + embeddings/LLM                                                                                                                                                                                                                                                                                                                       | quasi abandonné (~10 mois sans push)             |
| [vexxvakan/mcp-docsrs](https://github.com/vexxvakan/mcp-docsrs)                   | 2026-10-01   | 13     | Docs docs.rs « using the rustdoc JSON API » (description [mcpservers.org](https://mcpservers.org/servers/vexxvakan/mcp-docsrs))                                                                                                                                                                                                                       | jeune, faible adoption                           |
| [nuskey8/docs-rs-mcp](https://github.com/nuskey8/docs-rs-mcp)                     | 2025-07-31   | 37     | Recherche crates.io + docs.rs                                                                                                                                                                                                                                                                                                                         | inactif                                          |
| [0xKoda/mcp-rust-docs](https://github.com/0xKoda/mcp-rust-docs)                   | 2025-04-01   | 47     | Docs de crates                                                                                                                                                                                                                                                                                                                                        | inactif                                          |
| [zeenix/rust-analyzer-mcp](https://github.com/zeenix/rust-analyzer-mcp)           | 2026-09-15   | 83     | rust-analyzer exposé en MCP                                                                                                                                                                                                                                                                                                                           | redondant avec le plugin LSP officiel            |
| [Vaiz/rust-mcp-server](https://github.com/Vaiz/rust-mcp-server)                   | 2026-09-06   | 37     | « MCP server for development in Rust » (wrapper cargo)                                                                                                                                                                                                                                                                                                | redondant avec Bash                              |
| [SignalWhisperer/cargo-mcp](https://github.com/SignalWhisperer/cargo-mcp)         | 2026-03-02   | 2      | Cargo MCP                                                                                                                                                                                                                                                                                                                                             | confidentiel                                     |
| [fedemagnani/crates.io-mcp](https://github.com/fedemagnani/crates.io-mcp)         | 2026-01-09   | 1      | API crates.io                                                                                                                                                                                                                                                                                                                                         | confidentiel                                     |
| [upstash/context7](https://github.com/upstash/context7)                           | 2026-10-01   | ~62,6k | Docs versionnées multi-langages (déjà dans le stack Cordeau)                                                                                                                                                                                                                                                                                          | à garder, couverture Rust non vérifiée (cf Gaps) |
| [arabold/docs-mcp-server](https://github.com/arabold/docs-mcp-server)             | 2026-09-28   | ~1,8k  | « Open-Source Alternative to Context7 » (indexation de docs arbitraires)                                                                                                                                                                                                                                                                              | nice-to-have si Context7 ne couvre pas un crate  |

- Source des données d'activité : `gh api` du 2026-10-01 (tableau ci-dessus). Liens repos dans le tableau.

**Skills / templates CLAUDE.md / AGENTS.md pour Rust**

- [actionbook/rust-skills](https://github.com/actionbook/rust-skills) (push 2026-08-23, ~1,5k ★) est un plugin Claude Code de **31 skills** en trois « couches cognitives » : mécanique du langage (ownership, erreurs, concurrence), choix de design et contraintes de domaine (Web, Cloud-Native, FinTech…). Il inclut hooks et auto-triggering, avec pour devise « Don't answer directly. Trace through cognitive layers first. » — [README](https://github.com/actionbook/rust-skills)
- [leonardomso/rust-skills](https://github.com/leonardomso/rust-skills) (push 2026-06-14, 554 ★) : « 265 rules across 26 categories that AI coding agents can use to write idiomatic, fast, and safe Rust ». — gh api
- [zircote/rust-lsp](https://github.com/zircote/rust-lsp) (push 2026-08-10, 12 ★) est un plugin Claude Code combinant rust-analyzer, clippy, rustfmt et cargo-audit, avec « 16 automated hooks ». Adoption marginale. — [repo](https://github.com/zircote/rust-lsp)
- Microsoft Pragmatic Rust Guidelines ([microsoft/rust-guidelines](https://github.com/microsoft/rust-guidelines), push 2026-09-30, 205 ★) ont une section dédiée à l'IA (détails en Q3). Des articles tiers affirment qu'il existe une version condensée destinée aux agents. — [Medium/Rustaceans](https://medium.com/rustaceans/implementing-microsofts-rust-guidelines-for-ai-coding-agents-bf985dd60fc6) ; la page AI officielle ne mentionne pas ce format ([MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/))
- Registre communautaire de référence : [hesreallyhim/awesome-claude-code](https://github.com/hesreallyhim/awesome-claude-code) (push 2026-10-01, ~54,9k ★).

**Guides Anthropic applicables**

- CLAUDE.md : « keep it short… For each line, ask: "Would removing this cause Claude to make mistakes?" If not, cut it. Bloated CLAUDE.md files cause Claude to ignore your actual instructions! » À exclure : « Standard language conventions Claude already knows », « Self-evident practices ». — [Claude Code best practices](https://code.claude.com/docs/en/best-practices)
- Hooks vs CLAUDE.md : « Unlike CLAUDE.md instructions which are advisory, hooks are deterministic and guarantee the action happens. » Et pour le CLAUDE.md trop long : « If Claude already does something correctly without the instruction, delete it or convert it to a hook. » — [best practices](https://code.claude.com/docs/en/best-practices)
- Vérification : « Give Claude a check it can run… Claude stops when the work looks done. Without a check it can run, "looks done" is the only signal available. » Les gates possibles sont une instruction en prompt, `/goal`, un **Stop hook** (« runs your check as a script and blocks the turn from ending until it passes ») ou un subagent vérificateur. — [best practices](https://code.claude.com/docs/en/best-practices)
- Revue adversariale : un subagent en contexte frais relit le diff. Il faut cependant lui dire de ne signaler que les écarts de correction, car « Chasing every finding leads to over-engineering: extra abstraction layers, defensive code, and tests for cases that can't happen. » — [best practices](https://code.claude.com/docs/en/best-practices)
- Sémantique des hooks :
  - PostToolUse avec exit code 2 : « Shows stderr to Claude; the tool already ran ». On peut aussi renvoyer un JSON `hookSpecificOutput.additionalContext`.
  - Stop avec exit code 2 : « Prevents Claude from stopping, continues the conversation ».
  - Un plafond existe sur le nombre de blocages consécutifs d'un Stop hook ([Stop input](https://code.claude.com/docs/en/hooks#stop)).
  - Source : [Claude Code hooks](https://code.claude.com/docs/en/hooks)
- « Effective harnesses for long-running agents » (2025-11-26) recommande quatre choses :
  - un agent initialiseur qui crée `init.sh`, un `claude-progress.txt` et un commit initial ;
  - une liste de features en **JSON** marquées passing/failing, avec « It is unacceptable to remove or edit tests because this could lead to missing or buggy functionality » ;
  - une progression **une feature à la fois** ;
  - des tests end-to-end « as a human user would ».

  L'article décrit aussi quatre modes d'échec : complétion prématurée, progrès non documenté, vérification incomplète, temps perdu à comprendre le setup. — [Anthropic Engineering](https://www.anthropic.com/engineering/effective-harnesses-for-long-running-agents)

- « Writing effective tools for agents » (2025-09-11) recommande de renvoyer « only high signal information » et de soigner l'efficacité en tokens (« pagination, range selection, filtering, and/or truncation »). Les messages d'erreur doivent « clearly communicate specific and actionable improvements ». — [Anthropic Engineering](https://www.anthropic.com/engineering/writing-tools-for-agents)
- Armin Ronacher (praticien, juin 2025) : « Tools need to be **fast**. The quicker they respond (and the less useless output they produce) the better. » Il note aussi : « In Rust, agents sometimes fail because they misunderstand `cargo test`'s invocation syntax. » — [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/)
- Exemples communautaires de hook : un PostToolUse qui lance `cargo check`/`cargo clippy` après édition pour un feedback immédiat. Source secondaire, faible autorité. — [The Prompt Shelf](https://thepromptshelf.dev/blog/claude-code-rust-projects-guide-2026/)

### Inferences

- **Pile harness recommandée (par valeur décroissante)**
  1. **must-have** : plugin `rust-analyzer-lsp` officiel. Il remplace une bonne part de ce que ferait un hook `cargo check` post-édition, car les diagnostics arrivent à chaque Edit/Write.
  2. **must-have** : Stop hook qui lance `cargo fmt --check && cargo clippy --workspace --all-targets -- -D warnings && cargo nextest run && cargo test --doc` et sort en exit 2 avec un résumé filtré. C'est l'équivalent déterministe de `superpowers:verification-before-completion`.
  3. **must-have** : PostToolUse léger (`rustfmt` sur le fichier édité, < 1 s), pas un `cargo clippy` complet à chaque édition, qui coûterait cher en latence et entrerait en contention de lock avec rust-analyzer (cf Q4).
  4. **nice-to-have** : Serena. Il fait doublon avec l'outil `LSP` natif pour la navigation, mais garde une valeur pour l'édition symbolique (`replace_symbol_body`, `rename_symbol`) et les memories. À réévaluer pour éviter deux instances rust-analyzer simultanées, coûteuses en RAM.
  5. **nice-to-have** : un MCP docs. Context7 est déjà en place ; `snowmead/rust-docs-mcp` est une option si les docs versionnées manquent. Mais Cargo vendorise déjà les sources exactes des dépendances (verrouillées par `Cargo.lock`) dans `~/.cargo/registry/src`, et l'outil LSP navigue dedans : c'est une source de vérité versionnée sans dépendance externe.
  6. **overkill** : wrappers MCP de cargo (Vaiz, SignalWhisperer…). Bash plus `cargo` fait la même chose avec moins de surface.
- **Principe directeur** : chaque règle CLAUDE.md exprimable en lint clippy, ban cargo-deny ou erreur de compilation doit migrer vers l'outil, conformément à « convert it to a hook ». Le CLAUDE.md Rust ne garde que ce que le compilateur ne peut pas dire : commandes du projet, frontières de crates, conventions métier (THESAURUS), workflow.
- Les packs de skills communautaires sont de bonnes **sources d'inspiration** pour rédiger 2-3 skills maison ciblés (erreurs, async, ownership), mais leur volume (31 skills, 265 règles) dilue le contexte. À ne pas installer tels quels.
- La sortie du Stop hook doit être filtrée (top N erreurs, `--message-format=short`), conformément aux principes « high signal » d'Anthropic. Compromis : le format court perd les `help:`/`note:` de rustc, qui sont justement très utiles aux agents. Mieux vaut tronquer en nombre d'erreurs qu'en format.

### Gaps

- Couverture réelle de Context7 pour les crates Rust (axum, sqlx, tokio…) non vérifiée : le MCP Context7 n'était pas authentifié dans cette session.
- Non vérifié : si le `.lsp.json` des plugins Claude Code accepte des `initializationOptions` qui permettraient de configurer rust-analyzer (target dir séparé, `check.command = clippy`).
- Aucun retour d'expérience chiffré trouvé sur Serena + rust-analyzer combiné au plugin LSP officiel (RAM, conflits).
- Le plugin `lspmux-rust` (auteur `sunshowers`) est listé sur [agentskill.sh](https://agentskill.sh/plugins/sunshowers/lspmux-rust) mais le repo GitHub correspondant n'a pas été trouvé (404). Son principe, mutualiser une instance rust-analyzer entre éditeur et agent, serait pertinent ; existence et statut non confirmés.

## Q2 — Échelle de vérification : quels outils valent le coup pour un backend SaaS ?

### Takeaway

La boucle « compilateur + clippy durci + nextest + cargo-deny » est le cœur **must-have**. Les lints `restriction` anti-panic (`unwrap_used`, `expect_used`, `panic`, `indexing_slicing`, `todo`…) sont la leçon directe de l'outage Cloudflare. proptest et insta sont fortement recommandés pour le domaine et l'API ; cargo-mutants en `--in-diff` est le meilleur antidote aux tests tautologiques d'agents. Miri, Kani et la vérification formelle (Verus, Creusot, Prusti, Flux, Aeneas) sont **overkill** pour un SaaS sans `unsafe`, malgré des progrès spectaculaires des LLM en synthèse de preuves.

### Cited Findings

**Cloudflare, 18 novembre 2025 : le cas d'école `.unwrap()`**

- Déroulé : une mise à jour de permissions ClickHouse a doublé le fichier de features Bot Management. Le fichier a dépassé la limite interne de 200 features, pour laquelle la mémoire est préallouée, et le proxy FL2 a paniqué : « thread fl2_worker_thread panicked: called Result::unwrap() on an Err value ». Durée : ~6 h (11:20 à 17:06 UTC), « Cloudflare's worst outage since 2019 ». — [Cloudflare blog](https://blog.cloudflare.com/18-november-2025-outage/)
- Le code fautif `features.append_with_names(&self.config.feature_names).unwrap()` se trouvait dans une fonction `fetch_features` qui **retournait déjà un `Result`**. L'erreur était donc modélisée, et `?` aurait suffi. — [Splinter analysis / HN](https://www.splinter.com.au/2025/12/05/cloudflare-rust-analysis/) ; [HN](https://news.ycombinator.com/item?id=45973709)
- Remédiations annoncées : traiter les fichiers de config internes « with same scrutiny as user input », kill switches, revue des modes d'échec des modules proxy. — [Cloudflare blog](https://blog.cloudflare.com/18-november-2025-outage/)
- La communauté a débattu d'un `unwrap_used = "deny"` en `[workspace.lints.clippy]`, avec `expect()` justifié. Le sujet de la dépréciation d'`unwrap` a été rouvert sur internals.rust-lang.org. — [Lobsters](https://lobste.rs/s/nac5wa/cloudflare_outage_on_november_18_2025) ; [Rust internals](https://internals.rust-lang.org/t/re-opening-deprecating-option-unwrap-and-result-unwrap/23734?page=2) ; [schneems: disallowed via clippy.toml](https://www.schneems.com/2025/11/19/find-accidental-code-usage-with-a-custom-clippytoml/)

**Recommandation officielle Microsoft (M-STATIC-VERIFICATION)**

- Microsoft recommande :
  - des lints compilateur ciblés (`missing_debug_implementations`, `redundant_imports`, `unsafe_op_in_unsafe_fn`, `unused_lifetimes`…) ;
  - **toutes** les catégories clippy majeures : cargo, complexity, correctness, **pedantic**, perf, style, suspicious ;
  - des lints restriction sélectionnés (`allow_attributes_without_reason`, `undocumented_unsafe_blocks`…) ;
  - rustfmt, cargo-audit, cargo-hack, cargo-udeps et miri pour l'unsafe ;
  - **M-LINT-OVERRIDE-EXPECT** : `#[expect]` plutôt que `#[allow]` pour les dérogations.

  Source : [MS Pragmatic Rust Guidelines, Universal](https://microsoft.github.io/rust-guidelines/guidelines/universal/)

- Les lints `unwrap_used`, `expect_used`, `panic`, `indexing_slicing`, `arithmetic_side_effects`, `todo`, `unimplemented`, `wildcard_enum_match_arm` et `clone_on_ref_ptr` appartiennent au groupe `restriction` de clippy. Ce groupe est opt-in et ne s'active pas en bloc. — [Clippy lint list](https://rust-lang.github.io/rust-clippy/master/index.html) (référence canonique, non re-fetchée)
- Les lints se configurent dans `Cargo.toml` via `[workspace.lints]` puis `[lints] workspace = true` par crate. — [Cargo reference: lints](https://doc.rust-lang.org/cargo/reference/manifest.html#the-lints-section) (référence canonique, non re-fetchée)
- `clippy.toml` permet `allow-unwrap-in-tests` / `allow-expect-in-tests` (garder `unwrap` dans les tests) et `disallowed-methods` / `disallowed-types` (bannir des API précises). — [Clippy configuration](https://doc.rust-lang.org/clippy/lint_configuration.html) (référence canonique, non re-fetchée) ; [schneems](https://www.schneems.com/2025/11/19/find-accidental-code-usage-with-a-custom-clippytoml/)
- `overflow-checks` est désactivé par défaut en profil release, mais activable : `[profile.release] overflow-checks = true`. — [Cargo profiles](https://doc.rust-lang.org/cargo/reference/profiles.html#overflow-checks) (référence canonique, non re-fetchée)

**Catalogue outils (activité vérifiée le 2026-10-01)**

| Outil               | Repo / dernier push / ★                                                                                          | Rôle                                                                                                                                                                                                                                               | Priorité Cordeau                                           |
| ------------------- | ---------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| clippy              | [rust-lang/rust-clippy](https://github.com/rust-lang/rust-clippy) 2026-10-01, 13,5k                              | Lints                                                                                                                                                                                                                                              | **must-have**                                              |
| rustfmt             | toolchain                                                                                                        | Format                                                                                                                                                                                                                                             | **must-have**                                              |
| cargo-nextest       | [nextest-rs/nextest](https://github.com/nextest-rs/nextest) 2026-10-01, 3,3k (0.9.146 du 2026-09-21)             | Runner de tests, process-per-test ; « up to 60% faster » ([corrode](https://corrode.dev/blog/tips-for-faster-rust-compile-times/)) ; **ne lance pas les doctests**, il faut `cargo test --doc` à part ([nexte.st](https://nexte.st/docs/running/)) | **must-have**                                              |
| cargo-deny          | [EmbarkStudios/cargo-deny](https://github.com/EmbarkStudios/cargo-deny) 2026-10-01, 2,4k                         | Licences, advisories RustSec, bans (dont `wrappers`), sources                                                                                                                                                                                      | **must-have** (couvre l'essentiel de cargo-audit)          |
| cargo-audit         | [rustsec/rustsec](https://github.com/rustsec/rustsec) 2026-09-30, 2k                                             | Advisories RustSec                                                                                                                                                                                                                                 | redondant si cargo-deny                                    |
| cargo-machete       | [bnjbvr/cargo-machete](https://github.com/bnjbvr/cargo-machete) 2026-09-07, 1,4k                                 | Deps inutilisées (heuristique, stable, rapide)                                                                                                                                                                                                     | nice-to-have (CI)                                          |
| cargo-udeps         | [est31/cargo-udeps](https://github.com/est31/cargo-udeps) 2026-04-29, 2,1k                                       | Deps inutilisées (précis, nightly)                                                                                                                                                                                                                 | nice-to-have                                               |
| proptest            | [proptest-rs/proptest](https://github.com/proptest-rs/proptest) 2026-09-27, 2,2k                                 | Property testing « Hypothesis-like » avec shrinking                                                                                                                                                                                                | **must-have pour le domaine** (TVA, totaux devis, mesures) |
| quickcheck          | [BurntSushi/quickcheck](https://github.com/BurntSushi/quickcheck) 2026-04-03, 2,8k                               | Property testing plus simple                                                                                                                                                                                                                       | doublon de proptest                                        |
| bolero              | [camshaft/bolero](https://github.com/camshaft/bolero) 2026-09-30, 258                                            | « property testing and verification front-end » : un même harness pour fuzz, propriétés et Kani                                                                                                                                                    | nice-to-have                                               |
| insta               | [mitsuhiko/insta](https://github.com/mitsuhiko/insta) 2026-09-27, 3k                                             | Snapshots (JSON d'API, erreurs)                                                                                                                                                                                                                    | **must-have pour l'API**                                   |
| cargo-mutants       | [sourcefrog/cargo-mutants](https://github.com/sourcefrog/cargo-mutants) 2026-10-01, 1,3k (v27.1.0 du 2026-06-02) | Mutation testing ; `--in-diff` ne teste que les mutants qui recoupent le diff                                                                                                                                                                      | **nice-to-have+** (PR)                                     |
| cargo-llvm-cov      | [taiki-e/cargo-llvm-cov](https://github.com/taiki-e/cargo-llvm-cov) 2026-09-06, 1,5k                             | Couverture                                                                                                                                                                                                                                         | nice-to-have                                               |
| cargo-hack          | [taiki-e/cargo-hack](https://github.com/taiki-e/cargo-hack) 2026-10-01                                           | Combinaisons de features                                                                                                                                                                                                                           | overkill (app, pas lib)                                    |
| cargo-vet           | [mozilla/cargo-vet](https://github.com/mozilla/cargo-vet) 2026-04-19, 992                                        | Audits supply-chain                                                                                                                                                                                                                                | overkill solo (cargo-deny suffit)                          |
| cargo-semver-checks | [obi1kenobi/cargo-semver-checks](https://github.com/obi1kenobi/cargo-semver-checks) 2026-10-01, 1,7k             | SemVer d'API publiée                                                                                                                                                                                                                               | overkill (pas de crate publiée)                            |
| Miri                | [rust-lang/miri](https://github.com/rust-lang/miri) 2026-10-01, 6,6k                                             | UB dans `unsafe`                                                                                                                                                                                                                                   | overkill si `forbid(unsafe_code)`                          |
| Kani                | [model-checking/kani](https://github.com/model-checking/kani) 2026-10-01, 3,5k (0.68.0 du 2026-09-16)            | Model checker borné (AWS)                                                                                                                                                                                                                          | overkill (éventuellement pour 1-2 fonctions de calcul)     |
| Verus               | [verus-lang/verus](https://github.com/verus-lang/verus) 2026-10-01, 3,3k                                         | Vérification déductive (SMT)                                                                                                                                                                                                                       | overkill                                                   |
| AutoVerus           | [microsoft/verus-proof-synthesis](https://github.com/microsoft/verus-proof-synthesis) 2026-08-24, 117            | Synthèse de preuves Verus par LLM                                                                                                                                                                                                                  | overkill/recherche                                         |
| Creusot             | [creusot-rs/creusot](https://github.com/creusot-rs/creusot) 2026-10-01, 1,9k                                     | Preuve déductive (Why3)                                                                                                                                                                                                                            | overkill                                                   |
| Prusti              | [prusti/prusti](https://github.com/prusti/prusti) 2026-10-01, 1,8k (ex-viperproject/prusti-dev)                  | Vérificateur Viper                                                                                                                                                                                                                                 | overkill                                                   |
| Flux                | [flux-rs/flux](https://github.com/flux-rs/flux) 2026-10-01, 924                                                  | Types raffinés                                                                                                                                                                                                                                     | overkill (à surveiller)                                    |
| Aeneas              | [AeneasVerif/aeneas](https://github.com/AeneasVerif/aeneas) 2026-10-01, 1k                                       | Traduction Rust vers Lean                                                                                                                                                                                                                          | overkill                                                   |
| MIRAI               | [facebookexperimental/MIRAI](https://github.com/facebookexperimental/MIRAI) archivé (push 2024-08)               | Interprétation abstraite                                                                                                                                                                                                                           | mort                                                       |

**cargo-mutants `--in-diff`**

- « The `--in-diff DIFF_FILE` option tests only mutants that overlap with regions changed in the diff. » Usages recommandés : `git diff` avant soumission, ou diff PR contre la branche de base en CI.
- Deux limites : « a diff that only deletes or changes test code won't cause any mutants to run », et le mode reste « not a substitute for a full test run ».
- Source : [mutants.rs](https://mutants.rs/in-diff.html)

**LLM + vérification formelle (état de l'art)**

- AutoVerus est un réseau d'agents qui imite les trois phases d'un expert : génération, raffinement, debug guidé par les erreurs du vérificateur. Il prouve « more than 90% » des tâches de son benchmark, plus de la moitié en < 30 s ou 3 appels LLM (publié OOPSLA/PACMPL 2025). — [Microsoft Research](https://www.microsoft.com/en-us/research/publication/autoverus-automated-proof-generation-for-rust-code/) ; [ACM DL](https://dl.acm.org/doi/10.1145/3763174)
- AlphaVerus (CMU) génère code **et** preuves par auto-amélioration et surpasse AutoVerus de 10 % en annotation de preuves. — [AlphaVerus PDF](http://www.contrib.andrew.cmu.edu/~bparno/papers/alpha-verus.pdf) ; [OpenReview](https://openreview.net/pdf?id=AyjhgkQDeY)
- Travaux 2026 : ExVerus (réparation de preuves Verus par contre-exemples) et VCoT-Bench (raisonnement de type prouveur automatique sur Rust). — [ExVerus arXiv 2603.25810](https://arxiv.org/pdf/2603.25810) ; [VCoT-Bench arXiv 2603.18334](https://arxiv.org/pdf/2603.18334)
- « Harnessing Code Agents for Automatic Software Verification » (2026-07-07) confie des lemmes entiers à **Claude Code + Claude Opus 4.7** dans un harness de vérification. Résultats : 4 257 lemmes Iris, **217 lemmes de vérification de la std Rust**, et 318/318 sur reglang contre ~1/8 pour les systèmes antérieurs. Conclusion : imposer une stratégie de preuve fixe est « unnecessary and limiting ». — [arXiv 2607.06341](https://arxiv.org/abs/2607.06341)

### Inferences

- **Configuration de lints proposée** (à valider, non testée) :
  - `[workspace.lints.rust]` : `unsafe_code = "forbid"`, `missing_debug_implementations = "warn"`.
  - `[workspace.lints.clippy]` :
    - `pedantic = { level = "warn", priority = -1 }` ;
    - `unwrap_used`, `expect_used`, `panic`, `todo`, `unimplemented`, `dbg_macro` en `"deny"` ;
    - `indexing_slicing = "deny"` dans les crates domaine/application ;
    - `allow_attributes_without_reason = "deny"`, et `#[expect(..., reason = "...")]` comme seule échappatoire ;
    - `wildcard_enum_match_arm = "warn"`.
  - `clippy.toml` : `allow-unwrap-in-tests = true`, `allow-expect-in-tests = true`.
  - En CI : `-D warnings`.
  - L'**agent ne peut alors plus « gagner » en ajoutant un `unwrap()`** : la seule sortie est de propager l'erreur ou de justifier par écrit, et la justification se relit en review.
- `arithmetic_side_effects` est très bruyant sur du code généraliste. Le réserver au crate domaine monétaire (Devis/TVA), où l'arithmétique doit de toute façon passer par un type `Montant` (centimes entiers + `checked_*`, ou un décimal) et non par des `i64` nus. Activer en plus `overflow-checks = true` en release : un débordement devient une panique visible plutôt qu'un montant faux silencieux.
- **Ladder pratique pour Cordeau** :
  1. **Boucle interne agent** (secondes) : diagnostics LSP après chaque édition.
  2. **Gate de fin de tour**, Stop hook (dizaines de secondes) : fmt, clippy `-D warnings`, nextest, doctests.
  3. **Gate PR**, CI (minutes) : lui-même plus cargo-deny, cargo-machete, `cargo sqlx prepare --check` (cf Q3), cargo-mutants `--in-diff`, couverture.
  4. **Hebdo/nightly** : cargo-mutants complet et proptest avec plus de cas.
- **Pourquoi cargo-mutants est particulièrement pertinent avec un agent** : MS signale que les agents produisent des tests « that pass by construction » (M-TAUTOLOGICAL-TESTS, cf Q5). Un mutant survivant est une preuve objective qu'un test n'asserte rien d'utile, et il se mesure sans jugement humain. C'est l'équivalent Rust d'Infection en PHP.
- La vérification formelle progresse vite côté LLM : le résultat Claude Code + std Rust est notable. Mais elle exige d'écrire des spécifications dans un dialecte dédié et un toolchain spécifique, et elle cible du code algorithmique ou bas niveau. Pour un SaaS CRUD + calculs métier, proptest (et éventuellement Kani via bolero sur 1-2 fonctions pures de calcul) capture l'essentiel du ROI. À rouvrir en V2+ si un module devient critique (facturation légale ?).

### Gaps

- Pas de mesure publique trouvée du coût CI de cargo-mutants sur un workspace de 20-50k LOC.
- La couverture des lints `restriction` n'a pas été re-vérifiée lint par lint pour la version clippy de 2026-10 : des lints ont pu être renommés ou reclassés. À confirmer avec `cargo clippy --explain <lint>` au moment de l'implémentation.
- Aucune étude trouvée quantifiant la baisse de bugs en production liée à `unwrap_used = deny` (au-delà de l'anecdote Cloudflare).

## Q3 — Patterns de design qui aident les agents (et rendent les erreurs plus difficiles)

### Takeaway

Rust permet de transformer les invariants métier en erreurs de compilation, ce qui est précisément le signal pass/fail qu'Anthropic demande de fournir aux agents. Quatre leviers comptent :

- des newtypes validés au parsing (`nutype`), des enums exhaustifs **sans** wildcard ni `#[non_exhaustive]` interne, des erreurs `thiserror` typées ;
- `forbid(unsafe_code)` ;
- l'architecture hexagonale imposée physiquement par les frontières de crates et les bans cargo-deny `wrappers` (un « Deptrac gratuit ») ;
- le SQL vérifié à la compilation (SQLx).

Microsoft formalise des règles explicitement « AI-friendly ».

### Cited Findings

**Microsoft Pragmatic Rust Guidelines, section AI**

- **M-DESIGN-FOR-AI** : « Making APIs easier to use for humans also makes them easier to use by AI. » Recommandations : API idiomatiques, documentation complète, exemples, **types forts**, API testables, couverture de tests. Un article secondaire cite plusieurs formulations : « The more your APIs… look and feel like the majority of Rust code in the world, the better it is for AI », « Avoid primitive obsession by using strong types », « AI agents need to be able to iterate quickly to prove that the code they are writing… is working correctly ». — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/) ; formulations via [résumé de recherche](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- **M-SINGLE-ITEM-PATH** : un item public n'est accessible que par un seul chemin. Les agents « frequently violate this by re-exporting items under multiple paths during refactoring iterations ». — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- **M-NO-META-DESIGN-DOCUMENTATION** : la doc décrit l'état final, pas l'historique de conception (« Agents frequently produce sections that describe how a change was designed »). — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- **M-RUST-SHAPED** : lors d'un portage, le domaine se traduit, mais les patterns techniques doivent être idiomatiques Rust, sans copie 1-pour-1 des constructions d'un autre langage. — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- Côté Universal : M-SMALLER-CRATES (découper les crates quand les sous-modules sont indépendants), M-WEASEL-WORDS (éviter « Service », « Manager », « Factory »), M-PUBLIC-DEBUG, M-DOCUMENTED-MAGIC, M-LOG-STRUCTURED. — [MS guidelines Universal](https://microsoft.github.io/rust-guidelines/guidelines/universal/)

**Briques techniques (activité vérifiée)**

- [greyblake/nutype](https://github.com/greyblake/nutype) (push 2026-09-20, ~1,8k ★) : « Rust newtype with guarantees ». Ce sont des newtypes avec sanitize/validate à la construction, l'implémentation directe de « parse, don't validate ». — gh api ; principe : [Alexis King, Parse, don't validate](https://lexi-lambda.github.io/blog/2019/11/05/parse-don-t-validate/) (référence canonique, non re-fetchée)
- [dtolnay/thiserror](https://github.com/dtolnay/thiserror) (push 2026-09-23, ~5,6k ★) : « derive(Error) for struct and enum error types ». — gh api
- SQLx (« compile-time checked queries without a DSL », PostgreSQL/MySQL/SQLite) a migré de `launchbadge/sqlx` vers [transact-rs/sqlx](https://github.com/transact-rs/sqlx) (redirection GitHub constatée ; push 2026-09-14, ~17,5k ★). — gh api
- Typestate en Rust : encoder l'état dans le type pour rendre les transitions illégales non compilables. — [Cliffle, The Typestate Pattern in Rust](https://cliffle.com/blog/rust-typestate/) (référence canonique, non re-fetchée)
- `#[non_exhaustive]` n'a d'effet qu'**en dehors du crate qui définit le type** : il force un bras `_` chez les consommateurs. — [Rust Reference, type_system attributes](https://doc.rust-lang.org/reference/attributes/type_system.html) (référence canonique, non re-fetchée)
- cargo-deny `bans` permet `deny = [{ crate = "...", wrappers = ["this-can-use-it"] }]`. Seuls les crates listés peuvent dépendre directement du crate banni, et un `reason` s'affiche dans le diagnostic. — [cargo-deny bans config](https://embarkstudios.github.io/cargo-deny/checks/bans/cfg.html)
- Le Rust-SWE-bench (cf Q5) montre que 43,7 % des erreurs de compilation des agents viennent de la compréhension repo-wide (chemins de modules, scoping : E0433/E0432/E0425…) et 32,6 % de la conformité au système de types/traits (E0599/E0308/E0277). — [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1)

### Inferences

- **Hexagonale par crates** : un workspace Cargo du type `cordeau-domain` (pur, sans tokio/sqlx/axum), `cordeau-application` (ports = traits), `cordeau-infra-pg` (sqlx), `cordeau-http` (axum) et `cordeau-app` (bin, wiring). Cargo interdit les cycles entre crates, et le domaine ne peut pas importer ce qu'il ne déclare pas en dépendance. On y ajoute cargo-deny `wrappers` : `sqlx` n'est autorisé que dans `cordeau-infra-pg`, `axum` que dans `cordeau-http`. Résultat : l'équivalent de **Deptrac**, mais garanti par le compilateur et la CI, avec un message `reason` lisible par l'agent. Cette structure améliore aussi l'incrémental (Q4).
- **Exhaustivité comme to-do list de l'agent** : ajouter une variante à `StatutChantier` doit casser la compilation à chaque `match`. C'est un guide parfait pour un agent, à condition de ne pas avoir de bras `_ =>`. D'où trois règles : `wildcard_enum_match_arm` (au moins en warn), **pas** de `#[non_exhaustive]` sur les enums internes au workspace (il ne servirait qu'à forcer des wildcards entre crates), et `#[non_exhaustive]` réservé aux éventuelles API publiques.
- **Newtypes métier** systématiques pour `ChantierId`, `ClientId`, `Montant`, `TauxTva`, `Email`, `Siret`… Ils évitent l'inversion d'arguments (`fn f(client: ClientId, chantier: ChantierId)` au lieu de deux `Uuid`, une erreur classique d'agent sans signal) et concentrent la validation. Cela s'aligne avec THESAURUS.md : le nom canonique devient un type.
- **Erreurs** : `thiserror` dans domaine/application (enum par use case, exhaustif, mappable en HTTP), et éventuellement `anyhow` uniquement dans le bin et les tests. Un agent qui ajoute un cas d'erreur doit alors traiter son mapping HTTP sous peine d'erreur de compilation.
- **SQLx** : les requêtes `query!`/`query_as!` deviennent des erreurs de compilation si une colonne ou un type diverge du schéma. C'est un signal précieux pour l'agent, mais il exige soit une DB au compile-time, soit le cache offline (`cargo sqlx prepare`, `SQLX_OFFLINE`) avec vérification en CI. Détails de commande non re-vérifiés pour la version courante.
- **Portage depuis Symfony** : M-RUST-SHAPED est directement applicable à une migration PHP vers Rust. Il faut porter le **modèle de domaine** (Chantier/Lots/Tâches/Mesures, ADRs), pas les patterns techniques Symfony (services injectés, entités Doctrine mutables, `Manager`/`Factory`, cf M-WEASEL-WORDS).
- Garder le domaine **synchrone et pur**, avec l'async confiné aux adapters. Cela réduit la surface où l'agent peut produire les erreurs async les plus coûteuses (Q5).

### Gaps

- Pas de source primaire trouvée comparant empiriquement le taux de réussite des agents avec ou sans newtypes/typestate. L'argument repose sur le principe « give Claude a check it can run » et sur les catégories d'erreurs du Rust-SWE-bench, pas sur une expérience contrôlée.
- La version « condensée pour agents » des guidelines Microsoft, mentionnée par des articles tiers, n'a pas été localisée sur le site officiel.

## Q4 — Temps de compilation et vitesse de feedback

### Takeaway

La lenteur de l'incrémental est le point de douleur n°1 des développeurs Rust : 55 % attendent plus de 10 s par rebuild. Avec un agent qui itère des dizaines de fois par tâche, c'est le principal coût caché. Les leviers à fort ROI pour Cordeau, sur macOS en local et Linux en CI :

- `cargo check` / clippy plutôt que build ;
- un workspace découpé en crates ;
- `debug = "line-tables-only"` et `split-debuginfo = "unpacked"` (macOS) ;
- un **target dir séparé pour rust-analyzer** contre la contention de lock ;
- nextest ;
- lld (par défaut sur Linux x86_64 depuis 1.90) ou mold/wild sur Linux.

Cranelift et le front-end parallèle restent nightly (nice-to-have). Aucune source publique fiable ne donne de chiffres d'incrémental pour un workspace de 20-50k LOC.

### Cited Findings

- Résultats du sondage officiel 2025 (3 700+ réponses) :
  - « 55% of respondents have to wait more than ten seconds for a rebuild ».
  - « Waiting too long for an incremental rebuild after making a small source code change was by far the most common complaint ».
  - Causes citées : rebuilds inutiles de crates dépendants dans un workspace, « The linking phase is too slow », incrémental mal optimisé pour les derive macros.
  - « More than 35% users said that they consider the IDE and Cargo blocking one another to be a big problem » ; mitigation listée : un **target directory séparé pour Rust Analyzer**.
  - « Cargo check does not share the build cache with cargo build ».
  - « Almost 42% of respondents have not tried to use any mechanism for improving build performance ».

  Source : [Rust blog, survey 2025 results](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)

- Le même sondage indique que réduire la debuginfo à `line-tables-only` apporte 2-30 % de gain en cycles. Mitigations listées : LLD, réduire deps/features (« most effective »), découper les gros crates (« highly effective »), `hint-mostly-unused` (expérimental). — [Rust blog](https://blog.rust-lang.org/2025/09/10/rust-compiler-performance-survey-2025-results)
- Rust 1.90 (sept. 2025) utilise **LLD par défaut sur x86_64-unknown-linux-gnu** : jusqu'à 7x sur l'édition de liens, -40 % end-to-end sur ripgrep, ~20 % en debug. — [Phoronix](https://www.phoronix.com/news/Rust-1.90-LLD-Linking) ; [Phoronix release](https://www.phoronix.com/news/Rust-1.90-Released)
- Synthèse corrode.dev (mise à jour 2026-03-14) :
  - `cargo check` est 2-3x plus rapide que build ;
  - Cranelift produit les binaires debug 1,5-2x plus vite que LLVM ;
  - `-Z threads=8` (front-end parallèle nightly) donne « up to 50% speedup » ;
  - cargo-hakari réduit les builds consécutifs de « 50%+ in large workspaces with mixed feature sets » ;
  - `split-debuginfo = "unpacked"` donne « macOS: 70% faster incremental builds » ;
  - `debug = "line-tables-only"` ;
  - nextest est « up to 60% faster » ;
  - en CI : `CARGO_INCREMENTAL=0` et le cache `Swatinem/rust-cache` ;
  - côté macOS : `sudo spctl developer-mode enable-terminal` ;
  - profilage avec `cargo build --timings`, `cargo-llvm-lines`, `-Zmacro-stats`.

  Source : [corrode.dev](https://corrode.dev/blog/tips-for-faster-rust-compile-times/)

- Cranelift : rustup component nightly `rustc-codegen-cranelift-preview`, supporté sur **macOS aarch64**. Mais « Unwinding on panics is experimental and not supported on Windows and macOS; `-Cpanic=abort` is enabled by default ». Le SIMD n'est que partiellement implémenté. — [rustc_codegen_cranelift](https://github.com/rust-lang/rustc_codegen_cranelift)
- Linkers :
  - wild (déplacé vers [wild-linker/wild](https://github.com/wild-linker/wild), push 2026-10-01, 4k ★) : « A very fast linker for **Linux** » ;
  - mold ([rui314/mold](https://github.com/rui314/mold), push 2026-10-01, 17,3k ★) ;
  - corrode classe lld comme « Production-ready for Linux/macOS » et mold comme optimisé Linux.

  Source : gh api ; [corrode.dev](https://corrode.dev/blog/tips-for-faster-rust-compile-times/)

- Outils de boucle de fond :
  - [Canop/bacon](https://github.com/Canop/bacon) (« background code checker », push 2026-09-30, 3,4k ★) est actif ;
  - [watchexec/cargo-watch](https://github.com/watchexec/cargo-watch) est **archivé** (dernier push 2025-01-14) ;
  - [mozilla/sccache](https://github.com/mozilla/sccache) est actif (2026-09-29) : utile quand plusieurs projets partagent des dépendances ([corrode](https://corrode.dev/blog/tips-for-faster-rust-compile-times/)) ;
  - [guppy-rs/guppy](https://github.com/guppy-rs/guppy) (cargo-hakari) est actif (2026-10-01).

  Source : gh api

- Gros workspaces et rust-analyzer : avec 40-50 crates, rust-analyzer peut lancer ~200 process rustc en parallèle ([rust-analyzer #8631](https://github.com/rust-lang/rust-analyzer/issues/8631)). Le réglage `check.invocationStrategy` (`once` / `per_workspace`) existe ([rust-analyzer config](https://rust-analyzer.github.io/book/configuration.html)), ainsi que [cargo-subspace](https://github.com/ethowitz/cargo-subspace) pour l'indexation paresseuse.

### Inferences

- **Recommandations concrètes Cordeau (macOS M-series en local)**
  1. **must-have** : profil dev avec `debug = "line-tables-only"`, `split-debuginfo = "unpacked"`, et des dépendances compilées en `opt-level` modéré si besoin.
  2. **must-have** : rust-analyzer avec target dir séparé (option `rust-analyzer.cargo.targetDir`). Sans cela, le plugin LSP de Claude Code, le Stop hook cargo et un éventuel bacon se bloquent mutuellement sur `target/`. C'est précisément le problème que 35 % des sondés jugent majeur. Reste à vérifier comment passer cette option via le plugin (cf Gap Q1).
  3. **must-have** : workspace découpé selon l'hexagone (Q3). Toucher un adapter HTTP ne recompile pas le domaine, et les tests du domaine (sans DB) recompilent et tournent vite.
  4. **must-have** : nextest, plus `cargo test --doc` séparé.
  5. **nice-to-have** : Cranelift nightly pour le profil dev. Attention, sur macOS il active `panic=abort` par défaut. Inférence à vérifier : cela casserait les tests `#[should_panic]` et le mécanisme de proptest qui capture les panics. À cantonner à `cargo run` de dev, pas aux tests.
  6. **nice-to-have** : `-Zthreads=8` en nightly, sccache partagé si plusieurs worktrees.
  7. **overkill pour < 50k LOC** : cargo-hakari (gain annoncé surtout pour de grands workspaces à features hétérogènes).
- **Worktrees** (pratique Cordeau via `superpowers:using-git-worktrees`) : chaque worktree a son propre `target/`, donc un **build à froid par worktree**. Mutualiser via `CARGO_TARGET_DIR` partagé (au prix de la contention de lock) ou via sccache. Inférence, non mesurée.
- **CI Linux** : lld par défaut suffit, mold ou wild en bonus. `Swatinem/rust-cache` et `CARGO_INCREMENTAL=0` sont standards.
- Ordre de grandeur attendu (inférence, non sourcée) : sur 20-50k LOC bien découpés, un `cargo check` incrémental après une édition dans un crate feuille devrait rester dans la zone de quelques secondes. Un changement dans le crate domaine, dont tout dépend, déclenche le recheck de toute la chaîne. Les dérives viennent surtout des proc-macros (serde, sqlx, axum) et du link des binaires de test. Pour piloter avec des données réelles : `cargo build --timings` dès la première semaine.

### Gaps

- **Aucune source fiable** donnant des temps d'incrémental `cargo check` / clippy / nextest mesurés pour un workspace de 20-50k LOC (axum + sqlx + tokio). À mesurer dans un spike.
- Non vérifié : le linker par défaut sur macOS en 2026 (ld-prime d'Apple ?) et le gain réel de lld vs défaut sur Apple Silicon.
- Le statut de stabilisation de Cranelift et du front-end parallèle à l'automne 2026 n'a pas été confirmé (toujours documentés comme nightly).
- Option Cargo `build-dir` (séparer les artefacts intermédiaires) : statut de stabilisation non vérifié.

## Q5 — Modes d'échec connus des agents en Rust et règles de harness

### Takeaway

Les données empiriques (Rust-SWE-bench 2026) montrent que les agents échouent d'abord sur la **navigation repo-wide** (chemins de modules/imports, 43,7 % des erreurs de compilation) et la **conformité types/traits** (32,6 %), plus que sur le borrow checker. Celui-ci reste la source des « contournements » : `clone()`, `Arc<Mutex<>>`, `unwrap`, voire `unsafe`/`Box::leak`. Les benchmarks récents montrent que les modèles de pointe sont désormais compétitifs en Rust, avec une forte variance entre modèles. Chaque mode d'échec a un contre-poids outillable.

### Cited Findings

- **Rust-SWE-bench** (500 tâches, 34 repos ; arXiv 2602.22764) :
  - meilleur baseline OpenHands + Claude-Sonnet-3.7 : 21,2 % ;
  - erreurs de compilation : 43,7 % liées à la compréhension repo-wide (E0433, E0432, E0425, E0412, E0405) et 32,6 % à la conformité types/traits (E0599, E0308, E0277) ;
  - 44,5 % des tâches échouent dès la reproduction, et désactiver l'étape de reproduction cause « 42% drop in resolution rate » ;
  - RustForger (environnement de test isolé + traçage dynamique par proc-macros) monte à 28,6 % (+34,9 %), pour un coût inférieur de 21,3 %.

  Source : [arXiv 2602.22764](https://arxiv.org/html/2602.22764v1)

- **SWE-Bench ProMax** (arXiv 2608.09802, refactoring multilingue, d'après le résumé de recherche) : Claude Sonnet 4.6 atteint 63,6 % sur Rust et GPT-5.2 54,5 %, contre 18,2 % pour Kimi-K2.5. Les auteurs parlent d'une « surprising variance ». À l'inverse, Multi-SWE-bench (2025) montrait des taux bas sur Rust. — [arXiv 2608.09802](https://arxiv.org/pdf/2608.09802) ; [Multi-SWE-bench](https://arxiv.org/pdf/2504.02605). Chiffres issus de résumés, non relus dans le PDF.
- **RustAssistant** (Microsoft Research) : LLM + boucle compilateur pour corriger des erreurs de compilation Rust, avec un taux de correction rapporté de l'ordre de 74 %. La ventilation exacte par catégorie (borrow-check vs autres) n'a pas été vérifiée dans le PDF. — [arXiv 2308.05177](https://arxiv.org/pdf/2308.05177) ; [MSR PDF](https://www.microsoft.com/en-us/research/wp-content/uploads/2024/08/paper.pdf)
- Modes d'échec décrits par Microsoft pour les agents : re-exports multiples (M-SINGLE-ITEM-PATH), documentation de « méta-design » qui vieillit mal, **tests tautologiques** (« Agents produce tests that pass by construction and add noise rather than value »), et portages non « Rust-shaped ». — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- Pratiquants, source secondaire de faible autorité : trois motifs récurrents chez les agents sans garde-fous, à savoir `.unwrap()/.expect()` en code de lib, `clone()` pour satisfaire le borrow checker au lieu de restructurer l'ownership, et des locks tenus à travers des `.await`. Les agents « reach for unsafe, Box::leak, unsound lifetime annotations, or an Arc<Mutex<>> wrapper that papers over a borrow-checker complaint ». — [Medium, Hannecke](https://medium.com/@michael.hannecke/three-patterns-where-agent-generated-code-quietly-fails-1b9735493468) ; [agensi.io](https://www.agensi.io/learn/best-skills-rust-developers)
- Contre-point important : Armin Ronacher recommande **Go** plutôt que Rust pour un backend agentique (« if you can choose your language, I strongly recommend Go for new backend projects »). Il invoque la simplicité, des tests incrémentaux « straightforward », et le fait que les agents se trompent sur l'invocation de `cargo test`. — [lucumr.pocoo.org](https://lucumr.pocoo.org/2025/6/12/agentic-coding/)
- Guide pratique pour éviter de « combattre » le borrow checker (données possédées, IDs/indices plutôt que références croisées, etc.). — [qouteall, How to Avoid Fighting Rust Borrow Checker](https://qouteall.fun/qouteall-blog/2025/How%20to%20Avoid%20Fighting%20Rust%20Borrow%20Checker)

### Inferences

**Table « mode d'échec → contre-mesure » proposée pour Cordeau**

| Mode d'échec                                        | Contre-mesure déterministe                                                                                                           | Contre-mesure CLAUDE.md / skill                                              |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------- |
| `unwrap`/`expect`/`panic!`/`todo!` laissés          | clippy `unwrap_used`, `expect_used`, `panic`, `todo`, `unimplemented` en deny (tests exemptés)                                       | « Propager avec `?` ; justifier tout `#[expect]` »                           |
| Indexation qui panique (`v[i]`)                     | `indexing_slicing` (domaine)                                                                                                         | utiliser `.get()` et retourner une erreur typée                              |
| Clone-everywhere                                    | lints `clone_on_ref_ptr` (restriction), pedantic (`needless_pass_by_value`…) ; mutation testing et revue                             | règle courte : « emprunter par défaut ; un `clone()` doit être justifiable » |
| `Arc<Mutex<_>>` partout, lock à travers `.await`    | lint `await_holding_lock` ; domaine sync sans état partagé                                                                           | état partagé uniquement dans les adapters (pool DB, caches)                  |
| `unsafe` / `Box::leak` / `'static` forcé            | `unsafe_code = "forbid"` ; `disallowed-methods` pour `Box::leak`, `std::mem::forget`                                                 | —                                                                            |
| Lifetimes acrobatiques, génériques excessifs        | pedantic + revue ; préférence documentée « types possédés dans le domaine, `&str`/`&T` en paramètres »                               | règle « pas de paramètres de lifetime sur les types du domaine sans ADR »    |
| Imports et chemins faux (43,7 % des erreurs)        | plugin LSP (diagnostics à chaud, navigation) ; M-SINGLE-ITEM-PATH ; peu de re-exports                                                | carte des crates dans CLAUDE.md (5-6 lignes)                                 |
| Traits/types non conformes (32,6 %)                 | diagnostics LSP + compilation ; newtypes explicites                                                                                  | exemples canoniques (« suivre `ChantierRepository` »)                        |
| Tests tautologiques                                 | cargo-mutants `--in-diff` en CI                                                                                                      | M-TAUTOLOGICAL-TESTS ; TDD (red d'abord)                                     |
| Suppression ou affaiblissement de tests pour passer | Stop hook + review du diff de tests ; `allow_attributes_without_reason`                                                              | « It is unacceptable to remove or edit tests… » (Anthropic)                  |
| `#[allow(...)]` pour faire taire un lint            | `allow_attributes_without_reason`, `#[expect]` (M-LINT-OVERRIDE-EXPECT) ; grep CI des `expect(clippy::`                              | —                                                                            |
| Mauvaise invocation `cargo test`                    | commandes exactes dans CLAUDE.md (`cargo nextest run -p <crate> <filtre>`), éventuellement wrappées dans un `justfile`/script unique | —                                                                            |

- Le point fort de Rust pour l'agent est la qualité des messages d'erreur, avec suggestions `help:` (cf. le pattern « helpful error messages » d'Anthropic). Le point faible est la latence de la boucle (Q4). La configuration doit donc maximiser la densité de signal par itération (LSP à chaud) et minimiser les compilations complètes inutiles.
- Le résultat Rust-SWE-bench sur la **reproduction** (-42 % sans elle) confirme la discipline « red d'abord » déjà actée chez Cordeau (TDD, systematic-debugging). En Rust, le test qui reproduit est aussi celui qui fixe le comportement.

### Gaps

- Aucune étude contrôlée trouvée mesurant la fréquence de clone-everywhere ou d'`Arc<Mutex>` dans du code généré par Claude 2026, ni l'effet des lints sur ces fréquences.
- Les chiffres SWE-Bench ProMax et RustAssistant n'ont pas été vérifiés dans les PDF (résumés seulement).
- Pas de comparaison publique trouvée « même produit SaaS, agent en Rust vs PHP/TS », en vélocité ou en défauts.

## Q6 — Workflows spec/test-driven qui exploitent les types

### Takeaway

Le workflow le plus robuste combine trois choses : les idées d'Anthropic (spec écrite, critère de vérification exécutable, liste de features passing/failing, une feature à la fois, Stop hook comme gate, reviewer frais) et les forces de Rust (**types d'abord**, signatures qui compilent avec `todo!()`, puis tests red, puis implémentation). Les propriétés proptest servent de spécification exécutable du domaine, les doctests d'exemples vivants, et cargo-mutants mesure si les tests de l'agent spécifient réellement quelque chose.

### Cited Findings

- Spec : faire interviewer l'utilisateur par Claude puis écrire `SPEC.md`. Les meilleures specs « name the files and interfaces involved, state what is out of scope, and end with an end-to-end verification step ». On exécute ensuite dans une session fraîche. — [Claude Code best practices](https://code.claude.com/docs/en/best-practices)
- Writer/Reviewer et tests séparés : « have one Claude write tests, then another write code to pass them ». — [best practices](https://code.claude.com/docs/en/best-practices)
- Harness long : fichier JSON de features passing/failing, interdiction d'éditer ou supprimer les tests, progression feature par feature, fichier de progression + git pour la reprise de contexte. — [Anthropic, effective harnesses](https://www.anthropic.com/engineering/effective-harnesses-for-long-running-agents)
- M-DESIGN-FOR-AI demande « comprehensive examples » et des API testables. M-TAUTOLOGICAL-TESTS demande des tests qui vérifient un comportement significatif. — [MS guidelines AI](https://microsoft.github.io/rust-guidelines/guidelines/ai/)
- proptest est « Hypothesis-like property testing for Rust » (shrinking). bolero unifie fuzzing, propriétés et vérification (Kani) derrière un même harness. — [proptest](https://github.com/proptest-rs/proptest) ; [bolero](https://github.com/camshaft/bolero)
- nextest ne lance pas les doctests : il faut une étape `cargo test --doc`. — [nexte.st, running](https://nexte.st/docs/running/) ; [linera issue](https://github.com/linera-io/linera-protocol/issues/5983)
- cargo-mutants `--in-diff` cible les mutants du diff de PR. — [mutants.rs](https://mutants.rs/in-diff.html)

### Inferences

**Workflow « types-first » proposé pour une story Cordeau**, compatible avec `superpowers:test-driven-development`, `writing-plans` et `verification-before-completion` :

1. **Spec** : issue GitHub (le quoi) et plan story-level (le comment), comme aujourd'hui. Y ajouter la liste des **invariants** du domaine en langage naturel (« le total TTC d'un devis = somme des lignes HT × (1 + taux) arrondi au centime »).
2. **Types d'abord** : l'agent écrit newtypes, enums (états, erreurs) et signatures de ports/use cases avec des corps `todo!()`. Gate : `cargo check` passe. Le lint `todo` en deny garantit qu'aucun `todo!()` ne survit au Stop hook final. Revue humaine courte à ce stade : c'est là que se décide la modélisation, alignée THESAURUS et ADR.
3. **Red** : tests d'exemples (unitaires domaine) + **propriétés proptest** traduisant les invariants + snapshot insta pour la forme JSON de l'API. Gate : les tests compilent et **échouent**.
4. **Green** : implémentation. Boucle interne : diagnostics LSP. Gate de fin : Stop hook (fmt, clippy `-D warnings`, nextest, doctests).
5. **Refactor + revue** : subagent reviewer frais sur le diff contre le plan, puis cargo-mutants `--in-diff` en CI. Un mutant survivant devient un test manquant, ce qui répond aussi au protocole double-fix de Cordeau.

- **Doctests** sur les types publics du domaine : ce sont des exemples exécutables, utiles à l'agent suivant (M-DESIGN-FOR-AI) et impossibles à laisser périmer, puisqu'ils sont compilés. Le Stop hook et la CI doivent appeler `cargo test --doc` explicitement, sinon ils sont silencieusement ignorés avec nextest.
- **Snapshots insta** : à vérifier, le comportement exact de `INSTA_UPDATE` et le mode `--check` dans la version courante. Il faut s'assurer qu'en CI un snapshot divergent **échoue** au lieu d'être accepté, et interdire à l'agent `cargo insta accept` sans revue (règle de permission Claude Code ou hook PreToolUse).
- **Liste de features JSON** (pattern Anthropic) : pertinente pour les sessions longues ou multi-sessions d'une phase (ex. Phase 6 Lots/Tâches). Elle double le suivi par issues GitHub. À réserver aux runs autonomes, sinon les issues suffisent.

### Gaps

- Pas de source empirique trouvée mesurant le gain du « types-first » (signatures + `todo!()` avant implémentation) sur le taux de réussite des agents. Il s'agit d'une pratique raisonnée, non d'un résultat publié.
- Comportement exact d'insta en CI (variables `INSTA_UPDATE`, `CI`) non re-vérifié pendant cette session.
- Statut actuel de `-Zpanic-abort-tests` / compatibilité des tests avec `panic=abort` (Cranelift macOS) non vérifié.
