# 0009 — Adoption de Serena (MCP) pour l'outillage sémantique

- **Status** : Accepted
- **Date** : 2026-05-14
- **Deciders** : Maxime

## Context

À l'entrée de Phase 2, le repo Cordeau contient déjà trois codebases hétérogènes (Symfony/PHP, React/TS, Expo/TS) structurées en architecture hexagonale (cf. [ADR 0002](0002-architecture-hexagonale.md)). Les opérations courantes — renommer un port, retrouver tous les call sites d'un use case, naviguer entre Domain/Infrastructure/Application — se font aujourd'hui à coups de `grep` + `Read` complet de fichiers.

Cette approche pose trois problèmes qui vont s'amplifier au fil des phases :

1. **Faux positifs grep** : `Chantier` matche aussi les commentaires, strings, tests. Un renommage manuel rate des cas ou en casse d'autres.
2. **Lecture coûteuse en tokens** : Read d'un fichier complet pour ne consulter qu'une méthode brûle du contexte. Critique sur les fichiers riches (use cases avec dépendances injectées, controllers API Platform).
3. **Pas de "find references" fiable** : impossible de répondre proprement à "qui appelle `ChantierRepository::save` ?" sans lire chaque fichier candidat.

Phase 2 (verticale Clients) implique justement de **dupliquer puis généraliser** les patterns de Phase 1 — c'est-à-dire beaucoup de navigation symbolique et de petits refactors transverses.

### Options évaluées

| Option                                        | Verdict                                                                                                                                                                            |
| --------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Statu quo** (Grep + Read + Edit)            | Fonctionne, mais coûteux en tokens et fragile sur les refactors transverses. Acceptable Phase 1 (création), insuffisant Phase 2+ (évolution)                                       |
| Outils CLI dédiés (ast-grep, tree-sitter CLI) | Puissants mais non intégrés à Claude Code, friction à chaque usage                                                                                                                 |
| **Serena MCP**                                | Plugin MCP open-source d'Oraios AI exposant des outils sémantiques pilotés par Language Server (Intelephense pour PHP, ts-language-server pour TS). Intégration native Claude Code |

## Decision

Adopter **Serena** comme couche sémantique par défaut pour la navigation et les refactors de code, en complément (pas en remplacement) des outils Read/Edit/Grep.

Le projet sera activé via `.serena/project.yml` versionné dans le repo, avec :

- Langages déclarés : PHP (apps/api), TypeScript (apps/web, apps/mobile, packages/shared)
- Dossiers ignorés : `node_modules`, `vendor`, `var`, `.turbo`, `.wrangler`, `dist`, `build`
- Onboarding lancé une fois pour générer les memories Serena sur l'archi hexagonale et les bounded contexts

### Règles d'usage

- **Refactors transverses** (rename, extract, move) → outils Serena (`rename_symbol`, `find_referencing_symbols`)
- **Navigation pour comprendre** (find symbol, list refs) → outils Serena en priorité
- **Lecture pour éditer un bloc précis** (5-30 lignes) → Read + Edit classique
- **Recherche texte plein** (commentaire, string, doc) → `grep` reste plus pertinent que les outils symboliques

Les memories Serena (`.serena/memories/`) sont versionnées : elles font partie de la documentation projet au même titre que `CLAUDE.md`.

## Consequences

### Bénéfices attendus

- Refactors transverses fiables (rename d'un port, déplacement d'un use case) sans grep manuel
- Économie de tokens sur la navigation de fichiers complexes (utiliser `get_symbols_overview` plutôt que Read complet)
- Diagnostics LSP directement accessibles (`get_diagnostics_for_file`) sans avoir à lancer le compilateur
- Onboarding plus rapide pour futurs contributeurs : Serena indexe et résume l'archi à la demande

### Coûts assumés

- **Dépendance externe supplémentaire** : si le plugin Serena casse ou n'est plus maintenu, fallback sur Grep/Read (zéro impact runtime du projet — Serena est un outil d'agent, pas une dépendance applicative)
- **Discipline d'usage** : il faut résister à la tentation de tout faire avec Serena. Pour éditer 3 lignes dans une méthode, `Edit` reste plus simple que `replace_symbol_body`
- **Coût d'indexation initiale** : l'onboarding parcourt le repo (~quelques minutes). Re-run nécessaire après changements structurels majeurs

### Risques résiduels

- Les Language Servers peuvent être à la traîne sur les versions très récentes (PHP 8.5, TS 5.x). À surveiller, fallback grep si l'indexation devient bruyante
- Les memories Serena peuvent diverger du code si pas mises à jour. Convention : mettre à jour les memories au même moment que `CLAUDE.md` à chaque fin de phase
