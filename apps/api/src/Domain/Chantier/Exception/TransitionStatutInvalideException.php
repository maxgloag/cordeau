<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

use App\Domain\Chantier\Enum\StatutChantier;

final class TransitionStatutInvalideException extends ChantierException
{
    public static function depuisStatutTerminal(StatutChantier $statutCourant, StatutChantier $statutCible): self
    {
        return new self(\sprintf(
            'Impossible de passer de "%s" à "%s" : le statut "%s" est terminal.',
            $statutCourant->value,
            $statutCible->value,
            $statutCourant->value,
        ));
    }

    public static function dejaDansCeStatut(StatutChantier $statut): self
    {
        return new self(\sprintf('Le chantier est déjà dans le statut "%s".', $statut->value));
    }
}
