# ADR 0022 — Hoisting pnpm pour le toolchain Metro (monorepo)

- **Status** : Accepted
- **Date** : 2026-06-10
- **Deciders** : Maxime
- **Lié à** : [ADR 0019](0019-durcissement-ci-cd.md) (le job `mobile` doit attraper les erreurs de bundling), `apps/mobile/CLAUDE.md` (stack Expo)

## Context

La migration Expo SDK 54 → 56 (RN 0.85, cf commits `c03fb09` / `ef40b7b`) a déclenché une cascade d'erreurs de bundling Metro, jamais à l'exécution mais toujours pendant le `expo start` / `expo export` :

1. `Cannot find module '@babel/traverse--for-generate-function-map'`
2. `TypeError: fileMap.setMaxListeners is not a function`
3. `Cannot read properties of undefined (reading 'getPackage')`
4. `TypeError: depGraph.doesFileExist is not a function`

Deux contournements manuels ont été tentés (déclaration explicite de l'alias npm `@babel/traverse--for-generate-function-map` ; patch de `node_modules/@expo/cli/.../createFileMap-fork.js` réappliqué via un script `postinstall`). Chaque patch ne faisait que **déplacer le point de rupture** vers l'erreur suivante — signature d'un problème structurel, pas d'une suite de bugs isolés (cf protocole de debugging systématique, phase « 3+ fixes → questionner l'architecture »).

**Cause racine.** pnpm installe en mode `node-linker=isolated` par défaut (chaque paquet dans `node_modules/.pnpm`, liens symboliques, pas de hoisting plat). Or le toolchain Metro de SDK 56 suppose un `node_modules` plat. Conséquence observée : **deux versions de `metro` core coexistent** — `metro@0.84.4` hoisté à la racine et `metro@0.83.3` vendoré dans `@expo/metro@56.0.0/node_modules/metro`. Le fork `@expo/metro` mélange alors des objets `DependencyGraph` / `FileMap` issus de versions différentes, d'où les méthodes manquantes (`setMaxListeners`, `doesFileExist`, `getPackage`).

S'ajoute un second désalignement : l'override pnpm racine épinglait `react@19.1.0` pour tout le monorepo, alors que RN 0.85 / SDK 56 épingle `react@19.2.3` (`expo/bundledNativeModules.json`). La [doc Expo monorepos](https://docs.expo.dev/guides/monorepos/) avertit explicitement : _« Duplicate React versions in a single app will cause runtime errors »_.

La [doc Expo](https://docs.expo.dev/guides/monorepos/) documente la correction de référence : _« because React Native tooling expects a flat `node_modules` structure, switching to the hoisted node linker makes the setup behave more predictably »_.

## Decision

On adopte la configuration officielle Expo pour les monorepos pnpm, plutôt que d'accumuler des patchs dans `node_modules`.

1. **`.npmrc` racine** :

   ```
   node-linker=hoisted
   public-hoist-pattern[]=*expo*
   public-hoist-pattern[]=*react-native*
   public-hoist-pattern[]=@react-native/*
   public-hoist-pattern[]=metro*
   ```

   `node-linker=hoisted` aplatit `node_modules` (comme npm/yarn classic), garantissant **une seule** version de `metro` résolue de façon cohérente. Les `public-hoist-pattern` renforcent la remontée des paquets du toolchain RN/Expo.

2. **Override React aligné sur SDK 56** : `react` / `react-dom` passent de `19.1.0` à `19.2.3` (version épinglée par `bundledNativeModules`). Le web (`react: ^19.1.0`) accepte cette montée mineure sans changement. `@types/react` / `@types/react-dom` passent en `^19.2.0`.

3. **Suppression des contournements manuels** introduits par le commit `5aff929` :
   - le `postinstall` racine et `scripts/patch-expo-cli.sh` (patch de `@expo/cli`),
   - la dépendance-alias `@babel/traverse--for-generate-function-map` dans `apps/mobile/package.json`.

   Le hoisting rend ces patchs inutiles : l'alias npm se résout naturellement et `createFileMap-fork.js` reçoit la bonne version de `metro`.

## Consequences

**Positives**

- L'environnement de bundling mobile redevient fonctionnel (`expo start`, `expo export`) sans modification de `node_modules`.
- Plus aucun patch custom dans `node_modules` : surface de maintenance réduite, robuste aux `pnpm install` et aux bumps Dependabot.
- Configuration alignée sur la recommandation officielle Expo — pas un hack.

**Négatives / vigilance**

- `node-linker=hoisted` change la stratégie d'install de **tout le monorepo**, web compris. Le web a été re-testé (type-check + build) après bascule. L'API (Composer/PHP) n'est pas concernée.
- Le hoisting plat masque les dépendances fantômes (un paquet peut importer une dépendance non déclarée qui se trouve hoistée). Le `type-check` et la CI restent les garde-fous.
- Au prochain upgrade SDK, vérifier que les `public-hoist-pattern` couvrent toujours les paquets du toolchain.

**Implications sécurité.** La surface **diminue** : on retire un script `postinstall` qui réécrivait du code tiers dans `node_modules` (vecteur d'exécution de code à l'install). Aucune nouvelle dépendance externe, aucun secret ni donnée personnelle touchés.

**Lien CI.** Le job `mobile` exécute déjà un vrai bundling check (`expo export` iOS + Android, ajouté par l'[ADR 0019](0019-durcissement-ci-cd.md)). Ce garde-fou avait été **neutralisé** par le `postinstall` patch (commit `5aff929`), qui s'appliquait aussi en CI et masquait le toolchain cassé. La suppression du patch lui rend sa capacité de détection ; la correction par hoisting est désormais validée par ce même check.
