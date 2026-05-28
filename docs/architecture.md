# Architecture Cordeau — vue d'ensemble

> **Document vivant.** Contrairement aux [ADRs](adr/README.md) (décisions datées et immuables), ce fichier décrit l'**état courant** de l'architecture et évolue avec le code. À mettre à jour en fin de phase, au même rythme que `CLAUDE.md` et les memories Serena.
>
> Convention de diagrammes : [ADR 0018](adr/0018-documentation-architecture-as-code.md). Les diagrammes suivent le [modèle C4](https://c4model.com/) (Context → Container → Component) en Mermaid `flowchart` versionné. Ils se rendent nativement dans GitHub et Notion.

Dernière mise à jour : 2026-05-28 (fin Phase 4, avant Phase 5 Photos/R2).

---

## Niveau 1 — Contexte système

Qui utilise Cordeau et avec quels systèmes externes le produit dialogue.

```mermaid
flowchart TB
    artisan["👤 Artisan du bâtiment<br/>(auto-entrepreneur, TPE 1-5)<br/>Capture terrain + suivi d'activité"]

    subgraph cordeau_sys [" "]
        cordeau["🟦 Cordeau<br/>SaaS de gestion d'activité<br/>mobile-first, offline-first"]
    end

    google["Google OAuth 2.0<br/>Authentification déléguée"]
    r2["Cloudflare R2<br/>Stockage photos (Phase 5)"]
    neon["Neon<br/>PostgreSQL managé (EU)"]
    sentry["Sentry<br/>Suivi d'erreurs"]

    artisan -->|"Saisit chantiers, clients,<br/>photos terrain"| cordeau
    cordeau -->|"Délègue le login<br/>(id_token / code)"| google
    cordeau -->|"Upload direct via<br/>URL pré-signée"| r2
    cordeau -->|"Persiste les données"| neon
    cordeau -->|"Remonte les erreurs"| sentry

    classDef person fill:#08427b,stroke:#052e56,color:#fff
    classDef system fill:#1168bd,stroke:#0b4884,color:#fff
    classDef external fill:#999,stroke:#6b6b6b,color:#fff
    classDef boundary fill:none,stroke:none

    class artisan person
    class cordeau system
    class google,r2,neon,sentry external
    class cordeau_sys boundary
```

---

## Niveau 2 — Conteneurs

Les briques déployables qui composent Cordeau et leurs protocoles de communication.

```mermaid
flowchart TB
    artisan["👤 Artisan"]

    subgraph cordeau [" Cordeau "]
        direction TB
        web["🖥️ Web back-office<br/>React 19 + Vite + TanStack<br/>Cloudflare Pages"]
        mobile["📱 Mobile<br/>Expo SDK 54 + NativeWind<br/>offline-first"]
        api["⚙️ API<br/>Symfony 7 + API Platform 4<br/>Fly.io (cdg)"]
        sqlite[("💾 SQLite locale<br/>expo-sqlite + Drizzle<br/>+ outbox de sync")]
        shared{{"📦 @cordeau/shared<br/>Types OpenAPI générés<br/>(contrat build-time)"}}
    end

    pg[("🐘 PostgreSQL 18<br/>Neon")]
    redis[("🔴 Redis 8<br/>Messenger / cache")]
    google["Google OAuth"]
    r2["Cloudflare R2"]

    artisan -->|"HTTPS"| web
    artisan -->|"App native"| mobile

    web -->|"REST + Bearer token<br/>(TanStack Query)"| api
    mobile -->|"REST + Bearer token<br/>(sync différée)"| api
    mobile -->|"Lit/écrit en local<br/>(Drizzle)"| sqlite
    sqlite -.->|"Outbox rejouée<br/>à la reconnexion"| api

    web -.->|"types importés"| shared
    mobile -.->|"types importés"| shared
    api -.->|"génère l'OpenAPI<br/>→ openapi-typescript"| shared

    api -->|"Doctrine ORM"| pg
    api -->|"transport / cache"| redis
    api -->|"échange id_token /<br/>code, validation audience"| google
    mobile -.->|"upload direct<br/>URL pré-signée (Phase 5)"| r2

    classDef person fill:#08427b,stroke:#052e56,color:#fff
    classDef container fill:#1168bd,stroke:#0b4884,color:#fff
    classDef store fill:#2e7d32,stroke:#1b4d20,color:#fff
    classDef external fill:#999,stroke:#6b6b6b,color:#fff
    classDef boundary fill:#f5f7fa,stroke:#c4ccd6,color:#333

    class artisan person
    class web,mobile,api,shared container
    class sqlite,pg,redis store
    class google,r2 external
    class cordeau boundary
```

> **Contrat de type partagé** : l'API est la source de vérité du schéma. `bin/console api:openapi:export` produit l'OpenAPI, `openapi-typescript` le transforme en `api.generated.ts`, importé par web et mobile. Toute dérive de contrat casse le `type-check` côté clients — c'est volontaire.

---

## Niveau 3 — Composants de l'API (bounded contexts)

L'API applique une [architecture hexagonale pragmatique](adr/0002-architecture-hexagonale.md) : rigueur sur les contextes complexes, légèreté sur les CRUD simples.

```mermaid
flowchart TB
    subgraph presentation ["Presentation — API Platform"]
        res_auth["Auth endpoints<br/>/auth/login, /register,<br/>/refresh, /me, /oauth/*"]
        res_chantier["Chantier resources<br/>Providers / Processors"]
        res_client["Client resources<br/>Providers / Processors"]
    end

    subgraph auth ["Auth — léger + sécurité"]
        user["User (entité Doctrine)"]
        token["AuthToken<br/>selector + verifier"]
        oauth["OAuthAccount<br/>auto-link par email"]
    end

    subgraph chantier ["Chantier — hexagonal (référence)"]
        direction TB
        ch_dom["Domain<br/>Chantier, StatutChantier,<br/>ClientRef (VO dénormalisé)"]
        ch_uc["Application / UseCase<br/>Creer · Lister · Obtenir<br/>Modifier · Archiver"]
        ch_port["Repository (port / interface)"]
        ch_adapter["Infrastructure<br/>adapter Doctrine"]
    end

    subgraph client ["Client — CRUD léger"]
        cl["Entité = modèle métier<br/>(pas de use case dédié)"]
    end

    pg[("PostgreSQL")]

    res_auth --> user & token & oauth
    res_chantier --> ch_uc
    res_client --> cl

    ch_uc --> ch_port
    ch_uc --> ch_dom
    ch_port -.->|implémenté par| ch_adapter
    ch_adapter --> pg
    user & token & oauth --> pg
    cl --> pg

    classDef pres fill:#85bbf0,stroke:#5d82a8,color:#000
    classDef domain fill:#1168bd,stroke:#0b4884,color:#fff
    classDef store fill:#2e7d32,stroke:#1b4d20,color:#fff

    class res_auth,res_chantier,res_client pres
    class user,token,oauth,ch_dom,ch_uc,ch_port,ch_adapter,cl domain
    class pg store
```

> **Règle de dépendance** : les flèches pointent vers l'intérieur. `Domain/` n'importe ni Doctrine ni Symfony. Cette règle est **vérifiée mécaniquement par Deptrac** en CI ([`apps/api/deptrac.yaml`](../apps/api/deptrac.yaml)) : toute fuite framework dans `Domain/`/`Application/`/`Shared/` casse le build — cf [ADR 0018](adr/0018-documentation-architecture-as-code.md). Les CRUD légers (Auth, Client) sont hors périmètre par design.

---

## Niveau 3 — Flux offline-first (mobile)

Le différenciateur V1 : la saisie terrain ne dépend jamais du réseau. Voir [ADR 0005](adr/0005-offline-first-sqlite-drizzle.md) et [ADR 0012](adr/0012-offline-first-query-pattern.md).

```mermaid
sequenceDiagram
    actor U as Artisan
    participant UI as Écran (RN)
    participant Q as TanStack Query
    participant DB as SQLite (Drizzle)
    participant OB as Outbox
    participant SW as Sync Worker
    participant API as API Symfony

    U->>UI: Crée un chantier (hors-ligne)
    UI->>DB: Écrit en local (optimistic)
    UI->>Q: setQueryData (cache immédiat)
    UI->>OB: Enqueue mutation (status=pending)
    Note over UI,U: L'écran répond instantanément

    Note over SW: Reconnexion réseau / app au premier plan
    SW->>OB: Lit les mutations pending
    SW->>API: Rejoue (POST/PATCH) avec backoff
    API-->>SW: 2xx
    SW->>OB: Marque synced
    SW->>DB: Met à jour syncedAt
```

---

## Maturité par conteneur (Phase 4)

| Conteneur | Statut | Périmètre actuel |
|---|---|---|
| **API** | MVP | Auth (email + Google OAuth), Chantier (hexagonal complet), Client (CRUD léger), déployée Fly.io + Neon |
| **Web** | MVP | Login/register, CRUD chantiers + clients, garde de route, Sentry, Cloudflare Pages |
| **Mobile** | MVP + offline | Login/register, CRUD chantiers + clients, SQLite + outbox + sync worker, Google Sign-In, EAS |
| **Shared** | Outillage | Génération OpenAPI → TS opérationnelle |

Prochaines verticales (cf [ROADMAP.md](../ROADMAP.md)) : Phase 5 Photos/R2, Phase 6 Lots/Tâches, Phase 8 Devis/Facture.

---

## Comment mettre à jour ce document

1. Les diagrammes sont du Mermaid inline — éditer le bloc, prévisualiser dans l'aperçu Markdown de GitHub.
2. À chaque **fin de phase** : ajouter les nouveaux conteneurs/contextes, déplacer les éléments « (Phase N) » de prévision vers réel, dater l'en-tête.
3. Décision structurante derrière un changement de diagramme → un ADR **avant** la mise à jour ici. Ce document reflète, il ne décide pas.
