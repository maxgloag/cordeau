# Runbook — Déploiement Phase 0 (Lot 4)

> Mise en prod gratuite des 3 environnements Cordeau. Critère de sortie Phase 0 : un commit pushé déclenche la CI verte et `/health` répond depuis l'API, le web, et un build EAS preview installable.

## Vue d'ensemble

| Composant | Hébergement | URL cible | Coût |
|---|---|---|---|
| API (Symfony) | Oracle Cloud VM ARM (Always Free) + Coolify | `https://cordeau.duckdns.org` | 0€ |
| Web (Vite/React) | Cloudflare Pages | `https://cordeau-web.pages.dev` | 0€ |
| Mobile (Expo) | EAS preview build | APK installable (sideload) | 0€ |
| Erreurs | Sentry × 3 projets | `*.sentry.io` | 0€ |
| DNS API | DuckDNS (sous-domaine gratuit) | — | 0€ |

**Le jour où on achète `cordeau-pro.fr`** : on change uniquement les DNS et les variables `*_API_URL`. Ni le code ni l'infra ne bougent.

## Prérequis (comptes à créer avant de commencer)

À cocher avant chaque étape — chaque compte demande 2-10 min :

- [ ] **Oracle Cloud** — https://signup.cloud.oracle.com (carte bancaire pour vérification, jamais débitée sur le Free Tier)
- [ ] **DuckDNS** — https://www.duckdns.org (login GitHub, instantané)
- [ ] **Cloudflare** — https://dash.cloudflare.com/sign-up (email + mot de passe)
- [ ] **Expo** — https://expo.dev/signup (free tier inclus)
- [ ] **Sentry** — https://sentry.io/signup (Developer plan gratuit, 5k events/mois × 3 projets)

**Génération de la paire de clés SSH** (à faire une fois, si pas déjà fait) :

```bash
# Si tu n'as pas encore de clé SSH dédiée à ce projet
ssh-keygen -t ed25519 -f ~/.ssh/cordeau_oracle -C "cordeau-oracle"
# Note : NE PAS mettre de passphrase si tu veux automatiser les déploiements
cat ~/.ssh/cordeau_oracle.pub  # copier ce contenu pour Oracle Cloud
```

---

## Étape 1 — Oracle Cloud Free Tier (VM ARM Ampere A1)

**Objectif** : une VM Ubuntu 22.04 ARM (4 OCPU, 24 Go RAM) sur l'Always Free Tier d'Oracle.

### Création du compte

1. https://signup.cloud.oracle.com
2. **Choix de la région** : `eu-paris-1` ou `eu-frankfurt-1` (latence FR optimale)
   - ⚠️ La région ne pourra plus être changée → bien réfléchir
3. Vérification carte bancaire (1€ d'autorisation, jamais débité tant qu'on reste sur le Free Tier)
4. Activation du compte (peut prendre 15 min à 24h selon les checks anti-fraude)

### Création de la VM

1. Console Oracle → **Compute** → **Instances** → **Create Instance**
2. Configuration :
   - **Name** : `cordeau-prod`
   - **Image** : Canonical Ubuntu 22.04 (ou 24.04 si dispo)
   - **Shape** : **Ampere** → `VM.Standard.A1.Flex` → **4 OCPU + 24 GB RAM** (le max gratuit)
   - **Networking** : laisser le défaut (VCN auto-créé), assigner une IP publique
   - **SSH key** : coller la clé publique `~/.ssh/cordeau_oracle.pub`
3. Cliquer Create. La VM est prête en ~2 min.
4. Noter l'**IP publique** affichée (ex: `132.145.X.X`)

### Premier accès SSH

```bash
ssh -i ~/.ssh/cordeau_oracle ubuntu@<IP_PUBLIQUE>
# Accepter le fingerprint
```

### Ouverture des ports (sécurité réseau Oracle)

Par défaut, Oracle bloque tout sauf le 22 (SSH). Il faut ouvrir 80 et 443 pour Coolify et Let's Encrypt :

1. Console Oracle → **Networking** → **Virtual Cloud Networks** → ton VCN
2. **Security Lists** → `Default Security List for vcn-...`
3. **Add Ingress Rules** :
   - Source : `0.0.0.0/0` · Protocol : TCP · Destination Port : `80`
   - Source : `0.0.0.0/0` · Protocol : TCP · Destination Port : `443`
   - Source : `0.0.0.0/0` · Protocol : TCP · Destination Port : `8000` (Coolify dashboard, à fermer après setup)

Sur la VM, ouvrir aussi les ports côté firewall Ubuntu :

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

**Anti-reclaim Oracle** : Oracle reclame les VMs Free Tier inactives 7 jours. Notre `/health` consommé toutes les 10s par le web nous protège. En backup, ajouter un cron toutes les heures :

```bash
echo '0 * * * * curl -s https://cordeau.duckdns.org/health > /dev/null' | crontab -
```

---

## Étape 2 — DuckDNS (sous-domaine API gratuit)

**Objectif** : `cordeau.duckdns.org` pointe vers l'IP de la VM Oracle.

