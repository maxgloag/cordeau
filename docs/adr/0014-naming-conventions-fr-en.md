# 0014 — Conventions de nommage FR / EN dans le code

- **Status** : Accepted
- **Date** : 2026-05-22
- **Deciders** : Maxime
- **Lié à** : [ADR 0002](0002-architecture-hexagonale.md), [docs/THESAURUS.md](../THESAURUS.md)

## Context

Cordeau s'adresse à des artisans BTP français. Le vocabulaire métier (`Chantier`, `Devis`, `Métré`, `TVA`) n'a pas de bon équivalent anglais sans perte sémantique : *site* / *project* / *job* ne couvrent pas *chantier*, *quote* est ambigu, *survey* recouvre mal *métré*. Eric Evans (*Domain-Driven Design*, 2003) défend que le code doit parler la langue du métier — l'*Ubiquitous Language* — pour éviter une traduction permanente entre stakeholders et code, source de bugs sémantiques.

[docs/THESAURUS.md](../THESAURUS.md) acte déjà l'ubiquitous language en français côté concepts. [apps/api/CLAUDE.md](../../apps/api/CLAUDE.md) mentionne « Identifiants métier en français : `Chantier`, `Client`, `Devis`, `Facture`, `Metrage` ». Le code livré en Phase 1 et 2 respecte ce principe de fait (`Chantier`, `lierClient`, `proprietaireId`, `creeLe`, `StatutChantier::EN_PREPARATION`, use cases `CreerChantierUseCase`, `ArchiverChantierUseCase`…) mais **aucun document ne fixe la frontière** entre ce qui doit rester en français et ce qui reste en anglais. Trois ambiguïtés non couvertes :

1. **Verbes CRUD génériques** (`create` vs `creer`, `find` vs `obtenir`) : le code mixte `getChantierById` existe-t-il ? Non aujourd'hui, mais rien ne l'interdit explicitement.
2. **Accents dans les identifiers** : PHP 8 et TypeScript autorisent `class Détail` ou `function métré()`. Tolérer ces caractères crée des problèmes silencieux (diffs cassés, grep partiel, encoding Windows, autocomplete IDE, URLs de routes). Aucun fichier ne le formalise.
3. **Vocabulaire frontière** (`Status` vs `Statut`, `Owner` vs `Proprietaire`) : selon qu'on parle d'un champ technique générique ou d'un concept métier, le choix change. Sans règle, chaque story re-tranche.

Sans ADR, le risque est qu'à mesure que d'autres bounded contexts arrivent (Devis Phase 6, Métré AR Phase 5), un développeur de l'avenir — moi ou un futur recruté — introduise `Détail`, `métré` ou `getChantierById`, et que le code se diverge insidieusement.

## Decision

### Règle 1 — Partage FR / EN par couche

Le code suit un **partage par responsabilité**, pas un parti pris uniforme.

| Couche / nature | Langue | Exemples |
|---|---|---|
| Entités métier, value objects, agrégats | **FR** | `Chantier`, `ClientRef`, `Adresse`, `Surface`, `Metre` |
| Énums et valeurs métier | **FR** | `StatutChantier::EN_PREPARATION`, `EtatDevis::BROUILLON` |
| Use cases (applicatif) | **FR** sur le verbe métier | `CreerChantierUseCase`, `ArchiverChantierUseCase`, `LierClientUseCase` |
| Méthodes de transition métier sur entités | **FR** | `$chantier->lierClient()`, `$devis->signer()`, `$chantier->archiver()` |
| Champs d'entité métier (PHP, schémas Drizzle) | **FR** | `proprietaireId`, `creeLe`, `modifieLe`, `nomCache` |
| Routes API exposées | **FR** au pluriel | `/api/chantiers`, `/api/devis`, `/api/clients` |
| Tables PostgreSQL et colonnes | **FR** | `chantier`, `client`, `proprietaire_id`, `cree_le` |
| Repositories, providers, processors, factories | **EN** (primitives framework) + suffixe FR | `ChantierRepository`, `CreerChantierProcessor`, `ChantierFactory` |
| Interfaces de port | **EN** sur le suffixe | `ChantierRepositoryInterface` |
| Helpers techniques, utilitaires, types génériques | **EN** | `Maybe<T>`, `Result`, `dateFormatter`, `slugify` |
| Variables locales courtes, paramètres techniques | **EN** | `$result`, `$count`, `$now`, `$payload` |
| Tests, mocks, fixtures (noms de classes et méthodes) | **EN** sur le squelette, **FR** sur le sujet | `test_un_chantier_peut_etre_archive`, `ChantierFactory::createOne()` |
| Composants React, hooks, props | **EN** sur le squelette, **FR** sur le sujet métier | `useChantier()`, `<ChantierCard>`, prop `chantier` |
| Migrations Doctrine (méthodes du framework) | **EN** | `up()`, `down()`, `getDescription()` |
| Commentaires, messages d'erreur utilisateur, doc | **FR** | — |
| Messages d'exception techniques (dev-only) | **FR** acceptable | `TransitionStatutInvalideException` |

**Heuristique de décision en une phrase** : si le concept existe dans le THESAURUS ou pourrait y être ajouté, c'est du métier → FR. Si le concept est dans la documentation du framework (Symfony, API Platform, Doctrine, React, TanStack), c'est de la primitive → EN.

### Règle 2 — Pas d'accents ni de caractères spéciaux dans les identifiers

