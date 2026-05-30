# ADR 0003 — Tokens opaques (en DB) plutôt que JWT pour l'API mobile

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

L'app mobile doit s'authentifier auprès de l'API Symfony. Deux approches standard :

**JWT (JSON Web Tokens)** : tokens auto-porteurs signés, vérifiables sans appel DB. Couramment recommandés pour les APIs "stateless".

**Tokens opaques** : chaîne aléatoire stockée en DB, vérifiée à chaque requête par un SELECT.

## Decision

**Tokens opaques en DB avec rotation automatique des refresh tokens.**

Mécanisme :

- `access_token` : durée courte (1h), stocké en DB avec hash bcrypt, vérifié à chaque requête API (cache Redis 60s pour éviter le SELECT systématique)
- `refresh_token` : durée longue (30 jours), rotation à chaque rafraîchissement (le refresh token consommé est invalidé immédiatement)
- Table : `auth_token(id, user_id, token_hash, expires_at, device_info, revoked_at, created_at)`

## Consequences

**Bénéfices** :

- **Révocation instantanée** — critique en cas de vol de téléphone : l'artisan signale le vol → `UPDATE auth_token SET revoked_at = NOW() WHERE user_id = ?` → accès coupé immédiatement. Avec JWT, il faut attendre l'expiration (impossible à couper sans liste de révocation = la même complexité que les tokens opaques)
- **Debugging trivial** : un token = une ligne en DB, visible et révocable en SQL direct
- **Simplicité** : pas de bibliothèque JWT à configurer, pas de problème d'horloge (clock skew), pas d'algorithme à choisir
- **Performance suffisante** : cache Redis 60s sur les access tokens → 1 SELECT max par minute et par user

**Trade-offs** :

- **Requête DB** sur chaque appel API non caché → négligeable avec Redis en place
- **État côté serveur** → pas de scaling horizontal "stateless". Acceptable : une seule instance suffit pour des centaines d'users, et si scaling nécessaire, Redis partagé suffit

**Ce qui n'est pas retenu** :

- JWT sans révocation (trop risqué pour une app mobile avec données client)
- JWT avec liste de révocation (même complexité que tokens opaques, sans les avantages)
