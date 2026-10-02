# Rust côté front web, mobile et sync offline-first pour Cordeau — état au 2026-10-01

> Notes de recherche (périmètre : web React, mobile Expo, couche de sync offline, AR V2). Toutes les versions et dates ont été vérifiées le 2026-10-01 via `gh api`, crates.io, npm et les sources web citées. Légende maturité : **[PROD]** = prouvé en production par des acteurs identifiés ; **[BETA]** = utilisable mais auto-déclaré beta / pré-1.0 instable ; **[EXP]** = démo / expérimental / auto-déclaré « pas pour la prod ».
>
> Contexte projet rappelé : web Vite + React 19 + TanStack + shadcn/ui ; mobile Expo SDK 56 (RN 0.85) + expo-sqlite + Drizzle + outbox maison ; builds EAS ; API Symfony (PHP 8.5) ; dev solo assisté par Claude Code.

## Q1 — Frameworks UI web en Rust (Leptos, Dioxus, Yew, Sycamore) : maturité, écosystème vs shadcn/ui, SSR, taille de bundle, DX, tests, aptitude des agents LLM

### Takeaway

Aucun framework UI web Rust n'offre en octobre 2026 un remplacement crédible à React + shadcn/ui pour Cordeau : Leptos (le plus abouti techniquement) est officiellement « lightly maintained » depuis mai 2026, Dioxus est actif mais en pleine transition 0.7 → 0.8 (alpha), Yew et Sycamore ont une vélocité faible, et l'écosystème de composants équivalent à shadcn/ui est maigre (le port de référence a été archivé en février 2026). Option A côté web = **[BETA]** au mieux, avec un risque de pérennité élevé pour un projet solo.

### Cited Findings

**Leptos**

