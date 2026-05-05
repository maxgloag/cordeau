<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

use Symfony\Component\Uid\Uuid;

final class ChantierIntrouvableException extends ChantierException
{
    public static function avecId(Uuid $id): self
    {
        return new self(\sprintf('Aucun chantier trouvé avec l\'identifiant "%s".', $id->toRfc4122()));
    }
}
