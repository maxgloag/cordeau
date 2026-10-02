<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

final readonly class RemiseGlobalePourcentage implements RemiseGlobale
{
    public function __construct(
        public Pourcentage $pourcentage,
    ) {
    }

    /**
     * remise_base = arrondi(base × p / 100), base par base.
     */
    public function ventiler(array $bases): array
    {
        return array_map(fn (BaseHt $base): Montant => Montant::arrondi($this->pourcentage->appliquerA($base->montant->valeur)), $bases);
    }

    /**
     * Un pourcentage est borné à 100 : la remise ne dépasse jamais le total.
     */
    public function depasse(Montant $totalHtBrut): bool
    {
        return false;
    }
}
