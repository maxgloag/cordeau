# 0006 — Vitest Browser Mode + Playwright pour les tests UI React

- **Status** : Accepted
- **Date** : 2026-05-04
- **Deciders** : Maxime
- **Supersedes** : choix initial implicite « Vitest + happy-dom » mentionné dans le plan Phase 0

## Context

Le `apps/web` est en React 19 (RC stabilisée) sur TypeScript 6 strict. Le plan initial prévoyait d'utiliser Vitest avec un environnement DOM simulé (`happy-dom` ou `jsdom`) — l'approche standard depuis 5 ans sur la stack Vite.

Pendant le scaffolding du Lot 2, le test minimal de la page santé est tombé en CI avec un comportement différent du local :

- `happy-dom` sur Linux/Ubuntu CI : `Cannot read properties of null (reading 'useEffect')` — incompatibilité de résolution de modules entre `react@19` et happy-dom dans pnpm
- `jsdom` sur Linux/Ubuntu CI : le composant rend `<body><div /></body>` vide, même pour un composant pur sans hook. Le rendu fonctionne sur macOS local mais pas sur Linux CI
- Aucun de ces problèmes n'est reproductible en local sur macOS (faux sentiment de sécurité)

Trois options ont été évaluées :

1. **Continuer avec jsdom + workarounds** : downgrade React 19 → 18, ou patch-package, ou wrapper `act()` manuel. Coûteux à maintenir, fragile, et React 19 est un choix structurant non négociable
2. **Renoncer aux tests de composants** et ne tester que la logique pure. Acceptable au début mais bloque dès qu'on a des hooks custom et des interactions UI complexes (Phase 1+)
3. **Vitest Browser Mode + Playwright** : exécute les tests dans un vrai Chromium headless. Pas de simulation de DOM, donc rien à patcher

## Decision

Adopter **Vitest 4 Browser Mode + Playwright (Chromium headless)** comme runner de tests UI, via `vitest-browser-react` pour l'API `render()`. La config vit dans `apps/web/vitest.config.ts` (séparée de `vite.config.ts` pour clarifier que c'est une config de tests, pas de build).

Deux **projets Vitest** distincts :

- `unit` (env `node`) : pour la logique pure (`*.test.ts`). Aucun DOM. Démarre en quelques ms
- `browser` (provider Playwright) : pour les composants React (`*.test.tsx`). Démarre un Chromium headless réel

Côté CI, ajout d'un step `playwright install --with-deps chromium` avant le `pnpm test` du job `web`. Le binaire est mis en cache via le store pnpm.

## Consequences

**Bénéfices** :

- Aucune divergence entre l'environnement de test et l'environnement réel : un test qui passe en CI reflète le comportement utilisateur réel dans un navigateur
- Couvre nativement React 19, Suspense, transitions, IntersectionObserver, ResizeObserver, animations CSS, focus management — toutes choses où jsdom ment
- Pas besoin de monkey-patcher React, ni de manipuler `IS_REACT_ACT_ENVIRONMENT`, ni de wrapper `act()` à la main
- API d'assertion plus robuste : `expect.element(...).toBeVisible()` retry automatiquement et tient compte de la visibilité réelle (display, visibility, viewport)
- Permet de basculer plus tard vers du **Component testing** Playwright pur si on veut, sans réécrire les tests

**Coûts** :

- CI plus lente : ~30s d'install Chromium au premier run (puis cache), ~2-5s par test browser vs <100ms en jsdom. Acceptable tant qu'on garde la logique pure dans le projet `unit`
- Dépendance native (Chromium) à provisionner sur chaque environnement de test — couvert pour CI Ubuntu, à valider si on veut tester sur Windows un jour
- Outil moins mature que jsdom (Vitest Browser Mode est stable mais plus jeune) — accepté parce que Vitest 4 le marque officiellement stable et Playwright est ultra-mainstream

**Trade-offs assumés** :

- Les tests `*.test.tsx` ne sont **pas** conçus pour être lancés à la milliseconde dans un mode watch hyper-réactif type TDD pur. Pour ce besoin, écrire la logique en `.ts` pur testée dans le projet `unit`
- L'API `vitest-browser-react` diffère légèrement de `@testing-library/react` : `render()` retourne une promesse, les locators viennent de l'object `screen` retourné. Court refactor à l'apprentissage, ensuite c'est plus naturel

**Conséquence architecturale** : pour maximiser la testabilité unitaire (plus rapide), on adopte un pattern container/view (voir [ADR 0007](0007-pattern-container-view.md)).
