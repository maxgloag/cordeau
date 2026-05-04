# Runbook — Déploiement Phase 0 (Lot 4)

> Mise en prod gratuite des 3 environnements Cordeau. Critère de sortie Phase 0 : un commit pushé déclenche la CI verte et `/health` répond depuis l'API, le web, et un build EAS preview installable.
>
> **Trajectoire d'hébergement** : voir [ADR 0008](../adr/0008-trajectoire-hebergement.md). Ce runbook couvre la phase Fly.io + Neon (gratuite, mai → août 2026). La migration vers un hébergeur français est un Lot dédié à venir avant le lancement bêta.

## Vue d'ensemble

| Composant | Hébergement | URL cible | Coût |
|---|---|---|---|
| API (Symfony) | Fly.io région `cdg` (Paris) | `https://cordeau-api.fly.dev` | 0 € |
| Postgres | Neon (Frankfurt, EU central) | connexion via `DATABASE_URL` | 0 € |
| Web (Vite/React) | Cloudflare Pages | `https://cordeau-web.pages.dev` | 0 € |
| Mobile (Expo) | EAS preview build | APK installable (sideload) | 0 € |
| Erreurs | Sentry × 3 projets | `*.sentry.io` | 0 € |

**Le jour où on achète `cordeau-pro.fr`** : on ajoute un certificat custom sur Fly et un domaine custom sur Cloudflare Pages. Pas de changement de code.

## Gotchas rencontrés lors du premier déploiement

- **Neon credentials** : la console Neon crée par défaut le rôle `neondb_owner` et la DB `neondb`, pas `cordeau_owner`/`cordeau`. Toujours récupérer la connection string depuis la console Neon (bouton "Connect") plutôt que la construire à la main. Utiliser **single-quotes** dans `fly secrets set` pour éviter que bash interprète les caractères spéciaux (`@`, `&`, `$`) : `fly secrets set 'DATABASE_URL=postgresql://...'`
- **wrangler-action@v3 incompatible pnpm workspaces** : l'action tente `pnpm exec wrangler` puis `pnpm add wrangler` — les deux échouent dans un workspace root. Utiliser `npx wrangler@latest` dans un step `run:` à la place.
- **Dockerfile : `composer run-script post-install-cmd`** : les plugins Composer Flex sont désactivés dans le build remote Fly (non-interactif, non-superuser même avec `COMPOSER_ALLOW_SUPERUSER=1`). Remplacer par `php bin/console cache:warmup --env=prod` directement.
- **EAS login OAuth/GitHub** : `eas login` est interactif. Pour les comptes GitHub, utiliser `eas login --sso` qui ouvre le navigateur.

## Prérequis (comptes externes)

- [x] **Fly.io** — déjà connecté via GitHub
- [ ] **Neon** — https://console.neon.tech (login GitHub, instantané)
- [ ] **Cloudflare** — https://dash.cloudflare.com/sign-up (instantané)
- [ ] **Expo** — https://expo.dev/signup (login GitHub possible)
- [ ] **Sentry** — https://sentry.io/signup (Developer plan gratuit)

CLI à installer localement :

```bash
# Fly CLI
brew install flyctl
fly auth login   # ouvre le navigateur pour confirmer ton login GitHub

# EAS CLI
pnpm add -g eas-cli  # ou npx eas-cli si tu préfères ne rien installer en global
```

---

## Étape 1 — Neon (PostgreSQL managé, gratuit, EU)

**Objectif** : une DB Postgres 18 hébergée en Frankfurt, connectée à l'API Fly.

### Création du project

1. https://console.neon.tech → **New Project**
2. Configuration :
   - **Project name** : `cordeau`
   - **Postgres version** : `18` (la dernière stable proposée)
   - **Region** : **AWS Europe (Frankfurt)** — `eu-central-1`. ⚠️ Non modifiable a posteriori
   - **Database name** : `cordeau`
3. À la fin, Neon affiche une **connection string** :
   ```
   postgresql://cordeau_owner:<password>@ep-xxxx.eu-central-1.aws.neon.tech/cordeau?sslmode=require
   ```
   → la **copier dans un endroit safe** (1Password, fichier local non committé). On en aura besoin à l'Étape 2.

### Vérification (optionnel)

```bash
# Si tu as psql installé localement
psql "postgresql://cordeau_owner:...@ep-xxxx.eu-central-1.aws.neon.tech/cordeau?sslmode=require" -c "SELECT version();"
```

### Notes Neon

- **Suspension auto** : Neon suspend la DB après 5 min sans connexion (free tier). La 1ère requête après suspension prend ~1-2 s. Acceptable.
- **Branches DB** : Neon permet de créer une branche DB par PR (gratuit). On l'activera en Phase 1 quand on aura des migrations.
- **Backup** : sur le free tier, Neon garde 7 jours d'historique (PITR). Aucun backup manuel à faire en Phase 0.

---

## Étape 2 — Fly.io (API Symfony)

