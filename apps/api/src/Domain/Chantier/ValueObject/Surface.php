<?php

declare(strict_types=1);

namespace App\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\SurfaceInvalideException;

final readonly class Surface
{
    public const float MAXIMUM_M2 = 100_000.0;

    public function __construct(
        public float $valeurM2,
    ) {
        if ($this->valeurM2 <= 0) {
            throw SurfaceInvalideException::valeurNonPositive($this->valeurM2);
        }

        if ($this->valeurM2 > self::MAXIMUM_M2) {
            throw SurfaceInvalideException::valeurTropGrande($this->valeurM2, self::MAXIMUM_M2);
        }
    }

    public function equals(self $autre): bool
    {
        return abs($this->valeurM2 - $autre->valeurM2) < 0.001;
    }
}
