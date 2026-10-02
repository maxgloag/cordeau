# 09 — Inventaire technique de la surface de migration

> Note de travail de l'étude « Rust et développement agentique ». Inventaire **factuel** du monorepo à la date du 2026-10-01 (branche `chore/etude-rust-agentique`, base `main` = `6decdaf`, PR #105). Lecture seule. Comptages : `wc -l` (lignes physiques) et, pour PHP, lignes « code » (hors vides, commentaires et `use`). Données GitHub via `gh` (lecture seule).
>
> Recommandations : **uniquement** en section 7 (classement), le reste est descriptif.

---

## 0. Ordres de grandeur

| Périmètre                       | Fichiers | Lignes physiques | Remarque                                             |
| ------------------------------- | -------: | ---------------: | ---------------------------------------------------- |
| `apps/api/src` (PHP)            |      114 |            5 042 | 3 396 lignes « code »                                |
| `apps/api/tests` (PHP)          |       34 |            3 746 | 2 775 lignes « code », 189 méthodes de test          |
| `apps/api/migrations`           |        9 |              355 | DDL Postgres en chaînes PHP                          |
| `apps/web/src` (TS/TSX)         |       45 |            4 379 | dont 601 shadcn générés, 439 tests                   |
| `apps/mobile` (TS/TSX/SQL/JS)   |       47 |            4 702 | dont 723 tests + test-utils                          |
| `packages/shared`               |        3 |            3 352 | `openapi.json` 2 218 + `api.generated.ts` 1 109      |
| Docs (ADRs 23 fichiers)         |       23 |            1 942 | + 1 219 l. CLAUDE.md×4 / THESAURUS / archi / ROADMAP |
| Fichiers suivis par git (total) |      420 |                — | 346 sous `apps/`                                     |

