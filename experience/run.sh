#!/usr/bin/env bash
# Expérience B — wrapper CLI de l'implémentation PHP des totaux de devis.
# Lit la demande JSON sur stdin, écrit le résultat JSON sur stdout.
# Code de sortie : 0 succès, 1 erreurs de validation, 2 erreur interne.
# Sans base de données ni Docker : la commande Symfony ne touche à aucun service externe.
set -euo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ ! -f "${racine}/apps/api/vendor/autoload.php" ]]; then
    echo "Dépendances absentes : lancer 'composer install --ignore-platform-req=ext-imagick' dans apps/api." >&2
    exit 2
fi

exec php "${racine}/apps/api/bin/console" app:experience:totaux-devis --no-interaction
