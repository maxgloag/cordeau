# 0011 — ClientRef : value object dénormalisé sur Chantier

- **Status** : Accepted
- **Date** : 2026-05-14
- **Deciders** : Maxime
- **Lié à** : [ADR 0002](0002-architecture-hexagonale.md), [ADR 0010](0010-crud-leger-pattern-reference.md)

## Context

Phase 2 introduit la relation **Chantier → Client**. Un chantier peut appartenir à un client (ou aucun, héritage Phase 1 où la FK n'existait pas). Le cas d'usage dominant est l'affichage : sur le dashboard web et la liste mobile des chantiers, on veut voir le nom du client sans JOIN ni N+1.

Trois options ont été évaluées :

| Option                                                                      | Verdict                                                                                                                                               |
| --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **FK nullable simple** (`client_id` sur Chantier)                           | Standard relationnel. Impose un JOIN systématique sur les listes. Coût de lecture modeste mais récurrent, et N+1 facile à introduire via l'ORM        |
| **Value Object `ClientRef` dénormalisé** (`client_id` + `client_nom_cache`) | Lectures sans JOIN, sémantique DDD plus claire (`chantier.lierClient($ref)`). Coût : maintenir la cohérence du cache au renommage et à la suppression |
| **Table de liaison N-N**                                                    | Permettrait plusieurs clients par chantier (copropriété, sous-traitance). Pas de cas d'usage immédiat — YAGNI                                         |

Le mode rigoureux acté en Phase 1 sur Chantier (cf [ADR 0002](0002-architecture-hexagonale.md)) implique que le domaine Chantier porte des value objects métier explicites (`Adresse`, `Surface`, `Statut`). Ajouter un `ClientRef` poursuit cette logique — Chantier ne dépend pas du module Client, il dépend d'une **référence** à un client.

## Decision

Adopter le **Value Object `ClientRef`** porté par l'entité `Chantier`.

### Structure

```
apps/api/src/Domain/Chantier/ValueObject/ClientRef.php
  - readonly final class ClientRef
  - propriétés : Uuid $id, string $nomCache
  - invariants : id valide (UUID), nomCache non vide après trim
  - méthodes : equals(ClientRef): bool
```

Sur l'entité `Chantier` :

```
?ClientRef $client
+ lierClient(ClientRef $ref): void   # transition métier explicite
+ delierClient(): void               # transition métier explicite
```

Mapping Doctrine côté `ChantierDoctrineEntity` : deux colonnes embedded, `client_id` (UUID nullable, indexé) et `client_nom_cache` (varchar 255, nullable).

### Propagation du nom (cohérence du cache)

Stratégie **synchrone via Doctrine event listener** :

- `App\Client\EventListener\ClientRenameListener` écoute `preUpdate` sur l'entité `Client`
- Si le champ `nom` change, le listener met à jour `chantier.client_nom_cache` sur tous les chantiers liés (UPDATE SQL groupé, pas N requêtes)
- Le listener vit dans `src/Client/` (côté Client, le contexte propriétaire de la donnée), pas dans `src/Chantier/` — Chantier reste passif

Trade-off : on accepte un couplage **runtime** Client → Chantier (le listener référence la table chantier), mais pas un couplage **de domaine** (Chantier/Domain ne référence pas Client/Entity).

### Comportement à la suppression d'un Client

**Soft-detach** : la suppression d'un Client passe les colonnes `client_id` et `client_nom_cache` à NULL sur les chantiers liés, sans supprimer les chantiers. Implémenté dans le `SupprimerClientProcessor` (UPDATE explicite avant le DELETE).

Pas de FK contrainte avec `ON DELETE SET NULL` au niveau Postgres : on garde la cohérence au niveau applicatif pour qu'elle reste visible dans le code et testable. À reconsidérer si on observe de la dérive (orphelins, doublons).

### Comportement à la création / modification de Chantier

- POST/PATCH `Chantier` accepte un `clientId` optionnel
- Le Processor résout l'instance `Client` via le repository, construit le `ClientRef(id, nom)`, et appelle `chantier.lierClient($ref)`
- Si `clientId` est `null` dans le payload PATCH, appel à `chantier.delierClient()`
- Si le `Client` référencé n'existe pas ou n'appartient pas au même owner → 422

## Consequences

### Bénéfices attendus

- **Lectures sans JOIN** : la liste des chantiers (cas le plus chaud) renvoie le nom du client sans requête supplémentaire
- **Sémantique DDD claire** : `chantier.lierClient(...)` / `delierClient()` documentent la transition mieux qu'un setter `setClientId`
- **Domaine Chantier indépendant** : le contexte Chantier compile et se teste sans le contexte Client (seul un VO trivial est partagé conceptuellement)
- **Testabilité** : les transitions de liaison sont testées en pur PHP, sans DB

### Coûts assumés

- **Cohérence à maintenir** : un renommage de Client doit se propager. Le listener Doctrine assume cette responsabilité. Risque de divergence si quelqu'un fait un UPDATE SQL brut sur la table client (mitigation : commande Symfony `client:reconcilier-cache` à écrire si la situation se présente)
- **Couplage runtime Client → Chantier** dans le listener. Acceptable car concentré dans un seul fichier, documenté ici
- **Coût en écriture** : UPDATE multi-lignes au renommage. Marginal tant qu'un client a un nombre raisonnable de chantiers (cible artisan : ≤ quelques centaines)

### Risques résiduels

- Si Phase 6 (Devis) introduit aussi un `ClientRef` (probable), il faudra propager le renommage sur deux tables. Solution : extraire le listener en `ClientRenameListener` générique parcourant une liste de tables/colonnes configurées. À ce moment, ouvrir un nouvel ADR
- Si un cas « plusieurs clients par chantier » apparaît (copropriété), bascule vers table de liaison. Pas de migration triviale — accepté comme dette future

### Trade-offs assumés

- **Pas de `ON DELETE SET NULL` Postgres** : on garde la logique côté application. Si on a un jour à reconstruire la base depuis des dumps, un script de réconciliation suffira
- **Pas d'event sourcing pour le rename** : on assume le coût d'un UPDATE synchrone. Si la latence devient un problème (perf migration de masse), basculer vers un message Symfony Messenger asynchrone