Historique `main` : 127 commits, du 2026-05-03 au 2026-06-14. **Aucun commit humain sur `main` depuis le 2026-06-14** (Phase 6 arrêtée après 6.1 ; issues #101/#102/#103 ouvertes).

---

## 1. API (`apps/api`) — Symfony 7.4 + API Platform 4.3 + PHP 8.5

### 1.1 Structure réelle (layer-first, pas context-first)

La structure effective est **par couche puis par contexte** (`src/Domain/Chantier`, `src/Application/Chantier`, …) pour le seul BC hexagonal, et **par contexte à plat** (`src/Auth`, `src/Client`, `src/Photo`) pour les CRUD légers. Répertoires vides résiduels du skeleton : `src/ApiResource`, `src/Controller`, `src/Repository` (`.gitignore` seul).

> Dérive doc : la memory Serena `.serena/memories/architecture.md` décrit une structure `src/Chantier/Domain/…` (context-first) et « Auth : cookie de session côté firewall web » — les deux sont faux vis-à-vis du code (layer-first ; Bearer partout depuis `d883d9b`). `docs/architecture.md` date du 2026-05-28 (mentionne Expo SDK 54). `apps/mobile/CLAUDE.md` dit « pas de polling » alors que `useSyncWorker` poll toutes les 5 s.

### 1.2 Bounded contexts — chiffres

| BC                                        | Chemins                                                                                                                                             | Fichiers | Lignes (code) | Tests associés (lignes / méthodes)                                   |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------: | ------------: | -------------------------------------------------------------------- |
| **Chantier** (hexagonal, incl. Lot/Tâche) | `src/Domain/Chantier`, `src/Application/Chantier`, `src/Infrastructure/Persistence/Doctrine/Chantier`, `src/Presentation/Api/Chantier`, `…/Support` |       45 | 1 912 (1 344) | 1 609 l. — 55 unit domaine + 22 persistance + 23 HTTP = **100**      |
| ↳ Domain                                  | `src/Domain/Chantier`                                                                                                                               |       24 |     797 (571) |                                                                      |
| ↳ Application                             | `src/Application/Chantier/UseCase`                                                                                                                  |        5 |      151 (92) | aucun test dédié (couverts via HTTP)                                 |
| ↳ Infra Doctrine                          | `src/Infrastructure/Persistence/Doctrine/Chantier`                                                                                                  |        6 |     509 (388) |                                                                      |
| ↳ Presentation                            | `src/Presentation/Api/Chantier` + `Support`                                                                                                         |       10 |     455 (293) |                                                                      |
| **Auth** (léger + ports)                  | `src/Auth`, `src/Entity`, `src/Security`, `src/Infrastructure/Persistence/Doctrine/Auth`, `src/Presentation/Api/Auth`, `src/Presentation/Console`   |       31 | 1 528 (1 000) | 980 l. — 12 unit + 23 HTTP + 3 console = **38**                      |
| **Client** (CRUD léger)                   | `src/Client`, `src/Presentation/Api/Client`                                                                                                         |       13 |     630 (415) | 497 l. — 4 unit (+3 DataProvider) + 6 persistance + 16 HTTP = **26** |
| **Photo** (CRUD léger + async)            | `src/Photo`, `src/Presentation/Api/Photo`, `src/Messenger`, `src/Infrastructure/Storage`, `src/Infrastructure/Image`                                |       20 |     829 (535) | 347 l. — 2 unit + 12 HTTP = **14**                                   |
| **Shared**                                | `src/Shared` (Adresse + exception)                                                                                                                  |        2 |       73 (57) | 103 l. — **10** unit                                                 |
| Divers                                    | `Kernel.php`, `Presentation/Api/Health`, `Story/AppStory.php`                                                                                       |        3 |       70 (45) | 1 HTTP (health)                                                      |

Support tests : 4 factories Foundry (`tests/Factory/{Chantier,Client,Photo,User}Factory.php` — pas de factory Lot/Tâche), `JsonTestHelper` trait, `bootstrap.php`, fixtures images `tests/Fixtures/images/sample.{jpg,heic}`.

### 1.3 Modèle de domaine

- **Entités domaine** (immuables `final readonly`, transitions qui retournent une nouvelle instance) : `Chantier` (152 l. ; `creer`, `lierClient`, `delierClient`, `passerEnCours`, `terminer`, `archiver`, `modifierAdresse`, `renseignerSurface`, garde `changerStatut`), `Lot` (146 l. ; `creer`, `renommer`, `definirEstimation`, `enregistrerReel`, `ajouterImprevu`, `changerMode` qui reset estimation/réel), `Tache` (89 l. ; `cocher`, `decocher`, `renommer`).
- **Enums** : `StatutChantier` (4 cas, `estTerminal()`), `ModeFacturation` (3 cas, `unite()` → h / m² / €).
- **Value objects** (7) : `ClientRef`, `Estimation`, `Reel`, `Imprevu`, `Surface` (0 < v ≤ 100 000), `Shared/Adresse` (rue/ville non vides, pays ISO2, CP FR 5 chiffres), `Client/Telephone` (normalisation E.164 FR).
- **Exceptions métier** : 11 dans `Domain/Chantier/Exception`, + `AdresseInvalideException`, `TelephoneInvalideException`, 2 dans `Auth/Exception`.
- **Ports** : 3 repositories domaine (`ChantierRepository`, `LotRepository`, `TacheRepository`), 4 ports Auth (`OAuthAccountStore`, `UserStore`, `GoogleUserResolver`, `GoogleIdTokenVerifier`), `StorageAdapterInterface`.
- **Use cases** : 6 (`Creer/Lister/Obtenir/Modifier/ArchiverChantierUseCase`, `AuthentifierViaGoogleUseCase`). Lot/Tâche : **aucun use case** encore (6.2 non faite).
- **Entités Doctrine** : 9 (= 9 tables) — `ChantierDoctrineEntity`, `LotDoctrineEntity`, `TacheDoctrineEntity` (séparées du domaine, mapping manuel dans les repos Doctrine 104-142 l.), `User`, `AuthToken`, `OAuthLoginCode`, `OAuthAccount`, `Client`, `Photo` (entités = modèle, attributs Doctrine).
- **Type numérique** : `Surface`, `Estimation`, `Reel` en `float` côté domaine ; colonnes `lot.estimation/reel` en `NUMERIC(12,2)` → cast `(string)`/`(float)` dans `DoctrineLotRepository::valeurEnColonne`. `Lot.imprevus` en colonne JSON (sérialisation manuelle).
- **Effet de bord ORM** : `Client/EventListener/ClientRenameListener.php` (Doctrine `preUpdate`) propage le renommage client vers `chantier.client_nom_cache` (ADR 0011).

### 1.4 Endpoints exposés

**API Platform (préfixe `/api`) — 4 ressources, 15 opérations, 6 providers, 10 processors, 7 payloads d'entrée :**

| Ressource (DTO)         | Opérations                                                                                                     | Provider / Processor                                                                                    |
| ----------------------- | -------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `ChantierResource`      | GetCollection, Get, Post, Patch (merge-patch), Delete (= archiver)                                             | `ChantierCollectionProvider`, `ChantierItemProvider` / `Creer`, `Modifier`, `ArchiverChantierProcessor` |
| `ClientResource`        | GetCollection, Get, Post, Patch, Delete                                                                        | `ClientCollection/ItemProvider` / `Creer`, `Modifier`, `SupprimerClientProcessor`                       |
| `PhotoResource`         | Post `/photos/confirm` (201), GetCollection `/chantiers/{chantierId}/photos` (`Link`), Patch (légende), Delete | `PhotoCollection/ItemProvider` / `ConfirmUpload`, `ModifierLegende`, `SupprimerPhotoProcessor`          |
| `PrepareUploadResource` | Post `/photos/prepare` (200) → URL R2 pré-signée                                                               | `PrepareUploadProcessor`                                                                                |

**Contrôleurs Symfony `#[Route]` (hors OpenAPI) — 10 :** `POST /auth/{register,login,refresh,logout}`, `GET /auth/me`, `GET /auth/oauth/google/{start,callback}`, `POST /auth/oauth/google/exchange`, `POST /auth/oauth/code/exchange`, `GET /health` (SELECT 1). + `/api/docs`.

Total ≈ **25 endpoints**. Lot/Tâche : **0 endpoint** (6.2 ouverte, #101).

### 1.5 Fonctionnalités API Platform réellement utilisées

| Utilisé                                                                                                                                                      | Non utilisé                                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[ApiResource]` sur **DTO de sortie** (pas sur entités), opérations explicites, `uriTemplate` custom (×3), `Link` uriVariables (×1), `status` override (×2) | **Pagination** (providers renvoient des `array` complets), **filtres** (0 `ApiFilter`), **groupes de sérialisation** (0), `normalizationContext` (0)                                  |
| State Providers/Processors custom (tous — aucun provider Doctrine automatique)                                                                               | **Sécurité par opération** (0 `security:`) : contrôle via `access_control` par préfixe (`^/api` → `ROLE_USER`) + checks de propriété manuels dans providers/processors (pas de Voter) |
| `input:` DTO + Symfony Validator (55 `#[Assert]` sur 7 payloads), `merge-patch+json`                                                                         | GraphQL, Mercure, JSON:API/HAL, subresources Doctrine, état Doctrine ORM natif                                                                                                        |
| Formats `json` + `jsonld` (Hydra), OpenAPI export (`api:openapi:export`), Swagger UI en dev, `exception_to_status` (1 entrée), `cache_headers.vary`          |                                                                                                                                                                                       |

→ API Platform est utilisé essentiellement comme **routeur + désérialiseur/validateur + générateur OpenAPI**. Toute la logique d'accès aux données est déjà explicite (providers/processors à la main).

### 1.6 Dépendances Composer

88 paquets prod + 46 dev verrouillés (`composer.lock`), `vendor/` = 171 Mo. Directes :

| Dépendance                                                                                                                            | Version lock | Rôle / structurant ?                                                                                                                               |
| ------------------------------------------------------------------------------------------------------------------------------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `api-platform/core`                                                                                                                   | 4.3.4        | **Structurant** (routing ressources, validation, OpenAPI → contrat TS)                                                                             |
| `doctrine/orm` 3.6.3, `dbal` 4.4.3, `doctrine-bundle`, `migrations-bundle`                                                            | —            | **Structurant** (9 entités, 9 migrations, entity listener, proxies)                                                                                |
| `symfony/framework-bundle` 7.4.13, `security-bundle`, `validator`, `serializer`, `uid`, `http-client`, `console`, `runtime`           | 7.4.x        | **Structurant** (DI autowiring, authenticator custom, validation, CLI)                                                                             |
| `symfony/messenger` + `doctrine-messenger`                                                                                            | 7.4.12       | Jobs async (2 messages)                                                                                                                            |
| `ext-imagick`                                                                                                                         | —            | Vignettes HEIC (ADR 0023) — dépendance **C native** (ImageMagick + libheif) + `docker/imagemagick-policy.xml`                                      |
| `aws/aws-sdk-php`                                                                                                                     | 3.384.5      | Client S3 vers R2 (presign PUT, put, delete) — tire `guzzlehttp/*`, `mtdowling/jmespath.php`                                                       |
| `knpuniversity/oauth2-client-bundle` + `league/oauth2-google`                                                                         | 2.20.2 / 5.0 | Flow OAuth web (redirect)                                                                                                                          |
| `nelmio/cors-bundle`                                                                                                                  | 2.6.1        | CORS (web cross-origin Cloudflare Pages → Fly)                                                                                                     |
| `predis/predis`                                                                                                                       | 3.4.2        | **Non utilisé** : cache Redis commenté, transport Messenger = Doctrine. Redis présent en infra (compose, CI, ext Dockerfile) sans usage applicatif |
| dev : `phpunit` 12.5, `phpstan` 2.1 (+symfony, +doctrine), `deptrac` 4.6, `zenstruck/foundry` 2.9, `browser-kit`, `twig-bundle` (dev) |              | Qualité / tests                                                                                                                                    |

**Absents** : aucun SDK Sentry côté API (`SENTRY_DSN` en `.env` et dans le runbook, mais pas de `sentry/sentry-symfony` dans `composer.json`). Pas de rate limiter (issue #63 ouverte).

Dette sécurité dépendances : depuis le 2026-06-15, **toutes les PR Dependabot ont la CI rouge** ; `composer audit` remonte 14 advisories (api-platform/core 4.3.4 ×2, guzzle ×9, psr7 ×2, jmespath ×1) — run `33449888458`.

### 1.7 Migrations Doctrine

9 migrations (`migrations/Version2026…php`, 355 l.), 9 tables : `chantier`, `utilisateur`, `auth_token`, `client`, `oauth_account`, `oauth_login_code`, `photo`, `lot`, `tache` ; 16 index, FKs `ON DELETE CASCADE` sur `lot`/`tache`. Pas de table `messenger_messages` en migration alors que le DSN par défaut est `doctrine://default?auto_setup=0`. Migration auto au déploiement (`fly.toml` `release_command = doctrine:migrations:migrate`).

### 1.8 Auth

- **Un seul mécanisme** pour web et mobile : **token opaque Bearer** (firewall `stateless: true`, `src/Security/TokenAuthenticator.php`). Access token = `selector(16 o hex).verifier(32 o base64)`, verifier haché **bcrypt** en base (`password_verify` à chaque requête), TTL 1 h ; refresh token 32 o hex haché SHA-256, TTL 30 j (`src/Security/TokenGenerator.php`). Mots de passe utilisateurs : bcrypt.
- Le web stocke les tokens en **localStorage** (`apps/web/src/lib/api.ts`), refresh auto sur 401 ; bascule depuis le cookie de session faite le 2026-05-17 (`d883d9b`, cookie SameSite non envoyé en cross-origin). Mobile : `expo-secure-store`.
- **OAuth Google** (ADR 0013) : web = redirect KnpU (`GoogleStart` → `GoogleCallback`) → `OAuthLoginCode` éphémère TTL 60 s passé en query → `POST /auth/oauth/code/exchange` ; mobile = expo-auth-session PKCE → `id_token` → `POST /auth/oauth/google/exchange` → `GoogleTokeninfoVerifier` (appel HTTP `oauth2.googleapis.com/tokeninfo`, vérif audience). Auto-link par email vérifié, table `oauth_account` multi-provider.
- Bêta fermée : `Auth/RegistrationPolicy` (env `REGISTRATION_SELF_SERVICE_ENABLED`, ADR 0021) + commande `app:create-user` (`Presentation/Console/CreateUserCommand.php`, 104 l.).

### 1.9 Jobs asynchrones

2 messages / 2 handlers (`src/Messenger`) : `GenerateThumbnailMessage` (dispatch dans `ConfirmUploadProcessor` ; handler télécharge l'original via `file_get_contents($photoUrl)`, `ThumbnailGenerator` Imagick crop carré 400 px + orientation EXIF manuelle + strip EXIF, upload `thumbs/…` sur R2) et `DeleteR2ObjectMessage`. En test : transport `in-memory://`.
**Point non vérifiable depuis le repo** : aucun process consommateur déclaré (`fly.toml` n'a qu'un process `app`, pas de `messenger:consume`, pas de section `[processes]`), et le DSN prod n'est pas documenté dans `docs/runbooks/deploiement-prod.md`. Le mode réel d'exécution des jobs en prod (probable `sync://` via secret Fly, ou jobs non consommés) est à confirmer.

### 1.10 Déploiement

- `apps/api/Dockerfile` : `dunglas/frankenphp:1-php8.5-alpine`, extensions `pdo_pgsql redis intl opcache apcu imagick`, policy ImageMagick durcie, composer `--no-dev`, `cache:warmup`, healthcheck `/health`.
- `apps/api/fly.toml` : app `cordeau-api`, région `cdg`, VM `shared-cpu-1x` **256 Mo**, `auto_stop_machines = "stop"`, `min_machines_running = 0` (cold start ~1-2 s), release_command migrations. Postgres = Neon (EU), stockage = R2, web = Cloudflare Pages.
- Durées de déploiement API observées (CI `deploy / api`, remote build Fly) : 82 s à 159 s.

---

## 2. Tests, qualité, CI

### 2.1 Tests par app

| App    | Outil                                                                                                                                     | Unit                                             | Intégration                         | HTTP / composant                                                  | Total   |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ | ----------------------------------- | ----------------------------------------------------------------- | ------- |
| API    | PHPUnit 12 (`failOnDeprecation/Notice/Warning`), Foundry 2, WebTestCase, Postgres réel (`dbname_suffix _test`)                            | **83** (`tests/Unit`, 15 fichiers)               | **31** (persistance 28 + console 3) | **75** (`tests/Integration/Api` + health)                         | **189** |
| Web    | Vitest 4, 2 projets : `unit` (node, `*.test.ts`) + `browser` (Chromium via Playwright, `*.test.tsx`, screenshots)                         | 4 (`src/test/api.test.ts`)                       | —                                   | 14 (App 4, PhotoLightbox 5, PhotoGalleryView 3, usePhotoUpload 2) | **18**  |
| Mobile | Jest 29 + jest-expo 56 + @testing-library/react-native, 2 projets (`expo` + `node` avec SQLite réel via `sql.js`, `test-utils/testDb.ts`) | api 10, outbox 7, photoSync 6, usePhotoCapture 3 | db 6, photoDb.node 6 (SQLite réel)  | 0 test d'écran                                                    | **38**  |

Absents : aucun test E2E (pas de Playwright e2e, Detox, Maestro), aucun test de contrat OpenAPI (le `openapi.json` committé n'est pas comparé à l'API en CI), aucun test des écrans web Dashboard/Clients/Login/Register ni des écrans mobiles, aucun test des use cases Chantier en isolation, aucun test du handler Messenger (seulement `ThumbnailGeneratorTest`).

### 2.2 Outils qualité

- **PHP** : PHPStan **niveau 9** (`phpstan.neon`, `treatPhpDocTypesAsCertain: false`, extensions symfony/doctrine ; `containerXmlPath`/`objectManagerLoader` **non activés**) ; **Deptrac** (`deptrac.yaml`) limité à `Domain`/`Application`/`Shared` (interdit Doctrine/ApiPlatform/Symfony sauf `Uid|Clock`) — Auth/Client/Photo hors périmètre ; `composer audit` bloquant.
- **TS** : ESLint 10 + typescript-eslint + react-hooks (web seulement) ; **mobile : lint = `tsc --noEmit` uniquement** ; TypeScript ~6.0 (web) / ~5.9 (mobile), strict.
- **Transverse** : Prettier 3 (`format:check` en CI), lefthook (`pre-commit` prettier sur fichiers stagés, `commit-msg` commitlint, `pre-push` `pnpm lint`), commitlint (Conventional Commits), CodeQL **JS/TS uniquement** (pas PHP), Dependabot (npm, composer, actions), `pnpm audit --audit-level=high` non bloquant.

### 2.3 Workflows CI (`.github/workflows`)

- `ci.yml` (310 l.) : jobs `format`, `audit` (js), `api` (setup-php 8.5 + imagick, services Postgres 18 + Redis 8, composer install/audit, PHPStan, Deptrac, PHPUnit), `web` (lint, tests browser avec Chrome for Testing, build), `mobile` (tsc, jest, `expo export` iOS + Android — **bundle JS seulement, pas de build natif**, issue #87 ouverte), `ci-pass` (agrégateur), `deploy-api` (flyctl remote) et `deploy-web` (wrangler → Cloudflare Pages) sur push `main`.
- `pr-checks.yml` (commitlint sur la PR, ~0,5-0,8 min), `codeql.yml` (hebdo + PR, ~1,0-1,4 min).

Durées observées (runs `27511741787` PR 6.1, `27511921425` / `27479391840` push `main`) :

| Job                 | Durée                        |
| ------------------- | ---------------------------- |
| ci / format         | 20-23 s                      |
| ci / audit (js)     | 17-24 s                      |
| ci / api            | 56-67 s                      |
| ci / web            | 37-50 s                      |
| ci / mobile         | 107-120 s (job le plus long) |
| deploy / api        | 82-159 s                     |
| deploy / web        | 35-46 s                      |
| **Total PR**        | ~2,0-2,4 min mur             |
| **Total push main** | ~3,7-5,8 min mur             |

---

## 3. Web (`apps/web`) — Vite 8 + React 19 + TanStack

- **Routes** (TanStack Router file-based, `src/routes/`, 8 fichiers, 92 l.) : `__root`, `/` (échange `login_code` OAuth puis redirect), `/login`, `/register`, layout `_authed` (garde `requireAuth` + `AppShell`), `/dashboard`, `/clients`, `/chantiers/$id`. → **6 URL**.
- **Container/view (ADR 0007)** : 5 containers `*Page` (Login, Register, Dashboard, ClientsList, ChantierDetail) ; 9 vues pures (`LoginView`, `RegisterView`, `DashboardView` 470 l., `ChantierFormView`, `ClientsListView` 399 l., `ClientFormView`, `ChantierDetailView`, `PhotoGalleryView`, `PhotoLightbox`) ; pages = 2 565 l. Composants : `AppShell`, `GoogleSignInButton`, 8 composants shadcn/ui (601 l., Radix). 1 hook `usePhotoUpload` (prepare → PUT R2 → confirm). 5 `useQuery`, 6 `useMutation`.
- **Dépendances clés** (19 prod / 21 dev) : react 19, @tanstack/react-query 5, @tanstack/react-router 1, react-hook-form 7 + zod 4 + @hookform/resolvers, Tailwind v4, Radix (4 paquets), sonner, lucide-react, @sentry/react 10 (initialisé dans `src/lib/sentry.ts`).
- **Consommation des types partagés** : **partielle**. `src/lib/api.ts` (334 l.) n'importe que `components["schemas"]["Chantier"]` et `["Client"]` (2 usages), **intersectés** avec des champs ré-écrits à la main (les props générées sont optionnelles). Payloads (`CreerChantierPayload`, `CreerClientPayload`, `Modifier*`), `Photo`, `UserMe`, réponses auth : **écrits à la main**. Les endpoints `/auth/*` ne sont pas dans l'OpenAPI.

## 4. Mobile (`apps/mobile`) — Expo SDK 56, RN 0.85, New Architecture

- **Écrans** (expo-router, `app/`, 1 891 l.) : 8 écrans + 3 layouts — `(auth)/login`, `(auth)/register`, `(app)/index` (liste chantiers), `(app)/chantiers/new`, `(app)/chantiers/[id]` (**562 l.**, détail + photos + visionneuse + légende), `(app)/clients/index`, `(app)/clients/new`, `(app)/clients/[id]`. Composants : 6 (379 l.). `context/AuthContext.tsx` (85 l.).
- **Schéma SQLite/Drizzle** (`db/schema.ts`, 89 l.) : 5 tables — `chantiers`, `clients`, `outbox` (entity_type `chantier|client|photo`, operation `create|update|delete`, status `pending|syncing|synced|error|abandoned`, retry_count, payload JSON texte), `photos` (`local|confirmed`, `lot_id`/`tache_id` déjà prévus), `outbox_photos` (`pending|uploading|confirming|confirmed|failed`). 3 migrations drizzle-kit (`db/migrations/0000…0002`), inlinées via `babel-plugin-inline-import`.
- **Mécanique outbox/sync** (~920 l. au total) :
  - `db/outbox.ts` (252 l.) : `processOutbox` séquentiel ordonné par `createdAt`, mutex module `isSyncing`, backoff exponentiel capé 30 s, `MAX_RETRY=10`, 409 → `synced` (idempotence UUID client), 4xx → `abandoned`, 5xx/réseau → retry, purge des `synced` > 7 j ; `pushEntry` = chaîne `if/else` **3 types × 3 opérations** avec payloads `Record<string, unknown>` castés (`as Parameters<typeof …>`).
  - `db/photoOutbox.ts` (212 l.) : machine à états prepare → PUT R2 (`FileSystem.uploadAsync`, statut HTTP vérifié manuellement) → confirm, reprise des `uploading` bloqués, retry manuel.
  - `db/photoSync.ts` (33 l., réconciliation suppressions), `db/queries.ts` (207 l., upsert/reconcile), `lib/sync.ts` (51 l., `refreshAll`), `hooks/useSyncWorker.ts` (56 l. : event `expo-network` + `AppState=active` + **polling 5 s**), `hooks/useOfflineMutation.ts` (43 l., 6 sites d'appel, UUID v4 client via `expo-crypto`, anti double-tap 500 ms), `useSyncStatus`, `useNetworkStatus`, `components/SyncStatusBar.tsx`.
- **Client API** : `lib/api.ts` (281 l.) quasi-duplicat de `apps/web/src/lib/api.ts` (même usage partiel de `@cordeau/shared`).
- **Modules natifs / config plugins** : CNG (`ios/`/`android/` gitignorés), EAS (profils development/preview/production, `eas.json`). Plugins `app.json` : expo-router, expo-secure-store, expo-font, expo-web-browser, expo-image-picker (textes permissions), **`plugins/withFollyNoCoroutines.js`** (71 l., patch Podfile `FOLLY_CFG_NO_COROUTINES` pour Xcode 26.5). Aucun module natif custom (AR repoussé V2).
- **Dépendances natives** (~17 avec code natif) : expo-sqlite, expo-secure-store, expo-image-picker, expo-file-system, expo-network, expo-crypto, expo-auth-session, expo-web-browser, expo-font, expo-constants, expo-linking, expo-splash-screen, expo-status-bar, react-native-reanimated 4.3.1, react-native-worklets 0.8.3, react-native-screens, react-native-safe-area-context, react-native-svg. JS pur : drizzle-orm, TanStack Query, RHF, zod, NativeWind 4 (Tailwind 3), lucide-react-native, react-native-image-viewing.
- **Absent** : SDK Sentry mobile (DSN dans `eas.json` mais pas de `@sentry/react-native` ; `// TODO: migrate … Sentry` dans 2 hooks), malgré « Sentry sur les 3 apps » coché en Phase 0.
- Contrainte monorepo : `node-linker=hoisted` obligatoire pour Metro (ADR 0022).

---

## 5. Logique métier dupliquée ou à dupliquer

### 5.1 Aujourd'hui

| Règle                                                      | API (PHP)                                                                      | Web (TS)                                                                                                           | Mobile (TS)                                                                                              |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------- |
| Adresse : rue/ville non vides, CP FR 5 chiffres, pays ISO2 | `Shared/ValueObject/Adresse.php` + `#[Assert]` des 4 payloads Chantier/Client  | `pages/dashboard/DashboardPage.tsx`, `pages/clients/ClientsListPage.tsx` (CP 5 chiffres **quel que soit le pays**) | `lib/chantier.ts`, `lib/client.ts` (idem)                                                                |
| Surface > 0 (et ≤ 100 000 m² côté API)                     | `Domain/Chantier/ValueObject/Surface.php` + `Assert\Positive`                  | `DashboardPage.tsx` refine > 0 (**pas de max**)                                                                    | `lib/chantier.ts` refine > 0 (pas de max)                                                                |
| Email client                                               | `Assert\Email`                                                                 | regex `ClientsListPage.tsx`                                                                                        | regex `lib/client.ts`                                                                                    |
| Téléphone FR → E.164                                       | `Client/ValueObject/Telephone.php`                                             | — (brut)                                                                                                           | — (brut)                                                                                                 |
| Politique mot de passe (≥ 8, 1 maj, 1 chiffre)             | `Presentation/Api/Auth/RegisterController.php` (+ `CreateUserCommand`, cf #65) | `pages/register/RegisterPage.tsx`                                                                                  | `app/(auth)/register.tsx`                                                                                |
| Statut chantier : valeurs, libellés, couleurs, terminal    | `Enum/StatutChantier.php` (`estTerminal`), garde `Chantier::changerStatut`     | `DashboardView.tsx` `STATUT_LABELS` + `index.css` `.badge-*`                                                       | `lib/chantier.ts` `STATUT_LABELS`/`STATUT_COLORS` ; `chantiers/[id].tsx` masque les actions si `archive` |
| Statut initial à la création                               | `Chantier::creer()`                                                            | —                                                                                                                  | `chantiers/new.tsx` (optimiste : `statut: "en_preparation"`)                                             |
| Dénormalisation nom client (ClientRef)                     | `ClientRenameListener`, `Presentation/Api/Support/ClientRefResolver.php`       | —                                                                                                                  | `chantiers/new.tsx` calcule `clientNom` ; colonne SQLite `chantiers.client_nom`                          |
| Contrat HTTP (types de payload)                            | 7 payloads PHP                                                                 | `src/lib/api.ts` (334 l., écrits main)                                                                             | `lib/api.ts` (281 l., quasi-copie)                                                                       |
| Idempotence UUID client                                    | processors (`ChantierDejaExistantException` → 409)                             | —                                                                                                                  | `db/outbox.ts` (409 → synced)                                                                            |

Statuts typés `string` / `Record<string, string>` côté TS (pas d'union littérale) ; les transitions `passerEnCours`/`terminer` du domaine **ne sont exposées par aucun endpoint** (`ModifierChantierPayload` ne porte pas `statut`, seul l'archivage via DELETE).

### 5.2 À dupliquer avec Lots / Tâches / Mesures / Devis (ADR 0015, 0024, ROADMAP Phases 6-8)

- **`ModeFacturation::unite()`** : ADR 0024 acte que « tout consommateur (API, web, mobile) doit dériver l'unité du mode » → 3 implémentations si l'unité n'est pas exposée par la ressource.
- **`Lot::changerMode()`** (reset estimation/réel), invariants `valeur ≥ 0`, nom non vide, libellé de tâche non vide, `Tache::cocher` (horodatage `faiteLe`), `ordre` assigné client : à reproduire en **optimiste hors-ligne** sur mobile (6.3) + validations zod web/mobile.
- **Outbox** : +5 types d'entité (lot, tâche, matériau, mesure, pointage) avec **dépendance d'ordre parent → enfants** (memory Serena) ; la chaîne `if/else` de `pushEntry` passe de 3 à 8 types.
- **`Mesure::calculer()`** (ADR 0015, Phase 7.1) : calcul longueur/surface/volume depuis dimensions — la Phase 7.3 demande le « calcul auto » sur mobile, donc **PHP + TS** (hors-ligne).
- **Pointage / chrono** : pointage ouvert (`fin = null`), rappels locaux > 12 h (mobile seul), cumul (serveur + web 7.4).
- **Matériau** : bascule `PREVU`/`UTILISE`, écart prévu-utilisé.
- **Devis / Facture** (Phase 8) : `MontantHT/TTC`, `TauxTVA`, « totaux auto » en édition **mobile (8.5)** + **web (8.6)** + PDF serveur (8.4) → 3 implémentations d'un calcul monétaire ; états `EtatDevis` (6) / `EtatFacture` (3) ; génération de brouillon depuis les Lots (lignes auto SURFACE/FORFAIT), facture depuis Devis accepté + Matériaux `UTILISE` + Pointages ; numérotation `DEV-{yyyy}-{seq}` / `FAC-{yyyy}-{seq}` serveur uniquement (en ligne).
- Point d'attention factuel : les valeurs déjà « monétaires » (estimation d'un lot `FORFAIT` en €) sont des `float` PHP et `number` JS ; seule la colonne SQL est décimale. Aucune bibliothèque décimale n'est présente dans le repo.

Aucun package de logique partagée n'existe : `packages/shared` ne contient que des types générés (et un type `HealthResponse` manuel).

---

## 6. Historique

### 6.1 Vélocité par phase (PR mergées ; additions incluent lockfiles/docs)

| Phase                                                  | PRs                           | Fenêtre de merge         | +/−              | Estimation ROADMAP | Observé                                                               |
| ------------------------------------------------------ | ----------------------------- | ------------------------ | ---------------- | ------------------ | --------------------------------------------------------------------- |
| 0 Fondations                                           | (commits directs, 26)         | 05-03 → 05-04            | —                | ~1 sem             | 2 j                                                                   |
| 1 Chantiers                                            | #6-#10 (5)                    | 05-05 → 05-09            | +10 404 / −1 157 | 2-3 sem            | 5 j                                                                   |
| 2 Clients                                              | #16-#21 (6)                   | 05-14 → 05-15            | +5 102 / −82     | 1-2 sem            | ~1,5 j (« ×5 vs Phase 1 »)                                            |
| 3 Offline-first                                        | #27-#34 (8)                   | 05-15 (+ fixes 05-16)    | +2 513 / −342    | ~2 sem             | ~1 j + correctif                                                      |
| 4 OAuth Google                                         | #38-#42 (5)                   | 05-16 (+ fixes 05-17/19) | +2 310 / −10     | ~1 j               | 1 j + 3 j de fixes prod                                               |
| Intermède (CI, Prettier, ADRs, kill-switch, SDK 54→56) | 9 PRs humaines + 9 Dependabot | 05-23 → 06-08            | +6 324 / −3 219  | —                  | ~2 sem                                                                |
| 5 Photos + R2                                          | #77, #82, #85, #88-#99 (10)   | 06-11 → 06-14            | +7 090 / −896    | ~1 sem             | ~5 j (PR #77 ouverte 06-09, 30 commits)                               |
| 6 Lots/Tâches                                          | #104, #105 (2)                | 06-14                    | +1 700 / −160    | 2-3 sem            | 6.1 en 1 PR (+1 477) ; **6.2-6.4 non démarrées, 109 j sans activité** |

Rétroplanning ROADMAP : bêta payante prévue septembre 2026 — à date, Phases 6 (partielle), 7, 8 non livrées.

### 6.2 Bugs rencontrés et classification « types plus stricts (Rust) »

Sources : 33 commits `fix…` sur `main`, PR `fix:` #34/#88/#90/#92/#96 (sections « Audit système de test » présentes dans #88, #90, #98 ; « Note test » dans #92/#96), issues `type:bug` #89/#91/#94, issues #81/#83/#84.

| Bug                                                                                                                                            | Où           | Couche / nature                                            | Rust (API) l'aurait-il attrapé ?                                                                                                               |
| ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------ | ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Phase 0 : conflit pnpm, `.env` manquants, collision suffixe DB test, jsdom/happy-dom, `act` React 19, globals TS6 (8 commits)                  | CI/outillage | config / tooling                                           | Non (hors code applicatif)                                                                                                                     |
| Deploy CI (`cache:warmup` Dockerfile, wrangler), `android.package` EAS, Metro import `.sql`                                                    | infra/build  | config                                                     | Non                                                                                                                                            |
| Auth web cookie → Bearer (`d883d9b`, SameSite cross-origin)                                                                                    | API + web    | architecture / navigateur                                  | Non                                                                                                                                            |
| X-Forwarded Fly.io pour HTTPS OAuth (`b43c844`), allow-all-ip dev (`1403541`)                                                                  | API          | proxy / infra                                              | Non                                                                                                                                            |
| TwigBundle absent en prod mais configuré (`bfa2121`, `ca11b72`)                                                                                | API          | câblage DI/bundles dépendant de l'env (crash au boot prod) | **Classe réduite** : câblage explicite compilé (pas de config par env réflexive), mais pas « par les types » stricto sensu                     |
| Proxy Doctrine lazy incompatible `readonly` → fetch EAGER (`9ec2c8c`)                                                                          | API          | runtime ORM                                                | **Classe absente** sans ORM à proxies (SQLx) — éliminée par la stack, pas détectée par les types                                               |
| `stateless: false` en test pour `loginUser()` (`f483455`)                                                                                      | API tests    | config de test                                             | Non                                                                                                                                            |
| Assert redondant signalé PHPStan (`1e69961`)                                                                                                   | API          | bruit d'analyse statique                                   | N/A (déjà attrapé par PHPStan 9)                                                                                                               |
| Décision ADR 0012 « API accepte l'UUID client » non implémentée → rewrite d'IDs + FK fantômes dans l'outbox (`531026e`, `2d366f9`, PR #32/#33) | mobile + API | oubli de spec / intégrité de données                       | Non (oubli fonctionnel ; côté mobile, payloads `Record<string, unknown>` — un typage somme aurait rendu le champ FK visible, mais c'est du TS) |
| Outbox : retry infini sur 4xx, backoff 7 j (`3804038`, `257a683`)                                                                              | mobile       | politique de retry                                         | Non                                                                                                                                            |
| Race `processOutbox`/`refreshAll` (`3724755`)                                                                                                  | mobile       | ordonnancement async mono-thread                           | Non (pas une data race au sens Send/Sync)                                                                                                      |
| Event `expo-network` peu fiable iOS → polling (#34, `c74dd1f`)                                                                                 | mobile       | comportement plateforme                                    | Non                                                                                                                                            |
| Build natif iOS cassé SDK 56 : reanimated 3 résiduel du lockfile + folly coroutines Xcode 26.5 (#84/#88)                                       | mobile natif | toolchain C++/lockfile                                     | Non (non détecté car CI = `expo export` seulement)                                                                                             |
| Permissions caméra/galerie iOS (#89/#90)                                                                                                       | mobile       | contrat API plateforme                                     | Non                                                                                                                                            |
| Visionneuse remontée au swipe (#91/#92), clavier masquant la légende (#94/#96)                                                                 | mobile UI    | présentation / lib tierce                                  | Non                                                                                                                                            |
| CORS R2 prod (#83/#97)                                                                                                                         | infra        | config bucket (action dashboard)                           | Non                                                                                                                                            |
| Vignettes : GD sans HEIC + **aucune extension image dans l'image prod** → vignettes jamais générées, masqué par fallback (#81/#98)             | API + Docker | dépendance native runtime + capacité lib                   | **Partiel** : une lib liée statiquement échoue au build/link plutôt qu'à l'exécution ; le support HEIC reste une question de lib C (libheif)   |
| PUT R2 non rejeté sur statut HTTP d'erreur (commentaire `photoOutbox.ts`)                                                                      | mobile       | contrat de lib (`uploadAsync`)                             | Non (TS ; un `Result` forcerait la gestion d'erreur réseau, pas du statut HTTP)                                                                |

Synthèse factuelle : sur ~25 bugs distincts, **~19 relèvent de config/infra/toolchain/plateforme/UI**, **3-4 de logique de sync mobile** (TS, hors périmètre d'une migration de l'API), **0 bug d'API qui soit une erreur de typage manquée par PHPStan niveau 9**. 2-3 bugs API appartiennent à des **classes liées à la magie runtime de Symfony/Doctrine** (DI par env, proxies ORM, extension PHP absente) qu'une stack Rust compilée rend structurellement improbables ou détectables au build. Les bugs les plus coûteux (#84 build natif, #83 CORS, #81 extension prod) ne sont pas du ressort d'un système de types.

Fragilités de typage visibles dans le code actuel (non encore des bugs) : `float` pour des montants/estimations, payloads d'outbox `Record<string, unknown>` + casts, statuts en `string` côté TS, types générés OpenAPI à props optionnelles recomplétés à la main, absence de contrôle de dérive OpenAPI en CI.

---

## 7. Synthèse — classement du plus au moins « migrable » vers Rust

Critères : couplage framework, présence de magie runtime, dépendances natives, contrat externe à préserver, volume de tests à porter, écosystème Rust équivalent.

1. **Domaine Chantier/Lot/Tâche + VO + `Shared`** (`src/Domain/Chantier`, `src/Shared`, `Client/ValueObject/Telephone` — ~920 l., ~660 l. code ; 69 tests unitaires sans DB : 55 domaine + 10 Adresse + 4 Téléphone). Déjà pur (Deptrac l'impose), immuable, enums backed, exceptions métier = `Result`/enum d'erreurs. Correspondance quasi 1:1 (structs, enums, `impl`). C'est aussi le périmètre de l'expérience A (PR #105). Le futur domaine (Mesure, totaux Devis) est de même nature.
2. **Couche Application** (5 use cases, 151 l.) : orchestration mince sur ports → traits.
3. **Persistance Chantier** (`Infrastructure/Persistence/Doctrine/Chantier`, 509 l. ; 9 migrations SQL) : le mapping domaine ↔ entité Doctrine est **déjà manuel** ; DDL Postgres réutilisable tel quel (sqlx/refinery). Points durs : conversion `NUMERIC` (aujourd'hui `float`), colonne JSON `imprevus`, entity listener de dénormalisation (à rendre explicite), 22 tests d'intégration persistance.
4. **Déploiement API** (Dockerfile FrankenPHP, `fly.toml`) : binaire statique plausible dans 256 Mo, `release_command` à remplacer ; le point bloquant est ImageMagick/libheif (dépendance C, durcissement policy.xml).
5. **Photo / jobs async / R2** (829 l.) : presign S3 (aws-sdk-s3 Rust le supporte), Messenger → file à reconstruire (aucun consumer déclaré aujourd'hui, mode prod à clarifier), vignettes HEIC → bindings C (pas de décodeur HEIC pur Rust courant). Migrable mais avec dépendance native.
6. **Couche HTTP / API Platform + CRUD légers Client/Photo** (~1 100 l. Presentation + 75 tests HTTP) : API Platform est utilisé comme routeur + validation + **générateur OpenAPI** ; pas de pagination/filtres/groupes à reproduire, mais il faut **préserver le contrat** consommé par `packages/shared` (noms de schémas `Chantier`, `Client.CreerClientPayload`, `…jsonMergePatch`, formats JSON-LD/merge-patch, codes 409/422/404) ou régénérer les types côté clients (utoipa/aide, ou génération TS depuis Rust). Coût dominé par le portage des tests HTTP et par la compatibilité de contrat.
7. **Auth** (1 528 l. — la plus grosse surface ; 38 tests) : tokens opaques selector/verifier (bcrypt par requête), refresh SHA-256, OAuth Google web (redirect) + mobile (tokeninfo), login-code, kill-switch, CLI. Équivalents Rust existent (oauth2, bcrypt, axum extractors) mais surface **sécurité** = ré-audit obligatoire (ADR + `/security-review` selon CLAUDE.md), aucun bénéfice fonctionnel.
8. **`packages/shared`** : non migré en soi — c'est un **artefact** ; il change de générateur si l'API change de langage.
9. **Web React** (4,4 k l.) : pas de cible Rust réaliste sans réécriture complète (Leptos/Dioxus) et perte de l'écosystème TanStack/shadcn/Radix/Vitest Browser. Seule la **logique partagée** (validations, calculs) serait techniquement exportable via WASM — non existante aujourd'hui en tant que module.
10. **Mobile Expo** (4,7 k l., ~17 modules natifs, config plugin, offline/outbox couplé à TanStack Query + Drizzle + expo-sqlite) : **le moins migrable**. Un cœur Rust embarqué (UniFFI/JSI) ajouterait une toolchain native supplémentaire à un projet dont le bug le plus coûteux (#84) est déjà un bug de build natif, et la CI ne compile pas le natif (#87). La logique de sync (outbox, réconciliation) est spécifique à l'environnement RN/Expo.

Constat transverse : la surface PHP totale est **petite** (~5 k l. src + ~3,7 k l. tests + 0,35 k l. migrations ≈ 9 k lignes) et la partie la plus migrable (domaine pur) est aussi la plus petite et la mieux testée. La duplication métier réelle se situe **entre PHP et les deux clients TS** (validations, statuts, et demain unité de mode, `Mesure::calculer`, totaux Devis) ; une migration de l'API seule ne la réduit pas.
