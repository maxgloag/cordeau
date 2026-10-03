# ADR 0027 — Rate limiting du login : limiteur par compte, appelé depuis le contrôleur

- **Status** : Accepted
- **Date** : 2026-10-03
- **Deciders** : Maxime
- **Lié à** : [ADR 0021](0021-acces-restreint-beta.md) (« Décisions différées »), [ADR 0003](0003-tokens-opaques-mobile.md), issue #63

## Context

`POST /auth/login` n'avait aucune limitation de tentatives : brute-force de mots de passe possible. L'issue #63 proposait d'activer `login_throttling` sur le firewall `main`.

Deux constats rendent cette piste inopérante :

1. **Le login n'est pas un authentificateur du firewall.** `/auth/login` est `PUBLIC_ACCESS` et traité par `LoginController` (contrôleur maison) ; le firewall ne contient que `TokenAuthenticator`, qui lit un token déjà émis. `login_throttling` s'accroche aux authentificateurs de login (`json_login`, `form_login`, `http_basic`) : activé ici, il ne limiterait rien.
2. **Le blocage par email seul est un choix de prudence, pas une contrainte technique.** `framework.trusted_proxies` lit `SYMFONY_TRUSTED_PROXIES` ; absent de `.env` et de `fly.toml`, il est **défini comme secret Fly** en production (`REMOTE_ADDR`, avec `X-Forwarded-For` parmi les en-têtes de confiance, vérifié le 2026-10-03). L'IP cliente y est donc exploitable. Elle n'a pas été utilisée dans cette première version : ni testée en production, ni couverte par un test (le test d'intégration ne simule pas de proxy).
   _Correction du 2026-10-03 : la rédaction initiale affirmait à tort que l'IP n'était pas fiable._

## Decision

**Utiliser directement le composant `symfony/rate-limiter` dans `LoginController`**, avec un limiteur `login` (`sliding_window`, **5 tentatives par 15 minutes**) dont la clé est le **hash SHA-256 de l'email normalisé** (trim + minuscules). Pas de composante IP dans cette première version (cf Context, point 2).

- Le contrôle a lieu **avant** la vérification du mot de passe : un compte verrouillé répond `429` avec un en-tête `Retry-After`, sans hachage bcrypt (économie de CPU) et sans jamais authentifier, même avec le bon mot de passe.
- Un **login réussi remet le compteur à zéro**.
- Le compteur est consommé pour **toute tentative**, compte existant ou non : on ne révèle pas l'existence d'un compte par la différence de comportement (même `401` puis même `429`).
- L'email n'est jamais stocké en clair dans le cache (clé hachée).
- Compteurs dans un **pool de cache dédié** `cache.rate_limiter`.

### Limites assumées

- **Verrouillage d'un compte par un tiers** : quelqu'un qui connaît l'email d'un artisan peut déclencher 5 échecs et l'empêcher de se connecter pendant jusqu'à 15 minutes. Compromis accepté en bêta privée (peu de comptes, emails non publics) ; c'est le prix d'une clé sans IP. Atténuation : fenêtre courte, remise à zéro au succès.
- **Pas de limite par IP** : un attaquant peut essayer de nombreux emails à raison de 5 mots de passe par compte. Les mots de passe sont hachés en bcrypt, et l'inscription est fermée en bêta (ADR 0021), ce qui limite la surface.
- **Stockage sur fichiers, par machine** : le pool utilise l'adaptateur de cache par défaut (système de fichiers). Avec plusieurs machines Fly, chacune a son compteur (seuil effectif multiplié) ; les compteurs sont perdus à chaque déploiement.

### Suites (hors de cette PR)

- Ajouter un limiteur **par IP** en complément (l'IP cliente est exploitable en production, cf Context) après l'avoir vérifié sur l'application déployée, avec un test qui simule le proxy (#170).
- Brancher Redis (déjà dans la stack) comme adaptateur du pool `cache.rate_limiter` pour partager les compteurs entre machines (#170).
- Prévoir un limiteur dédié sur `POST /auth/register` à la réouverture de l'inscription self-service (ADR 0021).

### Choix écartés

- **`login_throttling` du firewall** : sans effet ici (cf Context).
- **Clé `IP + email`** (comportement par défaut de Symfony) : atténuerait le verrouillage par un tiers, mais l'IP n'avait pas été vérifiée de bout en bout au moment de la décision. À reconsidérer avec le limiteur par IP (#170).
- **Passer le login par `json_login`** pour réutiliser `login_throttling` : refonte de l'authentification disproportionnée par rapport au besoin.

## Implications sécurité

- **Surface d'attaque** : la limitation réduit le brute-force ; elle introduit le verrouillage par un tiers (ci-dessus). Nouvelle dépendance : `symfony/rate-limiter` 7.4 (composant officiel Symfony, même version que le reste de la stack) et `symfony/options-resolver` (transitif).
- **Secrets manipulés** : aucun.
- **Données personnelles** : l'email sert de clé de limitation, **haché** (SHA-256) avant tout stockage en cache ; pas de donnée personnelle en clair ajoutée. Durée de conservation : fenêtre de 15 minutes.
- **Points de fuite potentiels** : l'en-tête `Retry-After` et le `429` ne distinguent pas compte existant et inexistant. Les logs ne doivent pas consigner l'email en clair des tentatives bloquées (non journalisé ici).
