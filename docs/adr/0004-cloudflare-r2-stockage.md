# ADR 0004 — Cloudflare R2 pour le stockage des photos de chantier

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

L'app mobile permet aux artisans de photographier leurs chantiers. Ces photos doivent être :
- Uploadées depuis le mobile (parfois hors-ligne → file d'attente)
- Servies rapidement aux clients web et mobile
- Stockées durablement (plusieurs années)

Volumes estimés : 5-20 photos par chantier, 50+ chantiers actifs par utilisateur, résolution mobile standard (2-5 Mo avant compression).

## Decision

**Cloudflare R2** comme object storage, avec upload direct depuis le mobile via **pre-signed URLs** générées par l'API Symfony.

Flow :
1. Mobile → `POST /api/photos/prepare` → API retourne une pre-signed URL R2 (valide 15 min)
2. Mobile → upload direct vers R2 (ne transite pas par le backend)
3. Mobile → `POST /api/photos/confirm` → API enregistre la référence en DB
4. Thumbnails générés en asynchrone par un Symfony Messenger worker (stockés sur R2 aussi)

## Consequences

**Bénéfices** :
- **Bande passante en sortie gratuite** — R2 ne facture pas l'egress vers les clients. Sur AWS S3, le prix moyen est ~90€/To. Pour une app servant des centaines de photos d'artisans, l'économie est réelle dès les premiers utilisateurs
- **10 Go gratuits à vie** — suffit pour tout le MVP (phase bêta avec 5-15 users)
- **API S3-compatible** — si on migre vers S3 plus tard, le code reste identique (changement de configuration uniquement)
- **L'API ne devient pas un proxy de fichiers** — le backend ne gère pas les octets des photos, seulement les métadonnées → scalabilité et perf serveur préservées

**Trade-offs** :
- Cloudflare R2 est moins mature que S3 (moins de région, fonctionnalités avancées plus limitées)
- Pas de CDN intégré "out of the box" comme CloudFront → R2 custom domain suffit pour les cas d'usage actuels

**Portabilité** :
L'API S3-compatible garantit la migration en changeant uniquement `endpoint`, `access_key`, `secret_key` dans la configuration. Aucun changement de code applicatif nécessaire.
