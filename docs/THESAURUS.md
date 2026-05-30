# Thesaurus Cordeau

> Glossaire métier (ubiquitous language au sens DDD). Listage des termes canoniques utilisés partout — code, API, UI, doc, conversation. À consulter **avant** de nommer une classe, un champ, une route, un événement, une table.

## Règles

- **Un seul terme par concept.** Si tu hésites entre deux mots, regarde ici. Si le concept n'y est pas, ajoute-le **avant** de coder.
- **Partage FR / EN par couche** ([ADR 0014](adr/0014-naming-conventions-fr-en.md)). Vocabulaire métier en français (`Chantier`, `Devis`, `lierClient`, `StatutChantier`, routes `/api/chantiers`, tables `chantier`). Primitives de framework en anglais (`Repository`, `Provider`, `Processor`, `Payload`, `Factory`, `execute()`). Heuristique : si le terme est dans ce THESAURUS, il est en FR dans le code.
- **Pas d'accents dans les identifiers** ([ADR 0014](adr/0014-naming-conventions-fr-en.md)). Le code écrit `Metre`, `cloturer`, `etat`, `recu` même si le terme métier ici est listé avec ses accents. La translittération est mécanique : `é/è/ê/ë → e`, `à/â/ä → a`, `î/ï → i`, `ô/ö → o`, `ù/û/ü → u`, `ç → c`, `œ → oe`. Les accents restent obligatoires dans l'UI, les libellés, les commentaires, les données stockées.
- **Cohérence cross-plateforme.** Le même terme doit apparaître identique côté API (Symfony), web (React) et mobile (Expo). Pas de `Job` côté mobile pour ce qui s'appelle `Chantier` côté API.
- **Promouvoir un VO vers `Shared/ValueObject/`** dès qu'il est utilisé par ≥ 2 bounded contexts (cf [ADR 0010](adr/0010-crud-leger-pattern-reference.md)).

## Trajectoire V1 → V2

Le vocabulaire suit la trajectoire produit actée par les ADRs structurants :

- **V1 (Phases 5-9)** : `Lot`, `Tache`, `Mesure` (manuelle), `Materiau`, `Pointage`, `Imprevu`, `Devis brouillon`, `Facture brouillon`, `Avenant`, `Pre-document` — voir [ADR 0015](adr/0015-modele-chantier-lots-taches-mesures.md), [ADR 0016](adr/0016-positionnement-v1-outil-de-suivi.md), [ADR 0017](adr/0017-differer-ia-validation-manuelle.md)
- **V1.2+** (différé) : `Saisie vocale`, `Structuration`, `Recap auto`, `Descriptif genere`, `Chrono auto`, `Notification contextuelle` — ne pas introduire dans le code tant que le critère de validation bêta de [ADR 0017](adr/0017-differer-ia-validation-manuelle.md) n'est pas levé
- **V1.3+** (différé) : `Planning`, `Kanban`, `Vue calendrier`, `Risque chantier`, `Detection risques`, `Stats personnelles`, `Stock van`, `Inventaire`, `Liste de courses`
- **V2** : `Mesure AR` (source AR sur entité `Mesure` existante), `Ancre`, `Scan`

Format d'entrée :

```
### Terme
- **Définition** : phrase claire en une ligne
- **N'EST PAS** : ce avec quoi on confond souvent (la distinction est l'essentiel)
- **Synonymes à éviter** : mots à ne pas utiliser
- **Termes liés** : pointeurs vers d'autres entrées
```

---

## Bounded context : Chantier

### Chantier

