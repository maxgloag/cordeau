# 0007 — Pattern container / view pour la testabilité React

- **Status** : Accepted
- **Date** : 2026-05-04
- **Deciders** : Maxime
- **Lié à** : [ADR 0006 — Vitest Browser Mode](0006-vitest-browser-mode.md)

## Context

Le composant racine de `apps/web` consommait directement `useQuery` de TanStack Query :

```tsx
export default function App() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["health"],
    queryFn: fetchHealth,
  });
  return <main>...</main>;
}
```

Le tester en isolation imposait soit de monter un `QueryClientProvider` dans chaque test, soit de mocker `useQuery`. Les deux approches ajoutent du bruit et couplent la suite de tests à des détails d'implémentation (la lib de data-fetching utilisée, la forme du provider, le client de query utilisé).

C'est un cas trivial maintenant, mais le projet va se peupler de pages avec routing TanStack Router, formulaires React Hook Form, hooks d'auth, providers shadcn/ui. Sans discipline, chaque test de page nécessitera un harnais croissant — friction qui finit par décourager l'écriture de tests.

## Decision

Adopter une **séparation systématique container / view** sur les composants de page :

- **`<Xxx>` (container)** : ne fait que composer les hooks, providers, data-fetching, mutations. Aucun JSX au-delà de `<XxxView ... />`. Pas de logique de rendu conditionnelle non triviale
- **`<XxxView>` (view)** : composant pur, props only. Aucun hook, aucun import de `@tanstack/react-query`, `react-router`, `expo-secure-store`, etc. Reçoit son état (`isLoading`, `data`, `error`, callbacks) via props

Exemple actuel : [apps/web/src/App.tsx](../../apps/web/src/App.tsx) (container) délègue à [apps/web/src/AppView.tsx](../../apps/web/src/AppView.tsx) (view). Seule la view est testée — directement, sans provider.

**Quand appliquer** :

- Toute page (route TanStack Router → composant)
- Tout composant avec ≥2 hooks externes (data-fetching, auth, navigation, form)
- Pas obligatoire pour les composants UI purs (boutons, inputs shadcn/ui), qui sont déjà views par construction

**Quand ne PAS appliquer** :

- Composants triviaux sans hooks (button, badge, layout)
- Composants où la logique d'orchestration est elle-même le sujet du test (alors on monte le container avec MSW pour mocker la couche réseau)

## Consequences

**Bénéfices** :

- Tests de view ultra-simples : un seul harnais (`render(<AppView ...props />)`), pas de provider, pas de mock de hook. Lecture directe du contrat du composant via ses props
- Forçage d'un design discipliné : la liste des props d'une view est la spec de ce qu'elle peut afficher. Les états oubliés (loading, error, empty) sautent aux yeux
- Permet de prévisualiser une view dans Storybook (futur) sans rien monter d'autre — les props suffisent
- Autorise à garder la couverture des composants en Browser Mode rapide : pas de cascade de providers à monter à chaque test

**Coûts** :

- Un fichier de plus par page (`Xxx.tsx` + `XxxView.tsx`). Friction marginale, compensée par la lisibilité accrue
- Tentation de fuiter de la logique dans la view "juste pour cette fois" — à surveiller en revue
- Pour les pages très simples (statiques), le container devient une coquille triviale. Acceptable, c'est le prix de la cohérence

**Trade-off assumé** : on n'utilise **pas** de pattern "hook custom dédié" (`useChantierPage()`) à la place. Raison : l'hook custom déplace le couplage sans le réduire (les tests doivent encore monter `QueryClientProvider`). La séparation container/view, elle, **élimine** le couplage côté view.

**Convention de nommage** : `<Xxx>` (container, default export) et `<XxxView>` (view, named export). Container et view dans le même dossier. Tests sur la view, pas sur le container — sauf si l'orchestration est non triviale.
