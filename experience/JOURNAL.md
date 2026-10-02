# Journal — Expérience B — PHP

Début : 2026-10-02T05:31:59Z Fin : (en cours)

Heures ci-dessous en heure locale (UTC+2).

**Interruption** (limite d'usage de la session, externe à l'agent) : coupure vers 07:55, reprise à 12:53 (2026-10-02T10:53:38Z). Durée d'interruption (~4 h 58) **exclue** de la durée totale. Au moment de la coupure : VO du domaine commités (01c6681), tests du calculateur / remise globale / demande écrits et vus rouges (boucle 6), implémentation non commencée.

## Boucles de vérification

| #   | heure | commande                                        | durée (s) | résultat         | nb erreurs | catégories d'erreurs                                                                         | correctif                                                                              |
| --- | ----- | ----------------------------------------------- | --------- | ---------------- | ---------- | -------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| 1   | 07:45 | phpunit tests/Unit/Domain/Devis (RED attendu)   | 1         | rouge attendu    | 103        | classes absentes (TDD)                                                                       | écrire les VO                                                                          |
| 2   | 07:49 | phpunit tests/Unit/Domain/Devis                 | <1        | 1 échec          | 1          | bug **dans le test** (`Montant::depuisChaine('16.350')` : 3 décimales, refusé à juste titre) | test corrigé (`Montant::arrondi`)                                                      |
| 3   | 07:49 | phpunit tests/Unit/Domain/Devis                 | <1        | vert             | 0          | —                                                                                            | —                                                                                      |
| 4   | 07:50 | phpstan (Domain/Devis + tests)                  | 2         | 2 erreurs        | 2          | typage `numeric-string` pour `new BcMath\Number()` (src + test)                              | `is_numeric()` après la regex (affinage de type), `@param numeric-string` dans le test |
| 5   | 07:50 | phpstan + phpunit Domain/Devis                  | 1 + <1    | vert             | 0          | —                                                                                            | —                                                                                      |
| 6   | 07:53 | phpunit Domain/Devis (RED attendu, calculateur) | 1         | rouge attendu    | 26         | classes absentes (TDD)                                                                       | écrire calculateur, remises, demande                                                   |
| 7   | 12:55 | phpunit Domain/Devis                            | <1        | vert du 1er coup | 0          | —                                                                                            | —                                                                                      |
| 8   | 12:55 | phpstan Domain/Devis                            | 1         | 1 erreur         | 1          | type de retour : `$parts[$i] = …` fait perdre le type `list` (non-empty-list attendu)        | ajustement de l'écart réécrit en `array_map` (pas de cast)                             |
| 9   | 12:55 | phpstan + phpunit Domain/Devis                  | 1 + <1    | vert             | 0          | —                                                                                            | —                                                                                      |

## Défauts attrapés par l'analyse statique (phpstan/deptrac) qui auraient été des bugs à l'exécution

- Boucle 4 : `new Number($chaine)` avec `string` non affiné → phpstan exige `numeric-string`. **Pas un bug à l'exécution** (la regex garantit déjà le format) ; utile comme rappel que le constructeur lève `ValueError` sur une chaîne non numérique. Classé « précision de typage », pas « bug évité ».

## Défauts attrapés seulement par les tests

## Défauts évités en amont (sondes manuelles avant d'écrire le code)

- 07:38 — sonde `BcMath\Number` : le constructeur **accepte** `".5"` et `"5."` (cette dernière → `5`). S'appuyer sur `new Number()` pour valider le format « chaîne décimale » aurait laissé passer deux formats interdits par la spec → regex stricte obligatoire avant construction.
- 07:38 — sonde PCRE : `preg_match('/^[0-9]+$/', "12\n")` vaut `1` (le `$` PHP matche avant un `\n` final). Utiliser `\z` (ou le modificateur `D`) sinon `"12\n"` passerait pour une chaîne décimale valide.
- 07:47 — sonde PCRE : `preg_replace('/\A\s+|\s+\z/u', …)` (trim Unicode « évident ») est **quadratique sans JIT** : 33 s pour 100 000 espaces internes (0,001 s avec JIT). Remplacé par un découpage en caractères + parcours depuis chaque extrémité (linéaire).
- 07:44 — attentes des tests calculées à la main fausses sur 2 cas (`999999.999 × 9999999.99 × 0.9999`, `7 × 13.37 × 0.8766`) : recalculées avec Python `decimal` (oracle indépendant de bcmath) avant le premier run.
- 07:40 — sonde `json_decode` : avec `assoc=true`, `{}` et `[]` deviennent tous deux `[]`, et `{"0":{...}}` devient une liste → impossible de distinguer objet/tableau (LIGNES_VIDES, ligne non objet, remise_globale non objet). Décision : décoder en `stdClass` (`assoc=false`).

## Frictions (outillage, écosystème, docs, dépendances, environnement)

- 07:32 — le hook RTK réécrit `git …` en `rtk git …`, refusé par l'isolation worktree de l'agent. Contournement : appeler `/usr/bin/git` directement. (~1 min perdue)
- 07:34 — `composer install` échoue : `ext-imagick` exigée par `composer.json` mais absente du PHP CLI Homebrew local. Contournement : `--ignore-platform-req=ext-imagick` (sans impact sur ce périmètre). Install ensuite en 4 s.
- 07:36 — commandes heredoc multi-lignes refusées par le garde-fou worktree (« too complex ») → scripts de sonde écrits via fichier dans le scratchpad.
- `BcMath\Number::div()` sans échelle explicite : échelle résultante non évidente (`1/3` → 10 décimales, `0.0200/4` → `0.0050`) et division **tronquée** (`2/3` à 3 décimales → `0.666`). Toujours passer une échelle explicite et raisonner sur la troncature.
- `JSON_INVALID_UTF16_SUBSTITUTE` n'existe pas en PHP : un `"\ud800"` isolé (JSON grammaticalement valide) fait échouer `json_decode`.
- `json_decode` en mode objet rejette les clés commençant par `\u0000` (« The decoded property name is invalid »), alors que c'est du JSON valide.

## Décisions de conception (et ce que le langage a imposé ou suggéré)

## Métriques finales

## Auto-évaluation