**Objectif** : déployer `apps/api` sur Fly, accessible en HTTPS sur `https://cordeau-api.fly.dev`.

### Création de l'app Fly

Le `Dockerfile` et `fly.toml` sont déjà commités dans `apps/api/`. On ne lance **pas** `fly launch` (qui les écraserait) — on crée l'app à la main puis on déploie.

```bash
cd apps/api

# Crée l'app sur Fly (sans déployer, juste l'enregistrement)
fly apps create cordeau-api --org personal
```

Si le nom `cordeau-api` est déjà pris (espace de noms global Fly.io), prendre `cordeau-api-<initiales>` ou similaire et **mettre à jour `fly.toml` en conséquence** (champ `app = ...`).

### Injection des secrets

```bash
# Génère un APP_SECRET cryptographiquement sûr
APP_SECRET=$(openssl rand -hex 32)

# Renseigne tout d'un coup (déclenche un seul redeploy au lieu de deux)
fly secrets set \
  APP_SECRET="$APP_SECRET" \
  DATABASE_URL="postgresql://cordeau_owner:...@ep-xxxx.eu-central-1.aws.neon.tech/cordeau?sslmode=require&serverVersion=18" \
  --app cordeau-api
```

⚠️ **Important** : ajouter `&serverVersion=18` à la fin de la connection string Neon — Doctrine en a besoin pour ne pas faire un round-trip de détection.

### Premier déploiement

```bash
fly deploy --app cordeau-api
```

Le build Dockerfile prend ~3-5 min (premier run, sans cache). Les suivants : ~1-2 min grâce au cache de layers Fly.

### Vérification

```bash
curl https://cordeau-api.fly.dev/health
# Attendu : {"status":"ok","timestamp":"...","version":"...","services":{...}}
```

Si erreur 502/503 : `fly logs --app cordeau-api` pour voir le démarrage.

### Déploiement continu (CI GitHub Actions)

À ajouter dans `.github/workflows/ci.yml` après que la première deploy manuelle ait fonctionné :

```yaml
deploy-api:
  name: deploy / api
  needs: api  # Attend que les tests API passent
  if: github.ref == 'refs/heads/main'
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v6
    - uses: superfly/flyctl-actions/setup-flyctl@master
    - run: flyctl deploy --remote-only --config apps/api/fly.toml
      working-directory: apps/api
      env:
        FLY_API_TOKEN: ${{ secrets.FLY_API_TOKEN }}
```

Pour générer le token :

```bash
fly tokens create deploy --app cordeau-api --expiry 8760h  # 1 an
# Copier la sortie et l'ajouter dans GitHub :
# Settings → Secrets and variables → Actions → New repository secret
# Nom : FLY_API_TOKEN
```

---

## Étape 3 — Cloudflare Pages (web)

**Objectif** : `https://cordeau-web.pages.dev` rebuild automatiquement à chaque push sur `main`, et consomme l'API Fly.

1. https://dash.cloudflare.com → **Workers & Pages** → **Create** → **Pages** → **Connect to Git**
2. Autoriser Cloudflare sur GitHub → choisir `maxgloag/cordeau`
3. Configuration du build :
   - **Project name** : `cordeau-web`
   - **Production branch** : `main`
   - **Framework preset** : **None** (on configure manuellement, monorepo oblige)
   - **Build command** : `pnpm install --frozen-lockfile && pnpm --filter @cordeau/web build`
   - **Build output directory** : `apps/web/dist`
   - **Root directory** : laisser vide (build depuis la racine)
4. **Environment variables** (Production) :
   ```
   NODE_VERSION=24
   PNPM_VERSION=10.33.2
   VITE_API_URL=https://cordeau-api.fly.dev
   ```
5. **Save and Deploy**

### Vérification

