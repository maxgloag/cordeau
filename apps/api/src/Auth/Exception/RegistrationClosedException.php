<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/**
 * Levee quand une tentative de creation de compte arrive alors que
 * l'inscription self-service est fermee (cf ADR 0021). Concerne aussi bien
 * l'auto-provisioning OAuth (nouveau User au premier login Google) que tout
 * autre point creant un compte. Le login d'un compte existant ne la declenche pas.
 */
final class RegistrationClosedException extends \RuntimeException
{
}
