# Fixtures de conformité

Oracle commun des règles métier dupliquées entre l'API (PHP), le web (TypeScript) et le mobile (TypeScript). Chaque règle a un fichier JSON `<regle>.json` qui liste des cas `entrée → valide attendu`. L'API est la référence : un cas décrit ce que l'API accepte ou refuse.

## Format

```json
{
  "regle": "adresse.codePostal",
  "description": "Code postal : 5 chiffres en France ; ailleurs, obligatoire et 20 caractères au plus.",
  "reference": ["apps/api/src/Shared/ValueObject/Adresse.php"],
  "cas": [
    {
      "nom": "5 chiffres en France",
      "entree": { "codePostal": "75011", "pays": "FR" },
      "valide": true
    }
  ]
}
```

- `regle` : identifiant `contexte.champ`, identique au nom du fichier.
- `reference` : fichiers de l'API qui portent la règle.
- `entree` : objet de valeurs JSON, nommées comme les champs de l'API.
- `valide` : `true` si l'API accepte l'entrée, `false` si elle la refuse (exception de domaine ou violation de contrainte).

## Runners

Chaque plateforme lit tous les fichiers du dossier et échoue si une règle n'a pas d'implémentation chez elle :

| Plateforme | Runner                                                      | Implémentation testée                                     |
| ---------- | ----------------------------------------------------------- | --------------------------------------------------------- |
| API        | `apps/api/tests/Unit/Conformite/ConformiteFixturesTest.php` | payloads (contraintes Symfony) + value objects, sans base |
| Web        | `apps/web/src/lib/regles-metier.conformite.test.ts`         | `apps/web/src/lib/regles-metier.ts`                       |
| Mobile     | `apps/mobile/__tests__/regles-metier.conformite.test.ts`    | `apps/mobile/lib/regles-metier.ts`                        |

## Ajouter ou modifier une règle

1. Écrire ou modifier le fichier JSON, en partant du comportement de l'API.
2. Brancher la règle dans les trois runners (sinon ils échouent).
3. Les trois suites doivent passer : un cas rouge sur un client est une divergence à corriger côté client.
