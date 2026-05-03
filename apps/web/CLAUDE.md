# CLAUDE.md — apps/web (Vite 8 + React 19)

## Stack

Node 24 · Vite 8 · React 19 · TypeScript 6 (strict) · Tailwind v4 + @tailwindcss/vite · TanStack Query v5 · Vitest 4 · @testing-library/react

**À ajouter en Phase 1** : TanStack Router (type-safe, file-based) · shadcn/ui · React Hook Form + Zod

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

## Structure cible (à partir de Phase 1)

```
src/
├── routes/           # pages via TanStack Router (file-based)
│   ├── __root.tsx   # layout racine
│   ├── index.tsx    # route /
│   └── chantiers/   # routes /chantiers/*
├── components/       # composants partagés
│   └── ui/          # composants shadcn/ui (générés, ne pas modifier)
├── lib/
│   ├── api/         # fonctions fetch vers l'API + types depuis @cordeau/shared
│   └── utils/       # utilitaires
├── hooks/           # custom hooks
└── test/            # setup Vitest + helpers de test
```

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

- Framework : Vitest 4 + @testing-library/react + happy-dom
- Tests dans `src/test/` ou `src/**/*.test.tsx`
- `globals: true` dans vitest config (pas besoin d'importer `describe`/`it`)
- Mocker `fetch` via `vi.stubGlobal('fetch', ...)`
- Tester le comportement UI (ce que l'utilisateur voit), pas l'implémentation

## Variables d'environnement

- `VITE_API_URL` : URL de l'API (défaut `http://localhost:8000`)
- En prod : variable injectée par Cloudflare Pages (sans `VITE_` prefix dans les secrets CF)
- Accès dans le code : `import.meta.env['VITE_API_URL']` (pas de déstructuring)
