# Setup des environnements (`.env`)

Guide de référence pour mettre en place l'environnement de développement local sur Cordeau.

## Vue d'ensemble des 4 environnements

| Environnement         | Secrets                               | Où vivent les variables          |
| --------------------- | ------------------------------------- | -------------------------------- |
| **dev** (local)       | Dashlane (note `cordeau-dev`)         | Injectées par `bin/dev` (`dcli`) |
| **test** (CI + local) | Aucun (`.env.test.local` sans secret) | Fichiers locaux                  |
| **staging**           | Injectées par la plateforme           | Fly secrets / CF Pages env vars  |
| **prod**              | Injectées par la plateforme           | Fly secrets / CF Pages env vars  |

Chaque app (api, web, mobile) gère ses propres `.env`. Pas de `.env.staging` ni `.env.prod` dans le repo.

## Fichiers par app

```
apps/api/
├── .env             # valeurs par défaut, committées (sans secrets)
├── .env.example     # template commenté pour setup initial
├── .env.local       # config propre au poste, sans aucun secret — gitignored
├── .env.test.local  # idem pour les tests (DATABASE_URL) — gitignored
└── bin/dev          # lance le serveur avec les secrets Dashlane

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

**Outils requis :**

- [OrbStack](https://orbstack.dev) (ou Docker Desktop) — pour Postgres + Redis
- [Symfony CLI](https://symfony.com/download) — gère automatiquement la version PHP via `apps/api/.php-version`
- [pnpm](https://pnpm.io) — gestionnaire de packages JS

**Architecture dev locale :** Docker fournit uniquement l'infra (Postgres, Redis). Le serveur PHP tourne via Symfony CLI, qui lit `apps/api/.php-version` (`8.5`) et sélectionne automatiquement la bonne version — même si PHP 8.2 est la version globale de la machine. Toujours utiliser `symfony console` (pas `php bin/console`) depuis `apps/api/`.

```bash
docker compose up -d   # Lance Postgres + Redis
pnpm install           # Dépendances (à la racine du monorepo)
```

### 1. apps/api

**Règle : jamais de secret dans un fichier `.env*`** (`.env`, `.env.local`, `.env.example`…). `APP_SECRET`, `GOOGLE_CLIENT_SECRET` et `SENTRY_DSN` vivent dans une note sécurisée Dashlane et sont injectés au lancement par `dcli exec`.

#### Secrets : Dashlane

1. Installer la CLI : `brew install dashlane/tap/dashlane-cli`, puis `dcli sync` (connexion au compte).
2. Exiger Touch ID à chaque lecture : `dcli configure user-presence --method biometrics`.
3. Créer la note sécurisée `cordeau-dev`, champ `content`, au format JSON :

   ```json
   { "APP_SECRET": "…", "GOOGLE_CLIENT_SECRET": "…", "SENTRY_DSN": "…" }
   ```

   - `APP_SECRET` : `openssl rand -hex 32`
   - `GOOGLE_CLIENT_SECRET` : secret du client OAuth « Cordeau Web » ([Google Cloud Console](https://console.cloud.google.com/apis/credentials))
   - `SENTRY_DSN` : DSN du projet `cordeau-api` ([Sentry](https://cordeau.sentry.io) → Settings → Client Keys)

`bin/dev` exporte les références `dl://cordeau-dev/content?json=<CLÉ>` puis lance `dcli exec -- symfony serve --no-tls` (les arguments sont transmis à `symfony serve`). `dcli` remplace les variables exportées qui commencent par `dl://` ; il ne lit pas les fichiers `.env`. Symfony Dotenv ne remplace pas une variable déjà présente dans l'environnement : les valeurs injectées priment.

#### `apps/api/.env.local` : config sans secret

`.env.local` reste nécessaire, mais ne contient que des valeurs non secrètes. Le `.env` commité garde le défaut Symfony `DATABASE_URL=…app:!ChangeMe!…`, qui échoue : il faut le surcharger.

```bash
cd apps/api
cp .env.example .env.local   # puis ne garder que les trois variables ci-dessous
```

