# CLAUDE.md — apps/web (Vite 8 + React 19)

## Stack

Node 24 · Vite 8 · React 19 · TypeScript 6 (strict) · Tailwind v4 + @tailwindcss/vite · TanStack Query v5 · Vitest 4 (Browser Mode) + Playwright/Chromium · vitest-browser-react

**Ajouté en Phase 1** : TanStack Router v1 (file-based, plugin Vite) · shadcn/ui (Tailwind v4) · React Hook Form v7 + Zod v4

## Commandes

```bash
pnpm dev          # Vite dev server sur http://localhost:5173
pnpm build        # build de production dans dist/
pnpm test         # Vitest (run une fois)
pnpm test:watch   # Vitest en mode watch
pnpm lint         # ESLint + tsc --noEmit
pnpm type-check   # tsc --noEmit seul
pnpm preview      # preview du build prod
```

## Structure (Phase 1)

```
src/
├── routes/              # pages via TanStack Router (file-based, généré dans routeTree.gen.ts)
│   ├── __root.tsx       # layout racine (RouterContext avec QueryClient)
│   ├── index.tsx        # / → redirect login ou dashboard
│   ├── login.tsx        # /login
│   ├── register.tsx     # /register
│   ├── _authed.tsx      # layout protégé (beforeLoad → /login si 401)
│   └── _authed/
│       └── dashboard.tsx # /dashboard
├── pages/               # container + view par page (pattern ADR 0007)
│   ├── login/           # LoginPage (container) + LoginView (vue pure)
│   ├── register/        # RegisterPage + RegisterView
│   └── dashboard/       # DashboardPage + DashboardView + ChantierFormView
├── components/
│   ├── ui/              # composants shadcn/ui (générés, ne pas modifier)
│   └── layout/          # AppShell (sidebar + main)
├── lib/
│   ├── api.ts           # fonctions fetch (auth + chantiers) + types
│   └── utils.ts         # cn() helper
└── test/                # setup Vitest + tests
```

## Conventions importantes

- `src/routeTree.gen.ts` est auto-généré par le plugin Vite TanStack Router — ne pas modifier
- `src/components/ui/` est exclu d'ESLint (fichiers shadcn/ui)
- Views pures : aucune dépendance de routing (pas d'import `@tanstack/react-router`) — testables en Browser Mode sans mock
- `@/` alias configuré dans `vite.config.ts` ET `vitest.config.ts` (les deux configs séparées)

## Conventions TypeScript

- TypeScript 6 strict max : `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`
- `verbatimModuleSyntax` activé — toujours `import type` pour les types purs
- Pas de `any` — si incontournable, `unknown` puis assertion typée
- Identifiants métier en français dans l'UI (`Chantier`, `Client`, `Devis`)

## Tailwind v4

- Import dans CSS : `@import "tailwindcss"` (pas de directives v3)
- Plugin Vite `@tailwindcss/vite` — pas de `tailwind.config.js`
- Tokens custom dans `src/index.css` via `@theme { ... }` si nécessaire
- shadcn/ui sera ajouté Phase 1 : ne pas créer de composants UI from scratch

## TanStack Query

- `QueryClient` configuré dans `main.tsx`, transmis via `QueryClientProvider`
- `staleTime: 30s` par défaut
- Clés de query : tableaux avec contexte explicite `['chantiers', id]`
- Pas de `useEffect` + `fetch` manuel — passer par TanStack Query systématiquement

## Tests

Voir [docs/adr/0006-vitest-browser-mode.md](../../docs/adr/0006-vitest-browser-mode.md) et [docs/adr/0007-pattern-container-view.md](../../docs/adr/0007-pattern-container-view.md).

**Deux projets Vitest** déclarés dans `vitest.config.ts` :

- **`unit`** (environnement `node`) : logique pure, fichiers `*.test.ts`. Pas de DOM, pas de browser. Utilisé pour `src/lib/api.ts`, futurs hooks/utils, services.
- **`browser`** (provider Playwright/Chromium, headless) : composants React, fichiers `*.test.tsx`. Vrai navigateur via `vitest-browser-react`. Pas de jsdom, pas de happy-dom (incompatibles avec React 19 sur CI Linux).

**Conventions** :

- Composants conçus en mode **container/view** : un composant pur (props only) testé isolément en Browser Mode, un container avec hooks/providers monté uniquement en runtime
- API d'assertion : `await expect.element(screen.getByRole(...)).toBeVisible()` (locators auto-retry, pas de `waitFor`)
- Mocker `fetch` via `vi.stubGlobal('fetch', ...)` dans les tests `node`. En Browser Mode, préférer un `MSW` ou un mock de la fonction métier importée
- Identifiants métier en français pour les sélecteurs (`getByRole('heading', { name: /chantiers/i })`)
- Premier run en CI : `playwright install --with-deps chromium` (déjà câblé dans `.github/workflows/ci.yml`)

## Variables d'environnement

- `VITE_API_URL` : URL de l'API (défaut `http://localhost:8000`)
- En prod : variable injectée par Cloudflare Pages (sans `VITE_` prefix dans les secrets CF)
- Accès dans le code : `import.meta.env['VITE_API_URL']` (pas de déstructuring)
