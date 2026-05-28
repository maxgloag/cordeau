# CLAUDE.md — apps/api (Symfony 7.4 + API Platform 4)

## Stack

PHP 8.5 · Symfony 7.4 · API Platform 4 · Doctrine ORM + Migrations · PostgreSQL 18 · Redis 8 · Symfony Messenger · PHPUnit 12 · PHPStan 9

Fichier `.php-version` présent → Symfony CLI utilise automatiquement PHP 8.5 (pas le PHP 8.2 global).

## Commandes

```bash
symfony server:start --no-tls      # dev server sur http://localhost:8000
php bin/console cache:clear        # vider le cache
php bin/console debug:router       # routes enregistrées
php bin/phpunit                    # tous les tests
php bin/phpunit tests/Integration/ # tests d'intégration seuls
./vendor/bin/phpstan analyse       # analyse statique
./vendor/bin/deptrac analyse       # enforcement archi hexagonale (cf ADR 0018)
php bin/console doctrine:migrations:diff    # générer une migration
php bin/console doctrine:migrations:migrate # appliquer
php bin/console doctrine:database:create --env=test  # créer la DB de test
```

## Architecture hexagonale — structure cible

```
src/
├── Domain/
│   └── {BoundedContext}/
│       ├── Entity/          # entités métier (pas d'annotations Doctrine ici)
│       ├── ValueObject/     # value objects immuables
│       ├── Repository/      # interfaces UNIQUEMENT (ports)
│       └── Exception/       # exceptions métier
├── Application/
│   └── {BoundedContext}/
│       └── UseCase/         # un use case = une classe avec execute()
├── Infrastructure/
│   ├── Persistence/
│   │   └── Doctrine/        # adapters Doctrine (implémentent les ports)
│   │       ├── Entity/      # entités Doctrine (séparées du Domain si besoin)
│   │       └── Repository/  # implémentations des ports
│   ├── Http/                # clients HTTP externes
│   └── Storage/             # adapters storage (R2...)
└── Presentation/
    └── Api/
        └── {BoundedContext}/  # controllers + DTOs + StateProviders/Processors
```

**Règle absolue** : les dépendances pointent vers l'intérieur. `Domain/` ne connaît ni Doctrine, ni Symfony, ni rien d'externe. **Vérifiée mécaniquement par Deptrac** (`deptrac.yaml`) en CI : toute fuite de Doctrine / API Platform / framework Symfony dans `Domain/`, `Application/` ou `Shared/` casse le build (cf [ADR 0018](../../docs/adr/0018-documentation-architecture-as-code.md)). `symfony/uid` et `symfony/clock` sont tolérés (utilitaires de value objects). Les CRUD légers (Auth, Client) sont hors périmètre Deptrac, par design.

## Rigueur vs légèreté

**Full hexagonal** (domain riche, value objects, ports, tests unitaires sans DB) :
- `Lot` (modes de facturation, estimation/réel) · `Mesure` (calcul assisté manuel V1, AR V2) · génération `Devis` / `Facture` brouillon · règles TVA (Phase 8) · transitions de statut (cf [ADR 0015](../../docs/adr/0015-modele-chantier-lots-taches-mesures.md))

**Légèreté (controller → service → Doctrine directement, cf [ADR 0010](../../docs/adr/0010-crud-leger-pattern-reference.md))** :
- CRUD simples : clients, adresses, tags, photos, `Tache`, `Materiau`, `Pointage` (sans logique métier dense)

## Conventions PHP

- `declare(strict_types=1)` sur chaque fichier
- Types stricts partout — `readonly` sur les value objects et DTOs
- `final` par défaut sur les classes concrètes
- PHPStan niveau 9 doit passer sans `@phpstan-ignore`
- Pas de `public` properties — passer par le constructeur ou des méthodes nommées
- Identifiants métier en français, primitives de framework en anglais — partage par couche fixé par l'[ADR 0014](../../docs/adr/0014-naming-conventions-fr-en.md). Exemples : `Chantier`, `Client`, `Lot`, `Tache`, `Mesure`, `Materiau`, `Pointage`, `Devis`, `Facture`, `Avenant`, `lierClient()`, `StatutChantier::EN_PREPARATION`, `ModeFacturation::SURFACE`, `EtatMateriau::UTILISE` ; mais `ChantierRepository`, `CreerChantierProcessor`, `execute()`
- Pas d'accents dans les identifiers (classes, méthodes, champs, énums, fichiers, routes, tables, colonnes). `Metre`, pas `Mètre`. `cloturer`, pas `clôturer`. Détail des règles dans l'[ADR 0014](../../docs/adr/0014-naming-conventions-fr-en.md)

## Tests

- Tests unitaires dans `tests/Unit/` — aucun framework, aucune DB
- Tests d'intégration dans `tests/Integration/` — DB réelle, WebTestCase
- Tout bounded context complexe = tests unitaires sur le domaine
- Fixtures via Zenstruck Foundry (`tests/Factory/`)

## API Platform 4

- Ressources déclarées via `#[ApiResource]` sur les DTOs ou entités
- Processors et Providers pour brancher sur les use cases (pas de logique dans les controllers)
- OpenAPI auto-généré → `pnpm --filter @cordeau/shared generate` (à configurer Phase 1)
- Pas de Twig, pas de frontend Symfony — SPA React côté web

## Environnement

- `.env` : valeurs par défaut committées (sans secrets)
- `.env.local` : valeurs dev locales non committées (credentials DB, etc.)
- `.env.test.local` : credentials pour la DB de test
- En prod, les variables sont injectées par Coolify (pas de `.env.prod`)