1. https://www.duckdns.org → login GitHub
2. Dans `domains` : taper `cordeau` (ou `cordeau-api` si pris) → **add domain**
3. Renseigner l'IP publique Oracle dans le champ `current ip` → **update ip**
4. Noter le **token** affiché en haut de page (sera utile pour le renouvellement auto)

### Vérification

```bash
dig +short cordeau.duckdns.org   # doit retourner l'IP Oracle
```

### Mise à jour automatique de l'IP (optionnel mais recommandé)

L'IP Oracle peut théoriquement changer (rarement). Sur la VM :

```bash
mkdir ~/duckdns && cd ~/duckdns
echo 'echo url="https://www.duckdns.org/update?domains=cordeau&token=TON_TOKEN&ip=" | curl -k -o ~/duckdns/duck.log -K -' > duck.sh
chmod 700 duck.sh
(crontab -l ; echo "*/5 * * * * ~/duckdns/duck.sh >/dev/null 2>&1") | crontab -
```

---

## Étape 3 — Coolify (déploiement API)

**Objectif** : Coolify installé sur la VM, déploie automatiquement `apps/api` à chaque push sur `main`.

### Installation

```bash
# Sur la VM
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | sudo bash
```

L'installeur fait :
- install Docker + Docker Compose
- pull les images Coolify
- démarre le dashboard sur `http://<IP>:8000`

### Premier login

1. Ouvrir `http://<IP_PUBLIQUE>:8000` dans le navigateur
2. Créer le compte admin (utiliser ton email)
3. **Settings** → **Configuration** → renseigner ton email et la **FQDN** : `cordeau.duckdns.org`
4. Coolify provisionne automatiquement Let's Encrypt sur cette FQDN

### Connexion à GitHub

1. **Sources** → **GitHub App** → suivre l'assistant
2. Installer la GitHub App sur le repo `maxgloag/cordeau` (scope : ce repo uniquement)
3. Coolify peut maintenant lire le repo et écouter les webhooks

### Création du projet API

