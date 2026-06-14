<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

final class ValeurLotInvalideException extends ChantierException
{
    public static function valeurNegative(float $valeur): self
    {
        return new self(\sprintf('La valeur d\'un lot ne peut pas être négative, "%s" reçu.', (string) $valeur));
    }
}
