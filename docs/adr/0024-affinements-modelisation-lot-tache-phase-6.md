# 0024 — Affinements de modélisation Lot / Tâche (Phase 6)

- **Status** : Accepted
- **Date** : 2026-06-14
- **Deciders** : Maxime
- **Lié à** : [ADR 0015](0015-modele-chantier-lots-taches-mesures.md) (modèle socle), [ADR 0010](0010-crud-leger-pattern-reference.md) (CRUD léger), [ADR 0002](0002-architecture-hexagonale.md), [ADR 0014](0014-naming-conventions-fr-en.md)

## Context

L'[ADR 0015](0015-modele-chantier-lots-taches-mesures.md) acte le modèle `Chantier → Lot → Tâche` et laisse trois points de modélisation ouverts au moment d'implémenter la Phase 6 :

1. **Niveau de rigueur de `Tâche`** : `Tâche` porte des transitions (`cocher`/`decocher`/`renommer`) mais une logique métier quasi nulle. L'ADR 0015 la cite dans le modèle hexagonal, mais `apps/api/CLAUDE.md` la range explicitement dans la liste « légèreté ». Tension à trancher.
2. **Forme des VO `Estimation` / `Reel`** : l'ADR 0015 dit « VO porteur de la valeur + unité selon mode », sans préciser si l'unité est stockée dans le VO ou dérivée. L'unité dépend strictement du `ModeFacturation` du Lot (`TEMPS`→h, `SURFACE`→m², `FORFAIT`→€).
3. **Persistance de l'`Imprévu`** : l'ADR 0015 fixe `Imprevu` comme VO horodaté (pas entité), sans préciser le stockage.

Ces décisions conditionnent le code de la Phase 6 et méritent d'être tracées avant écriture (discipline « ADR avant code »).

## Decision

### 1. `Tâche` = léger, mais dans le domaine (séparation imposée par Deptrac)

Le pattern CRUD léger « single-class » de l'[ADR 0010](0010-crud-leger-pattern-reference.md) (entité = entité Doctrine, pas de port) ne vaut que pour les **contextes autonomes hors périmètre Deptrac** (Auth, Client). `Tâche` est imbriquée dans le bounded context **Chantier**, qui est rigoureux, layer-first et sous Deptrac (Doctrine interdit dans `src/Domain/`). Le single-class n'y rentre donc pas.

`Tâche` est donc placée comme les autres entités du BC : **entité de domaine `final readonly` dans `Domain/Chantier/Entity/`, entité Doctrine séparée (`TacheDoctrineEntity`), port `TacheRepository`**. Le caractère « léger » s'exprime par l'**absence de couche Application dédiée** (pilotée directement par les processors en 6.2) et l'**absence de VO** : `libelle` est un `string`, les transitions `cocher()` / `decocher()` (toggle `faite` + `faiteLe`) et `renommer()` sont des méthodes simples. L'immuabilité est conservée par cohérence avec le reste du BC et pour respecter « pas de propriété publique mutable » (`apps/api/CLAUDE.md`).

`Lot` reste **hexagonal strict** (entité domaine `final readonly`, factory + transitions, entité Doctrine séparée, port dédié, **plus** une couche Application en 6.2) : il porte le mode de facturation, l'estimation/réel et leurs invariants.

### 2. `Estimation` / `Reel` : VO `valeur`, unité dérivée du mode

```php
final readonly class Estimation { public function __construct(public float $valeur) {} } // valeur >= 0
final readonly class Reel       { public function __construct(public float $valeur) {} } // valeur >= 0
```

L'**unité n'est jamais stockée**. Elle est dérivée du `ModeFacturation` du Lot via `ModeFacturation::unite()`. Source unique de vérité : pas de risque de drift entre le mode et une unité dénormalisée. Les VO encapsulent l'invariant `valeur >= 0`.

### 3. `Imprevu` : VO `(note, horodatage)` en colonne JSON

```php
final readonly class Imprevu {
    public function __construct(public string $note, public \DateTimeImmutable $horodatage) {}
}
```

`Lot.imprevus` = `list<Imprevu>` persistée en **colonne JSON** sur la table `lot` (pas de table dédiée — conforme au choix « VO pas entité » de l'ADR 0015). L'API expose l'ajout d'imprévu ; l'UI riche (saisie terrain) est différée Phase 7, où l'imprévu prend son sens comme signal d'écart pendant l'exécution.

### 4. Portée Phase 6

`ordre` = `int` assigné côté client (UUID + ordre générés client pour l'offline). Pas d'endpoint de réordonnancement dédié en Phase 6 (YAGNI). Migration **additive** : tables `lot` et `tache`, aucune modification destructive sur `chantier`.

## Consequences

### Bénéfices

- **Moins de boilerplate** sur `Tâche` (pas de double entité domaine/Doctrine, pas de VO) pour une logique triviale.
- **Invariant unité↔mode garanti** par construction : impossible d'avoir une estimation en m² sur un lot au forfait.
- **Imprévu sans table** : schéma plus simple, cohérent avec le statut VO.

### Coûts / trade-offs

- **Asymétrie Lot/Tâche** dans le même bounded context (un hexagonal, un léger) : assumé, c'est exactement la doctrine « rigueur selon la nature de la logique » de l'[ADR 0002](0002-architecture-hexagonale.md). Si `Tâche` acquiert des règles (dépendances, sous-tâches…), bascule planifiée vers l'hexagonal (protocole ADR 0010).
- **Imprévu en JSON** : pas de requête SQL fine sur les imprévus (filtrage, agrégation). Acceptable en V1 (note libre). À promouvoir en table si un besoin de requêtage émerge.
- **Unité dérivée** : tout consommateur (API, web, mobile) doit dériver l'unité du mode plutôt que la lire d'un champ. Centralisé dans `ModeFacturation::unite()` côté API et exposé via la ressource.

### Risques résiduels

- Changement de `mode` d'un Lot après saisie d'une estimation : la valeur conserverait une unité qui « change » de sens. **Tranché (6.1)** : `changerMode()` **réinitialise** `estimation` et `reel` à `null` dès que le mode change réellement (no-op si mode identique), pour éviter une valeur silencieusement fausse. Charge à l'UI (6.3/6.4) de prévenir l'artisan que changer le mode efface l'estimation/réel saisis.
