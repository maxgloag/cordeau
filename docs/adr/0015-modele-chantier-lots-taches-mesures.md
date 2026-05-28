# 0015 — Modèle Chantier → Lots → Tâches → Mesures / Matériaux

- **Status** : Accepted
- **Date** : 2026-05-24
- **Deciders** : Maxime
- **Lié à** : [ADR 0002](0002-architecture-hexagonale.md), [ADR 0011](0011-clientref-value-object-denormalise.md), [ADR 0016](0016-positionnement-v1-outil-de-suivi.md), [ADR 0017](0017-differer-ia-validation-manuelle.md)

## Context

Phases 1 et 2 ont introduit une entité `Chantier` plate, sans découpage interne. Le brainstorm vision produit (mai 2026) acte que le **Lot** est l'unité métier centrale du chantier : c'est lui qui porte le mode de facturation, l'estimation avant/réel après, les tâches d'exécution, les matériaux et les mesures.

Sans cette structure, on ne peut pas :

- facturer un chantier avec plusieurs modes (ex : un lot peinture au m², un lot dépose au forfait, un lot main d'œuvre supplémentaire au temps)
- suivre l'écart estimation/réel par poste (apprentissage personnel de l'artisan, futur input du détecteur de risques V1.3+)
- contextualiser une mesure ou un matériau sur le bon poste de travail (donnée d'entrée du brouillon de devis/facture V1)
- préparer la trajectoire AR V2 où chaque mesure est un objet de domaine attaché à un lot

Trois alternatives ont été évaluées :

| Option | Verdict |
|---|---|
| **Conserver Chantier plat**, ajouter des champs (mode_facturation, surface_totale, etc.) directement sur Chantier | Simple court terme. Casse dès qu'un chantier a deux modes ou deux postes. Inadapté au métier. Rejeté |
| **Lot comme aggregate central**, Chantier devient un regroupement léger | Alignement DDD strict avec le brainstorm. Impose un refacto profond des Phases 1-2 (entité Chantier, API, web, mobile). Coût immédiat élevé pour un gain conceptuel pur. Rejeté |
| **Lot entité fille optionnelle de Chantier** (1-N), mode création express si 0 lot | Extension non destructive : Chantier garde tous ses champs et son API. Le découpage Lot/Tâche n'est imposé que quand l'artisan en a besoin. Compatible avec la discipline V1 manuel (cf [ADR 0017](0017-differer-ia-validation-manuelle.md)). **Retenu** |

Cette décision conditionne le code de Phase 6 (Verticale Lots/Tâches), Phase 7 (Capture terrain + Métré manuel) et Phase 8 (Devis + Facture brouillon).

## Decision

### Structure des entités

```
Chantier  (existant, inchangé)
  └── Lot  (1-N optionnel, nouveau)
        ├── mode : 'temps' | 'surface' | 'forfait'
        ├── estimation : valeur estimée avant exécution (heures, m², euros)
        ├── reel : valeur mesurée après exécution
        ├── Tâche  (1-N, checklist d'exécution)
        ├── Materiau  (1-N, prévu ou utilisé)
        ├── Mesure  (1-N, métré manuel V1, AR V2)
        ├── Pointage  (1-N, entrées chrono)
        └── Imprevu  (note libre, 0-N)
```

**Mode création express** : un chantier sans aucun lot est valide. Dans ce cas, la facturation et la capture se comportent comme s'il existait un lot implicite couvrant tout le chantier. Le mode express est le défaut côté UI mobile pour les chantiers simples (dépannage, petits travaux).

### Entité `Lot`

- Bounded context : reste dans `Chantier/` (cohésion fonctionnelle forte, pas de nouveau BC à part)
- Localisation code : `apps/api/src/Domain/Chantier/Entity/Lot.php`
- Propriétés :
  - `Uuid $id`
  - `Uuid $chantierId` (FK)
  - `string $nom` (libellé court : « Peinture salon », « Pose carrelage »)
  - `ModeFacturation $mode` (enum : `TEMPS`, `SURFACE`, `FORFAIT`)
  - `?Estimation $estimation` (VO porteur de la valeur estimée + unité selon mode)
  - `?Reel $reel` (VO porteur de la valeur mesurée + unité selon mode)
  - `int $ordre` (tri d'affichage)
- Transitions métier explicites : `definirEstimation(Estimation)`, `enregistrerReel(Reel)`, `renommer(string)`. Pas de setters publics.

### Entité `Tache`

- Localisation : `apps/api/src/Domain/Chantier/Entity/Tache.php`
- Propriétés : `Uuid $id`, `Uuid $lotId`, `string $libelle`, `bool $faite`, `?DateTimeImmutable $faiteLe`, `int $ordre`
- Transitions : `cocher()`, `decocher()`, `renommer(string)`

### Entité `Materiau`

- Localisation : `apps/api/src/Domain/Chantier/Entity/Materiau.php`
- Propriétés :
  - `Uuid $id`, `Uuid $lotId`
  - `string $libelle` (granularité large : « placo BA13 », « colle carrelage » — pas chaque sac)
  - `string $unite` (libre : « sac », « m² », « pcs »)
  - `float $quantite`
  - `EtatMateriau $etat` (enum : `PREVU`, `UTILISE`)
- Transitions : `marquerUtilise()`, `corrigerQuantite(float)`
- **Distinction prévu vs utilisé** : deux états d'un même matériau (pas deux entités). L'artisan saisit en `PREVU` lors de la préparation, bascule en `UTILISE` lors de l'exécution. La facture brouillon agrège les `UTILISE`. L'écart `PREVU - UTILISE` alimente le retour estimation/réel.

### Entité `Mesure`

- Localisation : `apps/api/src/Domain/Chantier/Entity/Mesure.php`
- Propriétés :
  - `Uuid $id`, `Uuid $lotId`
  - `string $nom` (« mur sud », « plafond cuisine »)
  - `TypeMesure $type` (enum : `LONGUEUR`, `SURFACE`, `VOLUME`, `QUANTITE_LIBRE`)
  - `?float $longueur`, `?float $largeur`, `?float $hauteur` (dimensions sources, nullables selon le type)
  - `float $valeurCalculee` (résultat du calcul assisté)
  - `string $unite` (`m`, `m²`, `m³`, libre pour `QUANTITE_LIBRE`)
  - `?string $note`
  - `?Uuid $photoId` (lien optionnel vers `Photo`, cf Phase 7)
  - `Source $source` (enum : `MANUEL`, `AR` — `AR` introduit en V2 sans refacto)
- Transitions : `corrigerDimensions(...)`, `attacherPhoto(Uuid)`, `detacherPhoto()`
- Le calcul `valeurCalculee` est dérivé des dimensions sources via une méthode pure du domaine (`calculer()`), pas stocké aveuglément.

### Entité `Pointage`

- Localisation : `apps/api/src/Domain/Chantier/Entity/Pointage.php`
- Propriétés :
  - `Uuid $id`, `Uuid $lotId`
  - `DateTimeImmutable $debut`, `?DateTimeImmutable $fin`
  - `Source $source` (enum : `MANUEL` en V1, `GEOFENCE` en V1.2+)
- Transitions : `terminer(DateTimeImmutable)`, `corrigerPlage(...)`
- **Distinction Chrono vs Pointage** (cf thésaurus) : `Chrono` désigne le mécanisme actif côté UI (start/stop) ; `Pointage` est l'entrée enregistrée. Un pointage ouvert (`fin = null`) représente un chrono en cours.

### Imprévu

Pas d'entité dédiée : `Lot.imprevus: list<string>` (notes libres horodatées en VO). Distinction explicite vis-à-vis d'un `Avenant` (cf [ADR 0016](0016-positionnement-v1-outil-de-suivi.md)) : l'imprévu est un **signal d'écart**, l'avenant est une ligne facturée.

### Migration Doctrine

Migration additive (Phase 6) :

- Tables nouvelles : `lot`, `tache`, `materiau`, `mesure`, `pointage`. Toutes avec FK `chantier_id` ou `lot_id` selon le cas.
- Aucune modification destructive sur `chantier` (Phase 1-2 préservées).
- Index sur les FK + index sur `(lot_id, ordre)` pour les enfants triés.

### API Platform

- `/chantiers/{id}/lots` — collection imbriquée (création/lecture)
- `/lots/{id}` — item (lecture/PATCH/DELETE)
- `/lots/{id}/taches`, `/lots/{id}/materiaux`, `/lots/{id}/mesures`, `/lots/{id}/pointages` — collections imbriquées
- Pattern Processor/Provider conforme [ADR 0010](0010-crud-leger-pattern-reference.md) côté CRUD léger (Tâche, Matériau, Mesure, Pointage). Le Lot porte une logique métier plus dense → Provider rigoureux.
- Génération OpenAPI → types TS via `openapi-typescript` comme pour les Phases précédentes.

### Mobile / Web

- **Mobile** : écran `Chantier > Lots` (liste), création express par défaut (un seul lot caché si l'artisan ne split pas), bouton « + lot » explicite si besoin. Édition des tâches/matériaux/mesures/pointages directement depuis l'écran lot.
- **Web** : vue détaillée chantier avec arborescence lots/tâches/matériaux/mesures, édition rapide.
- Offline-first : toutes les entités filles passent par le pattern Outbox déjà acté en [ADR 0012](0012-offline-first-query-pattern.md). UUIDs générés côté client.

## Consequences

### Bénéfices attendus

- **Alignement métier** : le modèle reflète comment l'artisan pense son chantier (par postes/lots, pas plat)
- **Facturation multi-mode** : un chantier peut combiner forfait + temps + surface sans hack
- **Estimation vs réel** par lot : base de l'apprentissage personnel (V1.3+) et du détecteur de risques
- **Métré manuel V1** : `Mesure` existe dès Phase 7, l'AR V2 n'est qu'un nouveau `Source` sur la même entité — pas de refacto
- **Photos contextualisées** : le lien `Mesure.photoId` (et plus tard `Photo.lotId`/`Photo.tacheId`) ouvre la voie à la contextualisation Phase 7
- **Création express** : aucun frein à l'usage pour les chantiers simples, le découpage est progressif

### Coûts assumés

- **Surface de domaine multipliée** : 5 nouvelles entités à modéliser, tester, persister, exposer en API, brancher en web et mobile. Charge réelle Phase 6 (~2-3 semaines) + Phase 7 (~2-3 semaines avec Mesure).
- **Synchronisation offline plus large** : l'outbox mobile doit gérer N entités filles avec dépendance d'ordre (un Lot doit être synchronisé avant ses Tâches). Pattern à généraliser depuis l'existant.
- **Risque d'over-modélisation** si l'artisan moyen reste en mode express : on a payé un modèle riche utilisé à 30 %. Mitigation : critère de validation bêta (cf [ADR 0017](0017-differer-ia-validation-manuelle.md)) — si les testeurs ne split jamais en lots, on saura qu'on a sur-modélisé et on ajustera.

### Risques résiduels

- **Cohérence des écarts** prévu/utilisé sur Matériau : si l'artisan oublie de basculer `PREVU → UTILISE`, le brouillon facture est faux. Mitigation possible V1.1 : rappel fin de chantier « 3 matériaux restent au statut prévu, est-ce correct ? »
- **Pointages ouverts oubliés** : un chrono démarré et jamais arrêté pollue la donnée. Mitigation V1 : rappel fin de journée si pointage ouvert > 12h. Mitigation V1.2+ : géofence stop auto.
- **Mesures sans photo** : la valeur seule perd son contexte (pas de preuve, pas de rappel visuel). Pas de contrainte UI en V1 (l'artisan peut sauter la photo), à observer en bêta.

### Trade-offs assumés

- **Pas d'aggregate root strict** : Lot n'est pas l'aggregate racine, Chantier reste la racine. Choix DDD pragmatique : on garde la cohérence des transactions au niveau Chantier (un chantier + ses lots/tâches sont créés/modifiés ensemble) sans imposer un refacto.
- **Imprevu en VO et pas en entité** : on perd la possibilité d'attacher un horodatage précis ou une photo à un imprévu individuel. Acceptable en V1 (note libre suffit). À promouvoir en entité si les testeurs réclament l'attachement multimédia.
- **Mesure stocke `valeurCalculee` même si dérivable** : redondance assumée pour requêtes rapides (sommes côté facture). Recalculée à chaque édition des dimensions sources, validation par contrainte d'invariant.
