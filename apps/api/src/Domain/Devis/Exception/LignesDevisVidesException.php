<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class LignesDevisVidesException extends DevisException
{
    public static function aucuneLigne(): self
    {
        return new self('Un devis comporte au moins une ligne.');
    }
}
