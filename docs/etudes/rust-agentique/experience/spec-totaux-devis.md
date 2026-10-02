# Spec d'expérience — Calcul des totaux d'un devis

> **Spec non normative**, écrite uniquement pour l'expérience A/B PHP vs Rust de l'étude « Rust et développement agentique ». Elle **ne préjuge pas** de la modélisation du Devis en Phase 8 (qui fera l'objet de son propre ADR). Les règles fiscales sont simplifiées mais réalistes pour un artisan du bâtiment français.

## Objectif

Implémenter une fonction pure de domaine qui, à partir d'une demande de devis (lignes, remise globale, régime de TVA, acompte), **valide** l'entrée et **calcule** les totaux. L'implémentation est exposée par une commande en ligne (CLI) qui lit un JSON sur l'entrée standard et écrit un JSON sur la sortie standard. **Le calcul doit être exact au centime** : aucun nombre flottant binaire (`float`/`f64`) ne doit intervenir dans les montants.

## Entrée (JSON sur stdin)

```json
{
  "regime_tva": "NORMAL",
  "lignes": [
    {
      "designation": "Pose carrelage sol",
      "quantite": "12.5",
      "unite": "M2",
      "prix_unitaire_ht": "45.90",
      "taux_tva": "10",
      "remise_pct": "5"
    }
  ],
  "remise_globale": { "type": "POURCENTAGE", "valeur": "3" },
  "acompte_pct": "30"
}
```

| Champ                       | Type                                                        | Règles                                                                            |
| --------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `regime_tva`                | `"NORMAL"` \| `"FRANCHISE_EN_BASE"`                         | obligatoire                                                                       |
| `lignes`                    | tableau                                                     | au moins 1 ligne                                                                  |
| `lignes[].designation`      | chaîne                                                      | non vide après suppression des espaces de début/fin                               |
| `lignes[].quantite`         | chaîne décimale                                             | strictement > 0, **au plus 3 décimales**                                          |
| `lignes[].unite`            | `"U"` \| `"M2"` \| `"ML"` \| `"M3"` \| `"H"` \| `"FORFAIT"` | obligatoire                                                                       |
| `lignes[].prix_unitaire_ht` | chaîne décimale                                             | ≥ 0, **au plus 2 décimales**                                                      |
| `lignes[].taux_tva`         | `"0"` \| `"5.5"` \| `"10"` \| `"20"`                        | obligatoire (chaîne exacte parmi ces 4 valeurs)                                   |
| `lignes[].remise_pct`       | chaîne décimale, optionnelle                                | absente = 0 ; entre 0 et 100 inclus, **au plus 2 décimales**                      |
| `remise_globale`            | objet, optionnel                                            | absent = pas de remise                                                            |
| `remise_globale.type`       | `"POURCENTAGE"` \| `"MONTANT"`                              |                                                                                   |
| `remise_globale.valeur`     | chaîne décimale                                             | POURCENTAGE : entre 0 et 100 inclus, ≤ 2 décimales ; MONTANT : ≥ 0, ≤ 2 décimales |
| `acompte_pct`               | chaîne décimale, optionnelle                                | absente = 0 ; entre 0 et 100 inclus, ≤ 2 décimales                                |

Format d'une « chaîne décimale » : `^[0-9]+(\.[0-9]+)?$` (pas de signe, pas d'exposant, pas d'espaces ; `"0"`, `"12.5"`, `"0.125"`, `"007"` sont valides ; `".5"`, `"5."`, `"-1"`, `"1e3"`, `"1,5"`, `" 1"` ne le sont pas). Un nombre JSON (non chaîne) là où une chaîne décimale est attendue est invalide. Les zéros finaux comptent dans les décimales : `"1.500"` a 3 décimales.

Bornes supérieures : `quantite` ≤ 1 000 000 et `prix_unitaire_ht` ≤ 10 000 000 ; au-delà, le code d'erreur du champ s'applique (`QUANTITE_INVALIDE`, `PRIX_INVALIDE`).

Valeur `null` : pour les champs **optionnels** (`remise_pct`, `remise_globale`, `acompte_pct`), `null` équivaut à « absent ». Pour les champs **obligatoires**, `null` est invalide (code du champ).

## Règles de calcul

Tous les arrondis sont **au centime, demi-supérieur** (`ROUND_HALF_UP` : 0,005 → 0,01). Les calculs intermédiaires sont exacts (décimal arbitraire), seul l'arrondi explicitement indiqué intervient.

1. **Montant HT d'une ligne** = arrondi( `quantite × prix_unitaire_ht × (1 − remise_pct / 100)` ).
2. **Total HT brut** = somme des montants HT des lignes.
3. **Bases HT par taux** : somme des montants HT des lignes de même `taux_tva`.
4. **Remise globale** :
   - `POURCENTAGE` p : pour chaque base, `remise_base = arrondi(base × p / 100)`. Remise globale = somme des `remise_base`.
   - `MONTANT` M : pour chaque base, `part = arrondi(M × base / total_ht_brut)` ; l'écart `M − Σ part` est ajouté à la part de la **base la plus élevée** (à égalité, celle du **taux le plus élevé**). Remise globale = M. Si `total_ht_brut = 0` et `M = 0`, toutes les parts valent 0.
   - Absente : remise globale = 0.
   - `base_nette = base − part` pour chaque taux.
5. **Total HT net** = total HT brut − remise globale.
6. **TVA par taux** (régime NORMAL) : `montant_tva = arrondi(base_nette × taux / 100)`. La TVA est calculée **par base**, jamais par ligne.
7. **Total TVA** = somme des montants de TVA. **Total TTC** = total HT net + total TVA.
8. **Acompte** = arrondi( `total_ttc × acompte_pct / 100` ). **Solde** = total TTC − acompte.
9. **Régime FRANCHISE_EN_BASE** (auto-entrepreneur, art. 293 B du CGI) : toutes les lignes doivent avoir `taux_tva = "0"` ; la ventilation de TVA est **vide**, total TVA = 0, et `mention_tva` vaut exactement `"TVA non applicable, art. 293 B du CGI"`. En régime NORMAL, `mention_tva` vaut `null`, et le taux `"0"` est autorisé (ex. autoliquidation de la sous-traitance BTP) : il apparaît alors dans la ventilation avec un montant de TVA nul.

## Sortie (JSON sur stdout)

En cas de succès :

```json
{
  "ok": {
    "lignes": [{ "montant_ht": "545.06" }],
    "total_ht_brut": "545.06",
    "remise_globale": "16.35",
    "total_ht_net": "528.71",
    "ventilation_tva": [
      { "taux": "10", "base_ht": "528.71", "montant_tva": "52.87" }
    ],
    "total_tva": "52.87",
    "total_ttc": "581.58",
    "acompte": "174.47",
    "solde": "407.11",
    "mention_tva": null
  }
}
```

- Tous les montants sont des **chaînes avec exactement 2 décimales** (`"0.00"`, `"1234.50"`), sans séparateur de milliers.
- `lignes` : dans l'ordre d'entrée.
- `ventilation_tva` : uniquement les taux présents dans les lignes, triés par taux croissant (`"0"`, `"5.5"`, `"10"`, `"20"`), `base_ht` = **base nette** (après remise globale), `taux` = la chaîne d'entrée.

En cas d'entrée invalide :

```json
{
  "erreurs": [
    { "code": "QUANTITE_INVALIDE", "ligne": 0 },
    { "code": "ACOMPTE_INVALIDE", "ligne": null }
  ]
}
```

- **Toutes** les erreurs détectables sont rapportées (pas d'arrêt à la première). L'ordre n'a pas d'importance (comparaison ensembliste). `ligne` = index (base 0) pour une erreur de ligne, `null` sinon.
- Codes :

| Code                         | Cas                                                                                                                                                                                                                                                                                |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `JSON_INVALIDE`              | entrée non parsable en JSON, ou racine non objet (seule erreur rapportée dans ce cas)                                                                                                                                                                                              |
| `REGIME_INVALIDE`            | `regime_tva` absent ou hors enum                                                                                                                                                                                                                                                   |
| `LIGNES_VIDES`               | `lignes` absent, non tableau ou vide                                                                                                                                                                                                                                               |
| `DESIGNATION_VIDE`           | désignation absente, non chaîne ou vide après trim                                                                                                                                                                                                                                 |
| `QUANTITE_INVALIDE`          | quantité absente, mal formée, ≤ 0 ou > 3 décimales                                                                                                                                                                                                                                 |
| `UNITE_INVALIDE`             | unité absente ou hors enum                                                                                                                                                                                                                                                         |
| `PRIX_INVALIDE`              | prix absent, mal formé ou > 2 décimales                                                                                                                                                                                                                                            |
| `TAUX_INVALIDE`              | taux absent ou hors des 4 chaînes autorisées                                                                                                                                                                                                                                       |
| `REMISE_LIGNE_INVALIDE`      | remise de ligne mal formée, > 100 ou > 2 décimales                                                                                                                                                                                                                                 |
| `FRANCHISE_AVEC_TVA`         | régime FRANCHISE_EN_BASE et ligne avec un taux valide différent de `"0"`                                                                                                                                                                                                           |
| `REMISE_GLOBALE_INVALIDE`    | objet mal formé, type hors enum, valeur mal formée, > 2 décimales, ou pourcentage > 100                                                                                                                                                                                            |
| `REMISE_SUPERIEURE_AU_TOTAL` | remise MONTANT valide > total HT brut — vérifiée **uniquement** si `lignes` est un tableau non vide et qu'**aucune erreur portant un index de ligne** n'a été détectée (y compris `FRANCHISE_AVEC_TVA`) ; elle est indépendante de la validité de `regime_tva` et de `acompte_pct` |
| `ACOMPTE_INVALIDE`           | acompte mal formé, > 100 ou > 2 décimales                                                                                                                                                                                                                                          |

- Une ligne qui n'est pas un objet JSON produit `DESIGNATION_VIDE`, `QUANTITE_INVALIDE`, `UNITE_INVALIDE`, `PRIX_INVALIDE` et `TAUX_INVALIDE` pour cet index.
- Les champs inconnus sont ignorés.
- Code de sortie du processus : 0 en cas de succès, 1 en cas d'erreurs de validation.

## Exemple vérifié (celui de la sortie ci-dessus)

Ligne : 12,5 × 45,90 = 573,75 ; × 0,95 = 545,0625 → **545,06**. Remise 3 % de la base 10 % : 545,06 × 0,03 = 16,3518 → **16,35**. Base nette 528,71. TVA 10 % : 52,871 → **52,87**. TTC 581,58. Acompte 30 % : 174,474 → **174,47**. Solde 407,11.
