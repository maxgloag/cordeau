# Cordeau — vue d'ensemble

SaaS mobile-first de gestion d'activité pour artisans du bâtiment indépendants (auto-entrepreneurs, TPE 1-5 personnes) en France. Différenciateurs : mesure AR (ARKit), offline-first, double interface mobile + web.

## Monorepo (Turborepo + pnpm)

```
apps/
  api/        Symfony 7.4 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8
  web/        Vite 8 + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui
  mobile/     Expo SDK 54 + expo-router + NativeWind + expo-sqlite/Drizzle
packages/
  shared/     Types TypeScript partagés (générés via openapi-typescript)
docs/
  adr/        Architecture Decision Records (0001 à 0009)
scripts/      Outils dev (ci-watch.sh, etc.)
.serena/      Config Serena (project.yml + memories versionnées)
.claude/      Hooks et settings Claude Code
```

## État roadmap

- **Phase 0 — Fondations** ✅ terminée (mai 2026)
- **Phase 1 — Verticale Chantiers** ✅ terminée (mai 2026, issues #1 → #5)
- **Phase 2 — Verticale Clients** 🟡 en cours (cible juin 2026)
- Phases suivantes : Offline-first, OAuth, Photos R2, Devis, AR mesure — voir ROADMAP.md

## Bounded contexts (présents ou planifiés)

- `chantier` (livré Phase 1)
- `client` (Phase 2)
- `auth` (livré Phase 1, à étendre OAuth en Phase 4)
- `photo` (Phase 5)
- `devis` (Phase 6)
- `metre-ar` (Phase 7)

## Sources canoniques de vérité

- **Conventions** : `CLAUDE.md` racine + un par app (`apps/*/CLAUDE.md`)
- **Décisions techniques** : `docs/adr/`
- **Roadmap technique** : `ROADMAP.md`
- **Vision business** : Notion workspace Cordeau (lecture seule pour Serena)

## Déploiement

- API → Fly.io région Paris (`cdg`) + Neon Postgres Frankfurt
- Web → Cloudflare Pages
- Mobile → EAS preview (Android d'abord, iOS Phase 7)
- Sentry sur les 3 apps
