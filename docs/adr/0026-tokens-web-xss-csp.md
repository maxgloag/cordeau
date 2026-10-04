# ADR 0026 — Exposition XSS des tokens web : CSP d'abord, cookie HttpOnly sous condition de domaine

- **Status** : Accepted (temps 1 appliqué le 2026-10-04 ; temps 2 en attente)
- **Date** : 2026-10-03
- **Deciders** : Maxime
- **Contrôle** : `apps/web/public/_headers`, `apps/web/src/test/en-tetes-securite.test.ts`
- **Lié à** : [ADR 0003](0003-tokens-opaques-mobile.md) (tokens opaques), [ADR 0013](0013-oauth-google-auto-link.md) (OAuth Google), issue #128, issue #63 (rate limiting login)

## Context

L'app web stocke ses deux tokens opaques dans `localStorage` (`apps/web/src/lib/api.ts`) :

- `cordeau_token` : access token, 1 h ;
- `cordeau_refresh_token` : refresh token, **30 jours**, rotatif (le token consommé est invalidé).

Tout script exécuté dans l'origine du web peut donc les lire et les exfiltrer. L'issue #128 demande l'exposition réelle et s'il faut passer à un cookie `HttpOnly`.

### Audit (2026-10-03, état de `main`)

**Surface d'injection : très faible aujourd'hui.**

- Aucun `dangerouslySetInnerHTML`, `innerHTML`, `eval`, `new Function` ni `document.write` dans `apps/web/src`.
- Le contenu saisi par l'utilisateur (noms de clients, adresses, légendes de photos limitées à 280 caractères) est rendu comme enfants React, donc échappé.
- Les seuls liens dynamiques sont des URLs présignées renvoyées par l'API pour les photos (`PhotoLightbox`, `PhotoGalleryView`) ; l'OAuth Google web passe par un code à usage unique (`OAuthLoginCode`) échangé côté API, le token n'apparaît pas dans l'URL.
- `index.html` ne charge aucun script ni style externe.

**Défenses en profondeur : absentes.**

- **Aucune CSP** dans le dépôt : pas de `apps/web/public/_headers`, pas de balise `<meta http-equiv>`. Une configuration posée directement dans le tableau de bord Cloudflare Pages n'est pas visible depuis le dépôt et n'a pas pu être vérifiée ici : **à confirmer par le fondateur**.
- Pas d'en-têtes de durcissement versionnés (`X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors`).

**Chaîne d'approvisionnement.** 19 dépendances d'exécution (React, TanStack, Radix, react-hook-form, zod, Sentry…), toutes embarquées dans le bundle. Une dépendance compromise s'exécuterait avec accès à `localStorage`. C'est le vecteur le plus réaliste, et une CSP stricte ne l'arrête pas (le code malveillant serait dans le bundle servi par `'self'`) ; seul un token inaccessible à JavaScript (`HttpOnly`) limite les dégâts.

**Contrainte sur l'option cookie.** Le web (Cloudflare Pages) et l'API (`cordeau-api.fly.dev`) sont aujourd'hui sur des sites différents. Un cookie posé par l'API serait un cookie **tiers** : bloqué par défaut par Safari (ITP) et en cours de restriction dans les autres navigateurs. `SameSite=None` ne le sauve pas. Un cookie `HttpOnly` fiable suppose que web et API partagent le même site (par exemple `app.<domaine>` et `api.<domaine>`). L'existence d'un domaine propre n'est pas établie par le dépôt (`CORS_ALLOW_ORIGIN` n'y est défini que pour le local).

## Decision (proposée)

Deux temps, pour ne pas bloquer la bêta sur un chantier d'infrastructure :

1. **Maintenant : CSP stricte et en-têtes de durcissement**, versionnés dans `apps/web/public/_headers` (supporté par Cloudflare Pages). Déploiement d'abord en `Content-Security-Policy-Report-Only` (rapports vers Sentry), puis passage en enforcement après une période d'observation. Cible : `default-src 'self'`, `script-src 'self'`, `object-src 'none'`, `base-uri 'none'`, `frame-ancestors 'none'`, `connect-src` limité à l'API et à Sentry, `img-src` limité à `'self'`, `data:`, `blob:` et au domaine R2 des photos. Aucun changement de mécanisme d'authentification ni de code applicatif.
2. **Avant la bêta payante : basculer le refresh token en cookie `HttpOnly; Secure; SameSite=Strict`**, l'access token restant en mémoire (jamais persistant), **à condition** que web et API soient sur un même site. Cela suppose : un domaine propre, la pose et la lecture du cookie côté API (login, refresh, OAuth, logout), une protection CSRF (SameSite + en-tête `X-Client-Type` déjà exigé par le CORS), et l'adaptation du client web. Si le domaine commun n'est pas disponible, on reste sur le temps 1 en connaissance de cause.

