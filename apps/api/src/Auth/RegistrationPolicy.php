<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Politique d'ouverture de l'inscription self-service (cf ADR 0021).
 *
 * Pilotee par la variable d'environnement REGISTRATION_SELF_SERVICE_ENABLED :
 * false en prod pendant la beta privee (onboarding manuel via la commande
 * app:user:create), true a la reouverture self-service (V1). Reversible sans
 * modification de code.
 */
final class RegistrationPolicy
{
    public function __construct(
        private readonly bool $selfServiceEnabled,
    ) {
    }

    public function selfServiceEnabled(): bool
    {
        return $this->selfServiceEnabled;
    }
}
