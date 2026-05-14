<?php

declare(strict_types=1);

namespace App\Client\Exception;

final class TelephoneInvalideException extends \DomainException
{
    public static function format(string $valeur): self
    {
        return new self(\sprintf('Le numéro de téléphone "%s" n\'est pas un numéro français valide.', $valeur));
    }
}
