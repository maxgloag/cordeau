# ADR 0021 — Accès restreint en bêta privée : kill-switch d'inscription réversible

- **Status** : Accepted
- **Date** : 2026-06-01
- **Deciders** : Maxime
- **Lié à** : [ADR 0013](0013-oauth-google-auto-link.md) (OAuth Google auto-link), [ADR 0003](0003-tokens-opaques-mobile.md) (tokens opaques mobile)

## Context

En production, l'inscription est **ouverte à tous** :

- `POST /auth/register` est déclaré `PUBLIC_ACCESS` (`config/packages/security.yaml`) et crée un `User` + un `AuthToken` dès qu'un email/mot de passe valide est fourni — sans invitation ni vérification d'email.
- L'auto-provisioning OAuth Google crée également un nouveau `User` au premier login d'un email inconnu (cas 3 de `AuthentifierViaGoogleUseCase`, cf [ADR 0013](0013-oauth-google-auto-link.md)).

Pour la **bêta payante** (cible septembre 2026), seuls des testeurs choisis doivent pouvoir entrer. Quiconque connaît l'URL de l'API peut aujourd'hui se créer un compte. Deux problèmes distincts en découlent :

1. **Contrôle d'accès** : qui a le droit d'avoir un compte (objet de cet ADR).
2. **Robustesse de l'auth** : absence de rate limiting sur `register`/`login` → brute-force et création de comptes en rafale possibles (traité en marge ici).

Contraintes propres au contexte Cordeau :

- **Peu de testeurs au départ**, connus de Maxime → l'onboarding manuel est acceptable.
- **Réouverture self-service prévue en V1** → la fermeture doit être réversible, pas une suppression de code.
- App **mobile** distribuée à des testeurs externes → une solution edge (Cloudflare Access, Basic Auth proxy) frictionne le mobile et a été écartée pour ce besoin (réservée à un éventuel staging interne).

## Decision

### Kill-switch d'inscription piloté par variable d'environnement

Un flag booléen `REGISTRATION_SELF_SERVICE_ENABLED` gouverne l'ouverture de l'inscription self-service :

- **Prod** : `false` par défaut (injecté via `fly secrets set`).
- **Dev / test** : `true` (comportement historique préservé ; les tests qui valident la fermeture forcent `false`).

Exposé comme paramètre lié (`%env(bool:REGISTRATION_SELF_SERVICE_ENABLED)%`) et consommé via un petit service `RegistrationPolicy` (une méthode `selfServiceEnabled(): bool`) injecté là où c'est nécessaire — plutôt qu'un `bool` brut, pour rester testable et nommer l'intention.

### Garde au niveau applicatif, sur les deux portes d'entrée

La garde vit dans le code, **pas dans le firewall** :

- Le firewall `access_control` ne lit pas dynamiquement un flag d'environnement.
- L'auto-provisioning OAuth se décide **dans le use case**, après une authentification Google réussie — hors de portée du firewall.

Points de garde :

1. **`RegisterController`** : si `selfServiceEnabled()` est faux → `403` (`{ "message": "Les inscriptions sont actuellement fermées." }`), aucun `User` créé.
2. **`AuthentifierViaGoogleUseCase`** : la branche « création d'un nouveau `User` » (cas 3 de l'ADR 0013) lève une exception métier traduite en `403` côté `GoogleExchangeController` / `GoogleCallbackController` si le flag est faux. Les cas 1 et 2 (compte ou `OAuthAccount` déjà existant → login) **restent ouverts**.

**Le login reste ouvert** dans tous les cas (`/auth/login`, `/auth/refresh`, login OAuth des comptes existants) : on ferme la création de comptes, pas l'accès des testeurs déjà onboardés.

### Onboarding manuel par commande console

Commande `app:user:create <email> [--mot-de-passe=]` (Symfony Console) : crée un `User` avec mot de passe hashé (généré aléatoirement et affiché si non fourni), idempotente sur email existant (erreur explicite). Elle **ne dépend pas** du flag : c'est le canal d'onboarding admin pendant la fermeture.

### Bonus sécurité : `login_throttling`

Activation de `login_throttling` (Symfony Security, `max_attempts: 5`) sur le firewall `main`, adossé à `symfony/rate-limiter`. Ferme le brute-force sur le login. Hors périmètre du contrôle d'accès stricto sensu, mais le même PR touche l'auth — autant fermer la fente tant qu'on y est.

### Alternatives écartées

- **Invitation-only (table `Invitation` + codes)** : le standard SaaS, mais surdimensionné pour une poignée de testeurs connus. Reportable si le volume grandit — l'onboarding manuel est un sous-ensemble trivial à faire évoluer vers ça.
- **Allowlist email/domaine** : maintenance manuelle d'une liste, sans gain sur l'onboarding console.
- **Waitlist + approbation** : pertinent pour capter une demande large ; pas le besoin ici (testeurs déjà identifiés).
- **Cloudflare Access / Basic Auth edge / IP allowlist / VPN** : gate avant l'app, mais friction forte avec l'app mobile distribuée à des externes. Réservé à un éventuel staging interne, pas à la bêta.

## Implications sécurité

- **Surface d'attaque** : _réduite_. La fermeture de l'inscription supprime la création de comptes par des inconnus (mot de passe **et** OAuth). Le flag est un secret d'environnement non exposé côté client.
- **Auth / tokens** : la garde ne change pas le modèle de tokens (cf [ADR 0003](0003-tokens-opaques-mobile.md)) ; elle empêche seulement l'émission d'un token à un `User` nouvellement créé quand le flag est faux. Le login des comptes existants est volontairement intact.
- **Données personnelles (RGPD)** : la décision **diminue** la collecte — moins de comptes (donc moins d'emails) créés par des tiers non sollicités. La commande console crée des comptes pour des testeurs ayant consenti à la bêta. Base légale : exécution de la relation de test/contrat bêta.
- **Points de fuite potentiels** : risque de **divergence entre les deux gardes** (fermer `register` mais oublier l'auto-provisioning OAuth laisserait une porte ouverte). Mitigation : tests d'intégration couvrant explicitement les deux chemins + le maintien du login existant. Risque de **mauvais défaut** (flag à `true` en prod par accident) : défaut `false` au niveau applicatif, et valeur prod posée explicitement via secret Fly.
- **Brute-force** : `login_throttling` ferme la fente sur le login. `register` étant fermé en prod, sa surface est nulle tant que le flag est faux ; à la réouverture self-service (V1), prévoir un rate limiter dédié sur `register` (noté ci-dessous).

## Consequences

**Bénéfices** :

- Bêta réellement privée : aucun compte créé par un inconnu, par mot de passe comme par OAuth.
- **Réversibilité totale** : réouverture par `fly secrets set REGISTRATION_SELF_SERVICE_ENABLED=true`, zéro retour de code.
- Coût d'implémentation minimal, proportionné au besoin (pas de table, pas de flux d'invitation).
- Brute-force login fermé en passant.

**Trade-offs / coûts** :

- Onboarding **manuel** : ne passe pas à l'échelle au-delà de quelques dizaines de testeurs. Assumé ; bascule vers invitation-only documentée comme évolution naturelle.
- Deux points de garde à maintenir cohérents (register + OAuth provisioning) → couverture de test obligatoire.

**Décisions différées** :

- **Invitation-only** : à introduire si le volume de testeurs dépasse l'onboarding manuel. La commande console en est le germe.
- **Rate limiter sur `register`** : à ajouter au moment de la réouverture self-service (V1), quand l'endpoint redeviendra public.
- **Vérification d'email** : hors scope ici ; à trancher avec la réouverture self-service.
