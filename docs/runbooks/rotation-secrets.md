# Runbook — Rotation des secrets

> Procédure pour rotater les credentials utilisés en CI/CD et en production. À exécuter dans les cas suivants :
>
> - **Passage du repo en public** (cas initial mai 2026 — cf [ADR à venir 0019](../adr/README.md) si formalisé)
> - **Suspicion de fuite** : commit malheureux d'un `.env*`, log CI imprimant un secret, accès non autorisé à un dashboard
> - **Rotation périodique** : 1×/an minimum, 1×/trimestre pour les tokens à fort blast radius (Fly API token, CF API token)
> - **Départ d'un collaborateur** ayant eu accès aux secrets prod
>
> Ce runbook **ne contient aucune valeur de secret** — uniquement des procédures. Les valeurs générées vont directement des CLIs/dashboards vers Fly secrets / GitHub Secrets / Cloudflare env vars, sans transiter par le repo.

## Exécution : humain, Claude Code, ou les deux

Ce runbook est rédigé pour être suivi à la main, mais **la plupart des plateformes ont un MCP officiel** qui permet de déléguer l'exécution à Claude Code. État au 2026-05 :

| Plateforme | MCP officiel | Installé en session ? | Lien |
|---|---|---|---|
| **Neon** | ✅ `mcp__Neon__*` (run_sql, get_connection_string, etc.) | ✅ oui | [neon.tech](https://neon.tech/docs/ai/neon-mcp-server) |
| **Fly.io** | ✅ `mcp__fly__*` (secrets-set, logs, status, etc.) | ✅ oui | [fly.io/docs/mcp](https://fly.io/docs/mcp/) |
| **Sentry** | ✅ `mcp__sentry__*` (find_dsns, create_dsn, find_projects, etc.) | ✅ oui | [mcp.sentry.dev](https://mcp.sentry.dev/) |
| **Cloudflare** | ✅ catalogue de 13 MCP (Workers, Pages, DNS…) hébergés sur `*.workers.dev` | ❌ à activer | [blog.cloudflare.com/thirteen-new-mcp-servers](https://blog.cloudflare.com/thirteen-new-mcp-servers-from-cloudflare/) |
| **Google Cloud** | ✅ MCP officiel avec OAuth client ID/secret | ❌ à activer | [docs.cloud.google.com/mcp](https://docs.cloud.google.com/mcp/overview) |
| **GitHub Secrets** | (pas de MCP officiel, mais `gh` CLI couvre tout) | — | — |

**Recommandation** : Neon + Fly + Sentry sont déjà installés — Claude peut mener la rotation des secrets 1, 2, 3 et 6 en autonomie supervisée. Cloudflare et Google Cloud restent manuels (dashboard rapide, rotation moins fréquente).

**Pour activer un MCP manquant** : généralement éditer `~/.claude/settings.json` ou via slash command `/mcp` selon ton client. Avant d'activer un MCP en prod, **vérifier les permissions** qu'il demande : un MCP avec scope `account:write` sur Cloudflare est plus dangereux qu'un MCP read-only.

**Workflow recommandé** quand tu lances une rotation avec Claude (après activation des MCPs) :
> « Lance le runbook `docs/runbooks/rotation-secrets.md` étape par étape, en utilisant les MCPs disponibles. Stoppe après chaque étape pour validation. »

Pour les étapes sans MCP installé, Claude reste en mode "guide" : il dicte les commandes, tu les exécutes.

## Audit séparation prod/dev (check préliminaire)

Avant chaque exécution de ce runbook, **vérifier que les secrets dev et prod sont bien distincts** — sinon une rotation prod n'invalide pas l'usage dev (et vice-versa), et le principe même de la rotation est cassé.

| Secret | Dev | Prod | Comment vérifier |
|---|---|---|---|
| `APP_SECRET` | `.env.local` (apps/api) | Fly secrets | Comparer la valeur `.env.local` avec `fly secrets list -a cordeau-api` (digests seuls visibles côté Fly). Si la même chaîne tourne dans les deux, **un seul secret partagé** = anti-pattern |
| `DATABASE_URL` | `docker-compose` Postgres (`cordeau:cordeau@127.0.0.1`) | Neon Frankfurt (`*.aws.neon.tech`) | Univers disjoints par nature, vérifier juste qu'`.env.local` ne pointe pas accidentellement vers Neon |
| `GOOGLE_CLIENT_ID/SECRET` | OAuth client "Dev" (origins `localhost`) | OAuth client "Prod" (origins `cordeau-web.pages.dev`) | Google Cloud Console → Credentials → **devrait afficher au moins 2 clients web** (dev + prod). Si un seul : trou de séparation (cf [ADR à venir si formalisé]) |
| `GOOGLE_OAUTH_AUDIENCES` | Client iOS Dev (bundle ID dev) | Client iOS Prod (bundle ID prod) | Idem mais pour iOS — Phase 1 acceptable d'avoir 1 seul iOS client (pas de `secret`), à séparer Phase 6+ |
| `SENTRY_DSN` (×3) | Même DSN que prod | DSN unique par projet | Vérifier dans Sentry projects qu'on a soit (a) 3 projets api/web/mobile avec `SENTRY_ENVIRONMENT=development|production` côté code, soit (b) 6 projets distincts. Sans environment ni séparation : pollution garantie |

**Si un trou est détecté** : créer le 2e secret (côté dev OU prod selon le cas), mettre à jour les destinations, **puis** lancer la rotation. Pas l'inverse.

**Pourquoi cette discipline** : si dev et prod partagent un secret et que ce secret fuit (gitleaks finding, log CI public, compromise d'un poste dev), la rotation côté prod ne suffit pas — l'attaquant garde la main sur dev jusqu'à ce qu'on rotate aussi côté dev. Inversement, un dev qui change son `.env.local` par flemme peut faire planter la prod si les valeurs sont liées.

## Inventaire des secrets

| # | Secret | Stocké dans | Plateforme source | Blast radius si fuite |
|---|---|---|---|---|
| 1 | `APP_SECRET` | Fly secrets (`cordeau-api`) | généré localement (`openssl rand`) | Forge de tokens CSRF / signatures Symfony |
| 2 | `DATABASE_URL` | Fly secrets (`cordeau-api`) | Neon console | Accès lecture/écriture DB prod |
| 3 | `GOOGLE_CLIENT_SECRET` | Fly secrets (`cordeau-api`) | Google Cloud Console | Usurpation de l'OAuth flow Google |
| 4 | `FLY_API_TOKEN` | GitHub Secrets (repo) | `fly tokens create` | Déploiement arbitraire sur Fly |
| 5 | `CLOUDFLARE_API_TOKEN` | GitHub Secrets (repo) | Cloudflare dashboard | Déploiement arbitraire sur CF Pages |
| 6 | `VITE_SENTRY_DSN` | GitHub Secrets (repo) + Cloudflare env | Sentry project (web) | Pollution du projet Sentry web |
| 7 | `SENTRY_DSN` (api) | Fly secrets (`cordeau-api`) | Sentry project (api) | Pollution du projet Sentry api |
| 8 | `EXPO_PUBLIC_SENTRY_DSN` | `apps/mobile/eas.json` (committé) | Sentry project (mobile) | Pollution du projet Sentry mobile |

> **Note** : pas de `JWT_SECRET` chez Cordeau — l'authentification API utilise des sessions Symfony + Bearer tokens auto-signés (selon la config `security.yaml`), pas du JWT signé manuellement. Vérifier dans `fly secrets list -a cordeau-api` avant chaque audit qu'aucun nouveau secret non listé ici n'est apparu.

**Non-rotables / non-critiques** :
- `CLOUDFLARE_ACCOUNT_ID` : identifiant statique du compte, pas un secret au sens strict
- `GOOGLE_CLIENT_ID` : public par design OAuth (apparaît dans les URLs `accounts.google.com/oauth/authorize`)
- CI fixtures Postgres (`cordeau:cordeau`, `ci_secret_for_tests`) : constantes de container éphémère, pas des secrets

## Prérequis

CLIs à installer si pas déjà fait :

```bash
brew install flyctl                           # Fly.io
brew install gh                               # GitHub CLI (probablement déjà là)
brew install neonctl                          # Neon (optionnel — la console marche aussi)
# openssl est natif macOS

fly auth login                                # ouvre le navigateur
gh auth status                                # vérifier le login GH
```

Accès dashboards :
- Fly : https://fly.io/dashboard
- Neon : https://console.neon.tech
- Cloudflare : https://dash.cloudflare.com
- Google Cloud : https://console.cloud.google.com
- Sentry : https://cordeau.sentry.io (ou ton organisation)

## Ordre recommandé et pourquoi

Du **moins disruptif** au **plus impactant** — pour pouvoir interrompre la rotation au milieu sans tout casser :

1. **APP_SECRET** (étape 1) : pas de dépendance externe, juste un `fly secrets set` qui redémarre l'API
2. **DATABASE_URL** (étape 2) : reset password Neon + mise à jour Fly. Quelques secondes de downtime pendant le restart
3. **FLY_API_TOKEN** (étape 3) : invalide les pipelines CI en cours mais ne casse rien en runtime
4. **CLOUDFLARE_API_TOKEN** (étape 4) : pareil, déploiement CI seulement
5. **GOOGLE_CLIENT_SECRET** (étape 5) : casse les nouveaux logins Google pendant la propagation (~quelques min). Sessions existantes OK
6. **SENTRY_DSN ×3** (étape 6) : non-critique, peut être fait n'importe quand

**Gotchas observés** :
- `fly secrets set` redémarre l'app — donc 5-30s d'indisponibilité par rotation. Les étapes 1, 2 et 5 touchent toutes à `fly secrets set` : les enchaîner si possible (un seul `fly secrets set` multi-args restart) plutôt que d'en faire trois séparés
- Toujours utiliser **single-quotes** dans `fly secrets set` pour échapper les caractères spéciaux (`@`, `&`, `$`) : `fly secrets set 'KEY=valeur@avec$caractères&speciaux'`
- Après chaque rotation côté GitHub Secrets, **ne pas relancer manuellement** un workflow — attendre le prochain push ou un trigger explicite

---

## Étape 1 — `APP_SECRET` (Symfony)

Secret utilisé par Symfony pour signer les CSRF tokens et autres signatures internes.

```bash
# 1. Générer un nouveau secret (64 chars hex)
NEW_APP_SECRET=$(openssl rand -hex 32)

# 2. Mettre à jour Fly (redémarre l'app)
fly secrets set "APP_SECRET=$NEW_APP_SECRET" -a cordeau-api

# 3. Vérifier
fly logs -a cordeau-api | head -20   # pas d'erreur au boot
curl -s https://cordeau-api.fly.dev/health   # 200 OK
```

**Effet de bord** : les CSRF tokens en cours d'utilisation deviennent invalides. En pratique sans impact (l'API est stateless, pas de session Symfony à invalider côté front).

---

## Étape 2 — `DATABASE_URL` (Neon)

Reset du password du rôle `cordeau_owner` (ou rôle équivalent — vérifier dans la console Neon).

**Option A — Via la console Neon (plus simple)** :

1. https://console.neon.tech → projet Cordeau → onglet **Roles**
2. Cliquer sur `cordeau_owner` (ou le rôle qui sert d'API user)
3. **Reset password** → copier la nouvelle valeur
4. Onglet **Connection Details** → copier la nouvelle `DATABASE_URL` complète (avec le nouveau password déjà inséré)
5. Mettre à jour Fly :

```bash
fly secrets set 'DATABASE_URL=postgresql://cordeau_owner:NOUVEAU_PWD@ep-xxx.eu-central-1.aws.neon.tech/neondb?sslmode=require' -a cordeau-api
```

**Option B — Via `neonctl`** :

```bash
neonctl roles reset-password cordeau_owner --project-id <id>
# récupère le nouveau password, puis fly secrets set comme en Option A
```

**Option C — Via MCP Neon (depuis Claude Code)** — la plus directe pour Claude :

1. Générer un password fort : `openssl rand -base64 32` (en local) — on évite que le password apparaisse dans le contexte de Claude
2. Demander à Claude : « Via le MCP Neon, lance un `ALTER ROLE cordeau_owner WITH ENCRYPTED PASSWORD '...'` sur le projet Cordeau, puis récupère la connection string via `get_connection_string` »
3. Claude appelle `mcp__Neon__run_sql` avec la requête SQL, puis `mcp__Neon__get_connection_string` pour la nouvelle `DATABASE_URL`
4. Tu copies la valeur retournée et tu lances toi-même `fly secrets set 'DATABASE_URL=...' -a cordeau-api` (étape qui sort du MCP)

**Si le MCP Fly est aussi installé** (`fly mcp server --claude --server flyctl`), Claude peut chaîner les deux : Neon MCP pour rotater + récupérer la connection string, puis Fly MCP pour exécuter le `fly secrets set` directement, sans étape manuelle. Sans MCP Fly, tu propages toi-même côté Fly.

**Vérification** :

```bash
fly logs -a cordeau-api | grep -i doctrine | head    # pas d'erreur connection refused
curl -s https://cordeau-api.fly.dev/health           # 200 OK
```

---

## Étape 3 — `FLY_API_TOKEN`

Token utilisé par GitHub Actions pour déployer sur Fly.

```bash
# 1. Créer un nouveau token (scope deploy uniquement)
fly tokens create deploy -a cordeau-api --expiry 8760h   # 1 an

# Copier la valeur affichée (commence par "FlyV1 ...")
# IMPORTANT : ce token n'est affiché qu'une seule fois

# 2. Mettre à jour le secret GitHub
gh secret set FLY_API_TOKEN --body "FlyV1 ..."

# 3. Révoquer l'ancien
fly tokens list -a cordeau-api               # identifier l'ancien token (le plus vieux)
fly tokens revoke <token-id-ou-name>

# 4. Vérifier au prochain push, ou trigger manuellement :
gh workflow run ci.yml
```

---

## Étape 4 — `CLOUDFLARE_API_TOKEN`

Pas de CLI rotate disponible — uniquement via le dashboard.

1. https://dash.cloudflare.com → cliquer ton avatar → **My Profile** → **API Tokens**
2. **Create Token** → utiliser le template **Edit Cloudflare Pages**
3. Limiter les permissions :
   - **Account** → `Cloudflare Pages: Edit`
   - **Zone resources** → toutes les zones (ou limiter au domaine cordeau)
   - **Account resources** → `Include - <ton compte>`
4. **TTL** : 1 an
5. Copier la valeur affichée (commence par un long string aléatoire)
6. Mettre à jour le secret GitHub :

```bash
gh secret set CLOUDFLARE_API_TOKEN --body "VALEUR_DU_TOKEN"
```

7. **Révoquer l'ancien** : dashboard → API Tokens → l'ancien token → **Roll** ou **Delete**

Vérification : prochain push sur `main` → job `deploy-web` doit passer.

---

## Étape 5 — `GOOGLE_CLIENT_SECRET`

Procédure dashboard uniquement.

1. https://console.cloud.google.com → projet Cordeau → **APIs & Services** → **Credentials**
2. Cliquer sur l'OAuth 2.0 Client ID (Web application) — typiquement nommé `cordeau-web` ou similaire
3. **Reset secret** → confirmer
4. Copier la nouvelle valeur
5. Mettre à jour Fly :

```bash
fly secrets set 'GOOGLE_CLIENT_SECRET=NOUVELLE_VALEUR' -a cordeau-api
```

**Propagation** : Google considère l'ancien secret valide pendant ~24h par défaut (configurable via "client secret rotation" si on a un plan Workspace). Les sessions existantes ne sont pas impactées, seuls les nouveaux logins.

**Vérification** : tenter un login Google sur https://cordeau-web.pages.dev — doit fonctionner avec le nouveau secret.

---

## Étape 6 — `SENTRY_DSN` (×3)

Sentry n'a pas de "rotate" — il faut **créer une nouvelle clé client** et supprimer l'ancienne.

**Pour chaque projet** (`cordeau-api`, `cordeau-web`, `cordeau-mobile`) :

1. https://cordeau.sentry.io → projet ciblé → **Settings** → **Client Keys (DSN)**
2. **Generate New Key** → copier la nouvelle DSN
3. Mettre à jour le destinataire selon le projet :
   - **api** : `fly secrets set 'SENTRY_DSN=https://xxx@xxx.ingest.sentry.io/yyy' -a cordeau-api`
   - **web** : `gh secret set VITE_SENTRY_DSN --body "https://..."` (rebuild auto au prochain deploy)
   - **mobile** : éditer [apps/mobile/eas.json](../../apps/mobile/eas.json), remplacer la valeur de `EXPO_PUBLIC_SENTRY_DSN`, committer, et déclencher un build EAS (`eas build --profile preview --platform android`)
4. **Désactiver l'ancienne clé** : Settings → Client Keys → ancien DSN → **Disable**

**Note** : les DSN Sentry sont **semi-publiques par design** (embarqués dans les bundles web/mobile, visibles dans le code source de toute app installée). La rotation est donc moins critique — ce qui compte c'est de pouvoir révoquer en cas d'abus. Faire la rotation à un rythme plus lent (annuel suffit) sauf incident.

---

## Vérification globale post-rotation

```bash
# 1. API health
curl -s https://cordeau-api.fly.dev/health | jq

# 2. Web health (200 attendu, page de login affichée)
curl -sI https://cordeau-web.pages.dev | head -1

# 3. Logs Fly — pas d'erreur au boot
fly logs -a cordeau-api --since 5m | grep -iE "error|critical|fatal"

# 4. Relancer le CI pour s'assurer que les nouveaux tokens GitHub fonctionnent
gh workflow run ci.yml --ref main
gh run watch $(gh run list --workflow=ci.yml --limit 1 --json databaseId -q '.[0].databaseId') --exit-status

# 5. Test login bout-à-bout (manuel, ~2 min)
#    - Ouvrir https://cordeau-web.pages.dev/login
#    - Login Google → vérifier redirect vers /dashboard
#    - Login email/password sur un compte de test → vérifier session
```

## Audit gitleaks périodique

À relancer **avant tout audit de sécurité externe** ou **avant tout changement structurant de l'accès au repo** (ajout d'un collaborateur, passage de visibilité, etc.) :

```bash
gitleaks detect --source . --log-opts="--all" --verbose \
  --report-format=json --report-path=/tmp/gitleaks-$(date +%Y%m%d).json
```

**Verdict CLEAN attendu** : `0 leaks found`. Si findings : ouvrir une issue `type:chore` + suivre ce runbook pour rotation immédiate du secret impacté.

**Renforcement CI envisageable** (non implémenté à date) : ajouter un job `gitleaks` dans `.github/workflows/ci.yml` qui bloque toute PR introduisant un secret. À étudier si on a une régression future.

## Historique des rotations

À tenir à jour à chaque exécution du runbook — permet de prouver la diligence en cas d'audit.

| Date | Déclencheur | Secrets rotatés | Opérateur | Notes |
|---|---|---|---|---|
| _vide_ | _vide_ | _vide_ | _vide_ | _vide_ |
