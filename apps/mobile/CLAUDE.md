# CLAUDE.md — apps/mobile (Expo SDK 54 + React Native 0.81)

## Stack

Node 24 · Expo SDK 54 · React Native 0.81 · TypeScript strict · Jest 29 + jest-expo · @testing-library/react-native

**Ajouté en Phase 1** : expo-router, expo-secure-store, NativeWind, TanStack Query, react-hook-form + zod
**Ajouté en Phase 3** : expo-sqlite + Drizzle ORM, expo-network, expo-crypto, outbox pattern

**Ajouté en Phase 4** : expo-auth-session + expo-web-browser, Google Sign-In

**À ajouter progressivement** :

- Phase 5 : expo-image-picker, expo-camera, upload R2
- Phase 7 : module natif ARKit/ARCore custom

## Offline-first (Phase 3)

- **Source de vérité locale** : SQLite via Drizzle (`db/schema.ts`, `db/queries.ts`)
- **Pattern d'écriture** : `useOfflineMutation` — optimistic update SQLite + queryClient cache, push outbox, `processOutbox` fire-and-forget
- **Pattern de lecture** : queryFn hybride — lit SQLite synchrone, déclenche refresh API en background via `setQueryData` (jamais `invalidateQueries` dans un queryFn)
- **Sync worker** : `useSyncWorker` monté dans `_layout.tsx`, déclenche sur reconnect + AppState foreground (pas de polling)
- **Détails** : cf [ADR 0012](../../docs/adr/0012-offline-first-query-pattern.md)

## Commandes

```bash
pnpm dev           # expo start (choix simulateur/device)
pnpm ios           # lance sur simulateur iOS
pnpm android       # lance sur émulateur Android
pnpm test          # jest --passWithNoTests
pnpm lint          # tsc --noEmit
pnpm type-check    # tsc --noEmit

# EAS (CI/CD mobile)
eas build --profile preview --platform android  # build APK preview
eas build --profile preview --platform ios       # build iOS preview (après Apple Dev)
eas build --profile production --platform all    # build prod
```

## Dev sur device physique

1. `pnpm dev` → QR code
2. Installer **Expo Go** sur le device (iOS App Store / Google Play)
3. Scanner le QR code
4. Pour les modules natifs custom (Phase 7 AR) → Dev Client, pas Expo Go

## Connexion à l'API

- Dev local iPhone physique : utiliser l'IP de ta machine, pas `localhost`
  ```
  EXPO_PUBLIC_API_URL=http://192.168.1.X:8000
  ```
- Dev sur simulateur macOS : `http://localhost:8000` fonctionne
- Variable dans `.env` : `EXPO_PUBLIC_API_URL=http://localhost:8000`
- Accès dans le code : `process.env['EXPO_PUBLIC_API_URL']` (pas de destructuring)

## Variables d'environnement

- `.env` : valeurs par défaut committées
- `.env.local` : valeurs dev locales — gitignored (API_URL, Google client IDs, Sentry DSN)
- `.env.test.local` : valeurs pour les tests — gitignored
- En prod, les variables sont injectées via EAS Secrets ou `eas.json` (profils non-sensibles)
- Toutes les variables doivent être préfixées `EXPO_PUBLIC_` pour être accessibles côté client
- Accès dans le code : `process.env['EXPO_PUBLIC_VAR']` (pas de destructuring)

**Setup initial** : consulter [docs/ENV-SETUP.md](../../docs/ENV-SETUP.md).

## Conventions

- TypeScript strict (`tsconfig.json` généré par Expo, vérifier que `strict: true`)
- Pas de StyleSheet en ligne dans les composants → fichier de styles séparé ou `StyleSheet.create`
- Identifiants métier en français (`Chantier`, `Client`, `Devis`)
- Hooks personnalisés pour la logique métier (pas dans les composants de présentation)

## Tests

- Jest 29 + jest-expo 54 + @testing-library/react-native
- `jest@29` obligatoire pour la compatibilité avec Expo SDK 54 (jest@30 incompatible)
- Mocker `global.fetch` via `jest.fn().mockResolvedValue(...)`
- Tests dans `__tests__/` ou `src/**/*.test.tsx`

## NativeWind (Tailwind pour React Native)

À ajouter Phase 1 pour le styling cohérent avec le web. Setup :

```bash
pnpm add nativewind tailwindcss
pnpm add -D babel-plugin-nativewind
```

Puis configurer `babel.config.js` et `metro.config.js`.

## EAS (Expo Application Services)

- Builds dans le cloud (free tier : 30 builds/mois)
- `eas.json` à créer à l'init EAS : profiles `development`, `preview`, `production`
- iOS : Apple Developer Program requis (99$/an) — différé au 1er user payant
- Android APK direct (sideloading) en bêta, Google Play Console ($25 one-shot) avant lancement public
