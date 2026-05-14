# Conventions Cordeau

Source canonique : `CLAUDE.md` racine + un par app.

## Langue

Tout en français : UI, docs, identifiants métier (`Chantier`, `Devis`, `Client`, `Adresse`, `Surface`, `Statut`). Les mots-clés de programmation et noms de libs restent en anglais. Pas d'accents dans les noms de symboles (`creer`, `archiver`, pas `créer`/`archiver`).

## Commits — Conventional Commits

```
type(scope): subject
```

Types autorisés : `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `perf`, `ci`, `build`.

Scope = nom d'app (`api`, `web`, `mobile`, `infra`, `ci`) ou bounded context (`chantier`, `client`, `auth`, `photo`, `devis`, `metre-ar`).

**Pas de trailer `Co-Authored-By:`** dans les commits Cordeau (préférence Maxime).

Exemples :
- `feat(chantier): add archivage use case`
- `fix(api): handle null adresse on creation`
- `chore(ci): cache composer downloads`

## Branches

- `feat/<issue-number>-<slug>` (ex. `feat/12-creation-chantier`)
- `fix/<issue-number>-<slug>`
- `chore/<slug>` (sans issue si purement local)

Squash merge sur `main`. `main` est protégée : PR obligatoire, CI verte requise.

## Tests

- Domaine riche → unitaires sans DB
- Use cases → intégration avec DB
- API → intégration HTTP avec WebTestCase + Foundry (attention à l'ordre boot kernel / Foundry)
- Pas de feature sans test sur le domaine au minimum

## ADRs

Toute décision structurante → ADR dans `docs/adr/NNNN-titre-court.md` **avant** le code. Index dans `docs/adr/README.md`.

## Workflow

- Issues GitHub : 1 par story produit, milestones par phase
- CI : hook `ci-watch.sh` surveille les runs post-push automatiquement (ne pas poller manuellement)
- Démo perso vendredi : lancer l'app comme un user

## Pas de hacks

Si quelque chose ne marche pas, **consulter Context7 MCP** avant de coder un workaround. Pas de patch silencieux d'une lib externe. Voir feedback memory `feedback_no_hacks` côté Claude Code.

## Dépendances workspace pnpm

Unifier les versions sur la plus récente (React, etc.) plutôt que patcher localement. Toujours relancer `pnpm install` et commiter `pnpm-lock.yaml` après tout changement de specifier.
