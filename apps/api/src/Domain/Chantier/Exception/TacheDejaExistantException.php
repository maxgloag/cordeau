<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

use Symfony\Component\Uid\Uuid;

final class TacheDejaExistantException extends ChantierException
{
    public function __construct(public readonly Uuid $id)
    {
        parent::__construct(\sprintf('Une tâche avec l\'identifiant "%s" existe déjà.', $id->toRfc4122()));
    }
}
