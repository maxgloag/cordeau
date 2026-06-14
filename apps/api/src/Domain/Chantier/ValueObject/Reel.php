<?php

declare(strict_types=1);

namespace App\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\ValeurLotInvalideException;

/**
 * Valeur réelle d'un lot après exécution. L'unité est dérivée du mode de
 * facturation du lot (cf ModeFacturation::unite), jamais stockée ici — ADR 0024.
 */
final readonly class Reel
{
    public function __construct(
        public float $valeur,
    ) {
        if ($this->valeur < 0) {
            throw ValeurLotInvalideException::valeurNegative($this->valeur);
        }
    }
}
