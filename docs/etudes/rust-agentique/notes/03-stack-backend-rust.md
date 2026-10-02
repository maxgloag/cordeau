# Écosystème backend Rust (état au 2026-10-01) pour remplacer Symfony 7.4 + API Platform 4, composant par composant

> Méthode : versions, dates et téléchargements relevés le 2026-10-01 via l'API crates.io (`/api/v1/crates/NAME` et `/versions`) ; santé des dépôts via `gh api repos/OWNER/REPO` (étoiles, `pushed_at`, issues ouvertes) et `/contributors` (risque mono-mainteneur). « dl/90j » = téléchargements crates.io sur 90 jours. Les sources tierces (blogs, Medium, Substack) sont signalées comme secondaires.
>
> Point de départ côté Cordeau (lu dans le dépôt) : `apps/api/src` ≈ 5 042 lignes PHP ; **16 State Providers/Processors**, **4 `#[ApiResource]`**, **0 `ApiFilter`**, **0 groupe de sérialisation** ; formats `json` + `jsonld` + `merge-patch+json`, mapping d'exception → statut, Swagger UI en dev seulement ([apps/api/config/packages/api_platform.yaml](../../../../apps/api/config/packages/api_platform.yaml)). `packages/shared` génère ses types via `api:openapi:export > openapi.json` puis `openapi-typescript ^7` ; le `openapi.json` actuel est en **OpenAPI 3.1.0**, 8 paths, et utilise `application/problem+json` ([packages/shared/package.json](../../../../packages/shared/package.json)). Conséquence : Cordeau utilise peu la « magie » d'API Platform (filtres, groupes) ; l'essentiel est déjà du code applicatif écrit à la main dans des Providers/Processors.

## Q1 — Framework HTTP : Axum vs Actix Web vs Loco.rs vs Poem vs Rocket vs Dropshot

### Takeaway

Axum (0.8.9) est le choix par défaut de l'écosystème (de loin le plus téléchargé, porté par tokio-rs, tout est `tower::Service`) ; Loco 1.x (Rails-like sur Axum + SeaORM 2) est le seul à offrir des « batteries » proches de Symfony, et vient de passer 1.0 avec un support explicite des agents de code, mais ses choix par défaut (JWT, SeaORM, scaffolding) divergent de Cordeau. Poem et surtout Rocket sont à écarter (maintenance ralentie / figée) ; Dropshot est solide mais niche (Oxide).

### Cited Findings