Le mobile n'est pas concerné : il utilise le stockage sécurisé de l'OS (ADR 0003).

### Choix écartés

- **Cookie HttpOnly immédiat sans domaine commun** : cassé sur Safari, fragile ailleurs.
- **BFF (Backend-for-Frontend) avec session côté serveur** : meilleure isolation, mais une nouvelle brique à héberger et à sécuriser, disproportionnée pour une équipe de une personne.
- **Ne rien faire** : acceptable à court terme vu l'absence de sink XSS, mais sans aucun filet si l'une des 19 dépendances ou un futur composant introduit une injection.

## Implications sécurité

- **Surface d'attaque ajoutée** : temps 1 aucune (réduit la surface) ; risque de régression fonctionnelle si la CSP bloque une ressource légitime (images R2, Sentry) : mitigé par le mode Report-Only. Temps 2 : un cookie d'authentification apparaît (CSRF à traiter), l'API gère un nouveau mécanisme de session.
- **Secrets manipulés** : tokens d'authentification (access et refresh). Le temps 2 retire le refresh token de la portée de JavaScript. Aucun nouveau secret.
- **Données personnelles** : les tokens donnent accès aux données des clients et des chantiers (adresses, téléphones, photos), soumises au RGPD. L'objectif est de réduire le risque d'exfiltration de ces accès. Pas de nouvelle donnée collectée ; les rapports CSP envoyés à Sentry contiennent des URLs de la page, à examiner avant d'activer (pas de données personnelles dans les URLs du web aujourd'hui).
- **Points de fuite potentiels** : rapports CSP (URLs), cookie mal configuré (domaine trop large, `SameSite` laxiste), refresh token en clair dans les journaux de l'API (non vérifié ici).

## Consequences

**Positives** : défense en profondeur versionnée et testable ; décision sur le cookie prise en connaissance de la contrainte de domaine ; aucune régression d'auth au temps 1.

**Négatives / à surveiller** : la CSP demande de maintenir la liste des origines autorisées à chaque ajout de service externe ; le temps 2 dépend d'une décision d'infrastructure (domaine) qui n'est pas encore prise ; tant qu'il n'est pas fait, une dépendance compromise reste capable de voler un refresh token de 30 jours.

**Questions pour le fondateur** : (1) Une CSP ou des en-têtes sont-ils déjà posés dans Cloudflare Pages ? (2) Un domaine propre est-il prévu pour le web et l'API avant la bêta ? (3) Valide-t-on cette décision en deux temps ?

## Amendements

- 2026-10-04 — **Temps 1 appliqué** : `apps/web/public/_headers` pose `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options` (appliqués) et une CSP en `Content-Security-Policy-Report-Only` (non bloquante). Validée avec un vrai Chromium sur le build de la page de connexion : 0 violation. Le passage en enforcement reste une décision du fondateur, après observation.
- 2026-10-04 — **Pas de `report-uri`** : la clé publique du projet Sentry web n'est pas dans le dépôt (le DSN web est un secret Cloudflare). Les violations ne sont visibles que dans la console du navigateur.
- 2026-10-04 — **Correction de l'audit** : la phrase « `index.html` ne charge aucun script ni style externe » est exacte pour `index.html`, mais la feuille de style principale importe Google Fonts (`apps/web/src/index.css`). La CSP autorise donc `fonts.googleapis.com` (styles) et `fonts.gstatic.com` (polices). Héberger les polices en local supprimerait cette requête vers Google à chaque visite.
- 2026-10-04 — **Zod** : Zod 4 sonde `new Function("")` pour activer sa compilation JIT ; sous une CSP stricte, ce test est signalé comme violation. `z.config({ jitless: true })` (`apps/web/src/lib/zod-config.ts`) supprime la sonde sans changer la validation.
- 2026-10-04 — **Temps 2 (cookie HttpOnly)** : non fait, toujours conditionné à un domaine commun pour le web et l'API.