- **Définition** : Réalisation concrète sur un site, liée à un client unique (ou aucun, héritage Phase 1), avec une adresse, un statut, et une période d'activité
- **N'EST PAS** : un devis (proposition commerciale antérieure), une facture (post-réalisation), une affaire au sens commercial regroupant plusieurs chantiers
- **Synonymes à éviter** : projet, mission, intervention, dossier, job, work
- **Termes liés** : [Client](#client), [ClientRef](#clientref), [Adresse](#adresse), [Statut de chantier](#statut-de-chantier)

### ClientRef

- **Définition** : Value Object dénormalisé portant `id` et `nomCache` d'un client, propriété de l'entité Chantier. Permet la lecture sans JOIN
- **N'EST PAS** : une référence (au sens lien faible) — c'est un VO immuable avec invariants
- **Synonymes à éviter** : ClientReference, ChantierClient, ClientLink
- **Termes liés** : [Client](#client), [Chantier](#chantier) — voir [ADR 0011](adr/0011-clientref-value-object-denormalise.md)

### Statut de chantier

- **Définition** : État courant d'un chantier dans son cycle de vie
- **Valeurs canoniques** : à compléter quand le cycle de vie complet est acté (probable : `brouillon`, `actif`, `en_pause`, `termine`, `archive`)
- **N'EST PAS** : un statut technique (HTTP, DB) ni une phase produit
- **Synonymes à éviter** : état, phase, étape
- **Termes liés** : [Chantier](#chantier)

### Lier un client (`lierClient`) / Délier un client (`delierClient`)

- **Définition** : Transitions métier explicites sur l'entité Chantier qui associent ou dissocient un `ClientRef`
- **N'EST PAS** : un setter `setClientId` — la transition est sémantique, pas technique
- **Synonymes à éviter** : assigner, attacher, attribuer
- **Termes liés** : [ClientRef](#clientref)

### Lot

- **Identifier code** : `Lot`
- **Définition** : Unité de travail dans un chantier portant un mode de facturation, une estimation, un réel, des tâches, matériaux, mesures et pointages. Entité fille optionnelle de Chantier (relation 1-N)
- **N'EST PAS** : une tâche (élément de checklist), un poste de devis (le poste devis dérive du lot mais n'est pas le lot lui-même), un sous-chantier
- **Synonymes à éviter** : poste, batch, work package, item
- **Termes liés** : [Chantier](#chantier), [Tâche](#tache), [Mode de facturation](#mode-de-facturation), [Mode création express](#mode-creation-express) — voir [ADR 0015](adr/0015-modele-chantier-lots-taches-mesures.md)

### Mode création express

- **Définition** : Création d'un chantier sans aucun lot. La facturation et la capture se comportent comme s'il existait un lot implicite couvrant tout le chantier
- **N'EST PAS** : un statut, une configuration globale — c'est juste l'absence de lots explicites
- **Synonymes à éviter** : chantier rapide, quick mode, simple mode
- **Termes liés** : [Lot](#lot), [Chantier](#chantier)

### Tâche

- **Identifier code** : `Tache` (sans accent, cf [ADR 0014](adr/0014-naming-conventions-fr-en.md))
- **Définition** : Élément de checklist d'exécution sous un Lot, avec libellé court et état fait/à faire
- **N'EST PAS** : un Lot (porteur de la facturation), une note, un imprévu
- **Synonymes à éviter** : todo, item, étape, action
- **Termes liés** : [Lot](#lot)

### Mode de facturation

- **Identifier code** : `ModeFacturation` (enum : `TEMPS`, `SURFACE`, `FORFAIT`)
- **Définition** : Comment un Lot est facturé. Le temps reste tracé quel que soit le mode (apprentissage personnel)
- **N'EST PAS** : un statut, une catégorie de chantier, un type de prestation
- **Synonymes à éviter** : mode tarification, billing mode, type de facturation
- **Termes liés** : [Lot](#lot), [Estimation](#estimation), [Réel](#reel)

### Estimation

- **Identifier code** : `Estimation` (VO)
- **Définition** : Valeur prévue avant exécution d'un Lot (heures pour `TEMPS`, m² pour `SURFACE`, euros pour `FORFAIT`)
- **N'EST PAS** : une promesse contractuelle (le devis a son propre statut), une moyenne historique automatique (V1.3+)
- **Synonymes à éviter** : prévision, target, budget
- **Termes liés** : [Réel](#reel), [Lot](#lot), [Mode de facturation](#mode-de-facturation)

### Réel

- **Identifier code** : `Reel` (sans accent)
- **Définition** : Valeur mesurée après exécution d'un Lot, dans la même unité que l'Estimation
- **N'EST PAS** : un montant facturé (la facture brouillon agrège mais peut être éditée), une moyenne
- **Synonymes à éviter** : effectif, observé, actual
- **Termes liés** : [Estimation](#estimation), [Lot](#lot), [Pointage](#pointage)

### Imprévu

- **Identifier code** : `Imprevu` (sans accent, VO)
- **Définition** : Note libre horodatée attachée à un Lot, signalant un écart par rapport au plan initial
- **N'EST PAS** : un avenant (ligne facturée — cf [Avenant](#avenant)), une tâche (action à cocher), un commentaire technique
- **Synonymes à éviter** : incident, anomalie, alerte, problème
- **Termes liés** : [Lot](#lot), [Avenant](#avenant)

### Chrono / Pointage

- **Identifier code** : `Pointage` (entrée enregistrée) ; le mot `Chrono` désigne uniquement le mécanisme UI start/stop, pas une classe
- **Définition** : Un **Pointage** est une plage de temps (début + fin éventuelle) enregistrée sur un Lot. Un Pointage ouvert (`fin = null`) représente un chrono en cours. **Chrono** = action UI start/stop ; **Pointage** = la donnée résultante
- **N'EST PAS** : une feuille d'heures globale (jamais agrégé par jour stocké — recalculé)
- **Synonymes à éviter** : timer (uniquement pour le chrono UI), timesheet, log, time entry
- **Termes liés** : [Lot](#lot), [Réel](#reel) — voir [ADR 0015](adr/0015-modele-chantier-lots-taches-mesures.md)

### Matériau

- **Identifier code** : `Materiau` (sans accent)
- **Définition** : Entrée d'un matériau prévu ou utilisé sur un Lot, granularité large (catégorie type « placo BA13 », pas chaque sac). Champ `etat` : `PREVU` ou `UTILISE`
- **N'EST PAS** : un article de catalogue avec prix unitaire (V1.3+), un produit du `Stock van`, un consommable spécifique
- **Synonymes à éviter** : produit, fourniture, article, item, supply
- **Termes liés** : [Lot](#lot), [État matériau](#etat-materiau)

### État matériau

- **Identifier code** : `EtatMateriau` (enum : `PREVU`, `UTILISE`)
- **Définition** : Bascule explicite d'un matériau entre prévu (préparation, liste de courses) et utilisé (réellement consommé). La facture brouillon agrège uniquement les `UTILISE`
- **N'EST PAS** : un statut de chantier, un statut de commande
- **Synonymes à éviter** : statut matériau, phase matériau

---

## Bounded context : Client

### Client

- **Définition** : Personne physique ou morale destinataire d'une facture pour un ou plusieurs chantiers
- **N'EST PAS** : un utilisateur (compte authentifié de l'application), un prospect (non encore client), un contact (personne associée à un client moral)
- **Synonymes à éviter** : customer, donneur d'ordre, acheteur, particulier
- **Termes liés** : [ClientRef](#clientref), [Chantier](#chantier)

### Owner (propriétaire de donnée)

- **Définition** : Utilisateur authentifié à qui appartiennent les données (chantiers, clients…). Filtre transverse pour le multi-tenant
- **N'EST PAS** : un rôle (au sens RBAC), un propriétaire métier (le client est propriétaire de son adresse, autre sens)
- **Synonymes à éviter** : user_id seul (sans sémantique), créateur, auteur
- **Termes liés** : [Utilisateur](#utilisateur)

---

## Bounded context : Auth

### Utilisateur

- **Définition** : Personne authentifiée dans l'application via un compte (artisan auto-entrepreneur, ou collaborateur d'une TPE)
- **N'EST PAS** : un client (destinataire de facture)
- **Synonymes à éviter** : user, compte, membre
- **Termes liés** : [Owner](#owner-propriétaire-de-donnée)

### Token opaque

- **Définition** : Jeton d'authentification mobile non parsable côté client (vs JWT). Validé par appel API
- **N'EST PAS** : un JWT, une session cookie, une API key statique
- **Termes liés** : voir [ADR 0003](adr/0003-tokens-opaques-mobile.md)

---

## Bounded context : Devis / Facture (Phase 8)

### Devis

- **Définition** : Document commercial proposant un prix pour un chantier futur, avant signature
- **N'EST PAS** : une facture (post-réalisation), une estimation informelle, une offre commerciale au sens marketing
- **Synonymes à éviter** : offre, proposition, estimation, quote
- **Termes liés** : [Chantier](#chantier), [Client](#client), [Devis brouillon](#devis-brouillon), [Pré-document](#pre-document)

### Devis brouillon / Facture brouillon

- **Identifier code** : champ `etat: 'brouillon'` sur les entités `Devis` et `Facture`
- **Définition** : Pré-document éditable, non encore finalisé. Pas de numérotation séquentielle attribuée tant qu'il est en brouillon. La finalisation (passage `brouillon → envoye` pour devis, `brouillon → emise` pour facture) déclenche l'attribution du numéro
- **N'EST PAS** : un devis/facture finalisé, un document signé, un PDF généré
- **Synonymes à éviter** : draft (anglicisme), pré-devis, version provisoire
- **Termes liés** : [Pré-document](#pre-document), [Devis](#devis) — voir [ADR 0016](adr/0016-positionnement-v1-outil-de-suivi.md)

### Pré-document

- **Identifier code** : pas une classe — concept transverse couvrant `Devis` et `Facture` en V1
- **Définition** : Document produit par Cordeau V1 (devis, facture), exportable PDF, **non conforme PDP**. L'artisan reste maître de sa conformité légale via son outil comptable
- **N'EST PAS** : une facture conforme PDP (V2), un document opposable légalement, un document signé électroniquement
- **Synonymes à éviter** : document interne, document de travail (en code/API), draft document
- **Termes liés** : [Devis brouillon](#devis-brouillon--facture-brouillon) — voir [ADR 0016](adr/0016-positionnement-v1-outil-de-suivi.md)

### Avenant

- **Identifier code** : `Avenant` (entité fille du Devis ou ligne typée)
- **Définition** : Ligne ajoutée à un Devis initial pendant ou après le chantier, modifiant son montant total. Différent d'un nouveau devis et différent d'un imprévu
- **N'EST PAS** : un [Imprévu](#imprevu) (note signal d'écart, non facturé tant qu'il n'est pas converti en avenant), un nouveau devis, une remise
- **Synonymes à éviter** : extra, supplément, addendum, change order
- **Termes liés** : [Devis](#devis), [Imprévu](#imprevu)

> Termes anticipés (Phase 8) : `LigneDevis`, `LigneFacture`, `TauxTVA`, `MontantHT`, `MontantTTC`, `Remise`, `ConditionsPaiement`, `EtatDevis` (`brouillon | envoye | accepte | refuse | expire | annule`), `EtatFacture` (`brouillon | emise | annulee`), `NumeroDevis` / `NumeroFacture` (séquence ininterrompue, cf [ADR 0016](adr/0016-positionnement-v1-outil-de-suivi.md)), `ProfilPro` (taux horaire, SIRET, mentions, logo).

---

## Bounded context : Métré (manuel V1, AR V2)

### Mesure

- **Identifier code** : `Mesure` (entité fille de Lot)
- **Définition** : Valeur métrique unitaire saisie pour un Lot (longueur, surface, volume, ou quantité libre), avec dimensions sources et calcul assisté. Source : `MANUEL` en V1, `AR` ajouté en V2 sans refacto
- **N'EST PAS** : un métré (regroupement, cf ci-dessous), une dimension brute sans nom (chaque mesure est nommée — « mur sud », « plafond cuisine »), une estimation
- **Synonymes à éviter** : measurement (anglicisme), valeur, dimension
- **Termes liés** : [Lot](#lot), [Métré](#metre), [Type de mesure](#type-de-mesure), [Source de mesure](#source-de-mesure) — voir [ADR 0015](adr/0015-modele-chantier-lots-taches-mesures.md)

### Métré

- **Identifier code** : pas d'entité dédiée en V1 — le métré est la collection des `Mesure` d'un Lot, exposée comme vue dérivée
- **Définition** : Regroupement sémantique des mesures d'un Lot, base de calcul du brouillon de devis pour les Lots en mode `SURFACE`
- **N'EST PAS** : une entité distincte en V1 (pas de table `metre`), un devis, un quantitatif global de chantier
- **Synonymes à éviter** : quantitatif, métrage, relevé
- **Termes liés** : [Mesure](#mesure), [Devis](#devis), [Lot](#lot)

### Type de mesure

- **Identifier code** : `TypeMesure` (enum : `LONGUEUR`, `SURFACE`, `VOLUME`, `QUANTITE_LIBRE`)
- **Définition** : Détermine quelles dimensions sources sont attendues (longueur seule / longueur+largeur / longueur+largeur+hauteur / valeur libre)
- **N'EST PAS** : une unité (m, m², m³ — portée par un champ séparé)
- **Synonymes à éviter** : kind, category, mesure type

### Source de mesure

- **Identifier code** : `Source` (enum : `MANUEL`, `AR`)
- **Définition** : Indique comment une Mesure a été saisie. `MANUEL` en V1, `AR` ajouté en V2 sur la même entité
- **N'EST PAS** : un outil, un device, une qualité de mesure (V2 pourra avoir des champs précision séparés)

---

## Bounded context : Photo (Phase 5, enrichi Phase 7)

### Photo

- **Identifier code** : `Photo` (entité)
- **Définition** : Image rattachée à un Chantier (Phase 5). Stockée sur Cloudflare R2 (cf [ADR 0004](adr/0004-cloudflare-r2-stockage.md)) avec URLs pré-signées
- **N'EST PAS** : un fichier brut sans contexte (toujours rattachée au minimum à un Chantier), un asset marketing
- **Synonymes à éviter** : image, picture, file
- **Termes liés** : [Chantier](#chantier), [Photo contextualisée](#photo-contextualisee)

### Photo contextualisée

- **Définition** : Photo dont les champs optionnels `lotId` et/ou `tacheId` sont renseignés (Phase 7). Pas une entité distincte — extension de `Photo`
- **N'EST PAS** : une photo annotée (V1.1+ éventuel), une photo signée, une photo OCRisée (V1.2+ avec LLM)
- **Synonymes à éviter** : photo taggée, photo liée, photo enrichie
- **Termes liés** : [Photo](#photo), [Lot](#lot), [Tâche](#tache), [Mesure](#mesure) (champ `photoId` sur Mesure)

> Termes anticipés Phase 7+ : `Annotation`, `EmplacementChantier`. `Album` : pas de groupement explicite en V1, on se base sur les filtres par lot/tâche/date.

---

## Value Objects partagés (`Shared/ValueObject/`)

### Adresse

- **Définition** : Adresse postale française structurée (numéro, rue, complément, code postal, ville, pays)
- **N'EST PAS** : une localisation GPS (latitude/longitude), un point d'intérêt
- **Synonymes à éviter** : address (anglicisme), location, lieu
- **Termes liés** : [Chantier](#chantier), [Client](#client)
- **Localisation code** : `apps/api/src/Shared/ValueObject/Adresse.php`

### Surface, Longueur, Volume (Phase 7, promus partagés)

- **Identifier code** : `Surface`, `Longueur`, `Volume` (VO immuables, `readonly final class`)
- **Définition** : VO métriques unitaires (valeur + unité) partagés entre [Mesure](#mesure) et [Devis](#devis) (calcul de quantitatifs sur Lot mode `SURFACE`)
- **Localisation code** : `apps/api/src/Shared/ValueObject/Surface.php`, `Longueur.php`, `Volume.php`
- **Promus partagés** dès Phase 7 (cf [ADR 0010](adr/0010-crud-leger-pattern-reference.md)) — utilisés par les BC Chantier (Mesure) et Devis (LigneDevis en mode surface)

---

## Termes techniques transverses (anglais OK)

Ces termes restent en anglais parce que ce sont des primitives de framework, pas du métier.

- **Repository** — accès persistance (Doctrine ou Domain port)
- **Provider** — API Platform read (Item / Collection)
- **Processor** — API Platform write (Creer / Modifier / Supprimer)
- **Payload** — DTO d'entrée API
- **Resource** — DTO de sortie API + définition `#[ApiResource]`
- **Use case** — service applicatif orchestrant un cas d'usage métier (`Application/<Context>/`)
- **Bounded context** — sous-domaine cohérent, voir [ADR 0002](adr/0002-architecture-hexagonale.md)
- **Value Object** — type immuable porteur d'invariants (`readonly final class`)

---

## Termes hérités / Legacy (à éviter dans le nouveau code)

> À remplir quand on détecte du legacy à renommer. Vide à ce jour.

---

## Maintenance

- À mettre à jour **en fin de chaque story** qui introduit un terme métier nouveau
- À auditer **en fin de phase** ([protocole de démarrage de phase](../CLAUDE.md#protocole-de-démarrage-de-phase) étape 9) : vérifier la cohérence des noms entre API/web/mobile
- Un terme qui apparaît en code mais pas ici = bug de discipline → l'ajouter ou renommer le code
