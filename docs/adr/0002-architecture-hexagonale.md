# ADR 0002 — Architecture hexagonale pragmatique

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

Le backend Symfony gère des bounded contexts de natures fondamentalement différentes, et cette différence n'est pas temporaire — elle reflète la réalité du domaine métier :

- **Logique métier riche** : mesure AR, calcul de métré, génération de devis, règles de facturation/TVA. Ces contextes ont des invariants à protéger, des transitions d'état, des règles de calcul qui évoluent. L'isolation du domaine du framework a une valeur réelle et durable.
- **Pas de logique métier** : gestion de clients, adresses, tags. Ces contextes sont des *conteneurs de données référencés* — validation triviale, zéro transition d'état, zéro règle de calcul. Il n'y a rien à isoler du framework parce qu'il n'y a pas de domaine à protéger.

Appliquer le même niveau d'architecture à tout crée de la cérémonie inutile (interfaces et adapters pour un simple CRUD de tags) sans bénéfice — pas de logique à tester sans DB, pas d'invariant à protéger, pas de framework à découpler.

## Decision

Architecture **hexagonale pragmatique** : rigueur maximale sur les bounded contexts complexes, légèreté assumée sur les CRUD simples.

**Structure des bounded contexts complexes** (mesure AR, métré, devis, TVA) :
```
src/Domain/{Context}/
    Entity/          # entités métier — aucune annotation Doctrine
    ValueObject/     # value objects immuables (readonly)
    Repository/      # interfaces uniquement (ports)
    Exception/       # exceptions métier

src/Application/{Context}/UseCase/
    # un use case = une classe avec execute()
    # dépend uniquement des ports (interfaces)

src/Infrastructure/Persistence/Doctrine/{Context}/
    # implémentations des ports (adapters)
    # Doctrine entities séparées si besoin de mapping différent

src/Presentation/Api/{Context}/
    # controllers API Platform (State Providers/Processors)
    # DTOs d'entrée/sortie
```

**Légèreté sur les CRUD simples** (clients, adresses, tags) :
- Controller → Service → Doctrine Entity directement
- Pas d'interface de repository, pas de use case dédié
- Entité Doctrine = entité métier (même classe)

**Règle absolue** : les dépendances pointent vers l'intérieur. `Domain/` ne connaît ni Doctrine, ni Symfony, ni rien d'externe. Les use cases ne connaissent que des interfaces.

## Consequences

**Bénéfices** :
- Tests unitaires sur le domaine sans base de données (rapides, fiables)
- Isolation des règles métier complexes (devis, TVA) du framework
- La légèreté sur les *vrais* CRUD évite la cérémonie sans valeur — ce n'est pas un compromis de démarrage, c'est le bon niveau d'architecture pour des entités sans logique métier
- L'architecture guide naturellement vers la bonne granularité

**Trade-offs** :
- Deux "modes" d'architecture à maintenir → risque d'incohérence si mal documenté
- Discipline requise pour ne pas glisser vers le "tout complexe" ou le "tout léger"

**Signal de bascule** (léger → hexagonal) : dès qu'une entité légère acquiert des invariants, des transitions d'état ou une logique de calcul. C'est un **refactor planifié, pas une dette** — les conditions ont changé. Protocole détaillé dans [ADR 0010](0010-crud-leger-pattern-reference.md).

**Signaux d'alarme** :
- Si le domaine importe Doctrine ou Symfony → bug architectural, corriger immédiatement (cf Deptrac)
