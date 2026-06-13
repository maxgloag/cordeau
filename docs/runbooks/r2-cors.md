# Runbook — Configuration CORS du bucket R2 (uploads photo)

> Procédure pour autoriser les **uploads photo depuis le navigateur** (back-office web) vers Cloudflare R2. À exécuter :
>
> - **Au premier déploiement web** (et en dev local) — sinon les uploads web échouent silencieusement
> - **À l'ajout d'une nouvelle origine web** (nouveau domaine de prod, environnement de preview)
>
> Ne concerne **pas** le mobile : l'app Expo uploade via `expo-file-system` (requête native, non soumise au CORS navigateur).

## Pourquoi c'est nécessaire

Le flux d'upload (cf Phase 5, [PR #77](https://github.com/maxgloag/cordeau/pull/77)) est en **upload direct** : l'API génère une URL R2 pré-signée, puis le client envoie un `PUT` directement sur R2 (le binaire ne transite pas par l'API).

Côté **web**, ce `PUT` est une requête cross-origin (`http://localhost:5173` ou le domaine de prod → `…r2.cloudflarestorage.com`). Le navigateur exige donc que le bucket réponde aux en-têtes CORS. Sans politique CORS sur le bucket, le navigateur bloque la requête :

```
Access to fetch at 'https://cordeau-photos.<account>.r2.cloudflarestorage.com/…'
from origin 'http://localhost:5173' has been blocked by CORS policy:
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## Politique à appliquer

```json
[
  {
    "AllowedOrigins": ["http://localhost:5173", "https://<domaine-web-prod>"],
    "AllowedMethods": ["PUT", "GET"],
    "AllowedHeaders": ["content-type"],
    "MaxAgeSeconds": 3600
  }
]
```

- `AllowedOrigins` : l'origine du **front web** (pas l'API). `http://localhost:5173` pour le dev Vite ; ajouter le domaine de prod (Cloudflare Pages) au déploiement. Ne pas utiliser `*` (le `PUT` permettrait à n'importe quel site d'uploader sur le bucket via une URL pré-signée fuitée).
- `AllowedMethods` : `PUT` (upload) + `GET` (lecture si on sert l'objet via l'endpoint S3 ; l'affichage passe normalement par `R2_PUBLIC_URL` = `pub-….r2.dev`, public, hors CORS).
- `AllowedHeaders` : `content-type` — seul en-tête custom envoyé par le `PUT` (le `Content-Type` doit correspondre exactement à celui signé dans l'URL pré-signée, cf [PR #77](https://github.com/maxgloag/cordeau/pull/77)).

## Comment l'appliquer

### Option A — Dashboard Cloudflare (recommandé)

Cloudflare → **R2** → bucket **`cordeau-photos`** → **Settings** → **CORS Policy** → **Edit** → coller le JSON ci-dessus. Propagation ~30 s.

### Option B — API S3 (`PutBucketCors`)

> ⚠️ Le token R2 **de l'application** (`R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` en `.env.local` / Fly secrets) n'a que la permission **Object Read & Write** : `PutBucketCors` renvoie `AccessDenied`. Il faut un token R2 **Admin Read & Write** (Cloudflare → R2 → Manage API Tokens), à usage ponctuel, **non committé** et non stocké.

Avec un token admin temporaire, via le SDK S3 (mêmes paramètres que `apps/api/config/services.yaml` : `region: auto`, `endpoint: R2_ENDPOINT`) :

```php
$s3->putBucketCors([
    'Bucket' => 'cordeau-photos',
    'CORSConfiguration' => ['CORSRules' => [[
        'AllowedOrigins' => ['http://localhost:5173', 'https://<domaine-web-prod>'],
        'AllowedMethods' => ['PUT', 'GET'],
        'AllowedHeaders' => ['content-type'],
        'MaxAgeSeconds' => 3600,
    ]]],
]);
```

## Vérification

Depuis le back-office web (`pnpm dev`), ouvrir un chantier, ajouter une photo. Dans l'onglet **Network**, le `PUT` vers `…r2.cloudflarestorage.com` doit répondre **200** (et non `net::ERR_FAILED` / erreur CORS). La vignette apparaît après le `confirm`.
