<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class DesignationInvalideException extends DevisException
{
    public static function vide(): self
    {
        return new self('La désignation ne peut pas être vide.');
    }

    public static function encodageInvalide(): self
    {
        return new self('La désignation n\'est pas une chaîne UTF-8 valide.');
    }
}
