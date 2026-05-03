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

**Règle absolue** : les dépendances pointent vers l'intérieur. `Domain/` ne connaît ni Doctrine, ni Symfony, ni rien d'externe.

## Rigueur vs légèreté

**Full hexagonal** (domain riche, value objects, ports, tests unitaires sans DB) :
- Mesure AR · calcul de métré · génération de devis · règles de facturation/TVA

**Légèreté (controller → service → Doctrine directement)** :
- CRUD simples : clients, adresses, tags, photos (sans logique métier)

## Conventions PHP

- `declare(strict_types=1)` sur chaque fichier
- Types stricts partout — `readonly` sur les value objects et DTOs
- `final` par défaut sur les classes concrètes
- PHPStan niveau 9 doit passer sans `@phpstan-ignore`
- Pas de `public` properties — passer par le constructeur ou des méthodes nommées
- Identifiants métier en français : `Chantier`, `Client`, `Devis`, `Facture`, `Metrage`

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
