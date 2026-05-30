# ADR 0005 — Offline-first sur mobile (SQLite + Drizzle ORM + Outbox pattern)

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

Les artisans travaillent dans des conditions de réseau dégradé (sous-sols, zones rurales, cages d'escalier, tunnels). Une app qui plante sans réseau n'est pas utilisable sur le terrain. C'est un différenciateur critique face à la concurrence.

**Exigence** : toutes les actions de création/modification doivent être possibles hors-ligne et se synchroniser silencieusement au retour de la connexion, sans perte de données.

## Decision

**SQLite local via expo-sqlite + Drizzle ORM + Outbox pattern** pour les mutations, avec **"last write wins"** pour la résolution de conflits (Phase 3).

Mécanisme :

**Lecture** :

- TanStack Query lit d'abord SQLite local → UI instantanée
- Quand le réseau est disponible, fetch API → mise à jour du cache local
- "Stale While Revalidate" : l'utilisateur voit toujours des données, fraîches si possible

**Écriture (Outbox pattern)** :

1. Mutation → écriture synchrone dans SQLite (table cible + table `outbox`)
2. UI se met à jour immédiatement (optimiste)
3. Worker background tente de pousser l'outbox vers l'API en loop
4. Succès → marque l'entrée outbox comme `synced`, met à jour `synced_at` sur l'entité
5. Échec → retry avec exponential backoff (max 7 jours de rétention)

**Résolution de conflits** : "last write wins" sur les timestamps serveur. En cas de conflit détecté, la version serveur gagne (l'artisan est prévenu s'il y a eu une modification concurrente). Acceptable pour une app solo/petite équipe.

**Schéma local** : miroir simplifié du schéma serveur. Drizzle ORM génère les migrations locales (indépendantes des migrations Doctrine).

## Consequences

**Bénéfices** :

- L'app est 100% fonctionnelle sans réseau (critère différenciateur)
- UI optimiste → ressenti "instantané" même avec une connexion lente
- SQLite est embarqué dans l'app → pas de serveur à démarrer côté device
- Drizzle ORM donne des types TypeScript sur le schéma local → cohérence avec le reste de la stack TS

**Trade-offs** :

- Complexité non triviale : deux sources de vérité (SQLite local + API) à maintenir en cohérence
- L'"outbox pattern" demande une gestion rigoureuse des états (pending, syncing, synced, error)
- Tests d'intégration plus complexes (simuler offline + reconnexion)

**Décision différée** :
La gestion des conflits multi-utilisateurs (Phase 8+ si équipes) est volontairement hors scope. "Last write wins" tient jusqu'à ~5 personnes par compte, ce qui couvre 95% de la cible actuelle.
