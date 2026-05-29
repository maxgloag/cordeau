# Setup des environnements (`.env`)

Guide de référence pour mettre en place l'environnement de développement local sur Cordeau.

## Vue d'ensemble des 4 environnements

| Environnement | Secrets | Où vivent les variables |
|---|---|---|
| **dev** (local) | `.env.local` (gitignored) | Fichiers locaux |
| **test** (CI + local) | `.env.test.local` (gitignored) | Fichiers locaux |
| **staging** | Injectées par la plateforme | Fly secrets / CF Pages env vars |
| **prod** | Injectées par la plateforme | Fly secrets / CF Pages env vars |

Chaque app (api, web, mobile) gère ses propres `.env`. Pas de `.env.staging` ni `.env.prod` dans le repo.

## Fichiers par app

```
apps/api/
├── .env             # valeurs par défaut, committées (sans secrets)
├── .env.example     # template commenté pour setup initial
├── .env.local       # ⚠️ secrets dev — gitignored, ne jamais committer
└── .env.test.local  # ⚠️ secrets test — gitignored

apps/web/
├── .env
├── .env.example
├── .env.local       # ⚠️ gitignored
└── .env.test.local  # ⚠️ gitignored

apps/mobile/
├── .env
├── .env.example
├── .env.local       # ⚠️ gitignored
└── .env.test.local  # ⚠️ gitignored
```

## Setup initial — nouveau développeur

### Prérequis

```bash
docker compose up -d   # Lance Postgres + Redis
pnpm install           # Dépendances (à la racine du monorepo)
```

### 1. apps/api

```bash
cd apps/api
cp .env.example .env.local
```

Éditer `.env.local` et remplir :

| Variable | Valeur | Source |
|---|---|---|
| `APP_SECRET` | `$(openssl rand -hex 32)` | Générer en local |
| `DATABASE_URL` | `postgresql://cordeau:cordeau@127.0.0.1:5432/cordeau` | docker-compose |
| `GOOGLE_CLIENT_SECRET` | Secret du client `cordeau-dev` | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) |
| `GOOGLE_OAUTH_AUDIENCES` | iOS client ID | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) |
| `SENTRY_DSN` | DSN du projet `cordeau-api` | [Sentry](https://cordeau.sentry.io) → Settings → Client Keys |

### 2. apps/web

```bash
cd apps/web
cp .env.example .env.local
```

Éditer `.env.local` et remplir :

| Variable | Valeur | Source |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000` | Dev local |
| `VITE_SENTRY_DSN` | DSN du projet `cordeau-web` | [Sentry](https://cordeau.sentry.io) → Settings → Client Keys |

### 3. apps/mobile

```bash
cd apps/mobile
cp .env.example .env.local
```

Éditer `.env.local` et remplir :

| Variable | Valeur | Source |
|---|---|---|
| `EXPO_PUBLIC_API_URL` | `http://localhost:8000` (simulateur) ou `http://<IP>:8000` (device) | `ipconfig getifaddr en0` |
| `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` | iOS client ID | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) |
| `EXPO_PUBLIC_SENTRY_DSN` | DSN du projet `cordeau-mobile` | [Sentry](https://cordeau.sentry.io) → Settings → Client Keys |

### 4. Lancer le dev

```bash
cd cordeau          # racine du monorepo
docker compose up -d
pnpm dev            # turbo run dev — lance api (8000) + web (5173) + mobile (Expo)
```

### 5. Vérifier

```bash
curl http://localhost:8000/health
# Expected: {"status":"ok"}
```

## Secrets staging & prod

Les secrets ne transitent jamais par des fichiers — ils sont injectés directement :

**API (Fly.io) :**
```bash
fly secrets set APP_SECRET=<value> --app cordeau-api
fly secrets set DATABASE_URL=<value> --app cordeau-api
fly secrets set GOOGLE_CLIENT_SECRET=<value> --app cordeau-api
fly secrets set SENTRY_DSN=<value> --app cordeau-api
```

**Web (Cloudflare Pages) :** Variables d'environnement dans le dashboard CF Pages (Settings → Environment Variables).

**Mobile (EAS) :** Variables dans `eas.json` (non-secrets) ou EAS Secrets pour les valeurs sensibles.

Procédure de rotation complète : `docs/runbooks/rotation-secrets.md`

## Troubleshooting

**"Connection refused" sur PostgreSQL**
```bash
docker ps | grep postgres
# Si absent :
docker compose up -d
```

**"GOOGLE_CLIENT_SECRET invalid"**
- Vérifier que le secret du client dev (`cordeau-dev`) est dans `apps/api/.env.local`
- Ne pas mélanger les credentials dev et prod

**"Database does not exist" en test**
```bash
cd apps/api
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

**Mobile ne se connecte pas à l'API sur device physique**
- Utiliser l'IP locale (`ipconfig getifaddr en0`), pas `localhost`
- `EXPO_PUBLIC_API_URL=http://192.168.1.X:8000` dans `apps/mobile/.env.local`
