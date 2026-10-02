<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\TauxTva;

/**
 * TVA d'un taux : base HT nette (après remise globale) et montant de TVA arrondi.
 */
final readonly class VentilationTva
{
    public function __construct(
        public TauxTva $taux,
        public Montant $baseHt,
        public Montant $montantTva,
    ) {
    }
}
