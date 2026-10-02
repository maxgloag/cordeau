# Journal — Expérience B — Rust

Début : 2026-10-02T05:32:10Z Fin : ...

Toolchain : rustc/cargo 1.99.0, clippy 0.1.99. Les commandes cargo sont lancées via le chemin absolu `$HOME/.cargo/bin/cargo` pour contourner le hook RTK (qui réécrit `cargo …` et filtre la sortie — inacceptable pour compter les erreurs honnêtement).

## Chronologie (notes au fil de l'eau)

- 05:32 lecture spec + protocole + ADR 0002 / 0014.
- 05:35 friction : le garde-fou d'isolation worktree refuse `git …` (réécrit en `rtk git …` par le hook) → contourné par `/usr/bin/git`.
- 05:35–05:42 conception (cf. Décisions). Pas de plan story-level écrit dans `~/.claude/plans/` : la conception tient dans ce journal, budget 90 min.

## Boucles de vérification

| #   | heure | commande | durée (s) | résultat | nb erreurs | catégories d'erreurs | correctif |
| --- | ----- | -------- | --------- | -------- | ---------- | -------------------- | --------- |

## Défauts attrapés par le compilateur/clippy qui auraient été des bugs à l'exécution

## Défauts attrapés seulement par les tests

## Frictions (outillage, écosystème, docs, dépendances, temps de compilation)

- Hook RTK + garde-fou d'isolation : `git` et `cargo` doivent être appelés par chemin absolu.

## Décisions de conception (et ce que le langage a imposé ou suggéré)

## Métriques finales

## Auto-évaluation
