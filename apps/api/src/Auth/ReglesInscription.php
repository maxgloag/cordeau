<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Règles de validation de l'inscription, dupliquées côté web et mobile.
 * Leur conformité est vérifiée par les fixtures de packages/shared/conformite.
 */
final class ReglesInscription
{
    public static function normaliserEmail(string $email): string
    {
        return trim($email);
    }

    /** @return list<Constraint> */
    public static function contraintesEmail(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ];
    }

    /** @return list<Constraint> */
    public static function contraintesMotDePasse(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: 8, max: 255),
            new Assert\Regex(pattern: '/[A-Z]/', message: 'Le mot de passe doit contenir au moins une majuscule.'),
            new Assert\Regex(pattern: '/[0-9]/', message: 'Le mot de passe doit contenir au moins un chiffre.'),
        ];
    }
}
