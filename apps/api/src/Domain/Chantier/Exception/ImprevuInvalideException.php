<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

final class ImprevuInvalideException extends ChantierException
{
    public static function noteVide(): self
    {
        return new self('La note d\'un imprévu ne peut pas être vide.');
    }
}