- Dernière version stable : v0.8.21 publiée le 2026-09-26 ; une v0.9.0-beta existe depuis le 2026-07-18 (pré-release) ; 21,3k étoiles, dernier push 2026-09-30, licence MIT — [GitHub API leptos-rs/leptos](https://github.com/leptos-rs/leptos/releases) (vérifié via `gh api` le 2026-10-01).
- Le créateur (gbj) a publié le 2026-05-08 un « Status Update - May 2026 » : « Leptos is not abandoned but will be lightly maintained going forward. I consider it feature-complete and do not expect to do significant new development in the future. I am open to additional maintainers » — [leptos-rs/leptos#4707](https://github.com/leptos-rs/leptos/issues/4707).
- Dans ce même post, gbj attribue en partie ce recul aux agents de code : « The rise of LLMs and of coding agents has really fundamentally changed the cost-benefit analysis on this for me… the replacement of human-to-human conversation over issues and PRs with replies directly copied-and-pasted from an agent » ; il indique qu'une bibliothèque de composants est « best developed in the community » — [leptos-rs/leptos#4707](https://github.com/leptos-rs/leptos/issues/4707).
- La branche 0.9 contient des changements semver-breaking (ex. les signaux n'implémentent plus directement `Fn()` sur nightly et déréférencent vers `dyn Fn()` ; props `impl SignalOrFn` ; lazy-loading derrière un feature flag `lazy`) — [newreleases.io, leptos 0.9.0-beta](https://newreleases.io/project/github/leptos-rs/leptos/release/v0.9.0-beta) (agrégateur reprenant les release notes).
- Téléchargements crates.io récents (fenêtre 90 j) : leptos ≈ 1,34 M — [crates.io API leptos](https://crates.io/crates/leptos) (vérifié 2026-10-01).

**Dioxus**

- Dernière stable : v0.7.10 (2026-07-30) ; v0.8.0-alpha.1 publiée le 2026-07-31 ; 39,3k étoiles, Apache-2.0, dernier push 2026-09-30 — [GitHub DioxusLabs/dioxus releases](https://github.com/dioxuslabs/dioxus/releases) (vérifié via `gh api`).
- Dioxus 0.7 (sortie 2025-09-08) a apporté : hot-patching Rust (« subsecond ») sur web/desktop/mobile, bibliothèque de 28 composants first-party basés sur Radix Primitives (non stylés, ARIA, variantes « shadcn-style » en copier-coller), bundle splitting WASM (`#[wasm_split]`), et un `llms.txt` first-party généré depuis la doc pour limiter les hallucinations des LLM ; le renderer natif Blitz est décrit comme « young » / « work in progress » — [Dioxus 0.7 release blog](https://dioxuslabs.com/blog/release-070/).
- Le dépôt de composants `DioxusLabs/dioxus-components` : 345 étoiles, dernier push 2026-09-08 — [GitHub API](https://github.com/DioxusLabs/dioxus-components) (vérifié via `gh api`).
- La roadmap 0.8 insiste sur le « polishing » (leçon tirée de la 0.7), vise à passer le cœur en « 1.0 » pour des releases majeures plus régulières, mais ne donne pas de date — [Dioxus 0.8 Roadmap, discussion #5024](https://github.com/DioxusLabs/dioxus/discussions/5024).
- Téléchargements crates.io récents : dioxus ≈ 1,07 M — [crates.io dioxus](https://crates.io/crates/dioxus).

**Yew / Sycamore**

- Yew : v0.23.0 le 2026-03-10 (yew-router 0.20.0) ; 32,8k étoiles ; ≈ 428k téléchargements récents — [GitHub yewstack/yew](https://github.com/yewstack/yew/releases) ; [crates.io yew](https://crates.io/crates/yew).
- Sycamore : v0.9.3 le 2026-08-31, la précédente (0.9.2) datait du 2025-09-23 et la 0.9.1 du 2024-11-17 — rythme d'une release par an ; 3,4k étoiles ; ≈ 20k téléchargements récents — [GitHub sycamore-rs/sycamore](https://github.com/sycamore-rs/sycamore/releases) ; [crates.io sycamore](https://crates.io/crates/sycamore).

**Écosystème de composants vs shadcn/ui**

- `RustForWeb/shadcn-ui` (port Rust de shadcn/ui pour Leptos et Yew) a été **archivé le 2026-02-02** — [GitHub RustForWeb/shadcn-ui](https://github.com/RustForWeb/shadcn-ui) (confirmé `archived=true` via `gh api`).
- Alternatives restantes : `cloud-shuttle/leptos-shadcn-ui` (71 étoiles, dernier push 2026-03-21), `shadcn-leptos` (pré-alpha, « API under active design »), `thaw` (610 étoiles, dernier push 2026-05-07) — [lib.rs leptos-shadcn-ui](https://lib.rs/crates/leptos-shadcn-ui) ; [crates.io shadcn-leptos](https://crates.io/crates/shadcn-leptos) ; activité vérifiée via `gh api`.

**Taille de bundle (sources secondaires, à prendre avec prudence)**

- Leptos : app minimale ≈ 100 KB gzip, app typique avec routing ≈ 180–280 KB gzip ; Dioxus 0.6 ≈ 120–300 KB gzip ; un benchmark dashboard cite 86 KB (Leptos) vs 210 KB (Dioxus) de WASM gzip — [reintech.io, comparatif 2026](https://reintech.io/blog/leptos-vs-yew-vs-dioxus-rust-frontend-framework-comparison-2026) ; [rustify.rs Leptos vs Dioxus 2026](https://rustify.rs/articles/leptos-vs-dioxus-rust-frontend-2026). Ces chiffres viennent de blogs non primaires sans méthodo publiée.

**Aptitude des agents LLM**

- Dioxus reconnaît le problème et livre un `llms.txt` ; il précise que le « vibe coding » est possible mais déconseillé pour du code critique — [Dioxus 0.7 release blog](https://dioxuslabs.com/blog/release-070/).
- Source secondaire : les outils IA « hallucinate lifetimes and outdated crates in Rust » ; l'écart entre churn de l'écosystème et cycles d'entraînement oblige à « a second pass » — [qwe.edu.pl, Best AI tools for Rust](https://www.qwe.edu.pl/tutorial/best-ai-tools-rust-programming/) (blog, qualité moyenne).

### Inferences

- Pour Cordeau (back-office web ≈ 4,4k lignes React, shadcn/ui, Vitest Browser Mode), migrer vers Leptos/Dioxus ferait perdre : l'écosystème shadcn/Radix complet, TanStack Router/Query, l'outillage de test navigateur mature, et la densité d'exemples dans les données d'entraînement des LLM. Le gain (types Rust partagés, WASM) ne compense pas, surtout que le back-office web n'a pas de besoin de calcul lourd.
- Le fait que le framework Rust web le plus abouti passe en maintenance légère **à cause, en partie, des agents** est un signal fort pour un projet « Claude Code comme développeur principal » : l'agent écrira du code pour un framework dont les questions/réponses humaines s'assèchent.
- Le churn (Leptos 0.8 → 0.9 breaking, Dioxus 0.7 → 0.8 alpha) se combine mal avec la coupure de connaissance des modèles : l'agent produira par défaut l'API de la version qu'il connaît.
- Dioxus est le seul candidat crédible à moyen terme (financement, cadence, composants first-party, `llms.txt`), mais reste pré-1.0.

### Gaps

- Aucune mesure primaire et reproductible de taille de bundle Leptos/Dioxus vs une app React+shadcn équivalente n'a été trouvée.
- Pas de benchmark public sur le taux de réussite des LLM (Claude, GPT) sur du code Leptos/Dioxus vs React ; uniquement des témoignages.
- Outillage de test (équivalent Vitest Browser Mode / Testing Library) pour Leptos/Dioxus non investigué en détail.

## Q2 — UI mobile en Rust (Dioxus mobile, Tauri 2 mobile, Slint, Makepad, egui) : production readiness iOS/Android, stores, accès caméra / secure storage / SQLite / tâches de fond, offline

### Takeaway

Aucune option d'UI mobile 100 % Rust n'est prouvée en production pour une app métier mobile-first comparable à Cordeau. Dioxus mobile rend dans une WebView et n'a pas encore d'API natives (caméra, stockage sécurisé) — prévues pour 0.8 ; Tauri 2 mobile fonctionne mais avec des « rough edges » et Tauri 3 est en alpha ; Slint et Makepad sont natifs-GPU mais orientés embarqué / démo (Robrix en 1.0.0-beta). Option A côté mobile = **[EXP]/[BETA]**, régression nette par rapport à Expo.

### Cited Findings

**Dioxus mobile**

- Les apps mobiles Dioxus sont « rendered with either the platform's WebView or experimentally with WGPU » ; « native Android animations and widgets aren't currently supported » ; la doc mobile ne couvre pas l'accès caméra / stockage sécurisé / SQLite / tâches de fond — [Dioxus 0.7 mobile guide](https://dioxuslabs.com/learn/0.7/guides/platforms/mobile/).
- Dioxus 0.7 permet de personnaliser `Info.plist` et `AndroidManifest.xml` depuis `Dioxus.toml`, ouvre automatiquement les simulateurs, supporte iPad — [Dioxus 0.7 release blog](https://dioxuslabs.com/blog/release-070/).
- La roadmap 0.8 **prévoit** (pas encore livré) : « High-quality Swift/Kotlin/Java FFI system », « Native API support for camera, location, storage, and OAuth », intégration de widgets SwiftUI/Kotlin — [Dioxus 0.8 Roadmap #5024](https://github.com/DioxusLabs/dioxus/discussions/5024).
- Dioxus revendique la publication directe vers App Store / Play Store et des apps en production chez Huawei (affirmation marketing non détaillée) — [Dioxus deploy tutorial](https://dioxuslabs.com/learn/0.7/tutorial/deploy/) ; [YC Launch Dioxus Labs](https://www.ycombinator.com/launches/JBK-dioxus-labs-web-desktop-and-mobile-apps-with-one-codebase).

**Tauri 2 mobile**

- Tauri v2 stable depuis le 2024-10-02 ; ligne 2.11 en 2026 (2.11.5 le 2026-07-01) — [Wikipedia Tauri](<https://en.wikipedia.org/wiki/Tauri_(software_framework)>) ; [rustify.rs Tauri v2 2026](https://rustify.rs/articles/rust-tauri-v2-desktop-app-tutorial-2026).
- **Tauri 3 est en alpha** : `tauri-v3.0.0-alpha.4` publié le 2026-10-01 ; la v3 alpha déplace notamment `tauri::android_binding!` vers `tauri_runtime_wry::android_binding!` et change le format des URL de scheme (breaking pour Android) — [GitHub tauri-apps/tauri releases](https://github.com/tauri-apps/tauri/releases) (vérifié `gh api`) ; [tauri-cli v3.0.0-alpha.0](https://github.com/tauri-apps/tauri/releases/tag/tauri-cli-v3.0.0-alpha.0).
- Plugins officiels supportant Android+iOS : barcode-scanner (caméra pour codes-barres uniquement), biometric, geolocation, haptics, NFC, notification, SQL, store, upload. **Stronghold (stockage chiffré) n'est pas supporté sur mobile ; pas de plugin officiel de tâches de fond ; pas de plugin caméra/photo générique** — [Tauri plugins index](https://v2.tauri.app/plugin/).
- Évaluation secondaire : desktop « mostly production-ready » (Spacedrive, AppFlowy…), mais iOS/Android « newer… you'll hit rough edges around plugin support, signing, and webview quirks… if your product is mobile-first and mobile-only, Flutter or React Native are still recommended » — [CODERCOPS, Tauri 2.0 desktop and mobile (2026)](https://blog.codercops.com/blog/tauri-2-desktop-mobile-apps-rust-web-2026) (blog, non primaire).
- Signal d'écosystème : PowerSync a publié `tauri-plugin-powersync@0.1.0` le 2026-10-01 — [GitHub powersync-ja/powersync-js releases](https://github.com/powersync-ja/powersync-js/releases) (vérifié `gh api`).

**Slint**

- v1.18.1 le 2026-09-21 ; 24k étoiles — [GitHub slint-ui/slint releases](https://github.com/slint-ui/slint/releases) (vérifié `gh api`).
- Android officiellement supporté pour Rust depuis Slint 1.5 ; port iOS introduit en 1.12 ; la 1.15 (2026-02-04) améliore safe area et clavier virtuel ; iOS/Android restent Rust-only (C++ iOS en PR en septembre 2026) — [Slint 1.5](https://slint.dev/blog/slint-1.5-released) ; [Slint 1.12](https://slint.dev/blog/slint-1.12-released) ; [Slint 1.15](https://slint.dev/blog/slint-1.15-released) ; [PR #13580](https://github.com/slint-ui/slint/pull/13580).

**Makepad**

- 7,1k étoiles, dépôt très actif (push 2026-10-01) mais aucune release GitHub depuis une « pre-alpha » de 2019 ; crate `makepad-widgets` 1.0.0 publiée le 2025-05-13 ; ≈ 1,4k téléchargements récents — [GitHub makepad/makepad](https://github.com/makepad/makepad) ; [crates.io makepad-widgets](https://crates.io/crates/makepad-widgets) (vérifié 2026-10-01).
- App vitrine : Robrix (client Matrix en Rust + Makepad + Robius), 1.0.0-beta.1 disponible pour macOS/Linux/Windows/Android/iOS, présentée à la Matrix Conference 2026 — [robrix.app](https://robrix.app/) ; [Matrix Conference 2026 talk](https://cfp.2026.matrix.org/matrix-conference-2026/talk/PUQNED/) ; repo 488 étoiles, push 2026-10-01 (`gh api`).

**egui**

- v0.36.2 le 2026-09-08, 30,8k étoiles, ≈ 5,7 M téléchargements récents (surtout desktop/outils) — [GitHub emilk/egui](https://github.com/emilk/egui/releases) ; [crates.io egui](https://crates.io/crates/egui).

### Inferences

- Cordeau dépend de capacités mobiles que l'écosystème Expo fournit déjà prêtes à l'emploi : caméra + upload photo natif, `expo-secure-store`, `expo-sqlite`, builds EAS, OTA. Aucune solution UI Rust n'offre aujourd'hui l'équivalent complet ; il faudrait écrire soi-même des ponts Swift/Kotlin, ce qui est précisément ce qu'Expo évite.
- Dioxus mobile et Tauri mobile étant des WebViews, le gain « natif » par rapport à React Native est nul voire négatif (UX terrain, gestes, clavier), et l'on perd NativeWind/expo-router.
- Slint/Makepad/egui dessinent eux-mêmes leurs widgets (GPU) : accessibilité, saisie texte, comportements natifs iOS/Android à valider au cas par cas — risque UX élevé pour des artisans peu technophiles.
- Tauri 3 alpha + Dioxus 0.8 alpha simultanés à l'automne 2026 = churn d'API garanti pendant la fenêtre V1 de Cordeau (septembre 2026 → 2027).

### Gaps

- Pas trouvé de source primaire listant des apps métier Dioxus/Tauri mobiles en production sur les stores avec retour d'expérience (taux de rejet App Store, perfs).
- Licences Slint (GitHub renvoie `NOASSERTION`) non vérifiées dans cette passe — à confirmer avant toute adoption commerciale.
- Support mobile d'egui (iOS/Android) et accès aux API natives non documentés par une source fiable trouvée.
- Tâches de fond (background sync) en Rust UI : aucune source trouvée.

## Q3 — « Rust core » partagé (Crux, UniFFI, uniffi-bindgen-react-native, wasm-bindgen, typeshare/specta) et exemples de production (1Password, Mozilla, Signal, Bitwarden, Proton, Figma, Cloudflare…)

### Takeaway

Le pattern « cœur Rust partagé + UI native par plateforme » est **[PROD]** chez de grands acteurs (1Password, Mozilla, Signal, Bitwarden, Proton, Element/Matrix, Cloudflare), mais quasiment toujours avec des UI **Swift/Kotlin natives** (UniFFI ou JNI/Swift) et WASM côté web — pas avec React Native. Le pont spécifique Rust ↔ React Native (`uniffi-bindgen-react-native`) se déclare lui-même « early development, should not yet be used in production » au 2026-10-01 ; Crux est « production ready » mais pré-1.0 sans shell React Native documenté. Pour Cordeau, l'option B est donc **[BETA]** sur le maillon critique (RN/Expo).

### Cited Findings

**UniFFI (Mozilla)**

- crate `uniffi` 0.32.2 (2026-09-23), ≈ 4,5 M téléchargements récents ; repo 5k étoiles, MPL-2.0 — [crates.io uniffi](https://crates.io/crates/uniffi) ; [GitHub mozilla/uniffi-rs](https://github.com/mozilla/uniffi-rs).
- UniFFI est « used extensively by Mozilla in Firefox mobile and desktop browsers » ; Mozilla a développé UniFFI après avoir constaté que les wrappers écrits à la main dans plusieurs langages étaient « a huge time-sink » et « responsible for many serious bugs » — [Mozilla Hacks, Autogenerating Rust-JS bindings with UniFFI (2023-08)](https://hacks.mozilla.org/2023/08/autogenerating-rust-js-bindings-with-uniffi/).
- Application Services (composants Rust sync/stockage : comptes, historique, logins) est consommé par Firefox Android (Fenix) et Firefox iOS ; inconvénients reconnus : « more work than creating a single implementation » et nécessité que les ingénieurs écrivent et comprennent Rust — [Firefox Source Docs, Developing Shared Rust Components](https://firefox-source-docs.mozilla.org/rust-components/developing-rust-components/index.html) ; [GitHub mozilla/application-services](https://github.com/mozilla/application-services).

**uniffi-bindgen-react-native (ubrn)**

- Génère TypeScript + C++ JSI (Turbo Module) pour React Native, plus une cible WASM pour le web et N-API pour Node ; prévu d'être renommé `uniffi-bindgen-javascript` ; dirigé par James Hugman, financé par Filament, Mozilla et LiveKit ; 552 étoiles ; MPL-2.0 — [GitHub jhugman/uniffi-bindgen-react-native](https://github.com/jhugman/uniffi-bindgen-react-native).
- La doc officielle indique toujours : « **This project is still in early development, and should not yet be used in production.** » — [docs/src/README.md (ubrn)](https://jhugman.github.io/uniffi-bindgen-react-native/) (vérifié dans le source du repo le 2026-10-01).
- Version courante 0.31.0-6 (2026-09-25), numérotation de type pré-release — [npm uniffi-bindgen-react-native](https://www.npmjs.com/package/uniffi-bindgen-react-native) ; [GitHub releases](https://github.com/jhugman/uniffi-bindgen-react-native/releases).
- Annonce Mozilla Hacks (2024-12-04) : « an early release » ; premier usage réel cité : Matrix SDK pour React Native (Unomed) — [Mozilla Hacks, Introducing Uniffi for React Native](https://hacks.mozilla.org/2024/12/introducing-uniffi-for-react-native-rust-powered-turbo-modules/).
- Utilisateurs publics listés : `@unomed/react-native-matrix-sdk` (v0.10.2 le 2026-09-30, 68 étoiles), ChessTiles (iOS), `@fressh/react-native-uniffi-russh` — [README ubrn](https://github.com/jhugman/uniffi-bindgen-react-native) ; [GitHub unomed-dev/react-native-matrix-sdk](https://github.com/unomed-dev/react-native-matrix-sdk).
- Points de friction Expo/RN dans le tracker : #195 « Incorporate feedback from using a generated library in an expo project » ouvert depuis 2024-12-22 ; #295 crash Android « on React Native 0.80+ and expo 54 » (fermé 2025-09-12) ; #414 (2026-07-20) « Generated bindings import @ubjs/core, but nothing ties its version to the generator — consumers must hand-sync two deps » — [issues ubrn](https://github.com/jhugman/uniffi-bindgen-react-native/issues).

**Crux (Red Badger)**

- Architecture Core (Rust : état + logique) / Shell (UI native + exécution des effets I/O) ; shells documentés : SwiftUI, Jetpack Compose, web React / Leptos / Yew ; « used in production apps today, and we consider it production ready » mais pré-1.0, « some breaking changes will be unavoidable » — [Crux book](https://redbadger.github.io/crux/).
- `crux_core` 0.20.0 le 2026-08-07, doc en cours de mise à jour pour la 0.21 (issue #619 du 2026-09-29) ; 2,7k étoiles ; ≈ 26k téléchargements récents ; aucun exemple React Native dans `examples/` (counter, notes, weather…) — [GitHub redbadger/crux](https://github.com/redbadger/crux) ; [crates.io crux_core](https://crates.io/crates/crux_core) (vérifiés `gh api`).

**wasm-bindgen / wasm-pack / types partagés**

- wasm-bindgen a migré sous l'organisation `wasm-bindgen` (l'ancienne org `rustwasm` redirige) ; v0.2.129 le 2026-09-25 ; ≈ 140 M téléchargements récents. wasm-pack : dernier push 2026-08-12 — [GitHub wasm-bindgen/wasm-bindgen](https://github.com/wasm-bindgen/wasm-bindgen) ; [crates.io wasm-bindgen](https://crates.io/crates/wasm-bindgen) (vérifiés `gh api`).
- typeshare (1Password) : CLI v1.13.4 du 2025-12-11 (release précédente juin 2025) — [GitHub 1Password/typeshare](https://github.com/1Password/typeshare/releases).
- specta : v2 toujours en release candidate (2.0.0-rc.25 le 2026-05-08) ; dernière stable 1.0.5 — [GitHub specta-rs/specta](https://github.com/specta-rs/specta/releases).

**Exemples de production**

- **1Password** (blog du 2021-08-12) : « The goal was to put every feasible piece of 1Password into the Core library, stopping just short of the user interface » ; UI = Electron (desktop), SwiftUI (iOS), Android natif, web pour l'extension ; compromis : abandon d'une app macOS SwiftUI native en cours de route, choix d'Electron — [1Password 8: The Story So Far](https://1password.com/blog/1password-8-the-story-so-far). Le cœur Rust est aussi livré en WebAssembly dans l'extension navigateur — [Rust Foundation, Member Spotlight 1Password](https://rustfoundation.org/media/member-spotlight-1password/).
- **Bitwarden** : `sdk-internal` en Rust, bindings UniFFI (Swift/Kotlin) pour mobile et `bitwarden-wasm-internal` (wasm-bindgen → npm) pour le web — [Bitwarden contributing docs, Internal SDK](https://contributing.bitwarden.com/architecture/sdk/internal/) ; [GitHub bitwarden/sdk-internal](https://github.com/bitwarden/sdk-internal) (push 2026-10-01).
- **Signal** : libsignal en Rust, exposé en Java, Swift et TypeScript pour les clients Android, iOS, Desktop ; ponts JNI, C et Node add-on « subject to change without notice » ; « Use outside of Signal is unsupported » ; cadence très rapide (v0.102.3 le 2026-09-15 → v0.103.1 le 2026-09-22) ; AGPL-3.0 — [GitHub signalapp/libsignal](https://github.com/signalapp/libsignal) (README + releases via `gh api`).
- **Proton Mail** (2025-09-25) : apps mobiles réécrites sur une base Rust, ~80 % de code partagé Android/iOS, apps restant « native », mode hors-ligne complet, actions « twice as fast » — [Proton blog, new Mail apps](https://proton.me/blog/new-mail-apps).
- **Element X / Matrix** : Element X iOS consomme `matrix-rust-components-swift` (version 26.09.25 épinglée dans `project.yml`) ; matrix-rust-sdk 0.19.1 le 2026-09-18 — [GitHub element-hq/element-x-ios](https://github.com/element-hq/element-x-ios) ; [GitHub matrix-org/matrix-rust-sdk](https://github.com/matrix-org/matrix-rust-sdk).
- **Cloudflare WARP** : BoringTun (WireGuard en Rust) « deployed on millions of iOS and Android consumer devices » — [GitHub cloudflare/boringtun](https://github.com/cloudflare/boringtun) ; [Cloudflare blog, history of WARP](https://blog.cloudflare.com/how-to-build-your-own-vpn-or-the-history-of-warp/).
- **Figma** : Rust utilisé pour le **serveur** multiplayer (réécrit depuis TypeScript en 2018), pas pour le client — [Figma blog, Rust in production (2018-05-02)](https://www.figma.com/blog/rust-in-production-at-figma/). Ce n'est donc pas un exemple de « Rust core client ».

**Côté API PHP**

- Pour réutiliser un cœur Rust dans l'API Symfony, la voie native est une extension PHP via `ext-php-rs` : v0.16.0 le 2026-09-29, 841 étoiles, PHP 8.1+, pré-1.0 ; Rust nightly requis sous Windows — [GitHub extphprs/ext-php-rs](https://github.com/extphprs/ext-php-rs) (vérifié `gh api`).

### Inferences

- Tous les succès « Rust core » cités ont trois points communs absents chez Cordeau : (1) un domaine où Rust apporte un avantage décisif (crypto, protocole, sync complexe, perf) ; (2) des équipes de plusieurs dizaines d'ingénieurs capables de maintenir la chaîne FFI ; (3) des UI natives Swift/Kotlin (UniFFI y est mature) plutôt que React Native (où le pont est encore « early »).
- Le domaine client de Cordeau (Mesures = surfaces/volumes ; Devis = HT/TVA/TTC) est arithmétique simple. Le partager en Rust entre PHP (ext-php-rs), web (WASM) et mobile (ubrn) multiplierait les chaînes de build par 3 pour du code qui tient en quelques centaines de lignes de TS/PHP testées par jeux de cas partagés (fixtures JSON communes).
- Le vrai risque de divergence (calcul TVA côté mobile hors-ligne ≠ côté API) se traite plus simplement par des **tests de contrat sur fixtures partagées** (mêmes cas d'entrée/sortie exécutés par PHPUnit et Vitest) que par un binaire partagé.
- Si un jour Cordeau veut un cœur partagé, Crux est conceptuellement le plus propre (effets purs, testable sans I/O), mais l'absence de shell React Native imposerait d'écrire le pont soi-même.

### Gaps

- Pas trouvé de retour d'expérience public d'une app **Expo** en production utilisant ubrn (seulement un projet perso, cf. Q4).
- Les gains/pertes chiffrés de Bitwarden et Proton (taille binaire, temps de build, vélocité) ne sont pas publiés dans les sources trouvées.
- Pas vérifié le coût réel d'hébergement d'une extension PHP compilée (ext-php-rs) sur l'infra de prod de l'API Cordeau.

## Q4 — Contraintes pratiques Expo / EAS pour embarquer du Rust (toolchains, cross-compile, taille binaire, Xcode/NDK, debug FFI, hot reload, coût CI) et impact sur la fragilité du build natif

### Takeaway

C'est faisable (Expo Module ou Turbo Module + UniFFI/ubrn, Rust compilé dans un hook `eas-build-pre-install`), mais **rien n'est fourni clé en main** : les images EAS (2026-09-29) n'embarquent pas Rust, il faut installer rustup + cibles iOS/Android à chaque build, gérer cargo-ndk, les XCFrameworks, l'alignement 16 KB Android, et chaque modif Rust impose un rebuild natif (pas de Fast Refresh). Cela ajoute une seconde chaîne native précisément là où Cordeau a déjà cassé une fois (#87).

### Cited Findings

- Images EAS par défaut (page datée du 2026-09-29) : Android `ubuntu-26.04-jdk-17-ndk-r27b-sdk-57` (NDK 27.1.12297006, Java 17, Node 22.23.1) ; iOS `macos-tahoe-26.5-xcode-26.6` (Xcode 26.6) ; **Rust/rustup/cargo ne figurent pas dans les outils préinstallés** — [Expo docs, EAS Build infrastructure](https://docs.expo.dev/build-reference/infrastructure/).
- Expo SDK 57 est la dernière version (`expo` 57.0.26 sur npm le 2026-10-01 ; images EAS alias `sdk-57`), Cordeau est sur SDK 56 — [npm expo](https://www.npmjs.com/package/expo) ; [Expo docs infrastructure](https://docs.expo.dev/build-reference/infrastructure/).
- Recette documentée par un projet réel (PR du 2026) : un script `eas-build-pre-install.sh` installe la toolchain épinglée par `rust-toolchain.toml` + les cibles, lance `cargo-ndk` (Android) ou un script iOS ; le hook **doit** être pre-install car « post-install runs after `pod install`, which is too late for the podspec to find the framework » ; l'auteur note qu'aucun build EAS n'avait encore exécuté le hook et que l'iOS « has never been run anywhere » ; surcoût de temps de build reconnu — [remcostoeten/skriuw PR #493](https://github.com/remcostoeten/skriuw/pull/493).
- Même projet : intégration d'un cœur Rust en Expo native module (« Android first, iOS via macOS CI »), en évaluant ubrn ; résultat : façade appelable depuis RN sur émulateur Android, lecture SQLite natif — [remcostoeten/skriuw issue #387](https://github.com/remcostoeten/skriuw/issues/387).
- Retour d'expérience Expo + UniFFI : abandon du C FFI direct (échec sur le passage de tableaux d'octets), « additional complexity », « more copying and allocation of data when passing between layers », NDK à cibler correctement, étapes répétées par architecture, outillage IDE limité ; recommande d'automatiser — [claas.dev, Expo with Rust](https://claas.dev/posts/expo-with-rust/).
- Cibles typiques : `aarch64-apple-ios`, `aarch64-apple-ios-sim` ; `cargo-ndk` pour Android ; lib statique iOS / dynamique Android — [rust-dd.com, Rust native module for React Native](https://rust-dd.com/post/building-a-rust-native-module-for-react-native-on-ios-and-android) ; [dgca/expo-rust-demo](https://github.com/dgca/expo-rust-demo).
- Exemple de maintenance native induite : cr-sqlite a dû livrer en août 2026 « align android loadable to 16 kb page size (#460) », « fix ios simulator build » et « Fix macos headerpad » — [GitHub vlcn-io/cr-sqlite commits](https://github.com/vlcn-io/cr-sqlite/commits) (vérifié `gh api`).
- Couplage de versions côté ubrn : le runtime `@ubjs/core` n'est pas lié à la version du générateur, « consumers must hand-sync two deps » (issue #414, 2026-07-20) ; crash Android sur RN 0.80+/Expo 54 (#295, résolu 2025-09-12) — [issues ubrn](https://github.com/jhugman/uniffi-bindgen-react-native/issues).
- Hot-patching Rust existe chez Dioxus (subsecond) mais avec points d'intégration explicites et migration d'état manuelle — [Dioxus 0.7 release blog](https://dioxuslabs.com/blog/release-070/) ; rien d'équivalent documenté pour un module Rust dans une app Expo.

### Inferences

- Chaque upgrade Expo SDK (56 → 57 déjà disponible) devient un triple test : RN/Expo, ubrn/UniFFI, toolchain Rust/NDK/Xcode. Avec un rythme Expo d'environ 3 SDK/an, c'est une charge récurrente non négligeable pour un dev solo.
- Coût CI : compiler 3–4 cibles Rust (arm64 iOS device, arm64/x86_64 simulateur, arm64-v8a + x86_64 Android) en release sur chaque build EAS, sans cache Rust natif côté EAS, rallonge les builds et donc le coût en crédits EAS (estimation non chiffrée, cf. Gaps).
- Debug : une panique Rust dans un Turbo Module JSI remonte comme un crash natif ; il faut symboliser les binaires Rust (dSYM / symboles NDK) dans Sentry/Crashlytics — une compétence de plus à maîtriser.
- Pour l'agent Claude Code : il doit raisonner simultanément sur TS, C++ JSI généré, Swift/Kotlin (podspec/Gradle), Rust et scripts EAS ; les erreurs de build natif sont précisément le terrain où les agents sont les moins fiables (logs longs, causes environnementales).

### Gaps

- Aucune mesure publique trouvée de la taille binaire ajoutée par un cœur Rust typique (UniFFI) dans une app Expo, ni du surcoût de temps EAS.
- Pas de documentation officielle Expo dédiée à Rust (ni guide, ni config plugin) trouvée.
- Le support de la mise en cache de `~/.cargo` / `target/` sur EAS n'a pas été vérifié.

## Q5 — Moteurs de sync offline-first à cœur Rust ou compatibles (PowerSync, ElectricSQL, cr-sqlite, Turso/libSQL, Automerge, Loro, Ditto, sqlite-sync) : Postgres + React Native/Expo + web, maturité, licence/coût, remplacement de l'outbox maison

### Takeaway

Le seul moteur qui coche **Postgres + Expo + web + écritures via sa propre API (donc compatible Symfony) + statut GA** est **PowerSync**, dont le cœur est justement une extension SQLite écrite en Rust — c'est la manière la plus réaliste de « bénéficier de Rust » côté sync sans écrire de Rust. ElectricSQL + TanStack DB (Apache-2.0) est l'alternative open source crédible pour le chemin de lecture, avec outbox `@tanstack/offline-transactions`. Turso Sync impose Turso Cloud comme source de vérité (pas Postgres) ; cr-sqlite est quasi à l'arrêt ; Automerge/Loro sont des CRDT documentaires peu adaptés à un modèle relationnel Devis/Lots.

### Cited Findings

**PowerSync** — **[PROD]** (SDK RN déclaré GA)

- Le SDK React Native est construit sur « a common Rust core for client SDKs » ; la v0.4.0 de l'extension `powersync-sqlite-core` a déplacé le client de sync dans l'extension SQLite (Rust) pour lever les goulots JSON/BSON — [PowerSync, improved sync performance](https://releases.powersync.com/announcements/improved-sync-performance-in-our-client-sdks) ; [PowerSync RN SDK announcement](https://www.powersync.com/blog/announcing-the-powersync-react-native-client-sdk).
- Versions au 2026-10-01 : `powersync-sqlite-core` v0.5.3 (2026-08-13) ; `@powersync/react-native` 2.3.1 et `@powersync/web` 2.4.2 (2026-10-01) ; `@powersync/drizzle-driver` 0.8.0 (2026-07-21) — [GitHub powersync-ja/powersync-sqlite-core](https://github.com/powersync-ja/powersync-sqlite-core/releases) ; [npm @powersync/react-native](https://www.npmjs.com/package/@powersync/react-native) (vérifiés `gh api` / `npm view`).
- SDK RN/Expo : **GA** ; Expo dev builds supportés (adaptateur natif `@op-engineering/op-sqlite` ≥ 1.17.0) ; Expo Go seulement via `@powersync/adapter-sql-js` ; les écritures locales sont mises en file et envoyées par la méthode `uploadData()` du connecteur **vers votre propre backend REST** — [PowerSync docs, React Native & Expo SDK](https://docs.powersync.com/client-sdks/reference/react-native-and-expo).
- Backends supportés : Postgres, MongoDB, MySQL, SQL Server — [PowerSync GitHub org / docs](https://github.com/powersync-ja).
- Licence / coût : Open Edition auto-hébergée gratuite sous Functional Source License (convertie en licence OSI après 2 ans) ; Cloud Free (2 GB synchronisés/mois, 50 clients concurrents), Pro dès 49 $/mois, Team dès 599 $/mois — [PowerSync Open Edition](https://powersync.com/blog/powersync-open-edition-release) ; [PowerSync pricing](https://powersync.com/pricing) ; [PowerSync licensing terms](https://powersync.com/legal/licensing-terms).
- PowerSync a aussi un SDK Rust natif, explicitement « **Experimental** PowerSync SDK for Rust applications » (crate `powersync` 0.1.0, repo `powersync-native` créé le 2026-02-05) — [crates.io powersync](https://crates.io/crates/powersync).
- Expo a publié un article dédié « What synced in-app SQLite brings to Expo apps » — [Expo blog](https://expo.dev/blog/what-synced-in-app-sqlite-brings-to-expo-apps).

**ElectricSQL (+ TanStack DB)** — **[PROD]** pour la lecture, **[BETA]** pour l'offline complet

- 10,4k étoiles, Apache-2.0, `@electric-sql/client` 1.5.28 (2026-09-09), starter `expo-db-electric-starter@1.0.29` — [GitHub electric-sql/electric](https://github.com/electric-sql/electric) (vérifié `gh api`/`npm view`).
- TanStack DB 0.6 (2026-03-25) ajoute la persistance SQLite sur navigateur, Node, React Native, Expo… ; la démo RN utilise `op-sqlite` (pas `expo-sqlite`) ; les écritures passent par **votre API** (Electric ne gère pas les écritures) ; `@tanstack/offline-transactions` fournit un pattern outbox-first avec rejeu séquentiel — [Electric blog, TanStack DB 0.6](https://electric.ax/blog/2026/03/25/tanstack-db-0.6-app-ready-with-persistence-and-includes) ; [TanStack blog DB 0.6](https://tanstack.com/blog/tanstack-db-0.6-app-ready-with-persistence-and-includes).
- Versions : `@tanstack/db` 0.11.0 (pré-1.0) et `@tanstack/offline-transactions` 1.0.58 au 2026-09-30 — [npm @tanstack/db](https://www.npmjs.com/package/@tanstack/db) (`npm view`).
- Une discussion ouverte porte sur le « fully offline support (through-the-database write pattern) » avec TanStack DB — [electric discussion #3502](https://github.com/electric-sql/electric/discussions/3502).

**Turso (réécriture Rust de SQLite) / libSQL**

- `turso` v0.8.1 (2026-09-29), 24,5k étoiles, MIT ; le README affirme « runs in production today at multiple organizations » (Turso Cloud, Kin, Spice.ai), testé par DST + Antithesis ; la réécriture Rust « replaces libSQL as our intended direction », libSQL étant « battle-tested for longer » — [GitHub tursodatabase/turso](https://github.com/tursodatabase/turso).
- Turso Sync (beta, 2025-10-08) : **la base distante Turso Cloud est la source de vérité** ; conflits en « Last-Push-Wins » + hook `transform` ; partial sync sur la roadmap ; aucune mention de Postgres — [Turso blog, Databases Anywhere with Turso Sync](https://turso.tech/blog/introducing-databases-anywhere-with-turso-sync) ; [Turso Offline Sync public beta](https://turso.tech/blog/turso-offline-sync-public-beta).
- `@tursodatabase/sync-react-native` annoncé le 2026-01-29 (RN 0.76+ New Architecture, pas de plugin Expo à l'annonce, chiffrement au repos, partial sync / subscriptions « planned ») ; version 0.8.1 au 2026-09-29 — [Turso blog, React Native bindings](https://turso.tech/blog/react-native-bindings-for-turso) ; [npm](https://www.npmjs.com/package/@tursodatabase/sync-react-native).

**sqlite-sync (SQLite Cloud / sqliteai)**

- Extension C (pas Rust), CRDT, backends SQLite Cloud, **PostgreSQL** et Supabase revendiqués ; iOS, Android, React Native, Expo, WASM ; **Elastic License 2.0** (licence commerciale proposée pour la prod) ; v1.2.0 le 2026-09-28 ; `@sqliteai/sqlite-sync-expo` 1.2.0 — [GitHub sqliteai/sqlite-sync](https://github.com/sqliteai/sqlite-sync) ; `npm view` 2026-10-01.

**cr-sqlite**

- Dernière release v0.16.3 le **2024-01-17** ; quelques commits de maintenance en août 2026 (16 KB page size Android, build simulateur iOS) — [GitHub vlcn-io/cr-sqlite](https://github.com/vlcn-io/cr-sqlite) (vérifié `gh api`).

**CRDT documentaires (Automerge, Loro)**

- Automerge : JS 3.5.0 (2026-09-16), crate Rust 0.12.0 ; Loro : `loro-crdt` 1.16.4 (2026-09-30), crate 1.16.2 ; tous deux MIT, cœur Rust compilé en WASM — [GitHub automerge/automerge](https://github.com/automerge/automerge/releases) ; [GitHub loro-dev/loro](https://github.com/loro-dev/loro/releases) (vérifiés `gh api`).

**Panorama Expo**

- Le guide Expo « Local-first » (mis à jour le 2026-08-27) liste Legend-State, TinyBase, Yjs, Prisma (early access), Jazz, LiveStore, Turso, Instant, RxDB et prévient que ces outils restent « in their early stages », avec des problèmes (sync custom, permissions multi-utilisateurs) parfois non résolus — [Expo docs, Local-first](https://docs.expo.dev/guides/local-first/).

### Inferences

- L'outbox maison de Cordeau (mutations rejouées vers l'API Symfony, dépendance Lot → Tâches) correspond exactement au modèle « upload queue + `uploadData()` vers son propre backend » de PowerSync et au modèle `@tanstack/offline-transactions`. PowerSync remplacerait surtout le **chemin descendant** (Postgres → SQLite via réplication logique + sync rules), aujourd'hui probablement géré par des fetchs ; il ne supprime pas la logique d'ordre/idempotence des écritures, qui reste côté API.
- PowerSync introduit un service supplémentaire (PowerSync Service, cloud ou auto-hébergé FSL) branché sur la réplication logique Postgres — à documenter en ADR (dépendance externe + données personnelles → section sécurité/RGPD obligatoire selon CLAUDE.md) ; vérifier l'hébergement UE du Cloud.
- PowerSync impose `op-sqlite` au lieu d'`expo-sqlite` : impact sur Drizzle (driver `@powersync/drizzle-driver` existe) et sur le build natif (une lib native de plus, mais maintenue par un éditeur).
- Turso est écarté par la contrainte Postgres (il faudrait faire de Turso Cloud la source de vérité ou maintenir un pont Postgres ↔ Turso).
- sqlite-sync est séduisant (Postgres + Expo) mais sous Elastic License 2.0 avec licence commerciale « pour la prod » → coût/licence à clarifier ; CRDT au niveau ligne à valider contre les invariants métier (totaux de Devis).
- Le paradoxe utile : **la meilleure manière d'avoir « du Rust » dans la sync de Cordeau est d'adopter un produit dont le cœur est Rust (PowerSync) tout en restant en TS côté app** — option C « augmentée ».

### Gaps

- **Ditto** non investigué faute de temps/sources (SDK propriétaire, Rust interne selon sa réputation — non vérifié) : licence, prix, support Postgres et Expo à vérifier.
- Localisation des données (région UE) de PowerSync Cloud et d'Electric Cloud non vérifiée — critique pour le RGPD.
- Tarification d'Electric Cloud non trouvée dans les sources consultées.
- Pas de retour d'expérience public trouvé sur PowerSync + backend PHP/Symfony (le modèle `uploadData` le permet en théorie).
- Compatibilité exacte de PowerSync avec Expo SDK 56/57 et RN 0.85 (New Architecture) non vérifiée dans la matrice « Supported Platforms ».

## Q6 — AR en V2 : existe-t-il une piste Rust pour ARKit/ARCore, ou est-ce forcément natif / module Expo ?

### Takeaway

Il n'existe pas de piste Rust sérieuse pour l'AR mobile : seul un binding ARKit auto-généré (`objc2-ar-kit`, usage marginal) existe, et aucune crate ARCore n'a été trouvée. La voie réaliste pour la V2 est un module natif Expo (Swift/Kotlin) ou ViroReact (activement maintenu, plugin Expo). Rust ne pourrait intervenir qu'en aval, pour le calcul géométrique des Mesures à partir des points capturés — ce que TS fait aussi très bien.

### Cited Findings

- `objc2-ar-kit` : « Bindings to the ARKit framework », créée le 2024-05-18, version 0.3.2 du 2025-10-04, ≈ 340 téléchargements récents (contre ≈ 21,6 M pour `objc2-ui-kit`) — [crates.io objc2-ar-kit](https://crates.io/crates/objc2-ar-kit) ; [crates.io objc2-ui-kit](https://crates.io/crates/objc2-ui-kit) (API crates.io 2026-10-01).
- Aucune crate `arcore` / `ar-core` / `arkit` publiée sur crates.io (requêtes API 404 le 2026-10-01) — [crates.io](https://crates.io/).
- ViroReact (ReactVision) : « open-source library for building production AR apps with React Native and Expo », accès ARKit et ARCore, plugin Expo (dev client / prebuild requis) ; v3.0.2 publiée le 2026-10-01, 1,8k étoiles ; `@reactvision/react-viro` 3.0.2 sur npm — [ReactVision, Expo AR](https://www.reactvision.xyz/expo-ar/) ; [Viro docs, integrating with Expo](https://viro-community.readme.io/docs/integrating-with-expo) ; [GitHub ReactVision/viro](https://github.com/ReactVision/viro/releases) (vérifié `gh api`).
- Un paquet npm `expo-ar` existe en 0.0.0 (placeholder, modifié le 2026-09-23) et un article décrit un module « One React Native AR View for ARKit and ARCore » — [stewmore.dev, expo-ar](https://www.stewmore.dev/blog/expo-ar-arkit-arcore-react-native) ; `npm view expo-ar` (statut non officiel, non évalué).

### Inferences

- L'AR (sessions, plane detection, LiDAR, raycast) est intrinsèquement liée aux SDK Apple/Google ; écrire en Rust ajouterait une couche FFI sans bénéfice. La voie naturelle Cordeau V2 = Expo Module Swift/Kotlin dédié (ou ViroReact), qui renvoie au JS des points 3D/dimensions ; le calcul de Mesures se fait ensuite dans le domaine TS déjà existant.
- Choisir Rust aujourd'hui « pour préparer l'AR » ne se justifie donc pas.

### Gaps

- Pas vérifié l'existence d'un usage de l'API C NDK d'ARCore depuis Rust via bindgen (possible en théorie, aucun projet maintenu trouvé).
- Qualité/maintien long terme de ViroReact sur la New Architecture et Expo SDK 57 non évalués en détail (seulement l'activité du repo).

## Q7 — Synthèse comparative des options A / B / C pour Cordeau (preuves et maturité)

### Takeaway

Au 2026-10-01 : **(A) UI full Rust = [EXP]/[BETA], à écarter** ; **(B) cœur Rust partagé = [PROD] dans l'industrie mais [BETA]/[EXP] sur le maillon React Native/Expo, et surdimensionné pour un domaine arithmétique simple et un dev solo** ; **(C) TS côté clients = la seule option entièrement production-proven dans la stack actuelle**, idéalement « augmentée » par un moteur de sync à cœur Rust (PowerSync) et par des tests de contrat partagés API ↔ clients pour les calculs Mesures/Devis.

### Cited Findings

- (A) Leptos en maintenance légère depuis 2026-05-08 — [leptos#4707](https://github.com/leptos-rs/leptos/issues/4707) ; Dioxus mobile en WebView sans API natives (prévues 0.8) — [Dioxus mobile guide](https://dioxuslabs.com/learn/0.7/guides/platforms/mobile/), [roadmap 0.8](https://github.com/DioxusLabs/dioxus/discussions/5024) ; Tauri 3 en alpha (2026-10-01) et pas de stockage sécurisé officiel mobile — [Tauri releases](https://github.com/tauri-apps/tauri/releases), [Tauri plugins](https://v2.tauri.app/plugin/) ; port shadcn/ui Rust archivé — [RustForWeb/shadcn-ui](https://github.com/RustForWeb/shadcn-ui).
- (B) Pattern prouvé : 1Password, Bitwarden, Signal, Proton, Mozilla, Element X — [1Password](https://1password.com/blog/1password-8-the-story-so-far), [Bitwarden SDK](https://contributing.bitwarden.com/architecture/sdk/internal/), [libsignal](https://github.com/signalapp/libsignal), [Proton](https://proton.me/blog/new-mail-apps), [Mozilla UniFFI](https://hacks.mozilla.org/2023/08/autogenerating-rust-js-bindings-with-uniffi/) ; mais pont RN « should not yet be used in production » — [ubrn docs](https://jhugman.github.io/uniffi-bindgen-react-native/) ; Rust absent des images EAS — [Expo infrastructure](https://docs.expo.dev/build-reference/infrastructure/) ; côté PHP, `ext-php-rs` pré-1.0 (0.16.0) — [ext-php-rs](https://github.com/extphprs/ext-php-rs).
- (C) Écosystème TS offline mature : PowerSync RN SDK GA à cœur Rust, écritures via son backend — [PowerSync RN docs](https://docs.powersync.com/client-sdks/reference/react-native-and-expo) ; Electric + TanStack DB avec outbox `offline-transactions` — [Electric blog 2026-03-25](https://electric.ax/blog/2026/03/25/tanstack-db-0.6-app-ready-with-persistence-and-includes) ; AR V2 via ViroReact/Expo — [ReactVision](https://www.reactvision.xyz/expo-ar/).

### Inferences

- Critère « solide pour aller loin » : la solidité vient ici de la **maturité de la chaîne** (Expo/EAS, React, PowerSync GA) bien plus que du langage. L'option B ajouterait 3 nouvelles chaînes de build (WASM, ubrn iOS/Android, ext-php-rs) pour un gain de typage/partage obtenable par fixtures de tests partagées + types OpenAPI déjà générés.
- Critère « Claude Code comme développeur principal » : React/TS/Expo est le terrain où l'agent est le plus fiable (volume d'entraînement, API stables) ; les frameworks Rust UI ont un churn élevé et des communautés en contraction (Leptos), et les ponts FFI génèrent des erreurs natives difficiles à diagnostiquer par un agent.
- Déclencheurs qui pourraient justifier de rouvrir B plus tard (à acter dans un ADR) : (1) un calcul client devenant lourd (ex. traitement de nuages de points AR, géométrie complexe) ; (2) ubrn sortant de « early development » avec support Expo officiel ; (3) un besoin crypto/sync que seule une lib Rust résout. Dans ce cas, privilégier un **module Rust ciblé** (une fonction de calcul) plutôt qu'un « core » englobant sync + DB.
- Point d'attention indépendant de Rust : Expo SDK 57 est déjà sorti ; la fragilité native constatée (#87) se traite par la CI de build natif, pas par un changement de langage.

### Gaps

- Pas de données chiffrées de vélocité (lignes/jour, taux de bugs) comparant un dev solo + agent sur TS vs Rust pour une app de ce type.
- Coûts EAS supplémentaires liés à la compilation Rust non chiffrés.
- Ditto, LiveStore, Jazz, Instant non évalués en profondeur (hors cœur Rust ou sans Postgres natif pour la plupart, à confirmer).