Aucun identifier (classe, méthode, variable, propriété, énum, constante, fichier, dossier, route, nom de table, nom de colonne, nom de migration, nom de test) ne doit contenir de caractère accentué, de cédille, de tréma, ou tout autre signe diacritique. Les caractères autorisés sont `[A-Za-z0-9_]` (plus `-` pour les noms de fichiers et de routes).

Translittération canonique à appliquer :

| Avec accent | Sans accent |
|---|---|
| `é`, `è`, `ê`, `ë` | `e` |
| `à`, `â`, `ä` | `a` |
| `î`, `ï` | `i` |
| `ô`, `ö` | `o` |
| `ù`, `û`, `ü` | `u` |
| `ç` | `c` |
| `œ` | `oe` |
| `æ` | `ae` |
| `ÿ` | `y` |

Les variantes en majuscules suivent la même règle : `É → E`, `À → A`, `Î → I`, `Ô → O`, `Ù → U`, `Ç → C`, `Œ → OE`, `Æ → AE`, `Ÿ → Y`.

Exemples : `Metre` (pas `Mètre`), `Detail` (pas `Détail`), `etat` (pas `état`), `creer` (pas `créer`), `cloturer` (pas `clôturer`), `recu` (pas `reçu`), `oeuvre` (pas `œuvre`).

Les accents restent **obligatoires** dans :

- Les chaînes de caractères destinées à l'affichage utilisateur (UI, messages d'erreur exposés, libellés)
- Les commentaires, docstrings, doc Markdown
- Les valeurs en base (un champ `nom` peut contenir `"Crémerie"`, c'est de la donnée, pas un identifier)
- Les fichiers de traduction et tout contenu i18n

### Règle 3 — Verbes : préférer le verbe métier au verbe générique quand il existe

Si le métier a un verbe spécifique pour une action, on l'utilise même si un verbe CRUD générique aurait suffi.

- `archiver` plutôt que `delete` quand l'archivage est un soft-delete avec sémantique métier
- `signer` plutôt que `update` quand on passe un devis en `signe`
- `relancer` plutôt que `notify` quand la relance est un acte commercial
- `cloturer` plutôt que `close`

Quand aucun verbe métier n'existe, on retombe sur les verbes CRUD anglais côté code framework (`find`, `save`) ou français côté use case (`Creer`, `Modifier`, `Supprimer`, `Lister`, `Obtenir` — choix déjà acté dans l'existant).

### Règle 4 — Pas de mélange FR/EN dans une même identifier composé

Interdit : `getChantierById`, `findDevisByOwner`, `archiverChantierAction`. Si la couche est technique → tout EN avec sujet FR en suffixe (`ChantierRepository::findById`). Si la couche est métier → tout FR sur les noms et concepts métier (`ListerChantierUseCase::execute`), avec l'exception assumée du verbe `execute()` qui reste EN parce qu'il vient d'une convention de framework (cf trade-offs ci-dessous).

### Règle 5 — Le THESAURUS est la source de vérité du vocabulaire métier

Avant d'introduire un terme métier nouveau dans le code, l'ajouter au [THESAURUS](../THESAURUS.md). Un terme qui n'y figure pas et qui apparaît en code est un bug de discipline.

## Consequences

### Bénéfices attendus

- **Ubiquitous language préservé** : un artisan peut lire un commit message, un nom de route ou un nom de classe et reconnaître son métier
- **Pas de traduction cognitive** : `lierClient` est immédiatement compris, pas `linkCustomer`
- **Diffs et grep propres** : pas de risque d'identifier invisible à un grep ASCII, pas d'encoding cassé sur un poste Windows ou dans un terminal mal configuré
- **Frontière claire** : la matrice de la Règle 1 permet de trancher sans débattre à chaque story
- **Cohérence multi-stack** : la même règle vaut pour Symfony, React, Expo, et le schéma SQL — un `Chantier` est un `Chantier` partout

### Coûts assumés

- **Identifiers parfois moches** : `cloturer` au lieu de `clôturer`, `etat` au lieu de `état`. Visuellement moins propre, mais traçable, greppable, portable. Trade-off assumé en faveur de l'outillage
- **Mémoire d'un mapping** : `Métré` (THESAURUS, doc, UI) ↔ `Metre` (code, classes, tables). Le mapping est mécanique (translittération) et déjà documenté ici
- **Risque de recrutement** : un développeur non francophone aura une courbe d'entrée plus raide sur les noms métier. Acceptable parce que (a) le marché cible est franco-français, (b) le THESAURUS sert de glossaire, (c) la couche technique reste en anglais et donne des points d'ancrage

### Trade-offs assumés

- **`Status` côté HTTP / framework reste anglais, `Statut` côté métier reste français**. Cohabitation explicite, pas une incohérence
- **Use cases avec verbe `execute()`** : exception au principe « tout FR dans le métier » parce que `execute` est le contrat implicite du pattern Use Case dans les frameworks PHP. Ne pas franciser en `executer` — la valeur d'avoir le même nom partout (et la même résonance avec les exemples Symfony/PHP) prime
- **Pas de migration de l'existant** : Phase 1 et 2 sont déjà conformes. Cet ADR fige la pratique ; il ne déclenche aucun refactor. Si un cas non conforme apparaît à la review, il se corrige sur place

### Application

- [docs/THESAURUS.md](../THESAURUS.md) — section « Règles » mise à jour pour pointer vers cet ADR et préciser la translittération
- [apps/api/CLAUDE.md](../../apps/api/CLAUDE.md) — la ligne « Identifiants métier en français » référence cet ADR
- Toute revue de PR (`/review`, `/ultrareview`, code review humaine) doit signaler les identifiers accentués comme bloquant
