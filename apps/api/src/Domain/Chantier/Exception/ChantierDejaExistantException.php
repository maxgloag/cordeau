<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

use Symfony\Component\Uid\Uuid;

final class ChantierDejaExistantException extends ChantierException
{
    public function __construct(public readonly Uuid $id)
    {
        parent::__construct(\sprintf('Un chantier avec l\'identifiant "%s" existe déjà.', $id->toRfc4122()));
    }
}
