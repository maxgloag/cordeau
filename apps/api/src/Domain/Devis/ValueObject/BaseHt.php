<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\TauxTva;

/**
 * Somme des montants HT des lignes d'un même taux de TVA.
 */
final readonly class BaseHt
{
    public function __construct(
        public TauxTva $taux,
        public Montant $montant,
    ) {
    }
}
