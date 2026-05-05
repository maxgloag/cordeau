<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

final class SurfaceInvalideException extends ChantierException
{
    public static function valeurNonPositive(float $valeur): self
    {
        return new self(\sprintf('La surface doit être strictement positive, "%s" reçu.', (string) $valeur));
    }

    public static function valeurTropGrande(float $valeur, float $maximum): self
    {
        return new self(\sprintf('La surface "%s m²" dépasse le maximum autorisé de "%s m²".', (string) $valeur, (string) $maximum));
    }
}
