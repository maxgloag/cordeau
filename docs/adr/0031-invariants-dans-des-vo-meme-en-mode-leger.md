# ADR 0031 — Les invariants vivent dans des value objects, y compris en mode léger

- **Status** : Proposed (décision du fondateur attendue)
- **Date** : 2026-10-04
- **Deciders** : Maxime
- **Lié à** : issue #194, [ADR 0010](0010-crud-leger-pattern-reference.md) (CRUD léger), [ADR 0002](0002-architecture-hexagonale.md), #189

## Context

Question du fondateur : le CRUD léger est-il toujours une bonne idée, maintenant que l'écriture du code assistée par agent n'est plus le goulot ?

Constats dans le code (2026-10-04) :

- **L'ADR 0010 prévoit déjà ce qu'il faut, mais le code ne l'applique pas.** Il autorise des value objects en mode léger (`Client/ValueObject/Telephone.php`) et prescrit que `Adresse`, utilisé par plusieurs contextes, soit promu dans `Shared/ValueObject/`. `Adresse` y est, mais **seul Chantier l'utilise** (6 fichiers). `Client` stocke quatre chaînes brutes (`adresseRue`, `adresseCodePostal`, `adresseVille`, `adressePays`) avec pour seules règles `NotBlank` et `Length`.
- **Conséquence mesurée.** Un chantier refuse un code postal français à 2 chiffres et une rue faite d'espaces (le value object lève `AdresseInvalideException`). Un client les accepte (201). Une même adresse n'est donc pas valide ou invalide selon le contexte qui la reçoit.
- Un client porte déjà des règles : normalisation du téléphone par `Telephone` et conflit 409 sur identifiant. La règle de bascule de l'ADR 0010 (« une règle d'invariant non triviale émerge ») est en partie atteinte.
- Côté API, les exceptions de domaine levées par ces value objects ont produit des 500 tant qu'elles n'étaient pas mappées (#189). Un contexte qui ne les lève pas ne produit pas de 500, mais accepte des données invalides.

La prémisse économique de l'ADR 0010 (gain de vélocité en Phase 2) pèse moins quand le code est écrit par un agent. Je n'ai pas de mesure de vélocité récente. Le coût qui reste est la surface à relire et à tester, et la cohérence des règles entre contextes.

## Options

| Option                                          | Contenu                                                                                      | Coût                                                                  | Limite                                                                        |
| ----------------------------------------------- | -------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| **A. Statu quo**                                | `Client` garde des chaînes brutes                                                            | nul                                                                   | l'incohérence reste ; chaque nouveau contexte « léger » la reproduit          |
| **B. Value objects partagés dans le Processor** | `Client` valide via `Adresse` et `Telephone` dans le Processor, colonnes Doctrine inchangées | faible : un VO appelé, deux exceptions mappées en 422, quelques tests | l'entité reste couplée à Doctrine (acceptable, ADR 0010)                      |
| **C. `Client` en mode rigoureux**               | ports, cas d'usage, séparation entité de domaine / entité Doctrine                           | élevé                                                                 | aucun invariant de plus que B tant qu'il n'y a ni transition d'état ni calcul |
| **D. Tout en rigoureux**                        | tous les contextes comme Chantier                                                            | très élevé                                                            | contredit l'ADR 0002 sans bénéfice démontré                                   |

## Decision (proposée)

**Option B.** Le critère de l'ADR 0010 ne change pas (c'est la **nature de la logique** qui sépare léger et rigoureux), mais sa règle est complétée :

1. **Mode léger : aucun invariant n'est écrit dans un Payload ou un Processor.** Tout format, toute borne ou toute normalisation est un value object. Le Payload ne garde que ce qui relève de la forme de la requête (`NotBlank`, `Length`, `Email`).
2. **Un value object utilisé par deux contextes est promu dans `Shared/ValueObject/`** (déjà écrit dans l'ADR 0010) **et utilisé par tous** : première application, `Client` utilise `Adresse`.
3. **Toute exception de domaine levée à partir d'une saisie est mappée en 422** dans `exception_to_status` (déjà fait pour `AdresseInvalideException` et `SurfaceInvalideException`, #189). Un test d'intégration le vérifie pour chaque nouvelle exception.
4. La règle de bascule vers le mode rigoureux est inchangée : transition d'état, calcul, logique partagée entre processors, besoin de tester sans base.
5. L'argument de vélocité est retiré de la justification du mode léger.

## Consequences

- **Cohérence** : une adresse est valide ou non de la même façon pour un chantier et un client.
- **Mise en œuvre** (issue à ouvrir si l'ADR est accepté) : appeler `Adresse` dans `CreerClientProcessor` et `ModifierClientProcessor`, mapper le 422, ajouter les tests (code postal à 2 chiffres, rue d'espaces).
- **Données existantes à vérifier avant d'activer la validation** : des clients déjà enregistrés peuvent avoir une adresse que `Adresse` refuserait (le `PATCH` d'un tel client échouerait). Il n'y a pas d'utilisateur en production aujourd'hui ; la base de développement peut en contenir.
- **Ce que cet ADR ne tranche pas** : le passage de `Client` en mode rigoureux (option C), à rouvrir si une transition d'état ou un calcul apparaît.

## Implications sécurité

- **Surface d'attaque** : aucune ajoutée. Valider à la frontière réduit les données invalides qui atteignent la base.
- **Données personnelles** : adresses et téléphones de clients des artisans (RGPD). Les collecter n'est pas modifié ; seule leur validation change.
- **Points de fuite** : le message de `AdresseInvalideException` reprend la valeur saisie (`Le code postal "…" est invalide`). Il est renvoyé dans une réponse JSON, pas dans une page HTML, et les 422 ne sont pas remontés à Sentry (seules les erreurs 5xx le sont, [ADR 0028](0028-sentry-api.md)).
- **Secrets** : aucun.
