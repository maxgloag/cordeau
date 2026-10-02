# Protocole expérimental — Rust vs PHP en développement agentique

> Volet empirique de l'étude [« Rust et développement agentique »](../README.md). Objectif : compléter la littérature par des **mesures sur le code et le contexte réels de Cordeau**, avec le même modèle d'agent (Claude Opus 5.5 via Claude Code) des deux côtés.

## Hypothèses testées

- **H1 — Solidité** : à spec et effort égaux, du code Rust écrit par un agent contient moins de défauts que du code PHP (phpstan niveau 9) écrit par le même agent.
- **H2 — Boucle de feedback** : le compilateur Rust attrape en amont des erreurs que PHP ne révèle qu'aux tests (ou jamais), au prix de boucles de vérification plus longues ou plus nombreuses.
- **H3 — Friction d'écosystème** : sur une tâche typique de Cordeau (domaine + persistance Postgres), l'écosystème Rust impose un surcoût mesurable (dépendances, outillage DB, temps de compilation).

## Expériences

### Expérience A — Portage de la PR #105 en Rust (traduction)

Refaire en Rust le périmètre de la PR #105 (commit `6decdaf` : domaine Lot / Tâche, VO, ports, adapters de persistance Postgres, migration, 77 tests). La spec est le code PHP existant + [ADR 0015](../../../adr/0015-modele-chantier-lots-taches-mesures.md) + [ADR 0024](../../../adr/0024-affinements-modelisation-lot-tache-phase-6.md).

- Mesure principalement **H3** (friction SQLx/Postgres, temps de compilation, structuration du workspace hexagonal) et la part des tests PHP **rendus inutiles par le système de types**.
- Biais connu : traduire est plus facile que concevoir. Les résultats de vélocité ne sont **pas** comparables à la PR PHP d'origine.
- Contrainte d'environnement : Docker n'est pas utilisable dans la session (socket OrbStack d'un autre compte macOS), donc Postgres embarqué ou équivalent sans Docker.

### Expérience B — A/B en aveugle sur une spec neuve (pur domaine)

Même [spec](spec-totaux-devis.md) (totaux d'un devis : TVA multi-taux, remises, arrondis, franchise en base, validation exhaustive) implémentée **indépendamment** par deux agents :

- **B-PHP** : dans `apps/api`, en suivant l'architecture hexagonale du projet, phpstan niveau 9 + deptrac + PHPUnit ;
- **B-Rust** : workspace Cargo `spikes/rust-devis/`, clippy pedantic + lints restrictifs, tests.

Un troisième agent **oracle** écrit, sans voir les implémentations, une implémentation de référence en Python (`decimal`) et un jeu de **vecteurs de test cachés** (nominal, arrondis limites, ventilation de remise, franchise, erreurs combinées, bornes). Un agent **juge** exécute ensuite les vecteurs contre les deux CLI, arbitre les désaccords en relisant la spec (triangulation à 3 implémentations), lance du mutation testing (Infection / cargo-mutants) et une revue de code.

- Mesure **H1** (taux de réussite sur vecteurs cachés, défauts trouvés en revue, score de mutation) et **H2** (journal des boucles de vérification).

## Métriques relevées (journal de chaque agent implémenteur)

| Métrique                                  | Comment                                                                                                 |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Durée murale                              | horodatage début / fin (`date`)                                                                         |
| Boucles de vérification                   | chaque exécution de compilateur / analyse statique / tests : durée, nb d'erreurs, catégories            |
| Erreurs attrapées statiquement            | erreurs de compilation / phpstan qui auraient été des bugs à l'exécution                                |
| Erreurs attrapées par les tests seulement |                                                                                                         |
| Frictions                                 | outillage, dépendances, documentation, environnement                                                    |
| Taille                                    | lignes src / tests, nombre de tests                                                                     |
| Vitesse de boucle                         | build à froid, check incrémental après modification d'une ligne (3 mesures), durée de la suite de tests |
| Dépendances                               | directes / transitives                                                                                  |
| Auto-évaluation                           | confiance 0-10, zones de doute                                                                          |

## Limites assumées

- n = 1 par cellule : ce sont des **indices**, pas une preuve statistique.
- Même modèle, mêmes consignes, mais les agents ne sont pas déterministes.
- Les mesures de temps sont bruitées : plusieurs agents tournent en parallèle sur la même machine (12 cœurs, 18 Go).
- La spec B est volontairement « piégeuse » (arrondis, validation) : elle favorise les langages qui rendent l'arithmétique décimale et la validation explicites. C'est représentatif des Phases 7-8 de Cordeau (Mesures, Devis, Factures), pas du CRUD léger.
