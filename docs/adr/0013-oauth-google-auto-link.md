# ADR 0013 — OAuth Google : table `oauth_account` séparée + auto-link par email vérifié

- **Status** : Accepted
- **Date** : 2026-05-16
- **Deciders** : Maxime
- **Lié à** : [ADR 0003](0003-tokens-opaques-mobile.md) (tokens opaques mobile)

## Context

Phase 1 a livré l'auth email/mot de passe. La friction d'inscription reste élevée pour un public artisan peu technique. Google Sign-In réduit la friction (1 clic) et est attendu par la cible (mobile-first, smartphones Android/iOS dominants).

Trois questions structurantes :

1. **Stack OAuth côté API** : `KnpUOAuth2ClientBundle` (wrapper Symfony de `league/oauth2-client`), `HWIOAuthBundle`, ou implémentation manuelle.
2. **Modèle de données** : colonnes sur la table `utilisateur` (google_id, etc.) vs table séparée `oauth_account`.
3. **Liaison à un compte email/password existant** : si quelqu'un s'est inscrit avec `bob@example.com` puis revient via Google avec le même email, on auto-link, on refuse, ou on demande explicitement ?

Apple Sign-In est différé jusqu'à l'inscription Apple Developer (~première bêta, automne 2026). Cf décisions du 16 mai 2026.

## Decision

### Stack : `KnpUOAuth2ClientBundle` + `league/oauth2-google`

Bundle Symfony standard, bien maintenu, supporte tous les providers via les libs `league/*`. Pattern Symfony idiomatique : un `OAuth2ClientInterface` par provider, exposé via `ClientRegistry`.

Pas d'implémentation manuelle (chaque provider a ses petites subtilités, ne pas réinventer). HWI Bundle moins actif depuis 2022.

### Modèle : table `oauth_account` séparée

```sql
CREATE TABLE oauth_account (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES utilisateur(id) ON DELETE CASCADE,
  provider VARCHAR(32) NOT NULL,        -- 'google', 'apple' (futur)
  provider_user_id VARCHAR(255) NOT NULL,  -- 'sub' Google, 'sub' Apple
  email VARCHAR(255),                   -- snapshot au moment de la liaison
  created_at TIMESTAMP NOT NULL,
  UNIQUE (provider, provider_user_id),
  UNIQUE (user_id, provider)
);
```

Justifications :
- **N providers par user** : un user peut lier Google ET Apple. Une table séparée évite des dizaines de colonnes nulles sur `utilisateur`.
- **Évolutif** : ajouter Microsoft / GitHub / Facebook n'impacte pas le schéma `utilisateur`.
- **Audit-friendly** : on garde la date de liaison, on peut tracer.
- **Pattern CRUD léger** (cf [ADR 0010](0010-crud-leger-pattern-reference.md)) : pas de domaine riche, juste une entité Doctrine simple.

### Auto-link silencieux si `email_verified=true`

Comportement attendu lors d'un callback Google :

1. Si `OAuthAccount(provider='google', provider_user_id=$sub)` existe → login le `User` associé.
2. Sinon, si Google renvoie `email_verified=true` ET un `User` existe avec ce `email` → créer un `OAuthAccount` lié à ce `User`, login.
3. Sinon → créer un nouveau `User` (avec `motDePasseHash=''`) + `OAuthAccount`, login.

L'auto-link silencieux est sûr : Google ne renvoie `email_verified=true` que si l'utilisateur Google a effectivement validé cet email côté Google. Donc si quelqu'un essaie de "voler" un compte en s'inscrivant Google avec `bob@example.com` sans avoir contrôlé cet email, Google renvoie `email_verified=false` → on tombe dans le case 3 (création d'un nouveau User avec une collision d'email → erreur DB → on retombe sur un message d'erreur explicite). Pas de risque réel.

L'alternative "refus explicite avec UI" a été rejetée pour la friction qu'elle impose à un public déjà sensible aux frictions.

### Token de session : réutilise l'infra Phase 1.3

- Web : `Security::login()` pose le cookie de session, pas de changement.
- Mobile : un endpoint dédié `POST /auth/oauth/google/exchange` reçoit l'`id_token` Google (récupéré côté app via `expo-auth-session` + PKCE), le vérifie (signature JWT + audience), applique la logique d'auto-link, et retourne `{token, refreshToken}` (AuthToken cf [ADR 0003](0003-tokens-opaques-mobile.md)).

## Consequences

**Bénéfices** :
- Friction d'inscription divisée par ~5 (1 clic + consent au lieu de email + password + validation)
- Schéma extensible (Apple, autres providers) sans migration ultérieure
- Réutilise toute l'infra existante (`User`, `AuthToken`, `TokenAuthenticator`) : seule la création d'`User` change (pas de password)
- Pattern idiomatique Symfony, peu de code custom à maintenir

**Trade-offs** :
- Dépendance externe (Google APIs) : si Google est down, register/login via Google ne marche pas. Le fallback email/password reste disponible.
- Comptes "sans mot de passe" (`motDePasseHash=''`) : un user créé via Google ne peut pas se login en email/password tant qu'il n'a pas explicitement défini un password. Endpoint `set-password` à ajouter en V2 si besoin.
- Config externe (Google Cloud Console, redirect URIs prod) à gérer en dehors du repo. Documenté dans le runbook.

**Décisions différées** :
- **Apple Sign-In** : ajouté quand le compte Apple Developer sera pris (automne 2026). L'architecture `oauth_account` accepte déjà `provider='apple'` sans modif. Un nouveau Processor + endpoint suffira.
- **Unlink** : pas d'endpoint pour délier Google en V1. Si demandé, ajouter `DELETE /auth/oauth/{provider}`.
- **Microsoft / GitHub / Facebook** : aucun signal d'usage de la cible artisans. Différé sine die.