- **axum 0.8.9** publié le 2026-04-14 (0.8.0 fin 2024, patchs réguliers 0.8.4→0.8.9 entre 2025-04 et 2026-04) ; 497 M téléchargements cumulés, **125 M dl/90j** ; dépôt tokio-rs/axum ★27,3k, push 2026-09-30, 80 issues ouvertes — [crates.io axum](https://crates.io/crates/axum) ; [GitHub tokio-rs/axum](https://github.com/tokio-rs/axum)
- Une **0.9 est en préparation** : milestone « 0.9 » (4 ouvertes / 11 fermées) et section `Unreleased` du CHANGELOG avec plusieurs changements **breaking** (fusion des fallbacks de routeurs imbriqués, `#[from_request(via(...))]` qui change de type de rejet, `axum::serve` qui applique `header_read_timeout`, type de sortie de `serve` modifié) — [axum CHANGELOG](https://github.com/tokio-rs/axum/blob/main/axum/CHANGELOG.md) ; [milestones axum](https://github.com/tokio-rs/axum/milestones)
- **actix-web 4.15.0** (2026-08-21), releases fréquentes (4.12→4.15 entre 2025-11 et 2026-08) ; 11,2 M dl/90j ; ★24,9k, 198 issues ouvertes — [crates.io actix-web](https://crates.io/crates/actix-web) ; [GitHub actix/actix-web](https://github.com/actix/actix-web)
- **loco-rs 1.2.0** (2026-09-24) ; 1.0.0 annoncée comme « first stable Loco release », daté 2026-07-25 dans les notes de release (crates.io indique une publication le 2026-07-29) ; 111k dl/90j ; ★9,4k ; deux contributeurs principaux (kaplanelad 802 commits, jondot 538) — [crates.io loco-rs](https://crates.io/crates/loco-rs) ; [Release v1.0.0](https://github.com/loco-rs/loco/releases/tag/v1.0.0)
- Loco 1.0 : passage à **SeaORM 2.0 + sqlx 0.9**, MSRV 1.94, « first-class LLM/agent support », files de jobs à priorité (Redis : passage Lists → Sorted Sets), `PageResponse<T>` avec `meta: PagerMeta` — [Release v1.0.0](https://github.com/loco-rs/loco/releases/tag/v1.0.0)
- Loco 1.1 (2026-08-16) : Tera 2, cible de déploiement Lambda, `--no-auth` sur le scaffold (« Scaffolded routes took a JWT extractor on all five handlers with no way out »), `cargo loco jobs retry` ; le guide d'upgrade contient « a prompt you can paste straight into Claude Code » — [Release v1.1.0](https://github.com/loco-rs/loco/releases) ; [Upgrades](https://loco.rs/docs/extras/upgrades/)
- Loco 1.2 (2026-09-24) : « Ships a Loco skill so coding agents write idiomatic Loco without guessing », **URLs pré-signées** de stockage (`presign_put`/`presign_get` vers S3/Azure/GCS), multi-tenancy par ligne (feature opt-in), builds Postgres-only ; un bug corrigé où `perform_later` « handed back a fabricated job id » quand aucune file n'était configurée — [Release v1.2.0](https://github.com/loco-rs/loco/releases)
- OpenAPI dans Loco : initializer `loco-openapi` basé sur utoipa (Swagger UI, ReDoc, Scalar) depuis la 0.16 — [loco-rs/loco-openapi](https://github.com/loco-rs/loco-openapi) ; [Discussion v0.16](https://github.com/loco-rs/loco/discussions/1525)
- **poem 3.1.12** et **poem-openapi 5.1.16** : dernière release le **2025-07-28** (14 mois sans release), bien que des commits existent en 2026-09 ; 195 issues ouvertes ; sunli829 = 1 096 commits vs 103 pour le suivant (mono-mainteneur) — [crates.io poem](https://crates.io/crates/poem) ; [GitHub poem-web/poem](https://github.com/poem-web/poem)
- **rocket 0.5.1** (2024-05-23), dernier commit sur rwf2/Rocket le 2025-12-28 — [crates.io rocket](https://crates.io/crates/rocket) ; [GitHub rwf2/Rocket](https://github.com/rwf2/Rocket)
- **dropshot 0.17.1** (2026-06-02), Oxide Computer, ★1,2k, 139k dl/90j, push 2026-09-29 ; « general-purpose crate for exposing REST APIs », OpenAPI généré depuis les handlers — [crates.io dropshot](https://crates.io/crates/dropshot) ; [GitHub oxidecomputer/dropshot](https://github.com/oxidecomputer/dropshot)
- Briques Axum : **tower-http 0.7.1** (2026-08-31, 161 M dl/90j), **axum-extra 0.12.6** (cookies signés/privés, typed headers…) — [crates.io tower-http](https://crates.io/crates/tower-http) ; [crates.io axum-extra](https://crates.io/crates/axum-extra)

### Inferences

- Pour « le plus de batteries sans cacher les types au compilateur » : **Axum + sélection manuelle de crates** garde tout explicite (extracteurs typés, `Router<State>`), ce qui est idéal pour un agent (erreurs de compilation locales et lisibles). Loco ajoute des batteries mais via conventions/générateurs et un `AppContext` partagé ; ses défauts (JWT, SeaORM ActiveModel, Tera) recoupent mal l'existant Cordeau (sessions cookie + tokens opaques, hexagonal strict, domaine pur). Loco reste une option crédible si l'on accepte ses conventions, et c'est le seul framework qui documente explicitement un workflow agent (skill + prompts d'upgrade).
- Actix Web est mature et rapide mais l'écosystème périphérique (tower-sessions, axum-login, utoipa-axum, axum-test, Loco) gravite autour d'Axum/tower ; pour un dev solo, suivre le centre de gravité réduit le risque.
- Prévoir la migration Axum 0.8 → 0.9 (breaking) : à faire tôt si on démarre maintenant ; le risque est modéré (changements surtout sur `serve` et les extracteurs dérivés).

### Gaps

- Pas de date de sortie annoncée pour Axum 0.9 (la milestone n'a pas d'échéance).
- Pas de benchmark indépendant 2026 Axum vs Actix vs Loco trouvé dans cette session (non pertinent à l'échelle de Cordeau de toute façon).

## Q2 — Persistance : SQLx vs SeaORM 2.x vs Diesel 2.x vs Toasty vs Cornucopia/Clorinde ; migrations ; Neon ; décimaux

### Takeaway

SQLx 0.9 (requêtes SQL vérifiées à la compilation, mode offline `.sqlx`) est le standard de fait et le meilleur fit « hexagonal + agent » ; SeaORM 2.0 (stable depuis juillet 2026) est l'option ORM la plus proche de Doctrine ; Diesel 2.3 est très mature mais synchrone par défaut ; Toasty est trop jeune (une version mineure breaking par mois) ; Cornucopia 1.0 a absorbé Clorinde (archivé). `rust_decimal` est le standard pour HT/TVA/TTC.

### Cited Findings

- **sqlx 0.9.0** : CHANGELOG daté 2026-05-06 (publication crates.io 2026-05-21) ; **41,4 M dl/90j** ; dépôt transféré vers l'organisation **transact-rs** (« SQLx has not been owned or maintained by LaunchBadge, LLC. for a few years now ») ; ★17,5k mais **778 issues ouvertes** ; MSRV 1.94 ; nouveau fichier de config par crate **`sqlx.toml`** (renommer `DATABASE_URL` par crate, overrides de types globaux, renommer/relocaliser la table `_sqlx_migrations`) ; changements importants du trait `Migrate` ; `Cargo.lock` retiré du dépôt donc `cargo install --locked sqlx-cli` ne fonctionne plus — [sqlx CHANGELOG](https://github.com/transact-rs/sqlx/blob/main/CHANGELOG.md) ; [crates.io sqlx](https://crates.io/crates/sqlx)
- `sqlx-cli` gère base, migrations et le **mode offline** pour `sqlx::query!()` (« Enable building in "offline mode" with `query!()` ») — [sqlx-cli README](https://github.com/transact-rs/sqlx/blob/main/sqlx-cli/README.md)
- **sea-orm 2.0.0** le 2026-07-19 (après ~43 RC), 2.0.4 le 2026-09-27 ; 4,5 M dl/90j ; ★9,9k, 236 issues ; 2.0.4 ajoute `select_except`, corrige un bug de `left_join_linked` qui « could return incorrect rows » — [crates.io sea-orm](https://crates.io/crates/sea-orm) ; [Release 2.0.4](https://github.com/SeaQL/sea-orm/releases)
- **diesel 2.3.13** (2026-09-04), patchs mensuels ; 8,2 M dl/90j ; ★14,2k — [crates.io diesel](https://crates.io/crates/diesel) ; [GitHub diesel-rs/diesel](https://github.com/diesel-rs/diesel)
- **toasty 0.11.0** (2026-09-25), tokio-rs ; versions 0.6 → 0.11 entre mai et septembre 2026 ; 34k dl/90j ; ★3,2k, 110 issues — [crates.io toasty](https://crates.io/crates/toasty) ; [GitHub tokio-rs/toasty](https://github.com/tokio-rs/toasty)
- **cornucopia 1.0.0** (2026-05-20) : « This release merges the Clorinde fork back into Cornucopia » ; 1.0.1 le 2026-08-14 ; **clorinde archivé** (« merged back into Cornucopia and will no longer be maintained ») — [Release cornucopia-v1.0.0](https://github.com/cornucopia-rs/cornucopia/releases) ; [GitHub halcyonnouveau/clorinde](https://github.com/halcyonnouveau/clorinde)
- Migrations : **refinery 0.9.2** (2026-06-10, 2 M dl/90j), **sea-orm-migration 2.0.4** (2,7 M dl/90j), `sqlx migrate` intégré à sqlx-cli — [crates.io refinery](https://crates.io/crates/refinery) ; [crates.io sea-orm-migration](https://crates.io/crates/sea-orm-migration)
- Query builder : **sea-query 1.0.2** (2026-08-12, 5,3 M dl/90j) utilisable seul avec sqlx pour les requêtes dynamiques (filtres/tri) — [crates.io sea-query](https://crates.io/crates/sea-query)
- Décimaux : **rust_decimal 1.43.0** (2026-09-02), 36,6 M dl/90j ; sqlx 0.9 documente la cohabitation `bigdecimal`/`rust_decimal` via `sqlx.toml` — [crates.io rust_decimal](https://crates.io/crates/rust_decimal) ; [sqlx CHANGELOG](https://github.com/transact-rs/sqlx/blob/main/CHANGELOG.md)
- Monnaie : `rusty-money` 0.5.1 (198k dl/90j), `iso_currency` 0.7.1 — [crates.io rusty-money](https://crates.io/crates/rusty-money) ; [crates.io iso_currency](https://crates.io/crates/iso_currency)

### Inferences

- **SQLx + rust_decimal** est le meilleur fit : le SQL reste explicite (lisible par l'agent et par toi), les erreurs de schéma remontent à la compilation, et l'adaptateur d'infrastructure implémente des ports définis dans le crate domaine (aucune fuite d'entité ORM dans le domaine, contrairement à l'actuel mapping Doctrine). Le mode offline (`cargo sqlx prepare` → `.sqlx/` versionné) évite d'avoir besoin d'une base en CI pour compiler ; une base reste nécessaire pour les tests d'intégration.
- **Neon branching** : SQLx est agnostique (connexion Postgres standard) ; la contrainte est que `cargo sqlx prepare` doit tourner contre un schéma à jour (branche Neon de la PR ou Postgres local Docker). Le flux par PR existant se transpose directement (migrations `sqlx migrate run` au démarrage ou en job).
- Coût vs Doctrine : pas d'unit-of-work ni de lazy-loading — chaque repository écrit ses requêtes. Pour un domaine riche (Lots/Tâches/Mesures/Devis), c'est plutôt un avantage (agrégats chargés explicitement), mais le volume de SQL écrit à la main augmente.
- SeaORM 2.0 est l'alternative si l'on veut un ORM « entités + relations » ; il vient juste de sortir de RC et ses bugs de jointures en 2.0.3/2.0.4 suggèrent d'attendre quelques patchs. Toasty : à surveiller, pas à adopter (pré-1.0, churn mensuel). Cornucopia 1.0 (SQL → code généré) est une alternative intéressante à `query!` mais la communauté est petite.
- Le volume de 778 issues ouvertes sur sqlx est un signal de charge de maintenance, pas d'abandon (release 0.9 récente, réorganisation de gouvernance).

### Gaps

- Pas vérifié dans cette session : statut de `diesel-async` (support async de Diesel) et support des branches Neon par les outils de migration au-delà du simple `DATABASE_URL`.
- Pas trouvé de benchmark ou retour d'expérience 2026 comparant SQLx 0.9 et SeaORM 2.0 sur des projets réels de taille moyenne.

## Q3 — OpenAPI + client TS : utoipa, aide, poem-openapi, Dropshot ; ts-rs/specta/typeshare ; préserver `packages/shared`

### Takeaway

**utoipa 6.0** (Axum via `utoipa-axum`) génère de l'OpenAPI 3.1 par défaut et 3.2 en opt-in : il suffit de remplacer `api:openapi:export` par un petit binaire Rust qui imprime le JSON, et toute la chaîne `openapi-typescript` de `packages/shared` reste intacte (le spec actuel est déjà en 3.1.0). Mais utoipa repose quasi entièrement sur un mainteneur. ts-rs/specta/typeshare sont des alternatives qui contournent OpenAPI — inutiles ici.

### Cited Findings

- **utoipa 6.0.0** (2026-09-22/23) : support **OpenAPI 3.2.0 via `Version32`** (opt-in), Link Object corrigé, support `bigdecimal`, MSRV 1.88, suppression de `serde_norway` (breaking) ; 16,2 M dl/90j — [utoipa CHANGELOG](https://github.com/juhaku/utoipa/blob/master/utoipa/CHANGELOG.md) ; [crates.io utoipa](https://crates.io/crates/utoipa)
- utoipa supporte « OpenAPI 3.1 » et intègre axum (via `utoipa-axum`), actix-web et rocket ; l'exemple de sortie déclare `"openapi": "3.1.0"` — [README utoipa](https://github.com/juhaku/utoipa)
- **utoipa-axum 0.3.0** et **utoipa-scalar 0.4.0** publiés le 2026-09-22 (3,3 M et 1,1 M dl/90j) — [crates.io utoipa-axum](https://crates.io/crates/utoipa-axum) ; [crates.io utoipa-scalar](https://crates.io/crates/utoipa-scalar)
- Risque bus factor : juhaku = 643 commits, contributeur suivant = 29 ; 242 issues ouvertes — [contributors juhaku/utoipa](https://github.com/juhaku/utoipa/graphs/contributors)
- **aide** : stable 0.15.1 (2025-08-19), **0.16.0-alpha.4** (2026-04-14) ; ★678 ; 850k dl/90j — [crates.io aide](https://crates.io/crates/aide) ; [GitHub tamasfe/aide](https://github.com/tamasfe/aide)
- **poem-openapi 5.1.16** : dernière release 2025-07-28 — [crates.io poem-openapi](https://crates.io/crates/poem-openapi)
- **ts-rs 12.0.1** (2026-01-31, 6,8 M dl/90j) ; **specta 2.0.0-rc.25** (2026-05-07 ; la branche 2.0 est en RC depuis 2024) ; **typeshare 1.0.5** (1Password, 2026-01-02) — [crates.io ts-rs](https://crates.io/crates/ts-rs) ; [crates.io specta](https://crates.io/crates/specta) ; [crates.io typeshare](https://crates.io/crates/typeshare)
- Côté TS : `openapi-typescript` dernière version **7.13.0** (npm) ; Cordeau l'utilise en `^7` sur un spec 3.1.0 — [npm openapi-typescript](https://www.npmjs.com/package/openapi-typescript) ; [packages/shared/package.json](../../../../packages/shared/package.json)
- Problem Details : **problem_details 0.10.0** (RFC 9457/7807, 2026-08-16, 42k dl/90j) ; `problemdetails` 0.7.0 — [crates.io problem_details](https://crates.io/crates/problem_details)

### Inferences

- **`packages/shared` peut rester tel quel** : remplacer la commande `symfony php bin/console api:openapi:export` par `cargo run -p cordeau-api --bin export-openapi` (≈ 10 lignes : `ApiDoc::openapi().to_pretty_json()`), garder `openapi-typescript openapi.json -o src/api.generated.ts`. Rester en 3.1 (défaut utoipa) pour éviter tout risque de compat 3.2 côté openapi-typescript.
- Différence majeure avec API Platform : avec utoipa, le schéma est **déclaré par annotations** (`#[utoipa::path(...)]`, `#[derive(ToSchema)]`) à côté du handler — il peut **dériver** du code réel si on oublie un statut ou un champ. Mitigation : test de contrat (snapshot `insta` du `openapi.json` + `oasdiff`/diff en CI) et un test qui vérifie que chaque route du `Router` est documentée. `aide` infère davantage depuis les types des extracteurs (moins de dérive) mais est en alpha et moins utilisé.
- Dropshot génère le spec directement depuis les signatures (zéro dérive), mais impose son propre framework et un écosystème plus petit.
- ts-rs/specta/typeshare n'apportent rien tant que le contrat HTTP est la frontière : garder OpenAPI comme source de vérité est plus robuste pour web + mobile.

### Gaps

- Pas vérifié : compatibilité d'openapi-typescript 7.13 avec OpenAPI 3.2 (non nécessaire si on reste en 3.1).
- Pas d'équivalent Rust identifié de JSON-LD/Hydra ; Cordeau expose `jsonld` mais l'usage réel côté clients n'a pas été mesuré (probablement nul).

## Q4 — Authentification : sessions, tokens opaques, OAuth2/OIDC Google, argon2, CSRF, rate limiting

### Takeaway

Tout existe, mais en briques à assembler : `tower-sessions` (+ store Postgres/Redis) et `axum-login` pour la session web, `openidconnect`/`oauth2` pour Google, `argon2`/`password-auth` (RustCrypto) pour les mots de passe ; les tokens opaques mobiles sont à écrire soi-même (simple : table + hash SHA-256). Les maillons faibles sont le **CSRF** (écosystème fragmenté) et le **rate limiting** (tower_governor peu actif), et plusieurs crates clés reposent sur un seul mainteneur (maxcountryman).

### Cited Findings

- **tower-sessions 0.15.0** (2026-02-01), 926k dl/90j, ★423 ; stores **tower-sessions-sqlx-store 0.15.0** et **tower-sessions-redis-store 0.16.0** publiés le 2025-01-01 (pas de release depuis 21 mois) — [crates.io tower-sessions](https://crates.io/crates/tower-sessions) ; [crates.io tower-sessions-sqlx-store](https://crates.io/crates/tower-sessions-sqlx-store) ; [crates.io tower-sessions-redis-store](https://crates.io/crates/tower-sessions-redis-store)
- **axum-login 0.18.0** (2025-07-20), 264k dl/90j, ★1k ; maxcountryman = 159 commits (tower-sessions : 216), contributeurs suivants < 10 commits — [crates.io axum-login](https://crates.io/crates/axum-login) ; [GitHub maxcountryman/axum-login](https://github.com/maxcountryman/axum-login)
- sqlx 0.9 cite explicitement un conflit `chrono`/`time` provoqué par `tower-sessions-sqlx-store`, réglable via `sqlx.toml` — [sqlx CHANGELOG](https://github.com/transact-rs/sqlx/blob/main/CHANGELOG.md)
- **oauth2 5.0.0** (2025-01-21, 15 M dl/90j) et **openidconnect 4.0.1** (2025-07-06, 4,3 M dl/90j), mainteneur ramosbugs ; dernier push 2026-02-22 (oauth2) et 2025-11-08 (openidconnect, 78 issues ouvertes) — [crates.io openidconnect](https://crates.io/crates/openidconnect) ; [GitHub ramosbugs/openidconnect-rs](https://github.com/ramosbugs/openidconnect-rs)
- **argon2 0.6.0** (2026-08-27, RustCrypto, 21,5 M dl/90j) ; **password-auth 1.0.0** (2026-01-12, RustCrypto, API haut niveau) — [crates.io argon2](https://crates.io/crates/argon2) ; [crates.io password-auth](https://crates.io/crates/password-auth)
- CSRF : **axum_csrf 0.11.0** (2025-01-01, 47k dl/90j), **axum-tower-sessions-csrf 0.1.4** (2026-06-29, 1,8k dl/90j), **tower-sec-fetch 0.1.2** (validation Fetch Metadata, 6,5k dl/90j), `axum-csrf-sync-pattern` 0.3.2 (2024, quasi abandonné) — [crates.io search « axum csrf »](https://crates.io/search?q=axum%20csrf) ; [crates.io tower-sec-fetch](https://crates.io/crates/tower-sec-fetch)
- Rate limiting : **tower_governor 0.8.0** (2025-08-14), 1,8 M dl/90j, dépôt sans push depuis 2025-08-14, mainteneur benwis 25 commits — [crates.io tower_governor](https://crates.io/crates/tower_governor) ; [GitHub benwis/tower-governor](https://github.com/benwis/tower-governor)
- JWT si besoin : **jsonwebtoken 11.1.0** (2026-09-16) — [crates.io jsonwebtoken](https://crates.io/crates/jsonwebtoken)
- Secrets en mémoire : **secrecy 0.10.3** — [crates.io secrecy](https://crates.io/crates/secrecy)

### Inferences

- Le modèle actuel (cookie de session web + token opaque mobile + OAuth Google avec auto-link) se transpose sans crate exotique : session = `tower-sessions` + store Postgres (ou Redis) ; token opaque = 32 octets aléatoires, hash SHA-256 en base, extracteur Axum maison (~100–150 lignes) ; Google = `openidconnect` (vérif. `id_token`, nonce, PKCE). `axum-login` est optionnel : un extracteur `CurrentUser` maison unifiant cookie et bearer est probablement plus simple à raisonner (et à tester) que l'abstraction `AuthnBackend`.
- CSRF : avec cookies `SameSite=Lax/Strict` + vérification `Origin`/`Sec-Fetch-Site` (≈ 30 lignes de middleware, ou `tower-sec-fetch`), on obtient une protection équivalente à celle de Symfony pour une API JSON ; éviter de dépendre d'une petite crate CSRF.
- Rate limiting : `tower_governor` fonctionne mais est en mode maintenance ; alternative : limiter au niveau proxy (Fly/Scaleway/Cloudflare) ou petit middleware Redis maison.
- Risque bus factor élevé sur la pile session (maxcountryman). Mitigation : couche fine, remplaçable (trait `SessionStore`).

### Gaps

- Pas d'audit de sécurité public trouvé pour tower-sessions/axum-login (contrairement au composant Security de Symfony, très éprouvé).
- Pas vérifié : compatibilité exacte des stores tower-sessions 0.15 avec sqlx 0.9 (les stores datent de jan. 2025, avant sqlx 0.9).

## Q5 — Validation & modélisation du domaine ; règles de dépendance hexagonales sans Deptrac

### Takeaway

Rust est nettement **plus fort** que PHP ici : newtypes (`nutype`), enums exhaustifs, « parse, don't validate » et typestate rendent les états invalides non représentables, et un **workspace Cargo avec un crate par couche/bounded context** fait appliquer la direction des dépendances par le compilateur lui-même (ce que Deptrac vérifie a posteriori).

### Cited Findings

- **nutype 0.8.0** (2026-09-20) : newtypes avec sanitize/validate dérivés ; 1,8 M dl/90j ; ★1,8k ; greyblake = 612 commits (mono-mainteneur) — [crates.io nutype](https://crates.io/crates/nutype) ; [GitHub greyblake/nutype](https://github.com/greyblake/nutype)
- **garde 0.23.0** (2026-05-23, 2,4 M dl/90j, jprochazk 314 commits) ; **validator 0.21.0** (2026-07-27, 13,1 M dl/90j) ; **axum-valid 0.25.0** (intégration extracteurs Axum) — [crates.io garde](https://crates.io/crates/garde) ; [crates.io validator](https://crates.io/crates/validator) ; [crates.io axum-valid](https://crates.io/crates/axum-valid)
- **serde_with 3.24.0**, **uuid 1.26.1**, **jiff 0.2.37** / **chrono 0.4.45** pour les types de base — [crates.io serde_with](https://crates.io/crates/serde_with) ; [crates.io jiff](https://crates.io/crates/jiff)
- Hexagonal en workspace : « By putting each ring in its own crate and giving each crate a dependencies list that only points inward, the compiler enforces the rule » ; découpage domain / ports / application / adapters / composition root — [DEV : Hexagonal architecture in Rust… legible to coding agents](https://dev.to/llmgraph/hexagonal-architecture-in-rust-and-why-it-makes-your-codebase-legible-to-coding-agents-544a) (secondaire) ; [How To Code It — Master Hexagonal Architecture in Rust](https://www.howtocodeit.com/guides/master-hexagonal-architecture-in-rust)
- Outil de vérification des frontières : `hav` (nfma/hexagonal-architecture-validator), « deterministic Rust CLI for checking declared dependency boundaries » — [GitHub nfma/hexagonal-architecture-validator](https://github.com/nfma/hexagonal-architecture-validator) (maturité inconnue)
- **cargo-deny 0.20.2** (2026-07-09) pour les politiques de dépendances en CI — [crates.io cargo-deny](https://crates.io/crates/cargo-deny)

### Inferences

- Structure proposée : `crates/shared-kernel` (newtypes, `Money`, `TauxTva`), `crates/chantier-domain` (aucune dépendance I/O : ni sqlx, ni axum, ni tokio), `crates/chantier-app` (use cases + traits ports), `crates/chantier-infra` (sqlx, R2…), `crates/api` (Axum, composition root). Un `use sqlx` dans un crate domaine **ne compile pas** si `sqlx` n'est pas dans son `Cargo.toml` : la règle Deptrac devient une propriété du build. Garde-fou supplémentaire : `cargo-deny` (bans par crate « wrapper », à vérifier) ou un test qui parse `cargo metadata`.
- Pour la validation : `garde`/`validator` au bord HTTP (DTO), `nutype` + constructeurs `TryFrom` dans le domaine. Le « parse, don't validate » remplace les contraintes Symfony `Assert\*` (9 fichiers les utilisent aujourd'hui) par des types.
- Coût : un workspace multi-crates augmente un peu le temps de compilation à froid mais améliore l'incrémental (crates domaine rarement recompilés).
- Lisibilité agent : les types forts + crates séparés donnent à l'agent des erreurs compilateur précises et un périmètre de contexte réduit — cohérent avec l'objectif « construire solide ».

### Gaps

- Pas vérifié dans cette session : la syntaxe exacte de `cargo-deny` pour restreindre « qui peut dépendre de qui » (option `wrappers` des bans) — à confirmer dans la doc.
- Pas d'outil Rust aussi établi que Deptrac pour des règles **intra-crate** (modules) ; `hav` est récent et non évalué.

## Q6 — Jobs asynchrones (équivalent Messenger) : apalis, files Postgres (underway, sqlxmq, graphile_worker, pgmq), Redis ; planification

### Takeaway

Pas d'équivalent stabilisé de Symfony Messenger. **apalis** est le plus complet mais sa 1.0 est en RC depuis des mois avec des changements d'API breaking à chaque RC et un seul mainteneur ; les files Postgres (graphile_worker-rs, underway, sqlxmq) sont petites. Pour 2 handlers (vignettes), une **file Postgres maison `SELECT … FOR UPDATE SKIP LOCKED`** (~200 lignes) ou apalis-postgres sont les options raisonnables ; Loco fournit sa propre file si on choisit Loco.

### Cited Findings

- **apalis** : stable **0.7.4**, **1.0.0-rc.10** (2026-09-15) ; RC précédentes rc.5→rc.9 entre mars et mai 2026 ; rc.10 contient plusieurs changements marqués `(api)!` (metadata, execution context, `Backend`/`BackendExt`, `poll_*`) ; ajout d'idempotence (rc.8/rc.9) et propagation de contexte OTel ; 336k dl/90j ; geofmureithi = 437 commits (contributeur humain suivant : 27) — [Releases apalis](https://github.com/apalis-dev/apalis/releases) ; [crates.io apalis](https://crates.io/crates/apalis)
- **apalis-postgres 1.0.0-rc.9** (2026-09-16) ; **apalis-cron** — [crates.io apalis-postgres](https://crates.io/crates/apalis-postgres) ; [crates.io apalis-cron](https://crates.io/crates/apalis-cron)
- **underway 0.2.0** (2025-07-16), ★174, 6k dl/90j (maxcountryman) — [crates.io underway](https://crates.io/crates/underway)
- **sqlxmq 0.6.0** (2025-05-25), 15k dl/90j — [crates.io sqlxmq](https://crates.io/crates/sqlxmq)
- **graphile_worker 0.13.6** (2026-09-29), releases fréquentes, ★84, leo91000 = 408 commits humains (le reste : bots) — [crates.io graphile_worker](https://crates.io/crates/graphile_worker) ; [GitHub leo91000/graphile_worker_rs](https://github.com/leo91000/graphile_worker_rs)
- **pgmq 0.33.7** (client Rust de l'extension Postgres pgmq), 33k dl/90j — [crates.io pgmq](https://crates.io/crates/pgmq)
- **tokio-cron-scheduler 0.15.1** (2025-10-28), 1,8 M dl/90j — [crates.io tokio-cron-scheduler](https://crates.io/crates/tokio-cron-scheduler)
- Redis : **redis 1.7.1** (2026-09-25, 27,7 M dl/90j), **deadpool-redis 0.23.1** ; `fred` 10.1.0 sans release depuis 2025-02 — [crates.io redis](https://crates.io/crates/redis) ; [crates.io fred](https://crates.io/crates/fred)
- Loco : workers intégrés (Redis/Postgres/SQLite), priorités (1.0), `jobs retry` (1.1), bug « fabricated job id » corrigé en 1.2 — [Releases loco](https://github.com/loco-rs/loco/releases)

### Inferences

- Pour Cordeau (2 handlers Messenger aujourd'hui, vignettes ; demain génération PDF, e-mails), une **file Postgres** évite Redis comme dépendance critique et rend l'enqueue transactionnel avec l'écriture métier (pattern outbox gratuit). Option A : apalis-postgres (épingler une RC, accepter les migrations d'API). Option B : table `jobs` + worker `tokio` avec `FOR UPDATE SKIP LOCKED`, retries exponentiels, idempotence — code simple qu'un agent écrit et teste bien.
- Redis reste utile pour cache/rate-limit, pas forcément pour les jobs.
- Risque à signaler : aucune des options n'a la maturité de Messenger (transports multiples, retry strategy, failure transport, `messenger:consume` supervisé).

### Gaps

- Pas vérifié : disponibilité de l'extension `pgmq` sur Neon et sur les Postgres managés Scaleway/Clever Cloud.
- Pas de retour d'expérience 2026 trouvé sur apalis 1.0 RC en production.

## Q7 — Stockage R2 (URL pré-signées) et traitement d'images, HEIC compris ; posture sécurité vs ImageMagick

### Takeaway

R2 + PUT pré-signé est un cas résolu (`aws-sdk-s3` officiel, ou `object_store`/`opendal`). Le décodage **HEIC n'a pas d'implémentation Rust pure** : il faut `libheif-rs` (binding de la lib C++ libheif, mono-mainteneur), donc la même surface d'attaque C/C++ que via Imagick pour HEIC — avec en 2026 plusieurs CVE heap-overflow corrigées seulement en libheif 1.22.0. Le reste (redimensionnement, JPEG/WebP) est bien couvert en Rust sûr (`image`, `fast_image_resize`).

### Cited Findings

- **aws-sdk-s3 1.152.0** publié le 2026-10-01 (AWS officiel, 22,5 M dl/90j) ; **rust-s3 0.37.2** (2026-05-04) ; **object_store 0.14.2** (Apache Arrow, 21 M dl/90j) ; **opendal 0.59.3** (2026-09-22, 5 M dl/90j, utilisé par Loco) — [crates.io aws-sdk-s3](https://crates.io/crates/aws-sdk-s3) ; [crates.io object_store](https://crates.io/crates/object_store) ; [crates.io opendal](https://crates.io/crates/opendal)
- Loco 1.2 : `presign_put(path, ttl, PresignPutOptions)` « hand a client a time-limited URL that talks to S3, Azure Blob or GCS directly » — [Release loco v1.2.0](https://github.com/loco-rs/loco/releases)
- **image 0.25.10** (2026-03-10), 54,8 M dl/90j, ★5,9k ; **fast_image_resize 6.1.0** (2026-07-21, 4,6 M dl/90j) ; **zune-image 0.5.0** (37k dl/90j) — [crates.io image](https://crates.io/crates/image) ; [crates.io fast_image_resize](https://crates.io/crates/fast_image_resize)
- **libheif-rs 3.0.0** (2026-08-18), 379k dl/90j, mais ★61 et Cykooz = 170 commits (suivant : 5) ; « Safe wrapper around the libheif-sys crate » ; dépendance système `libheif-dev >= 1.17.0`, features `v1_17`…`v1_21` — [GitHub Cykooz/libheif-rs](https://github.com/Cykooz/libheif-rs) ; [crates.io libheif-rs](https://crates.io/crates/libheif-rs)
- CVE libheif 2026 : **CVE-2026-32740** (heap-buffer-overflow en écriture, CVSS 8.8, publiée le 2026-05-19) ; **CVE-2026-32741** (overflow dans `MaskImageCodec::decode_mask_image()`, ≤ 1.21.2, corrigée en 1.22.0) ; **CVE-2026-47178** (codec `unci`, versions 1.19.0–1.21.2) — [Red Hat CVE-2026-32741](https://access.redhat.com/security/cve/cve-2026-32741) ; [TheHackerWire CVE-2026-32740](https://www.thehackerwire.com/libheif-heap-buffer-overflow-cve-2026-32740/) ; [SentinelOne CVE-2026-47178](https://www.sentinelone.com/vulnerability-database/cve-2026-47178/) (sources secondaires sauf Red Hat)
- EXIF : `kamadak-exif` 0.6.1 (3,3 M dl/90j), `little_exif` 0.6.23 — [crates.io kamadak-exif](https://crates.io/crates/kamadak-exif)

### Inferences

- **Posture sécurité** : en Rust, seul le décodage HEIC reste en C++ (libheif) ; JPEG/PNG/WebP/redimensionnement passent par du code Rust memory-safe, alors qu'Imagick expose tous les « coders » ImageMagick (surface bien plus large). Gain réel mais partiel : le risque HEIC est identique dans les deux stacks et impose de **compiler/épingler libheif ≥ 1.22** dans l'image Docker et d'isoler le décodage (processus worker séparé, limites mémoire/temps, éventuellement seccomp).
- `libheif-rs` est un point de fragilité (mono-mainteneur, 61 étoiles) mais c'est une couche fine ; un fallback est d'appeler le binaire `heif-convert`/`vips` en sous-processus (isolation gratuite).
- Pour la PUT pré-signée vers R2 : `aws-sdk-s3` (endpoint R2, région `auto`) est le choix le plus sûr ; `object_store` est plus léger.

### Gaps

- Pas vérifié : si `libheif-rs` 3.0 expose déjà des features pour libheif 1.22 (le README consulté s'arrête à `v1_21`), ni les versions de libheif fournies par Debian trixie / Alpine et leurs backports de correctifs.
- Pas trouvé de décodeur HEVC/HEIC en Rust pur prêt pour la production.

## Q8 — PDF devis/factures (Typst, krilla, printpdf, genpdf) et Factur-X / PDF-A3 + CII/UBL

### Takeaway

La génération PDF est **un point fort** de Rust en 2026 grâce à Typst (bibliothèque Rust, templates déclaratifs, PDF/A-3 et pièces jointes supportés) — ce qui couvre le conteneur Factur-X. En revanche, la **couche sémantique e-facture** (EN 16931, CII/UBL, profils Factur-X, règles françaises, PDP) n'a que des crates très jeunes, mono-mainteneur et quasi pas utilisées : à traiter comme « pas d'équivalent fiable ».

### Cited Findings

- **typst 0.15.1** (2026-07-17), ★56,4k ; **typst-pdf 0.15.1** (1,2 M dl/90j) ; **typst-as-lib 0.16.0** (wrapper tiers pour l'embarquer, 565k dl/90j) — [crates.io typst](https://crates.io/crates/typst) ; [crates.io typst-as-lib](https://crates.io/crates/typst-as-lib)
- Typst supporte **PDF/A-1b/1a, 2b/2u/2a, 3b/3u/3a (« with attachment support »), 4/4f/4e**, PDF/UA-1, et la fonction **`pdf.attach`** pour embarquer des fichiers — [Typst docs : pdf](https://typst.app/docs/reference/pdf/)
- Issue Typst « PDF A-3 - embeding xml into the exported document » (#4411) fermée — [typst#4411](https://github.com/typst/typst/issues/4411)
- Package Typst Universe **invoice-pro** avec support ZUGFeRD/Factur-X « experimental » (XML EN 16931 embarqué dans un PDF/A-3B) — [Typst Universe invoice-pro](https://typst.app/universe/package/invoice-pro/)
- Exigences Factur-X : pièce jointe nommée `factur-x.xml`, listée sous `/Names/EmbeddedFiles`, référencée dans `/AF` du catalogue, `/AFRelationship`, schéma XMP d'extension déclaré ; validation veraPDF + Mustang — [DEV : Factur-X / ZUGFeRD from HTML… pass veraPDF and Mustang](https://dev.to/pdfik/factur-x-zugferd-from-html-how-to-embed-en-16931-xml-in-a-pdfa-3-and-actually-pass-verapdf-and-pgn) (secondaire)
- **krilla 0.8.2** (2026-06-04, backend PDF de Typst, LaurenzV, ★448) ; **printpdf 0.12.8** (2026-09-05, fschutt 652 commits, mono-mainteneur) ; **genpdf 0.2.0** (2021-06-17, abandonné) ; **lopdf 0.45.0** (manipulation bas niveau) — [crates.io krilla](https://crates.io/crates/krilla) ; [crates.io printpdf](https://crates.io/crates/printpdf) ; [crates.io genpdf](https://crates.io/crates/genpdf) ; [crates.io lopdf](https://crates.io/crates/lopdf)
- E-facture : **en16931 / en16931-formats 0.7.0** — « UBL 2.1 and UN/CEFACT CII in both directions, the XRechnung CIUS, and ZUGFeRD / Factur-X hybrid PDFs », 317 règles métier + 1 339 règles de syntaxe annoncées ; mais dépôt créé le **2026-07-30**, **★3, 0 fork, 1 seul contributeur (20 commits)**, ~4,2k dl au total — [GitHub hupe1980/en16931](https://github.com/hupe1980/en16931) ; [crates.io en16931-formats](https://crates.io/crates/en16931-formats)
- **core-invoice 2.0.3** (EN 16931 + Peppol PINT, codecs UBL/CII) : dépôt créé le 2026-08-30, ★0, ~215 dl — [GitHub proxeon/core-invoice](https://github.com/proxeon/core-invoice)
- **zugferd 0.1.7** (génération XML ZUGFeRD, ★21, 182 dl/90j) ; `faktura` 0.0.0 (placeholder) ; `einvoice` 0.1.1 (2025-01) — [crates.io zugferd](https://crates.io/crates/zugferd) ; [crates.io search « zugferd »](https://crates.io/search?q=zugferd)
- XML générique : **quick-xml 0.42.0** (122,7 M dl/90j) ; `yaserde` 0.12.0 (2024-11) ; `xot` 0.31.2 — [crates.io quick-xml](https://crates.io/crates/quick-xml)

### Inferences

- **Devis/factures PDF (V1)** : Typst embarqué est probablement meilleur que la plupart des solutions PHP (Dompdf/wkhtmltopdf) — mise en page de qualité, déterministe, rapide, sans navigateur headless. Coût : implémenter le trait `World` (polices, fichiers) ou utiliser `typst-as-lib` (tiers), et embarquer les polices dans l'image.
- **Factur-X (V2)** : le conteneur PDF/A-3 + pièce jointe est faisable avec Typst ; il faudra vérifier par veraPDF la conformité XMP Factur-X (le schéma d'extension XMP n'est pas documenté côté Typst). La génération du XML CII est faisable à la main avec `quick-xml` + types Rust (le modèle EN 16931 est fini et bien spécifié), mais **la validation officielle (Schematron EN 16931/Factur-X) n'a pas d'implémentation Rust mature** : prévoir un validateur externe (Mustang en JVM, ou service PDP) en CI/en sidecar.
- Côté PHP, l'écosystème Factur-X est plus ancien (bibliothèques atgp/factur-x, horstoeko/zugferd — non vérifié dans cette session) ; l'écart Rust est réel sur ce point.
- Le raccordement PDP (plateformes de dématérialisation partenaires) sera de toute façon une intégration HTTP vers l'API du PDP choisi : neutre vis-à-vis du langage.

### Gaps

- Pas trouvé de crate Rust couvrant les spécificités françaises de la réforme 2026–2027 (cas d'usage CTC, e-reporting, annuaire PPF, flux 1/6/10) ni d'implémentation Rust d'un validateur Schematron.
- Pas vérifié la conformité veraPDF d'un PDF/A-3 Factur-X produit par Typst 0.15 (aucun retour d'expérience trouvé).
- Pas vérifié l'état des bibliothèques PHP Factur-X pour la comparaison.

## Q9 — E-mail, i18n, observabilité, configuration, erreurs, tests

### Takeaway

Ces briques sont **matures et largement adoptées** : `lettre`, `tracing` + `sentry` + `opentelemetry`, `thiserror`/`anyhow`, `config`/`figment`, `insta`/`proptest`/`testcontainers`/`axum-test`/`wiremock`, `cargo-nextest`. L'i18n est le maillon le moins confortable (pas d'équivalent direct de symfony/translation + ICU), mais l'API Cordeau a peu de texte localisé.

### Cited Findings

- E-mail : **lettre 0.11.23** (2026-08-03), 6,8 M dl/90j, ★2,3k — [crates.io lettre](https://crates.io/crates/lettre)
- i18n : **fluent 0.17.0** (Mozilla Project Fluent, 2025-05-23, 5,7 M dl/90j) ; **rust-i18n 4.2.4** (2026-09-29, 1,3 M dl/90j) — [crates.io fluent](https://crates.io/crates/fluent) ; [crates.io rust-i18n](https://crates.io/crates/rust-i18n)
- Observabilité : **tracing 0.1.44** (205 M dl/90j), **tracing-subscriber 0.3.23** ; **sentry 0.49.3** (2026-09-21) + **sentry-tower 0.49.3** ; **opentelemetry 0.33.0** / **opentelemetry-otlp 0.33.0** / **tracing-opentelemetry 0.34.0** (toujours en 0.x) — [crates.io tracing](https://crates.io/crates/tracing) ; [crates.io sentry](https://crates.io/crates/sentry) ; [crates.io opentelemetry](https://crates.io/crates/opentelemetry)
- Config : **config 0.15.27** (2026-09-30, 19,3 M dl/90j) ; **figment 0.10.19** (2024-05-17, toujours 11 M dl/90j) ; **dotenvy 0.15.7** (2023) — [crates.io config](https://crates.io/crates/config) ; [crates.io figment](https://crates.io/crates/figment)
- Erreurs : **thiserror 2.0.21** (393 M dl/90j), **anyhow 1.0.104**, **snafu 0.9.2** — [crates.io thiserror](https://crates.io/crates/thiserror) ; [crates.io anyhow](https://crates.io/crates/anyhow)
- Tests : **testcontainers 0.28.0**, **axum-test 21.1.0** (4 M dl/90j, ★271, mainteneur JosephLenton), **insta 1.48.0**, **proptest 1.11.0**, **wiremock 0.6.5** (2025-08-24), **rstest 0.27.0**, **mockall 0.15.0**, **fake 5.1.0**, **cargo-nextest 0.9.146** — [crates.io testcontainers](https://crates.io/crates/testcontainers) ; [crates.io axum-test](https://crates.io/crates/axum-test) ; [crates.io insta](https://crates.io/crates/insta) ; [crates.io proptest](https://crates.io/crates/proptest) ; [crates.io wiremock](https://crates.io/crates/wiremock) ; [crates.io cargo-nextest](https://crates.io/crates/cargo-nextest)
- `#[sqlx::test]` et le mode offline font partie de sqlx/sqlx-cli — [sqlx-cli README](https://github.com/transact-rs/sqlx/blob/main/sqlx-cli/README.md)

### Inferences

- La pyramide de tests Cordeau se transpose 1:1 : domaine → tests unitaires purs (+ `proptest` pour les calculs TVA/arrondis, nettement plus puissant que ce qui est courant en PHP) ; use cases → `#[sqlx::test]` (base de test isolée par test) ou testcontainers ; HTTP → `axum-test` ou `tower::ServiceExt::oneshot` sur le `Router` réel ; contrat OpenAPI → snapshot `insta`.
- Pas d'équivalent direct de **Foundry** (factories/stories Doctrine) : `fake` + builders maison. Coût modéré, et un agent écrit bien ce genre de builders.
- phpstan niveau 9 → remplacé « gratuitement » par le compilateur + `clippy` (pedantic sélectif) ; c'est l'un des gains les plus nets.

### Gaps

- Pas de source quantitative sur le temps de `cargo test` d'une API de ~10k lignes avec tests DB (dépend fortement du nombre de crates et du linker).

## Q10 — Déploiement : Docker multi-stage + cargo-chef, taille d'image, Fly.io / Scaleway / Clever Cloud, empreinte mémoire, temps de build CI

### Takeaway

Un binaire Rust donne des images de ~10–25 Mo et une empreinte mémoire très faible, démarrage quasi instantané ; Clever Cloud a un runtime Rust natif, Fly.io/Scaleway prennent n'importe quelle image Docker. Le prix est payé en **temps de compilation** (CI et boucle agent), qu'il faut gérer dès le départ (cargo-chef, cache CI, sccache, workspace découpé). Les dépendances natives (libheif, polices Typst) empêchent l'image `scratch` et ajoutent un peu de taille.

### Cited Findings

- **cargo-chef 0.1.78** (2026-08-12) : `prepare` → `recipe.json`, `cook` met en cache les dépendances dans une couche Docker, « up to 5x measured on some commercial projects » ; prend en charge automatiquement les crates d'un workspace ; limite : `cook` et `build` doivent tourner dans le même répertoire, les dépendances locales hors projet sont recompilées — [GitHub LukeMathWalker/cargo-chef](https://github.com/LukeMathWalker/cargo-chef)
- **sccache 0.18.0** (2026-09-16, Mozilla) — [crates.io sccache](https://crates.io/crates/sccache)
- Tailles d'images Rust : ~5–12 Mo en `scratch` (binaire statique), ~20–22 Mo en distroless, ~11 Mo en Alpine — [OneUptime : minimal Docker images for Rust](https://oneuptime.com/blog/post/2026-01-07-rust-minimal-docker-images/view) (secondaire)
- Référence PHP : PHP-FPM ~20–25 Mo au repos ; FrankenPHP ~60 Mo (sans worker) et ~85 Mo (worker) au repos ; sous charge FrankenPHP worker ~100–120 Mo — [Medium : FrankenPHP vs PHP-FPM (Part 3)](https://medium.com/@vulke/frankenphp-vs-php-fpm-part-3-cpu-memory-and-the-hidden-cost-of-doing-nothing-92bfee7b00a5) (secondaire)
- **Clever Cloud** : runtime Rust natif ; build `cargo build --release --locked`, écoute sur `0.0.0.0:8080`, `Cargo.lock` commité, `CC_RUST_BIN` pour choisir le binaire ; premier déploiement ~3–5 min, suivants plus rapides grâce au cache — [Clever Cloud docs : Rust](https://www.clever.cloud/developers/doc/applications/rust/)
- Loco 1.1 ajoute une cible de déploiement Lambda (« Loco's router is a `tower::Service` ») — [Releases loco](https://github.com/loco-rs/loco/releases)
- MSRV récents : sqlx 0.9 et SeaORM 2.0/Loco 1.0 exigent **Rust 1.94** — [sqlx CHANGELOG](https://github.com/transact-rs/sqlx/blob/main/CHANGELOG.md) ; [Release loco v1.0.0](https://github.com/loco-rs/loco/releases/tag/v1.0.0)

### Inferences

- Image cible réaliste pour Cordeau : `debian:trixie-slim` (ou distroless cc) + libheif ≥ 1.22 compilée + polices → probablement 60–120 Mo, toujours bien moins que l'image PHP + Imagick + extensions. Machine Fly/Scaleway de 256 Mo suffisante pour l'API (à mesurer).
- Cold start : binaire natif → démarrage en millisecondes, ce qui rend viables les offres « scale-to-zero » (Scaleway Serverless Containers, Fly machines auto-stop) sans pénalité notable.
- CI : sans cache, une compilation release d'un workspace Axum+sqlx+aws-sdk-s3+typst se compte en minutes ; avec cargo-chef + cache des registres/target (ex. `Swatinem/rust-cache`, non vérifié ici) + `cargo-nextest`, les PR incrémentales restent raisonnables. `aws-sdk-s3` et `typst` sont des dépendances lourdes à compiler : envisager `object_store` pour R2 et isoler Typst dans un crate dédié.
- Boucle agent : `cargo check` incrémental est rapide sur un workspace découpé ; les requêtes `sqlx::query!` exigent une base ou le cache `.sqlx` à jour — à intégrer dans les instructions agent (CLAUDE.md).

### Gaps

- Pas trouvé de mesure fiable 2026 de RSS au repos d'une API Axum réelle (les chiffres « quelques Mo » circulent mais sans source primaire solide).
- Pas vérifié la doc Fly.io et Scaleway spécifique à Rust (les deux étant basés sur des images OCI, aucun blocage attendu).
- Pas de mesure des temps de build CI pour un projet de taille comparable.

## Q11 — Ce qu'API Platform donne « gratuitement » et qu'il faut écrire en Rust (et combien)

### Takeaway

Parce que Cordeau n'utilise ni `ApiFilter` ni groupes de sérialisation et code déjà sa logique dans 16 Providers/Processors, l'écart est **plus faible qu'on pourrait le croire** : il faut surtout écrire un petit socle transverse (pagination, format d'erreur Problem Details, mapping d'exceptions, validation → 422, merge-patch, doc OpenAPI annotée). Ordre de grandeur estimé : quelques centaines de lignes de socle + ~20–40 lignes d'annotations/DTO par endpoint.

### Cited Findings

- Usage actuel mesuré dans le dépôt : 16 classes `ProviderInterface`/`ProcessorInterface`, 4 `#[ApiResource]`, 0 `ApiFilter`/`SearchFilter`/`OrderFilter`, 0 `normalizationContext`/`#[Groups]`, 2 handlers Messenger, 9 fichiers avec contraintes `Assert\` ; config : formats `json`/`jsonld`, `merge-patch+json`, `exception_to_status`, Swagger UI désactivée en prod — [apps/api/config/packages/api_platform.yaml](../../../../apps/api/config/packages/api_platform.yaml)
- Problem Details RFC 9457 disponible via `problem_details` 0.10.0 — [crates.io problem_details](https://crates.io/crates/problem_details)
- Pagination « clé en main » côté Loco : `PageResponse<T>` avec `PagerMeta` (page, page_size, total) — [Release loco v1.0.0](https://github.com/loco-rs/loco/releases/tag/v1.0.0)
- Requêtes dynamiques (filtres/tri) : `sea-query` 1.0.2 ; `modql` 0.5.0 (filtres déclaratifs, 5,5k dl/90j, niche) — [crates.io sea-query](https://crates.io/crates/sea-query) ; [crates.io modql](https://crates.io/crates/modql)
- Multipart typé : `axum_typed_multipart` 0.16.7 — [crates.io axum_typed_multipart](https://crates.io/crates/axum_typed_multipart)

### Inferences

- Socle à écrire une fois (estimation, non sourcée) : type `ApiError` + `IntoResponse` en `application/problem+json` avec mapping des erreurs domaine (~100–150 lignes) ; extracteur `ValidatedJson<T>` produisant des violations champ par champ en 422 (~80 lignes) ; `Page<T>`/`PageQuery` + helper SQL `LIMIT/OFFSET`+`COUNT` (~80 lignes) ; extracteur `CurrentUser` (cookie + bearer, ~150 lignes) ; support `merge-patch+json` via `Option<Option<T>>`/`serde_with` (~50 lignes) ; export OpenAPI + Scalar en dev (~40 lignes). Total ≈ 500–700 lignes, écrites et testées une fois.
- Ce qui **disparaît** : JSON-LD/Hydra (non utilisé par les clients a priori), les opérations CRUD auto-générées, l'IRI, le content negotiation multi-formats. Ce qui devient **plus verbeux** : chaque endpoint déclare explicitement ses DTO entrée/sortie et ses annotations OpenAPI — verbeux mais très agent-friendly (motif répétitif, vérifié par le compilateur).
- Le back-office web a besoin de filtres/tri/recherche sur les listes (Chantiers, Clients, Devis) : à écrire par ressource avec `sea-query` ou SQL dynamique — c'est le principal poste où API Platform aurait fait gagner du temps si Cordeau l'avait utilisé.

### Gaps

- Pas d'équivalent Rust identifié d'un « API Platform » complet (ressource déclarative → CRUD + filtres + OpenAPI + Hydra). Loco s'en approche par génération de code (scaffold), pas par déclaration.

## Q12 — Fit pour un dev solo piloté par agent (Claude Code)

### Takeaway

Les signaux 2026 sont favorables mais pas univoques : le compilateur Rust fournit un retour précis que les agents exploitent bien, et Loco publie désormais un « skill » agent ; en revanche les benchmarks montrent que les agents résolvent encore difficilement les tâches de niveau dépôt en Rust à cause des traits/types, et le temps de compilation ralentit la boucle.

### Cited Findings

- **Rust-SWE-bench** : 500 tâches réelles sur 34 dépôts Rust ; agents ReAct de base jusqu'à **21,2 %** de résolution, RustForger (Claude 3.7 Sonnet) **28,6 %** ; difficultés principales : « comprehending repository-wide code structure and complying with Rust's strict type and trait semantics » — [arXiv 2602.22764](https://arxiv.org/abs/2602.22764)
- Étude sur 34 moteurs d'échecs dans 17 langages : « Programming language is no longer about whether AI teammates can build a working system, but about performance, cost, what gets built, and how much human supervision validation still needs » — [arXiv 2606.13763](https://arxiv.org/abs/2606.13763)
- Loco 1.2 « Ships a Loco skill so coding agents write idiomatic Loco without guessing » ; guide d'upgrade avec prompt Claude Code — [Releases loco](https://github.com/loco-rs/loco/releases) ; [Loco Upgrades](https://loco.rs/docs/extras/upgrades/)
- Témoignages (secondaires, non vérifiés) : réécriture du runtime d'agent GitHub Copilot en Rust assistée par agents (annonce rapportée au 2026-09-16) ; arguments « le compilateur comme garde-fou des agents » — [Techscoop Substack](https://techscoop.substack.com/p/github-used-ai-agents-to-rewrite) ; [HAMY : Why Rust Wins in the Age of AI](https://hamy.xyz/blog/2026-04_rust-age-of-ai)

### Inferences

- Pour une API CRUD + domaine riche en architecture hexagonale, les difficultés pointées par Rust-SWE-bench (traits génériques complexes, structure de dépôt) sont **atténuées** si l'on évite la généricité excessive : ports en `trait` simples (`async fn` dans les traits, stable depuis Rust 1.75), `Arc<dyn Port>` dans l'état Axum, pas de macros maison.
- Préférer des crates **massivement utilisées** (axum, sqlx, serde, tracing, thiserror) : les modèles ont beaucoup d'exemples, donc moins d'hallucinations d'API. Les crates à faible adoption (aide alpha, apalis RC, libheif-rs, en16931) sont exactement celles où un agent devinera mal les API → épingler les versions et donner la doc en contexte (Context7).
- Le churn de versions (Axum 0.9 à venir, apalis RC, utoipa 6) est le principal piège agent : l'agent risque de mélanger des API de versions différentes ; à cadrer par `Cargo.lock` + notes de version dans CLAUDE.md.

### Gaps

- Pas de benchmark comparant directement la productivité d'agents sur une API web Rust vs PHP/Symfony.
- L'annonce GitHub Copilot n'a été vue que via une source secondaire.

## Synthèse — Stacks cohérentes recommandées

### Takeaway

**Stack A (recommandée, « explicite ») : Axum 0.8 + SQLx 0.9 + rust_decimal + utoipa 6 + tower-sessions + openidconnect + argon2 + file Postgres (maison ou apalis-postgres) + aws-sdk-s3/object_store + image/fast_image_resize + libheif-rs + Typst + lettre + tracing/Sentry, en workspace Cargo hexagonal.** **Stack B (« batteries ») : Loco 1.2** (Axum + SeaORM 2 + jobs + storage pré-signé + loco-openapi), au prix de ses conventions (JWT, SeaORM, Tera) qui divergent de l'existant.

### Cited Findings

- Adoption et santé des briques de la stack A : axum 125 M dl/90j ; sqlx 41 M ; rust_decimal 37 M ; utoipa 16 M ; tower-sessions 926k ; openidconnect 4,3 M ; argon2 21 M ; aws-sdk-s3 22 M ; image 55 M ; typst-pdf 1,2 M ; lettre 6,8 M ; sentry 13 M — [crates.io](https://crates.io/) (API relevée le 2026-10-01)
- Loco 1.x : SeaORM 2.0 + sqlx 0.9, files à priorité, PageResponse, storage pré-signé, skill agent, multi-tenancy, OpenAPI via utoipa — [Release v1.0.0](https://github.com/loco-rs/loco/releases/tag/v1.0.0) ; [Releases loco](https://github.com/loco-rs/loco/releases) ; [loco-openapi](https://github.com/loco-rs/loco-openapi)

### Inferences

- **Stack A – détail** :
  - HTTP : `axum` 0.8 (+ `tower-http` : CORS, compression, timeouts, request-id ; `axum-extra` : cookies) ; migrer vers 0.9 dès sa sortie.
  - Persistance : `sqlx` 0.9 (`query!`/`query_as!` + `.sqlx` offline), `sqlx migrate`, `sea-query` pour les listes filtrables, `rust_decimal` (TVA/montants), `uuid`, `jiff` ou `chrono`.
  - Contrat : `utoipa` 6 + `utoipa-axum` + `utoipa-scalar` (dev) → `openapi.json` 3.1 → `openapi-typescript` (inchangé) ; snapshot `insta` du spec en CI.
  - Domaine : `nutype`, `thiserror`, enums/typestate ; `garde` (ou `validator`) aux bords.
  - Auth : `tower-sessions` + store sqlx (ou Redis), tokens opaques maison (hash SHA-256), `openidconnect` 4 (Google), `password-auth`/`argon2`, CSRF par `Origin`/`Sec-Fetch-*` + SameSite, rate limit au proxy ou `tower_governor`.
  - Jobs : table Postgres + `SKIP LOCKED` (ou `apalis-postgres` épinglé), `tokio-cron-scheduler` pour le planifié.
  - Fichiers : `aws-sdk-s3` (ou `object_store`) pour R2 pré-signé ; `image` + `fast_image_resize` ; `libheif-rs` sur libheif ≥ 1.22, décodage isolé.
  - PDF : `typst` embarqué (devis/factures, PDF/A-3 pour Factur-X) ; XML CII via `quick-xml` + types maison ; validation Factur-X externe.
  - Transverse : `tracing` + `sentry`(-tower) + `opentelemetry-otlp`, `config`, `secrecy`, `lettre`, `fluent` si besoin.
  - Tests/outillage : `cargo-nextest`, `#[sqlx::test]`, `axum-test`, `insta`, `proptest`, `wiremock`, `rstest`, `fake`, `clippy`, `cargo-deny`.
  - Build/déploiement : workspace (crate par couche × bounded context), `cargo-chef` multi-stage, cache CI, image slim ; Clever Cloud (runtime Rust natif) ou Scaleway/Fly (OCI).
- **Pourquoi A plutôt que B pour Cordeau** : A respecte l'architecture hexagonale existante (domaine pur, ports/adapters), garde l'auth actuelle (cookie + opaque), et ne dépend d'aucun méta-framework pré-mature ; B fait gagner du temps sur le socle (jobs, storage, pagination, scaffolding) mais impose SeaORM dans les modèles, un `AppContext` global et un style Rails moins adapté au domaine riche Lot/Mesure/Devis. B reste pertinent pour un prototype rapide ou si l'on veut minimiser le code « plomberie ».
- Les points de fragilité communs aux deux : utoipa (bus factor 1), libheif-rs (bus factor 1 + CVE C++), file de jobs (pas de standard stable), e-facture (rien de mature).

### Gaps

- Aucune des deux stacks n'a été prototypée dans cette étude ; les estimations de lignes de socle (Q11) sont des ordres de grandeur non mesurés.

## Ce qui n'a PAS de bon équivalent Rust (liste honnête)

### Takeaway

Les vrais trous sont : (1) un **API Platform déclaratif** complet, (2) un **Messenger** stable et standard, (3) une pile **Factur-X/EN 16931 + validation Schematron** mature, (4) un **décodeur HEIC en Rust pur**, (5) des **factories de test type Foundry**, (6) un **framework de sécurité** unifié et audité comme Symfony Security ; l'i18n ICU complète et l'admin auto-générée sont secondaires pour Cordeau.

### Cited Findings

- Pas de framework Rust « ressource déclarative → CRUD + filtres + OpenAPI + Hydra » identifié ; le plus proche est Loco (scaffolding + loco-openapi) — [Releases loco](https://github.com/loco-rs/loco/releases) ; [loco-openapi](https://github.com/loco-rs/loco-openapi)
- Jobs : apalis toujours en 1.0 RC avec API breaking en rc.10 (2026-09-15) ; underway 0.2 (6k dl/90j), sqlxmq 0.6 (15k dl/90j), graphile_worker ★84 — [Releases apalis](https://github.com/apalis-dev/apalis/releases) ; [crates.io underway](https://crates.io/crates/underway) ; [crates.io sqlxmq](https://crates.io/crates/sqlxmq) ; [GitHub graphile_worker_rs](https://github.com/leo91000/graphile_worker_rs)
- E-facture : en16931 (★3, créé 2026-07-30, 1 contributeur), core-invoice (★0, créé 2026-08-30), zugferd 0.1.7 (★21) — [GitHub hupe1980/en16931](https://github.com/hupe1980/en16931) ; [GitHub proxeon/core-invoice](https://github.com/proxeon/core-invoice) ; [crates.io zugferd](https://crates.io/crates/zugferd)
- HEIC : uniquement via `libheif-rs` (binding C++, ★61), libheif touchée par plusieurs CVE en 2026 corrigées en 1.22.0 — [GitHub Cykooz/libheif-rs](https://github.com/Cykooz/libheif-rs) ; [Red Hat CVE-2026-32741](https://access.redhat.com/security/cve/cve-2026-32741)
- Auth : pile session/login portée par un mainteneur principal (tower-sessions, axum-login), CSRF fragmenté, rate limiter peu actif — [GitHub maxcountryman/tower-sessions](https://github.com/maxcountryman/tower-sessions) ; [crates.io search « axum csrf »](https://crates.io/search?q=axum%20csrf) ; [GitHub benwis/tower-governor](https://github.com/benwis/tower-governor)
- OpenAPI : utoipa, la référence, dépend à ~90 % des commits d'un seul auteur — [GitHub juhaku/utoipa](https://github.com/juhaku/utoipa)

### Inferences

- Classement par impact pour Cordeau : **Factur-X/EN 16931** (impact fort en V2, obligation légale ; prévoir un validateur externe ou un service PDP qui génère/valide) > **jobs** (impact moyen, contournable par une file Postgres maison) > **HEIC** (impact moyen, même risque qu'aujourd'hui avec Imagick) > **pagination/filtres auto** (impact faible, Cordeau ne les utilise pas) > **Foundry** (faible, builders maison) > **Symfony Security** (moyen en risque, faible en code : la surface auth de Cordeau est petite et bien délimitée).
- Doctrine (unit-of-work, lazy loading) n'est pas « manquant » au sens d'un trou : SQLx est un choix délibéré plus explicite ; SeaORM 2 couvre le besoin ORM si nécessaire.
- Le LLM (V1.2+) n'est pas un trou : appels HTTP aux API (SDK communautaires ou `reqwest`) — hors périmètre de cette note.

### Gaps

- Pas évalué ici : SDK Rust officiels pour les fournisseurs LLM, ni les intégrations PDP françaises (APIs des plateformes) — à couvrir par une autre note si nécessaire.
