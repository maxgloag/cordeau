# ADR 0002 — Architecture hexagonale pragmatique

- **Status** : Accepted
- **Date** : 2026-05-03
- **Deciders** : Maxime

## Context

Le backend Symfony doit gérer des bounded contexts de complexités très différentes :
- **Complexes** : mesure AR, calcul de métré, génération de devis, règles de facturation/TVA (logique métier riche, règles évolutives)
- **Simples** : gestion de clients, adresses, tags (CRUD pur, zéro logique métier)

Appliquer le même niveau d'architecture à tout crée de la cérémonie inutile (interfaces et adapters pour un simple CRUD de tags) et ralentit le développement.

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
- La légèreté sur les CRUD évite la sur-ingénierie et accélère les phases 1/2
- L'architecture guide naturellement vers la bonne granularité

**Trade-offs** :
- Deux "modes" d'architecture à maintenir → risque d'incohérence si mal documenté
- Discipline requise pour ne pas glisser vers le "tout complexe" ou le "tout léger"

**Signaux d'alarme** :
- Si un "CRUD simple" commence à avoir des règles métier → migrer vers l'archi hexagonale
- Si le domaine importe Doctrine ou Symfony → bug architectural, corriger immédiatement
