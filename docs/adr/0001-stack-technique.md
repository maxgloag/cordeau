# ADR 0001 — Stack technique globale

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

Cordeau est un SaaS de gestion d'activité pour artisans du bâtiment. Il nécessite trois surfaces : une API (logique métier, persistance), un back-office web (gestion au bureau), et une app mobile (terrain, offline). Le projet est développé en solo avec une partenaire non-tech impliquée sur la partie produit.

Contraintes structurantes :
- Budget zéro jusqu'aux premiers utilisateurs payants
- Dev solo → minimiser la surface de compétences à maîtriser
- Capitaliser sur l'expérience Symfony existante (Printoclock)
- Déploiement sur Oracle Cloud Free Tier (Always Free)

## Decision

**Monorepo** Turborepo + pnpm avec trois apps et un package partagé :

| Surface | Stack |
|---|---|
| API | Symfony 7.4 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8 |
| Web (back-office) | Vite 8 + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui |
| Mobile | Expo SDK 54+ + React Native + expo-router + NativeWind |
| Shared | `@cordeau/shared` — types TypeScript partagés (générés depuis OpenAPI) |

**Infra MVP** : Oracle Cloud Free Tier (ARM 24 Go) + Coolify pour l'API, Cloudflare Pages pour le web, EAS pour le mobile.

## Consequences

**Bénéfices** :
- Symfony capitalise l'expérience Printoclock — montée en compétence pro et perso mutualisée
- API Platform génère l'OpenAPI gratuitement → types TS via openapi-typescript dans `@cordeau/shared`
- Un seul codebase TypeScript/React pour web et mobile (même patterns, même libs)
- Infra MVP à ~1€/mois (Oracle Free + Cloudflare)

**Trade-offs** :
- Pas de partage de types automatique entre PHP et TypeScript → compensé par openapi-typescript
- Oracle Cloud Free Tier : pas de SLA, backups à gérer manuellement, pas de scaling horizontal → acceptable jusqu'à 5-10 users
- Expo n'est pas du natif pur → performances AR limitées sans module natif custom (ARKit via Expo Config Plugin)

**Risques résiduels** :
- Oracle Cloud peut changer ses conditions "Always Free" (low risk, historiquement stable)
- Expo peut introduire des breaking changes majeurs (mitigé par le verrouillage des versions dans `package.json`)
