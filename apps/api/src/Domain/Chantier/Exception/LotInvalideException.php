<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

final class LotInvalideException extends ChantierException
{
    public static function nomVide(): self
    {
        return new self('Le nom d\'un lot ne peut pas être vide.');
    }
}
