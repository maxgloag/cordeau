<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class AdresseInvalideException extends \DomainException
{
    public static function rueVide(): self
    {
        return new self('La rue de l\'adresse ne peut pas être vide.');
    }

    public static function villeVide(): self
    {
        return new self('La ville de l\'adresse ne peut pas être vide.');
    }

    public static function codePostalInvalide(string $codePostal, string $pays): self
    {
        return new self(\sprintf('Le code postal "%s" est invalide pour le pays "%s".', $codePostal, $pays));
    }

    public static function paysInvalide(string $pays): self
    {
        return new self(\sprintf('Le code pays "%s" doit être un code ISO 3166-1 alpha-2 (2 lettres).', $pays));
    }
}
