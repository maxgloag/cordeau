# 0010 — CRUD léger : pattern de référence (Client)

- **Status** : Accepted
- **Date** : 2026-05-14
- **Deciders** : Maxime
- **Lié à** : [ADR 0002](0002-architecture-hexagonale.md)

## Context

[ADR 0002](0002-architecture-hexagonale.md) acte une **architecture hexagonale pragmatique** : rigueur maximale sur les bounded contexts complexes (mesure AR, métré, devis, TVA), légèreté assumée sur les CRUD simples (clients, adresses, tags). Phase 1 a livré le mode rigoureux sur `Chantier` ([apps/api/src/Domain/Chantier/](../../apps/api/src/Domain/Chantier), [apps/api/src/Application/Chantier/](../../apps/api/src/Application/Chantier), [apps/api/src/Infrastructure/Persistence/Doctrine/Chantier/](../../apps/api/src/Infrastructure/Persistence/Doctrine/Chantier), [apps/api/src/Presentation/Api/Chantier/](../../apps/api/src/Presentation/Api/Chantier)).

Le mode léger n'a pas encore d'occurrence concrète. Phase 2 (verticale Client) est la **première** : Client n'a aucune logique métier (zéro transition d'état, aucune règle de calcul, validation triviale). Sans structure de référence écrite, le risque est double :

1. **Sur-ingénierie** : recopier mécaniquement la structure Domain/Application/Infrastructure de Chantier — ports, DTOs, use cases — alors que rien ne le justifie. Ralentit Phase 2 et fait mentir l'objectif de vélocité ×2.
2. **Sous-ingénierie incohérente** : chaque CRUD léger futur (adresses, tags, étiquettes) ré-improvise sa structure, créant de la dispersion.

Cet ADR fige la structure de référence du mode léger sur l'exemple Client, et donne les critères pour basculer vers le mode rigoureux si des règles métier émergent.

## Decision

### Structure de référence (mode léger)

Pour un bounded context CRUD simple, structure sous `apps/api/src/<Context>/` :

```
src/Client/
  Entity/Client.php              # entité Doctrine = entité métier (mêmes fichiers, mêmes attributs)
  ValueObject/Telephone.php       # VO triviaux (validation format), si pertinents
  Repository/ClientRepository.php # ServiceEntityRepository<Client> standard Doctrine

src/Presentation/Api/Client/
  Resource/ClientResource.php          # DTO #[ApiResource]
  Provider/{Item,Collection}Provider.php
  Processor/{Creer,Modifier,Supprimer}ClientProcessor.php
  Payload/{Creer,Modifier}ClientPayload.php
```

### Différences vs mode rigoureux (Chantier)

| Élément | Mode rigoureux (Chantier) | Mode léger (Client) |
|---|---|---|
| Séparation Domain / Infrastructure | Oui (`Domain/Chantier/Entity/Chantier.php` + `Infrastructure/.../ChantierDoctrineEntity.php`) | **Non** — une seule classe `Client` portant les attributs Doctrine |
| Port de repository (interface) | Oui (`Domain/Chantier/Repository/ChantierRepository.php`) | **Non** — `ServiceEntityRepository<Client>` direct |
| Use cases dédiés (`Application/`) | Oui (`Creer`, `Modifier`, `Archiver`, `Lister`, `Obtenir`) | **Non** — le Processor API Platform pilote directement le Repository |
| DTOs entrée/sortie séparés | Oui | Payload + Resource uniquement (pas de DTO intermédiaire) |
| Tests unitaires de domaine | Oui (sans DB) | Limités aux VO triviaux ; couverture portée par les tests d'intégration |
| Tests d'intégration persistance | Oui | Oui |
| Tests d'intégration API | Oui | Oui |

### Règle de bascule (léger → rigoureux)

Migrer un CRUD léger vers le mode rigoureux **dès que** l'une de ces conditions devient vraie :

- Une transition d'état métier apparaît (ex. `valider`, `archiver`, `cloturer`)
- Une règle de calcul ou d'invariant non triviale émerge (ex. règle TVA, séquence numérotation)
- Deux processors différents doivent partager une logique métier (DRY brisé sur la logique, pas sur l'IO)
- Le besoin de tester la logique sans DB devient pressant

La bascule est un refactor planifié, pas une dette : on extrait le port, le use case, puis on isole l'entité Doctrine. Faire l'ADR de bascule **avant** le refactor.

### Pattern partagé : extraction des VO transverses

Quand un VO est utilisé par plusieurs bounded contexts (cas typique : `Adresse` réutilisé par Chantier et Client), on le promeut sous `apps/api/src/Shared/ValueObject/` plutôt que de le laisser sous le contexte d'origine. Pas de duplication, pas de cross-import entre contextes pairs.

## Consequences

### Bénéfices attendus

- **Vélocité Phase 2** : la sous-étape 2.1 (Client domaine + persistance) doit prendre ~0.5-1 j vs 2-3 j pour Chantier en Phase 1 — c'est le test de la promesse
- **Lisibilité** : un lecteur voit immédiatement si un contexte est riche ou plat (présence ou absence de `Application/<Context>/`)
- **Cohérence** : tous les futurs CRUD simples (adresses, tags, étiquettes, catégories) suivent le même squelette
- **Tests focalisés** : on ne paye pas le coût d'écrire des tests unitaires de domaine quand il n'y a pas de domaine à tester

### Coûts assumés

- **Couplage Doctrine assumé** dans Client/Entity/Client.php — les attributs Doctrine sont sur l'entité métier directement. Acceptable parce qu'il n'y a pas de logique métier à isoler du framework
- **Risque de glissement** : un CRUD léger peut accumuler des règles sans qu'on s'en rende compte. Mitigé par la règle de bascule explicite ci-dessus, à revérifier en fin de chaque phase
- **Deux modes à maintenir** : risque d'incohérence si un développeur applique le mauvais mode. Mitigé par cet ADR + la mention dans les memories Serena (`architecture`)

### Trade-offs assumés

- **Pas de tests unitaires de domaine sur Client** : la couverture vient des tests d'intégration (Repository + API). Si une règle métier apparaît plus tard, elle déclenche la bascule (et donc l'ajout des tests unitaires)
- **Pas d'abstraction `AbstractCrudProcessor`** au lancement de Phase 2 : on attend de voir le pattern se répéter (≥3 occurrences) avant d'extraire. Sinon risque d'abstraire prématurément sur 2 cas