1. **Projects** → **New Project** → `cordeau`
2. **Resources** → **New Resource** → **Application** → **Public Repository** ou **GitHub App**
3. Configuration :
   - **Repository** : `maxgloag/cordeau`
   - **Branch** : `main`
   - **Build Pack** : `Dockerfile` (on en crée un à l'étape suivante)
   - **Base Directory** : `apps/api`
   - **Dockerfile Location** : `apps/api/Dockerfile`
   - **Port** : `80` (FrankenPHP par défaut)
   - **FQDN** : `https://cordeau.duckdns.org`
4. **Environment Variables** :
   ```
   APP_ENV=prod
   APP_SECRET=<générer avec : openssl rand -hex 32>
   DATABASE_URL=postgresql://cordeau:<MDP>@<HOST_PG>:5432/cordeau?serverVersion=18&charset=utf8
   ```
5. Sauvegarder, **PAS encore déployer** — il faut d'abord créer le Dockerfile et la DB.

### Création de la DB PostgreSQL managée par Coolify

1. **Resources** → **New Resource** → **Database** → **PostgreSQL** (`postgres:18-alpine`)
2. Nom : `cordeau-pg`
3. Récupérer les credentials générés et les coller dans `DATABASE_URL` de l'API
4. Démarrer la DB

### Création du Dockerfile API

Sur ta machine locale, créer `apps/api/Dockerfile` (à versionner) :

```dockerfile
FROM dunglas/frankenphp:1-php8.5-alpine

# Extensions PHP requises
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    opcache \
    apcu

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install deps (cache layer)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Copy app
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

# Permissions
RUN chown -R www-data:www-data /app/var

ENV SERVER_NAME=:80
EXPOSE 80
```

Commit + push : Coolify détecte le webhook et déploie.

### Vérification

```bash
curl https://cordeau.duckdns.org/health
# Attendu : {"status":"ok",...}
```

---

## Étape 4 — Cloudflare Pages (déploiement web)

**Objectif** : `https://cordeau-web.pages.dev` rebuild automatiquement à chaque push sur `main`.

1. https://dash.cloudflare.com → **Workers & Pages** → **Create** → **Pages** → **Connect to Git**
2. Autoriser Cloudflare sur GitHub → choisir `maxgloag/cordeau`
3. Configuration du build :
   - **Project name** : `cordeau-web`
   - **Production branch** : `main`
   - **Framework preset** : **None** (on configure manuellement, monorepo oblige)
   - **Build command** : `pnpm install --frozen-lockfile && pnpm --filter @cordeau/web build`
   - **Build output directory** : `apps/web/dist`
   - **Root directory (advanced)** : laisser vide (build depuis la racine pour pnpm workspaces)
4. **Environment variables** (Production) :
   ```
   NODE_VERSION=24
   PNPM_VERSION=10.33.2
   VITE_API_URL=https://cordeau.duckdns.org
   ```
5. **Save and Deploy**

### Vérification

Une fois le build terminé (~2 min) :
- Ouvrir `https://cordeau-web.pages.dev`
- La page doit afficher le statut santé en vert (consommé depuis l'API prod)

### Preview deploys

Chaque PR ouverte sur `main` déclenche un preview deploy avec une URL `https://<hash>.cordeau-web.pages.dev` — utile pour reviewer un changement UI sans merger.

---

## Étape 5 — EAS preview (build mobile Android)

**Objectif** : un APK installable sur ton Android, qui consomme l'API prod.

### Setup

```bash
cd apps/mobile
pnpm add -g eas-cli  # ou npx eas-cli si tu préfères
eas login            # avec ton compte Expo
eas init             # crée le projet sur expo.dev et update app.json avec le projectId
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
        "EXPO_PUBLIC_API_URL": "https://cordeau.duckdns.org"
      }
    },
    "production": {
      "distribution": "store",
      "android": { "buildType": "app-bundle" },
      "env": {
        "EXPO_PUBLIC_API_URL": "https://cordeau.duckdns.org"
      }
    }
  }
}
```

### Premier build

```bash
eas build --profile preview --platform android
```

Le build prend 10-20 min sur les serveurs EAS (cloud, gratuit). À la fin, EAS donne :
- une URL de download de l'APK
- un QR code à scanner depuis l'Android pour installer

### Vérification

1. Installer l'APK sur ton Android (autoriser sources inconnues)
2. Lancer l'app → écran d'accueil affiche le statut santé en vert
3. Désactiver le wifi → l'écran passe en "API injoignable" (preuve qu'il appelle bien l'URL prod)

**iOS** : différé jusqu'au 1er user payant (Apple Developer Program 99$/an non engagé).

---

## Étape 6 — Sentry (3 projets)

**Objectif** : exceptions remontées dans Sentry depuis l'API, le web et le mobile.

### Création des 3 projets

https://sentry.io → **Projects** → **Create Project** ×3 :

| Projet | Plateforme |
|---|---|
| `cordeau-api` | PHP / Symfony |
| `cordeau-web` | React |
| `cordeau-mobile` | React Native |

Pour chacun, Sentry fournit un **DSN** (`https://<key>@o<org>.ingest.sentry.io/<project>`).

### Injection des DSN

**API** (Coolify env vars) :
```
SENTRY_DSN=<dsn cordeau-api>
```
Puis dans `composer.json` : `composer require sentry/sentry-symfony` (à faire en Phase 1, pas obligatoire pour Phase 0).

**Web** (Cloudflare Pages env vars) :
```
VITE_SENTRY_DSN=<dsn cordeau-web>
```
Lib à ajouter en Phase 1 : `pnpm --filter @cordeau/web add @sentry/react`.

**Mobile** (`eas.json` → `env`) :
```json
"EXPO_PUBLIC_SENTRY_DSN": "<dsn cordeau-mobile>"
```
Lib à ajouter en Phase 1 : `pnpm --filter @cordeau/mobile add @sentry/react-native`.

**Note** : les projets et DSN sont créés en Phase 0, mais l'**intégration code** est légitimement reportée en Phase 1 (rien à instrumenter sur `/health` seul). Phase 0 valide juste que les DSN sont stockés et que la chaîne env vars → app fonctionne.

---

## Critère de sortie Phase 0 — checklist finale

- [ ] `https://cordeau.duckdns.org/health` répond 200 avec `{"status":"ok",...}` depuis Internet
- [ ] `https://cordeau-web.pages.dev` affiche le statut santé en vert (consommation de l'API prod)
- [ ] APK EAS preview installé sur un Android, l'écran appelle l'API prod
- [ ] Push d'un commit anodin sur `main` (ex: doc) déclenche :
  - CI GitHub Actions verte
  - Re-deploy auto API via Coolify webhook
  - Re-deploy auto web via Cloudflare Pages webhook
- [ ] 3 projets Sentry créés, DSN stockés dans les env vars correspondantes
- [ ] Branch protection sur `main` active (PR + CI verte requis)

Quand tout est coché → Phase 0 close, on ouvre les premières issues `Phase 1 — Verticale Chantiers`.

---

## Troubleshooting

### Coolify ne déclenche pas le déploiement

```bash
# Sur la VM, voir les logs Coolify
docker logs coolify -f --tail 100
```
Vérifier que la GitHub App est bien installée et que le webhook arrive (Settings GitHub → Webhooks → Recent deliveries).

### Let's Encrypt échoue

Souvent : ports 80/443 pas ouverts côté Oracle (Security List), ou DNS pas encore propagé.

```bash
dig +short cordeau.duckdns.org
# Si ça retourne pas l'IP Oracle, attendre 5 min et réessayer
```

### Cloudflare Pages échoue au build (pnpm not found)

Vérifier que `PNPM_VERSION` est bien dans les env vars du projet Cloudflare Pages, et que `package.json` a `"packageManager": "pnpm@10.33.2"` (déjà OK).

### EAS build échoue sur "expo prebuild"

Notre `apps/mobile` est en mode managed (pas de `ios/` ni `android/` versionnés, déjà dans `.gitignore`). EAS regénère ces dossiers à la volée — c'est normal. Si erreur : vérifier `app.json` et `package.json` cohérents.
