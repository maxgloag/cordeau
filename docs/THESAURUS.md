# Thesaurus Cordeau

> Glossaire métier (ubiquitous language au sens DDD). Listage des termes canoniques utilisés partout — code, API, UI, doc, conversation. À consulter **avant** de nommer une classe, un champ, une route, un événement, une table.

## Règles

- **Un seul terme par concept.** Si tu hésites entre deux mots, regarde ici. Si le concept n'y est pas, ajoute-le **avant** de coder.
- **Partage FR / EN par couche** ([ADR 0014](adr/0014-naming-conventions-fr-en.md)). Vocabulaire métier en français (`Chantier`, `Devis`, `lierClient`, `StatutChantier`, routes `/api/chantiers`, tables `chantier`). Primitives de framework en anglais (`Repository`, `Provider`, `Processor`, `Payload`, `Factory`, `execute()`). Heuristique : si le terme est dans ce THESAURUS, il est en FR dans le code.
- **Pas d'accents dans les identifiers** ([ADR 0014](adr/0014-naming-conventions-fr-en.md)). Le code écrit `Metre`, `cloturer`, `etat`, `recu` même si le terme métier ici est listé avec ses accents. La translittération est mécanique : `é/è/ê/ë → e`, `à/â/ä → a`, `î/ï → i`, `ô/ö → o`, `ù/û/ü → u`, `ç → c`, `œ → oe`. Les accents restent obligatoires dans l'UI, les libellés, les commentaires, les données stockées.
- **Cohérence cross-plateforme.** Le même terme doit apparaître identique côté API (Symfony), web (React) et mobile (Expo). Pas de `Job` côté mobile pour ce qui s'appelle `Chantier` côté API.
- **Promouvoir un VO vers `Shared/ValueObject/`** dès qu'il est utilisé par ≥ 2 bounded contexts (cf [ADR 0010](adr/0010-crud-leger-pattern-reference.md)).

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

## Bounded context : Devis (Phase 6 — à compléter)

### Devis
- **Définition** : Document commercial proposant un prix pour un chantier futur, avant signature
- **N'EST PAS** : une facture (post-réalisation), une estimation informelle, une offre commerciale au sens marketing
- **Synonymes à éviter** : offre, proposition, estimation, quote
- **Termes liés** : [Chantier](#chantier), [Client](#client), `LigneDevis` (à définir), `TVA` (à définir)

> Le bounded context Devis se complétera à partir de la Phase 6. Termes anticipés : `LigneDevis`, `TauxTVA`, `MontantHT`, `MontantTTC`, `Remise`, `ConditionsPaiement`, `EtatDevis` (`brouillon | envoye | accepte | refuse | expire`).

---

## Bounded context : Métré AR (Phase 5+ — à compléter)

### Mesure
- **Définition** : Valeur métrique unitaire prise via la caméra AR du téléphone (longueur, surface, volume)
- **N'EST PAS** : un métré (regroupement de mesures pour un chantier)
- **Synonymes à éviter** : measurement (anglicisme), valeur, dimension
- **Termes liés** : `Metre` (à définir)

### Métré
- **Définition** : Document de quantitatif rassemblant des mesures pour un chantier, base de calcul d'un devis
- **N'EST PAS** : une mesure (unitaire), un devis (commercial)
- **Synonymes à éviter** : quantitatif, métrage, relevé
- **Termes liés** : [Mesure](#mesure), [Devis](#devis), [Chantier](#chantier)

> Vocabulaire complet à acter en Phase 5 quand le bounded context démarre. Termes anticipés : `Surface`, `Longueur`, `Volume`, `Hauteur`, `PointDeMesure`, `Ancre`, `Scan`.

---

## Bounded context : Photo (Phase 3 — à compléter)

> Bounded context à venir. Termes anticipés : `Photo`, `Album`, `Annotation`, `EmplacementChantier`.

---

## Value Objects partagés (`Shared/ValueObject/`)

### Adresse
- **Définition** : Adresse postale française structurée (numéro, rue, complément, code postal, ville, pays)
- **N'EST PAS** : une localisation GPS (latitude/longitude), un point d'intérêt
- **Synonymes à éviter** : address (anglicisme), location, lieu
- **Termes liés** : [Chantier](#chantier), [Client](#client)
- **Localisation code** : `apps/api/src/Shared/ValueObject/Adresse.php`

### Surface, Longueur, Volume (à définir Phase 5)
- VO métriques unitaires partagés entre Métré et Devis (calcul de quantitatifs)

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
