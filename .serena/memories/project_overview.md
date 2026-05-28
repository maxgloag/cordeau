# Cordeau — vue d'ensemble

SaaS mobile-first de gestion d'activité pour artisans du bâtiment indépendants (auto-entrepreneurs, TPE 1-5 personnes) en France.

**Différenciateurs V1** (repositionnement acté 2026-05-24, cf ADR 0015/0016/0017) : modèle Chantier/Lots/Tâches/Mesures pensé pour l'artisan, capture terrain sans friction, offline-first, double interface mobile + web. **Mesure AR repoussée V2**. **IA différée V1.2+** (validation manuelle d'abord).

## Monorepo (Turborepo + pnpm)

```
apps/
  api/        Symfony 7.4 + API Platform 4 + PHP 8.5 + PostgreSQL 18 + Redis 8
  web/        Vite 8 + React 19 + TanStack Router/Query + Tailwind v4 + shadcn/ui
  mobile/     Expo SDK 54 + expo-router + NativeWind + expo-sqlite/Drizzle
packages/
  shared/     Types TypeScript partagés (générés via openapi-typescript)
docs/
  adr/        Architecture Decision Records (0001 à 0017)
scripts/      Outils dev (ci-watch.sh, etc.)
.serena/      Config Serena (project.yml + memories versionnées)
.claude/      Hooks et settings Claude Code
```

## État roadmap (post-repositionnement 2026-05-24)

- **Phase 0 — Fondations** ✅ terminée
- **Phase 1 — Verticale Chantiers** ✅ terminée
- **Phase 2 — Verticale Clients** ✅ terminée (vélocité ×5 vs Phase 1)
- **Phase 3 — Offline-first mobile** ✅ terminée (ADR 0012)
- **Phase 4 — OAuth Google** ✅ terminée (ADR 0013)
- **Phase 5 — Photos + R2** à démarrer
- **Phase 6 — Verticale Lots / Tâches** (modèle ADR 0015)
- **Phase 7 — Capture terrain + Métré manuel**
- **Phase 8 — Devis + Facture brouillon** (mobile-first, pré-documents non conformes PDP — ADR 0016)
- **Phase 9 — Bêta payante V1** (validation critère ADR 0017 — porte d'entrée V1.2+)
- **Phases 10+** : V1.1 (UX fluidifiée) / V1.2 (magie LLM si critère bêta levé) / V1.3 / V2

Voir [ROADMAP.md](../../ROADMAP.md).

## Bounded contexts (présents ou planifiés)

- `chantier` (livré Phase 1, étendu Phase 6 avec Lot/Tâche, Phase 7 avec Materiau/Mesure/Pointage)
- `client` (livré Phase 2)
- `auth` (livré Phase 1 + OAuth Phase 4)
- `photo` (Phase 5, contextualisation Phase 7)
- `devis` / `facture` (Phase 8, pré-documents non conformes PDP V1)
- ~~`metre-ar`~~ → fonction métré intégrée au BC `chantier` via entité `Mesure` (source MANUEL V1, AR V2 sans refacto)

## Sources canoniques de vérité

- **Conventions** : `CLAUDE.md` racine + un par app (`apps/*/CLAUDE.md`)
- **Décisions techniques** : `docs/adr/`
- **Roadmap technique** : `ROADMAP.md`
- **Vision business** : Notion workspace Cordeau (lecture seule pour Serena)

## Déploiement

- API → Fly.io région Paris (`cdg`) + Neon Postgres Frankfurt
- Web → Cloudflare Pages
- Mobile → EAS preview (Android d'abord, iOS plus tard)
- Sentry sur les 3 apps