| Variable                 | Valeur                                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------------------------- |
| `DATABASE_URL`           | `"postgresql://cordeau:cordeau@127.0.0.1:5432/cordeau?serverVersion=18&charset=utf8"`                    |
| `GOOGLE_CLIENT_ID`       | ID du client « Cordeau Web » ([Google Cloud Console](https://console.cloud.google.com/apis/credentials)) |
| `GOOGLE_OAUTH_AUDIENCES` | ID du client « Cordeau iOS » ([Google Cloud Console](https://console.cloud.google.com/apis/credentials)) |

Les ID client Google sont publics. Les trois lignes `dl://` de `.env.example` documentent les références Dashlane : ne pas les copier dans `.env.local` ni y coller de valeur.

#### Migrer la base puis lancer

```bash
docker compose up -d                                       # Postgres + Redis (racine du monorepo)
cd apps/api
symfony console doctrine:migrations:migrate --no-interaction   # base de dev
bin/dev                                                    # Touch ID attendu
```

Vérification : `curl http://localhost:8000/health` renvoie `{"status":"ok"}` (étape 5).

#### Tests (`bin/phpunit`)

Les tests n'utilisent aucune valeur Dashlane. En env `test`, Symfony ignore `.env.local` : sans `apps/api/.env.test.local`, ils échouent (`password authentication failed for user "app"`). Créer ce fichier (gitignoré, sans secret) avec la même `DATABASE_URL` que `.env.local` ; la base `cordeau_test` en est dérivée automatiquement.

```bash
cd apps/api
# .env.test.local : DATABASE_URL="postgresql://cordeau:cordeau@127.0.0.1:5432/cordeau?serverVersion=18&charset=utf8"
symfony console doctrine:database:create --env=test --if-not-exists
symfony console doctrine:migrations:migrate --env=test --no-interaction
```

### 2. apps/web

```bash
cd apps/web
cp .env.example .env.local
```

Éditer `.env.local` et remplir :

| Variable          | Valeur                      | Source                                                       |
| ----------------- | --------------------------- | ------------------------------------------------------------ |
| `VITE_API_URL`    | `http://localhost:8000`     | Dev local                                                    |
| `VITE_SENTRY_DSN` | DSN du projet `cordeau-web` | [Sentry](https://cordeau.sentry.io) → Settings → Client Keys |

### 3. apps/mobile

```bash
cd apps/mobile
cp .env.example .env.local
```

Éditer `.env.local` et remplir :

| Variable                           | Valeur                                                              | Source                                                                    |
| ---------------------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `EXPO_PUBLIC_API_URL`              | `http://localhost:8000` (simulateur) ou `http://<IP>:8000` (device) | `ipconfig getifaddr en0`                                                  |
| `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` | iOS client ID                                                       | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) |
| `EXPO_PUBLIC_SENTRY_DSN`           | DSN du projet `cordeau-mobile`                                      | [Sentry](https://cordeau.sentry.io) → Settings → Client Keys              |

### 4. Lancer le dev

```bash
cd cordeau          # racine du monorepo
docker compose up -d
apps/api/bin/dev    # API (8000) avec les secrets Dashlane, dans un terminal dédié
pnpm dev --filter='!@cordeau/api'   # web (5173) + mobile (Expo), sans l'API (déjà lancée par bin/dev)
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

- Lancer l'API via `apps/api/bin/dev` (et non `symfony serve` seul), sinon la variable reste la chaîne `dl://…`
- Vérifier que la note Dashlane `cordeau-dev` contient la clé `GOOGLE_CLIENT_SECRET` du client « Cordeau Web »
- Le client « Cordeau Web » sert aussi la prod (`cordeau-api.fly.dev`) : ne pas en faire tourner la rotation sans prévenir

**"Database does not exist" en test**

```bash
cd apps/api
symfony console doctrine:database:create --env=test
symfony console doctrine:migrations:migrate --env=test --no-interaction
```

**Mobile ne se connecte pas à l'API sur device physique**

- Utiliser l'IP locale (`ipconfig getifaddr en0`), pas `localhost`
- `EXPO_PUBLIC_API_URL=http://192.168.1.X:8000` dans `apps/mobile/.env.local`