Une fois le build terminé (~2 min) :
- Ouvrir `https://cordeau-web.pages.dev`
- La page doit afficher le statut santé en vert (consommé depuis l'API Fly)

### Preview deploys

Chaque PR sur `main` déclenche un preview deploy à `https://<hash>.cordeau-web.pages.dev` — utile pour reviewer un changement UI sans merger.

---

## Étape 4 — EAS preview (build mobile Android)

**Objectif** : un APK installable sur ton Android, qui consomme l'API Fly prod.

### Setup

```bash
cd apps/mobile
eas login         # avec ton compte Expo
eas init          # crée le projet sur expo.dev et update app.json avec le projectId
```

### Configuration `eas.json`

Créer `apps/mobile/eas.json` :

```json
{
  "cli": { "version": ">= 7.0.0" },
  "build": {
    "preview": {
      "distribution": "internal",
      "android": { "buildType": "apk" },
      "env": {
        "EXPO_PUBLIC_API_URL": "https://cordeau-api.fly.dev"
      }
    },
    "production": {
      "distribution": "store",
      "android": { "buildType": "app-bundle" },
      "env": {
        "EXPO_PUBLIC_API_URL": "https://cordeau-api.fly.dev"
      }
    }
  }
}
```

### Premier build

```bash
eas build --profile preview --platform android
```

Le build prend 10-20 min sur les serveurs EAS (cloud, gratuit, 30 builds/mois). À la fin, EAS donne :
- une URL de download de l'APK
- un QR code à scanner depuis l'Android pour installer

### Vérification

1. Installer l'APK sur ton Android (autoriser sources inconnues une seule fois)
2. Lancer l'app → écran d'accueil affiche le statut santé en vert
3. Désactiver le wifi → l'écran passe en "API injoignable" (preuve qu'il appelle bien l'URL prod)

**iOS** : différé jusqu'au 1er user payant (Apple Developer Program 99 $/an non engagé).

---

## Étape 5 — Sentry (3 projets)

**Objectif** : exceptions remontées dans Sentry depuis l'API, le web et le mobile.

### Création des 3 projets

https://sentry.io → **Projects** → **Create Project** ×3 :

| Projet | Plateforme |
|---|---|
| `cordeau-api` | PHP / Symfony |
| `cordeau-web` | React |
| `cordeau-mobile` | React Native |

Pour chacun, Sentry fournit un **DSN** (`https://<key>@o<org>.ingest.sentry.io/<project>`).

### Stockage des DSN

**API** (secret Fly) :
```bash
fly secrets set SENTRY_DSN="<dsn cordeau-api>" --app cordeau-api
```

**Web** (variable Cloudflare Pages, dashboard) :
```
VITE_SENTRY_DSN=<dsn cordeau-web>
```

**Mobile** (`apps/mobile/eas.json` → `env`) :
```json
"EXPO_PUBLIC_SENTRY_DSN": "<dsn cordeau-mobile>"
```

### Note Phase 0 vs Phase 1

Phase 0 valide juste la **chaîne** : DSN stocké, accessible côté app via env var. L'**intégration code** (lib `@sentry/*` installée + initialisée) est légitimement reportée en Phase 1 — il n'y a rien à instrumenter sur `/health` seul. À Phase 1, ajouter :
- API : `composer require sentry/sentry-symfony`
- Web : `pnpm --filter @cordeau/web add @sentry/react`
- Mobile : `pnpm --filter @cordeau/mobile add @sentry/react-native`

---

## Critère de sortie Phase 0 — checklist finale

- [ ] `https://cordeau-api.fly.dev/health` répond 200 avec `{"status":"ok",...}` depuis Internet
- [ ] `https://cordeau-web.pages.dev` affiche le statut santé en vert (consommation de l'API prod)
- [ ] APK EAS preview installé sur un Android, l'écran appelle l'API prod
- [ ] Push d'un commit anodin sur `main` (ex: doc) déclenche :
  - CI GitHub Actions verte
  - Re-deploy auto API via `fly deploy` (job CI à câbler)
  - Re-deploy auto web via Cloudflare Pages webhook
- [ ] 3 projets Sentry créés, DSN stockés dans les env vars correspondantes
- [ ] Branch protection sur `main` active (PR + CI verte requis) — déjà fait

Quand tout est coché → Phase 0 close, on ouvre les premières issues `Phase 1 — Verticale Chantiers`.

---

## Troubleshooting

### Fly deploy échoue sur `composer install`

Manque probablement une extension PHP. Lire les logs (`fly logs`), ajouter l'extension dans le `Dockerfile` (`install-php-extensions <ext>`), redéployer.

### `/health` répond 500 — connexion DB refusée

Vérifier le secret `DATABASE_URL` :

```bash
fly ssh console --app cordeau-api
# Dans le container :
echo $DATABASE_URL  # doit contenir la chaîne Neon, pas localhost
php bin/console doctrine:query:sql "SELECT 1"
```

Causes fréquentes :
- `serverVersion=18` manquant → Doctrine plante en bootstrap
- IP Fly non whitelist sur Neon → vérifier dans dashboard Neon que les "allowed IPs" sont en `0.0.0.0/0` (par défaut, c'est ouvert sur le free tier)

### Cloudflare Pages échoue au build (`pnpm not found`)

Vérifier que `PNPM_VERSION` est bien dans les env vars du projet Cloudflare Pages, et que `package.json` racine a `"packageManager": "pnpm@10.33.2"` (déjà OK).

### EAS build échoue sur "expo prebuild"

Notre `apps/mobile` est en mode managed (pas de `ios/` ni `android/` versionnés, déjà dans `.gitignore`). EAS regénère ces dossiers à la volée — c'est normal. Si erreur : vérifier `app.json` et `package.json` cohérents.

### Cold start Fly trop lent

Si la 1ère requête après inactivité dépasse 5 s, vérifier :
- OPcache activé (oui, dans le Dockerfile)
- `auto_stop_machines = "stop"` plutôt que `"suspend"` (suspend = ~3s de plus mais conserve la mémoire). On a choisi `stop` pour rester full-free.
- Si vraiment gênant, mettre `min_machines_running = 1` dans `fly.toml` (sort du free tier mais garde une machine toujours warm)
