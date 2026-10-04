# ADR 0023 — Traitement d'images serveur via Imagick (HEIC) pour les vignettes

- **Status** : Accepted (amendé le 2026-10-04)
- **Date** : 2026-06-14
- **Deciders** : Maxime
- **Contrôle** : `apps/api/docker/imagemagick-policy.xml`, `apps/api/tests/Integration/Messenger/GenerateThumbnailHandlerTest.php`
- **Lié à** : [#81](https://github.com/maxgloag/cordeau/issues/81), [ADR 0010](0010-crud-leger-pattern-reference.md) (Photo = CRUD léger), [ADR 0019](0019-durcissement-ci-cd.md) (la CI doit attraper la régression)

## Context

Les vignettes serveur (`GenerateThumbnailHandler`, 400×400 JPEG) sont générées avec **GD** (`imagecreatefromstring` → `imagecopyresampled` → `imagejpeg`). Deux problèmes :

1. **GD ne décode pas le HEIC/HEIF**, format par défaut des iPhone récents. Pour ces photos, `imagecreatefromstring` échoue → early return → `thumbnailUrl` reste `null`. Le web et le mobile retombent alors sur l'**original plein format** (plusieurs Mo) au lieu d'une vignette (~30 Ko) : fonctionnel mais coûteux en bande passante, perf (4G chantier) et egress R2.

2. **Le `Dockerfile` de prod n'installe aucune extension image** (`pdo_pgsql, redis, intl, opcache, apcu`). GD n'est présent qu'en dev (PHP système). La génération de vignettes est donc probablement **inopérante en prod pour toutes les photos**, pas seulement les HEIC — le fallback original masquait le trou.

Une vérification empirique a été faite sur l'image de base prod (`dunglas/frankenphp:1-php8.5-alpine`) :

```
install-php-extensions imagick
php -r 'print_r(Imagick::queryFormats("HEIC"));'  // => HEIC, HEIF
```

→ `imagick` y embarque ImageMagick **avec le delegate libheif**, sans build manuel de libheif. La contrainte « disponibilité de libheif sur l'image Fly » (#81) est levée.

## Decision

On bascule la génération de vignettes de **GD vers Imagick** (ImageMagick + libheif), qui décode HEIC/HEIF en plus de JPEG/PNG/WebP et unifie le pipeline.

1. **`apps/api/Dockerfile`** : ajouter `imagick` à `install-php-extensions` (libheif inclus). GD n'est plus requis.
2. **`composer.json`** : déclarer `ext-imagick` en `require` (contrat explicite).
3. **CI** (`shivammathur/setup-php`) : ajouter `imagick` aux extensions du job `api`, pour que le layer de test compile et exécute le handler (cf [ADR 0019](0019-durcissement-ci-cd.md)).
4. **`GenerateThumbnailHandler`** : réécriture en Imagick — auto-orientation EXIF, crop carré centré, resize 400, sortie JPEG qualité 85, suppression des métadonnées (`stripImage`).
5. **Durcissement ImageMagick** (cf sécurité) : `policy.xml` restrictif + limites de ressources.

Alternatives écartées :

- **GD + conversion HEIC séparée** : GD reste incapable du HEIC ; ajouter un convertisseur externe = deux dépendances au lieu d'une.
- **Conversion côté client (mobile, `expo-image-manipulator`)** : ne couvre pas les uploads HEIC depuis le **web** (back-office), et ré-encode sur l'appareil. Imagick côté serveur couvre tous les chemins d'upload de façon centralisée.
- **Service externe** (Cloudflare Images, etc.) : surcoût et dépendance réseau pour un besoin que la lib locale couvre ; contraire à la discipline V1 (simple d'abord).

## Consequences

**Positives**

- Vignettes réelles pour **toutes** les photos (HEIC inclus) → bande passante / perf / egress R2 réduits.
- Corrige au passage le trou « aucune extension image en prod » : le traitement d'images fonctionne enfin de bout en bout en production.
- Un seul pipeline image (Imagick) pour tous les formats.

**Négatives / vigilance**

- ImageMagick élargit la surface d'attaque (cf sécurité) → durcissement obligatoire via `policy.xml`.
- Image Docker légèrement plus lourde (ImageMagick + delegates).
- Le delegate HEIC n'est pas garanti sur le runner CI (`setup-php` sur Ubuntu) : le test du pipeline utilise un fixture **JPEG** (toujours exécuté) ; un test **HEIC** dédié est gardé par `Imagick::queryFormats('HEIC')` et `markTestSkipped` si le delegate manque. Le décodage HEIC reste validé sur l'image prod (alpine) et en local quand le delegate est présent.

**Implications sécurité.** ImageMagick traite des **fichiers fournis par l'utilisateur** (photos) et a un historique de CVE (ex. _ImageTragick_ : RCE via coders `MSL`/`MVG`/`URL`/SVG, SSRF, lecture de fichiers, decompression bombs). Mitigations :

- **`policy.xml` restrictif** committé et copié dans l'image : désactiver les coders inutiles et dangereux (`MSL`, `MVG`, `MAGICK`, `URL`, `HTTPS`, `HTTP`, `FTP`, `EPHEMERAL`, `LABEL`, `TEXT`, `SVG`, `PS`/`PDF`/`XPS`), n'autoriser que les formats raster attendus (JPEG, PNG, WebP, HEIC/HEIF).
- **Limites de ressources** (mémoire, disque, surface en pixels, temps) dans `policy.xml` pour contrer les decompression bombs.
- `stripImage()` sur la vignette → suppression des métadonnées EXIF (géolocalisation incluse) : la vignette servie publiquement via `R2_PUBLIC_URL` ne fuite pas la position GPS du chantier. **Donnée personnelle** : les photos peuvent contenir des données identifiantes / de localisation (base légale déjà couverte par la finalité « suivi de chantier ») ; le strip réduit la fuite côté vignette publique.
- Traitement **asynchrone** dans un worker Messenger isolé (pas dans le cycle requête), sur des objets déjà validés à l'upload (mime/taille). Aucun nouveau secret manipulé.

**Lien CI.** Le job `api` exécutera le handler via un test d'intégration (fixture JPEG, plus HEIC si delegate présent) — c'est le layer qui aurait dû attraper l'absence d'extension image en prod et l'échec HEIC (cf [ADR 0019](0019-durcissement-ci-cd.md)).

## Amendements

- 2026-10-04 — Jusqu'au 2026-10-04, aucun process n'exécutait `messenger:consume` en production : les vignettes n'étaient pas générées et le « traitement asynchrone dans un worker Messenger » de cet ADR n'était pas en place. Corrigé par le worker de l'[ADR 0029](0029-worker-messenger-prod.md).
- 2026-10-04 — Le test d'intégration annoncé dans « Lien CI » n'existait pas ; `GenerateThumbnailHandlerTest` (créé le 2026-10-04) couvre la génération avec une fixture JPEG.
