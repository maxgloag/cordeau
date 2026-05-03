# CLAUDE.md — apps/mobile (Expo SDK 54 + React Native 0.81)

## Stack

Node 24 · Expo SDK 54 · React Native 0.81 · TypeScript strict · Jest 29 + jest-expo · @testing-library/react-native

**À ajouter progressivement** :
- Phase 1 : expo-router (navigation multi-écrans), expo-secure-store (tokens)
- Phase 3 : expo-sqlite + Drizzle ORM (offline-first)
- Phase 4 : expo-auth-session (OAuth)
- Phase 7 : module natif ARKit/ARCore custom

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
