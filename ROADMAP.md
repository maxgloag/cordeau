# Roadmap — Cordeau

> Plan d'attaque par phases. Chaque phase a un objectif, des jalons, et un **critère de sortie** mesurable. On ne passe à la phase suivante que quand le critère est atteint.

**Principe directeur** : construire **verticalement** (un bounded context complet end-to-end sur les 3 plateformes), pas horizontalement (toute la DB puis toute l'API puis tout le front).

## Statut actuel

**Phase 1 — Verticale Chantiers** ✅ terminée (mai 2026, issues #1 → #5). **Phase 2 — Verticale Clients** ✅ terminée (mai 2026, issues #11 → #15, vélocité ×5 vs Phase 1). **Phase 3 — Offline-first** ✅ terminée (mai 2026, issues #22 → #26, PRs #27 → #31). **Phase 4 — OAuth Google** ✅ terminée (16 mai 2026, issues #35 → #37, PRs #38 → #42, ADR 0013). Apple Sign-In différé. **Phase 5 — Photos + R2** à démarrer. **Repositionnement V1 acté mai 2026** ([ADR 0015](docs/adr/0015-modele-chantier-lots-taches-mesures.md), [ADR 0016](docs/adr/0016-positionnement-v1-outil-de-suivi.md), [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)) : wedge V1 = capture terrain manuelle ; AR et PDP différés V2 ; IA différée V1.2+ sur critère de validation bêta.

## Rétroplanning indicatif

| Période | Phases | Cible |
|---|---|---|
| Mai 2026 | Phases 0 → 4 ✅ | Fondations + Chantiers + Clients + Offline + OAuth |
| Mai-juin 2026 | Phase 5 | Photos + R2 |
| Juin-juillet 2026 | Phases 6 + 7 | Verticale Lots/Tâches + Capture terrain & Métré manuel |
| Juillet-août 2026 | Phase 8 | Devis + Facture brouillon (édition manuelle) |
| Septembre 2026 | Phase 9 | Bêta payante V1 — validation critère manuel |
| Q4 2026 → 2027 | Phases 10+ | V1.1 (UX fluidifiée) / V1.2 (magie LLM si critère levé) / V1.3 / V2 |

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

## Phase 6 — Verticale Lots / Tâches (~2-3 semaines)

**Premier vrai morceau métier post-Phase 5**. Le Lot devient l'unité centrale du chantier (cf [ADR 0015](docs/adr/0015-modele-chantier-lots-taches-mesures.md)). Mode création express : un chantier sans lot reste valide.

### Sous-étapes

- **6.1 Domaine + persistance** (4-5 j) : entités `Lot`, `Tache`, VO `ModeFacturation` (enum `TEMPS` / `SURFACE` / `FORFAIT`), `Estimation`, `Reel`, `Imprevu` (VO horodaté). Ports `LotRepository`, `TacheRepository`. Adapters Doctrine, migration additive (pas de modif `chantier`), tests domaine purs.
- **6.2 Use cases + API** (3-4 j) : `Creer/Lister/Modifier/SupprimerLot`, `Creer/Cocher/SupprimerTache`. Routes `/chantiers/{id}/lots`, `/lots/{id}/taches`. OpenAPI → types TS.
- **6.3 Mobile** (3-4 j) : écran chantier enrichi avec section Lots, bouton « + lot » + UI mode express (chantier sans lot fonctionne par défaut). Édition tâches inline. Outbox pour les nouvelles entités.
- **6.4 Web** (2-3 j) : vue détaillée chantier avec arborescence lots/tâches, édition rapide.

**Critère de sortie** : un artisan crée un chantier mode express depuis mobile, ajoute un lot mode `SURFACE` avec estimation, ajoute 3 tâches, coche les tâches, le tout synchronisé sur web.

---

## Phase 7 — Capture terrain + Métré manuel (~2-3 semaines)

**La phase qui valide le wedge V1**. Si la capture manuelle est friction-prone ici, le concept échouera la bêta — c'est le test ultime de [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md).

### Sous-étapes

- **7.1 Domaine** (3 j) : entités `Materiau` (avec `EtatMateriau` enum `PREVU` / `UTILISE`), `Mesure` (avec `TypeMesure` enum + `Source` enum), `Pointage`. VO `Surface` / `Longueur` / `Volume` promus dans `Shared/ValueObject/`. Méthode pure `Mesure::calculer()` depuis dimensions sources. Tests domaine.
- **7.2 Use cases + API** (3 j) : `Creer/Marquer/SupprimerMateriau`, `Creer/CorrigerMesure`, `DemarrerChrono`/`ArreterChrono` (gestion Pointage). Photos contextualisées (champ optionnel `lotId`/`tacheId` sur `Photo`).
- **7.3 Mobile** (5-6 j) — **focus UX critique** : écran Lot avec sections Tâches / Matériaux / Mesures / Chrono ; formulaires en 1-2 taps ; calcul auto Mesure depuis dimensions ; bouton start/stop chrono persistant ; photo en un geste rattachée au lot ou à une mesure ; rappels locaux fin de journée si chrono ouvert > 12h ; rappel de pointage configurable.
- **7.4 Web** (2 j) : vue métré par lot (récap des mesures avec total), liste matériaux avec bascule prévu/utilisé en un clic, vue chrono cumulé.

**Critère de sortie** : un artisan, sur un chantier réel, capture en une journée 5+ entrées matériaux, 3+ mesures, 4+ pointages chrono et 10+ photos, sans aide. Vélocité ressentie : « plus rapide qu'un carnet ».

---

## Phase 8 — Devis + Facture brouillon (~3-4 semaines)

**Les sorties qui consomment les données captées en Phase 7**. Pas de conformité PDP en V1 ([ADR 0016](docs/adr/0016-positionnement-v1-outil-de-suivi.md)). Mobile-first sur la création basique (« devis sur place »), web pour la finalisation pointue (logo, mentions complexes).

### Sous-étapes

- **8.1 Profil pro / Onboarding** (3-4 j) : entité `ProfilPro` (raison sociale, SIRET, adresse, mentions assurance/RGE, taux horaire par défaut, logo upload), écrans onboarding mobile/web post-inscription, validation côté serveur (export PDF bloqué si profil incomplet).
- **8.2 Domaine + persistance Devis** (3-4 j) : entité `Devis` avec `LigneDevis`, `TauxTVA`, `MontantHT`/`MontantTTC`, `EtatDevis` (`brouillon | envoye | accepte | refuse | expire | annule`), entité `Avenant`. Numérotation séquentielle attribuée à la finalisation (`DEV-{yyyy}-{seq}`). Génération brouillon depuis Lots du chantier (lignes auto pour modes `SURFACE`/`FORFAIT`, lignes vides pré-remplies pour `TEMPS`).
- **8.3 Domaine + persistance Facture** (2-3 j) : entité `Facture`, `LigneFacture`, `EtatFacture` (`brouillon | emise | annulee`), génération brouillon depuis Devis accepté + `Materiau.UTILISE` + `Pointage`. Séquence `FAC-{yyyy}-{seq}`.
- **8.4 Génération PDF** (2-3 j) : worker Symfony Messenger, template avec mentions légales depuis ProfilPro, watermark « Document de travail » visible (V1, retiré V2). Upload vers R2 avec URLs pré-signées TTL court.
- **8.5 Mobile** (4-5 j) : UI complète création/édition Devis et Facture (édition lignes, taux TVA, totaux auto, ajout avenant, partage PDF). Pas une UI de lecture — vraie édition.
- **8.6 Web** (3-4 j) : édition pointue (logo upload, mentions personnalisées, conditions paiement), envoi par email avec lien magique.

**Critère de sortie** : un artisan, depuis mobile chez un client, génère un devis depuis les lots du chantier, l'édite, finalise en PDF avec numéro séquentiel et mentions légales, et l'envoie par email — le tout sans toucher au web.

---

## Phase 9 — Bêta payante V1 (en parallèle dès Phase 6)

**Validation du critère** acté en [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md). Cette phase démarre en parallèle de Phase 6 (recrutement testeurs, landing, juridique) et culmine en septembre 2026 par l'onboarding effectif.

### Sous-étapes

- **9.1 Landing + waitlist** (2-3 j) : page Next.js séparée, formulaire d'attente, positionnement « carnet de chantier » (pas « secrétaire vocal », pas d'IA mentionnée — cf [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)).
- **9.2 Juridique** (~1-2 sem) : CGU / CGV / DPA chez avocat (~1.5-2.5 k€), clauses spécifiques non-conformité PDP, mention assurance pro.
- **9.3 Pricing acté** : freemium (tier gratuit limité 1-2 chantiers actifs, photos limitées, pas de logo personnalisé) + abonnement 20-30 €/mois HT (acté avant ouverture bêta payante — vision Notion avril).
- **9.4 Recrutement** (~2-4 sem en // des phases précédentes) : 5 artisans bêta dont **au moins 2 hors réseau bigouden direct** (mitigation faux positif politesse, cf [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)). Réseau bigouden + Toulouse + appel ouvert via réseau pro.
- **9.5 Onboarding manuel** (1 par 1 en visio) puis 3-4 semaines d'usage réel.
- **9.6 Évaluation critère** : interviews verbatim + observation terrain. Conditions cumulatives :
  - **3/5** déclarent gagner du temps **en saisie manuelle**
  - **2/5** acceptent abonnement payant symbolique (10-15 €/mois minimum)
  - Pas de feedback récurrent « la magie LLM aurait sauvé ce truc »

**Critère de sortie** : si critère levé → V1.2 priorisée (magie LLM). Sinon → rétro avant V1.1, le socle est revu.

---

## Phases 10+ — Trajectoire post-V1 (esquissée)

À détailler en fin de Phase 9 selon le retour bêta.

- **V1.1 — UX fluidifiée** (~2-3 sem) : templates de tâches récurrentes, raccourcis (réutiliser dernier matériau), Whisper local pour transcription brute offline (sans LLM structurant, cf [ADR 0017](docs/adr/0017-differer-ia-validation-manuelle.md)), rappels locaux enrichis. Pas de magie LLM.
- **V1.2 — Magie LLM** (~4-6 sem, conditionnée au critère 9.6) : nouvel ADR sur stack LLM (Whisper API vs local, Claude vs OpenAI, schémas structurés). Structuration vocale, récap auto journée, descriptifs générés. Chrono auto géofencé. Premier coût variable LLM 5-10 €/mois/utilisateur (intégré au pricing).
- **V1.3 — Intelligence personnelle** (~3-4 sem) : planning kanban, vue calendrier, stats personnelles, détection chantiers à risque (depuis historique), stock van + liste de courses.
- **V2** : AR comme `Source: AR` sur entité `Mesure` existante (pas de refacto), client dans l'expérience (signature, portail), partenariat / intégration PDP (Pennylane, Sellsy, ou Chorus Pro direct), catalogue produits visualisable AR (partenariats fabricants).

---

## Règles d'hygiène à tenir tout du long

1. Un commit = une intention claire (Conventional Commits)
2. Pas de feature sans test (sur le domaine au minimum)
3. L'ADR avant le code pour toute décision non triviale
4. Déploiement continu dès Phase 0 (zéro deploy manuel)
5. Revue régulière du `CLAUDE.md` à chaque fin de phase
6. Un bounded context à la fois (pas de mélange)
7. Démo hebdo perso (vendredi)
