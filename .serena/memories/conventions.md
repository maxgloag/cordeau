# Conventions Cordeau

Source canonique : `CLAUDE.md` racine + un par app.

## Langue

Tout en français : UI, docs, identifiants métier (`Chantier`, `Devis`, `Facture`, `Client`, `Adresse`, `Surface`, `Statut`, `Lot`, `Tache`, `Mesure`, `Materiau`, `Pointage`, `Imprevu`, `Avenant`). Les mots-clés de programmation et noms de libs restent en anglais. **Pas d'accents dans les noms de symboles** ([ADR 0014](../../docs/adr/0014-naming-conventions-fr-en.md)) : `creer`, `Tache`, `Materiau`, `Imprevu`, `Pre-document` (le code), `Tâche`, `Matériau`, `Imprévu` (l'UI et la doc).

Heuristique : si le terme est dans [docs/THESAURUS.md](../../docs/THESAURUS.md), il est en FR dans le code. Avant d'introduire un terme métier nouveau dans le code : l'ajouter au thésaurus.

## Commits — Conventional Commits

```
type(scope): subject
```

Types autorisés : `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `perf`, `ci`, `build`.

Scope = nom d'app (`api`, `web`, `mobile`, `infra`, `ci`) ou bounded context (`chantier`, `client`, `auth`, `photo`, `lot`, `mesure`, `materiau`, `devis`, `facture`).

**Pas de trailer `Co-Authored-By:`** dans les commits Cordeau (préférence Maxime).

Exemples :

- `feat(chantier): add archivage use case`
- `feat(lot): introduire ModeFacturation et Estimation`
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

Toute décision structurante → ADR dans `docs/adr/NNNN-titre-court.md` **avant** le code. Index dans `docs/adr/README.md`. Section « Implications sécurité » obligatoire si auth/sessions/tokens, permissions/RBAC, secrets, données personnelles, stockage de fichiers, données financières, dépendance externe.

## Workflow

- Issues GitHub : 1 par story produit, milestones par phase
- **Fermeture d'issue** : utiliser le mot-clé **anglais** `Closes #N` (ou `Fixes`/`Resolves`) dans le corps de la PR — le « Ferme #N » français **n'auto-ferme pas**. Sinon fermer à la main + vérifier (`gh issue list --milestone ... --state open`)
- CI : `gh run watch <RUN_ID> --exit-status` lancé en `run_in_background` après chaque push (le hook ci-watch.sh ne se déclenche pas dans Claude Code actuel). Validation finale = **`gh pr checks <pr>`**, jamais le seul exit code d'un `gh run watch` (un run-id ne couvre pas tous les workflows)
- Démo perso vendredi : lancer l'app comme un user

## Trajectoire V1 manuelle → V1.2+ magie ([ADR 0017](../../docs/adr/0017-differer-ia-validation-manuelle.md))

**Aucune dépendance LLM en V1.** Avant toute proposition de feature « IA / vocale / structuration automatique / chrono auto / détection risques », vérifier la trajectoire dans [ROADMAP.md](../../ROADMAP.md) :

- V1 (Phases 5-9) : manuel, pas de LLM, pas de coût variable IA
- V1.1 : UX fluidifiée, Whisper local (transcription brute offline) possible sans nouvel ADR
- V1.2 : magie LLM (structuration, récap, descriptifs, chrono geofencé) **conditionnée au critère bêta levé** — nouvel ADR requis sur stack LLM
- V1.3 : intelligence personnelle (planning, stats, stock van)
- V2 : AR comme source de Mesure, client dans expérience, partenariat PDP

**Critère de validation V1.2** (porte d'entrée magie LLM) : 3/5 testeurs disent gagner du temps en saisie manuelle, 2/5 payent, aucun feedback récurrent « la dictée vocale aurait sauvé ce truc ».

## Pas de hacks

Si quelque chose ne marche pas, **consulter Context7 MCP** avant de coder un workaround. Pas de patch silencieux d'une lib externe. Voir feedback memory `feedback_no_hacks` côté Claude Code.

## Dépendances workspace pnpm

Unifier les versions sur la plus récente (React, etc.) plutôt que patcher localement. Toujours relancer `pnpm install` et commiter `pnpm-lock.yaml` après tout changement de specifier.

**Montée de SDK Expo** : les peer deps natives transitives (reanimated, worklets, screens...) ne se bumpent pas seules → après chaque SDK bump, les comparer à `node_modules/expo/bundledNativeModules.json` et `npx expo install <lib>` celles qui divergent, sinon résidu de l'ancien SDK = **build natif cassé** (invisible en `expo export`). Cas vécu : reanimated 3.17.5 (résidu SDK 54) a cassé le build iOS Phase 5.

## Bug-fix : protocole double-fix

Tout bug hors dev local = deux bugs en un (code + système de test qui l'a laissé passer). Section « Audit système de test » obligatoire dans les PR `fix:` correspondantes (cf `CLAUDE.md` racine).
