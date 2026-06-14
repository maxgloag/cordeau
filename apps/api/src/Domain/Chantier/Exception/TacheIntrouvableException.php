<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

use Symfony\Component\Uid\Uuid;

final class TacheIntrouvableException extends ChantierException
{
    public static function avecId(Uuid $id): self
    {
        return new self(\sprintf('Aucune tâche trouvée avec l\'identifiant "%s".', $id->toRfc4122()));
    }
}
