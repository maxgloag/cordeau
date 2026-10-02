<?php

declare(strict_types=1);

namespace App\Domain\Devis\Enum;

use App\Domain\Devis\ValueObject\Pourcentage;

/**
 * Taux de TVA applicables à une ligne de devis. La valeur est la chaîne exacte
 * attendue en entrée et restituée en sortie ("5.50" ou "20.0" ne sont pas des taux).
 */
enum TauxTva: string
{
    case ZERO = '0';
    case REDUIT = '5.5';
    case INTERMEDIAIRE = '10';
    case NORMAL = '20';

    public function pourcentage(): Pourcentage
    {
        return Pourcentage::depuisChaine($this->value);
    }

    /**
     * @return list<self> du taux le plus bas au plus élevé
     */
    public static function parOrdreCroissant(): array
    {
        $taux = self::cases();
        usort($taux, static fn (self $a, self $b): int => $a->compare($b));

        return $taux;
    }

    /**
     * Comparaison numérique des taux : < 0, 0 ou > 0.
     */
    public function compare(self $autre): int
    {
        return $this->pourcentage()->valeur->compare($autre->pourcentage()->valeur);
    }
}
