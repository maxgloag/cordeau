# Roadmap — Cordeau

> Plan d'attaque par phases. Chaque phase a un objectif, des jalons, et un **critère de sortie** mesurable. On ne passe à la phase suivante que quand le critère est atteint.

**Principe directeur** : construire **verticalement** (un bounded context complet end-to-end sur les 3 plateformes), pas horizontalement (toute la DB puis toute l'API puis tout le front).

## Statut actuel

**Phase 1 — Verticale Chantiers** ✅ terminée (mai 2026, issues #1 → #5). **Phase 2 — Verticale Clients** ✅ terminée (mai 2026, issues #11 → #15, vélocité ×5 vs Phase 1). **Phase 3 — Offline-first** ✅ terminée (mai 2026, issues #22 → #26, PRs #27 → #31). **Phase 4 — OAuth Google** ✅ terminée (16 mai 2026, issues #35 → #37, PRs #38 → #42, ADR 0013). Apple Sign-In différé. **Phase 5 — Photos + R2** à démarrer.

## Rétroplanning indicatif

| Période | Phases | Cible |
|---|---|---|
| Mai 2026 | Phase 0 + début Phase 1 | Fondations + premiers chantiers |
| Juin 2026 | Phase 1 + Phase 2 | Verticales Chantiers + Clients |
| Juillet 2026 | Phase 3 + Phase 4 | Offline-first + OAuth |
| Août 2026 | Phase 5 + Phase 6 | Photos R2 + Devis |
| Septembre 2026 | Phase 7 | AR de mesure V1 |
| Octobre 2026 | Lancement bêta payante | 5 artisans bêta-testeurs |

---

## Phase 0 — Fondations (~1 semaine)

**Objectif** : monorepo qui build, lint, teste et déploie automatiquement. Pas une ligne de code métier.

### Jalons

- [x] Monorepo Turborepo + pnpm + Node 24 + Postgres 18 / Redis 8 via Docker Compose
- [x] `CLAUDE.md` racine, `README.md`, `ROADMAP.md`, `.editorconfig`, `.gitignore`
- [x] Symfony 7.4 + API Platform 4 + PHP 8.5 → route `/health`
- [x] Vite 8 + React 19 + Tailwind v4 → page qui appelle `/health`
- [x] Expo SDK 54 → écran qui appelle `/health` (expo-router à ajouter Phase 1)
- [x] CI GitHub Actions : lint + test + build (3 jobs api/web/mobile, services Postgres/Redis)
- [x] Repo GitHub `maxgloag/cordeau` privé + branch protection sur `main`
- [x] Organisation GitHub : milestones (1 par phase) + labels + Projects board + templates issues/PR
- [x] ADRs 0001 à 0007 rédigés (incluant Browser Mode + pattern container/view, ajoutés en cours de route)
- [x] Hook `ci-watch.sh` (asyncRewake) pour surveillance auto post-push
- [x] Déploiement : API → Fly.io (Paris) + Neon, web → Cloudflare Pages, mobile → EAS preview Android
- [x] Sentry sur les 3 apps (DSN injectés, intégration code Phase 1)

### Critère de sortie

> Je pushe un commit, la CI passe, et `/health` répond depuis les 3 environnements de prod.

---

## Phase 1 — Verticale Chantiers (2-3 semaines)

**Objectif** : CRUD Chantiers complet sur les 3 plateformes, avec auth email/password, en suivant rigoureusement l'architecture cible. **C'est ici qu'on valide TOUT** : conventions, archi, CI/CD, patterns.

### Sous-étapes

- **1.1 Domaine + persistance** (2-3 j) : entité `Chantier`, value objects (Adresse, Surface, Statut), port `ChantierRepository`, adapter Doctrine, migration, tests
- **1.2 Use cases + API** (2-3 j) : Creer/Lister/Obtenir/Modifier/ArchiverChantier, controllers API Platform, OpenAPI → types TS via openapi-typescript
- **1.3 Auth minimale** (2 j) : email/password, cookie de session côté web, token opaque côté mobile, endpoints `/auth/{register,login,refresh,logout}`
- **1.4 Web** (3-4 j) : login/register + CRUD chantiers, TanStack Query + RHF + Zod, shadcn/ui
- **1.5 Mobile** (3-4 j) : login/register + CRUD chantiers, expo-secure-store. Pas encore d'offline.

### Critère de sortie

> Un utilisateur s'inscrit sur web ou mobile, crée un chantier depuis l'une, le voit synchronisé sur l'autre, le tout déployé en prod.

---

## Phase 2 — Verticale Clients (1-2 semaines) ✅

CRUD Clients (entité + relation `Chantier → Client`), CRUD sur les 3 plateformes, écran de liaison client ↔ chantier.

**Patterns actés** : CRUD léger (ADR 0010), ClientRef VO dénormalisé (ADR 0011), ClientRefResolver, JsonTestHelper trait.

**Critère de sortie atteint** : vélocité ×5 vs Phase 1 (2.1→2.4 en ~2h30 au total vs plusieurs jours en Phase 1).

---

## Phase 3 — Offline-first mobile (~2 semaines) ✅

SQLite via expo-sqlite + Drizzle ORM, schéma local miroir des entités serveur (chantiers, clients, outbox), file d'attente des mutations (outbox), sync via worker (`useSyncWorker` sur AppState + reconnect), UI optimiste via `useOfflineMutation`, indicateur `SyncStatusBar`.

**Patterns actés** :
- queryFn hybride : SQLite synchrone + refresh API en background (ADR 0012)
- `useOfflineMutation` : optimistic local + outbox push + processOutbox fire-and-forget. Le mobile génère un UUID v4 → l'API l'accepte tel quel via le champ `uuid` (idempotence 409 si collision)
- Sync worker : event natif `expo-network` + `AppState=active` + polling 5 s en fallback (l'event natif est peu fiable sur iOS lors d'un toggle mode avion). `processOutbox` puis `refreshAll` séquentiels pour éviter la race « refreshAll écrase le cache avant que processOutbox finisse »
- Backoff exponentiel dans `processOutbox` capé à 30 s (MAX_RETRY=10). 409 → `synced`, autres 4xx → `abandoned`, 5xx/network → retry

**Critère de sortie atteint** : verticales Chantiers + Clients fonctionnent en offline complet, sync au retour réseau (≤ 5 s sans reload), indicateur visuel en temps réel.

**Phase 3 corrective (16 mai 2026, PRs #32 → #34)** : implémentation effective de la décision ADR 0012 (API accepte UUIDs client) qui avait été oubliée. Suppression de 70 lignes de dette mobile (`rewriteLocalId`, payload rewriting, setQueryData défensif). Worker résilient avec polling 5 s + logs structurés `[network]/[sync]/[outbox]`.

---

## Phase 4 — OAuth Google (~1 jour) ✅

KnpUOAuth2ClientBundle + league/oauth2-google. Table `oauth_account` séparée (multi-providers ready). Flow web (redirect Symfony) + flow mobile (expo-auth-session + PKCE → endpoint `/auth/oauth/google/exchange` qui vérifie le `id_token` via `tokeninfo` Google et applique l'auto-link). Idempotence par 409 sur collision UUID.

**Patterns actés (ADR 0013)** :
- Auto-link silencieux par email si `email_verified=true` (Google a déjà validé l'email)
- Table `oauth_account` extensible (provider varchar) — Apple, GitHub, etc. ajoutables sans migration
- Ports `OAuthAccountStore`, `UserStore`, `GoogleUserResolver`, `GoogleIdTokenVerifier` pour testabilité (les repos Doctrine sont `final`)
- Bouton "Continuer avec Google" web + mobile (logo officiel multicolor SVG)

**Critère de sortie atteint** : compte créable en 3 clics via Google depuis web (testé end-to-end). Code mobile livré et bundle Metro vert ; test E2E iPhone différé jusqu'au compte Apple Developer (Expo Go ne supporte pas le bundle ID custom requis par Google iOS).

**Apple Sign-In différé** : sera ajouté quand le compte Apple Developer sera pris (~Phase 5+). Aucun rework nécessaire vu l'archi multi-providers.

---

## Phase 5 — Photos + R2 (~1 semaine)

Bucket Cloudflare R2, endpoints Symfony pour pre-signed URLs, upload direct depuis mobile (pas de transit backend), drag-and-drop web, worker Messenger pour thumbnails, liaison photos ↔ chantiers, suppression cascade.

**Critère de sortie** : 20 photos prises sur un chantier en mobile (même hors-ligne) s'uploadent en arrière-plan quand la connexion revient, consultables depuis le web.

---

## Phase 6 — Devis (~2-3 semaines)

**Premier vrai morceau métier**. Étudier Tolteck/Obat avant de coder, parler à de vrais artisans pour figer le design.

Entité `Devis` avec lignes/TVA/totaux, use cases (créer/éditer/dupliquer/envoyer), génération PDF (worker Messenger), numérotation légale (séquence ininterrompue), états (brouillon/envoyé/accepté/refusé/expiré), UI web pour édition + UI mobile en lecture/partage.

**Critère de sortie** : un artisan génère un devis conforme légalement, l'envoie par email, le client peut l'ouvrir.

---

## Phase 7 — AR de mesure V1 (~3-4 semaines)

**Le "wow" feature**. Volontairement en phase 7 — tant que le reste n'est pas solide, l'AR ne sauve rien.

Expo Module custom pour ARKit (iOS prio 1, LiDAR), flow tap-to-measure, calcul de polygone, sauvegarde du métré lié au chantier, export du résultat. ARCore Android en V2.

**Critère de sortie** : surface au sol mesurée à ±3 cm sur iPhone Pro, valeur enregistrée dans le chantier.

---

## Phase 8 — Facturation + conformité (~2-3 semaines, optionnel)

Si on décide de rester un "logiciel de facturation" légal : entité `Facture`, transformation devis → facture, conformité loi anti-fraude TVA, intégration Chorus Pro / PDP, audit avec un expert (~2-4k€).

**Alternative** : se positionner "outil de suivi" et intégrer plus tard un vrai logiciel de facturation via API.

---

## Phase 9 — Go-to-market (en parallèle dès Phase 5)

Landing page Next.js séparée, formulaire d'attente, 5 artisans bêta recrutés réseau/terrain, onboarding manuel un par un en visio, itération rapide sur feedbacks, pricing à poser, mentions légales / CGU / CGV / DPA chez avocat (~1.5-2.5k€).

---

## Règles d'hygiène à tenir tout du long

1. Un commit = une intention claire (Conventional Commits)
2. Pas de feature sans test (sur le domaine au minimum)
3. L'ADR avant le code pour toute décision non triviale
4. Déploiement continu dès Phase 0 (zéro deploy manuel)
5. Revue régulière du `CLAUDE.md` à chaque fin de phase
6. Un bounded context à la fois (pas de mélange)
7. Démo hebdo perso (vendredi)
