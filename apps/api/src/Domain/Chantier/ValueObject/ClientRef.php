<?php

declare(strict_types=1);

namespace App\Domain\Chantier\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ClientRef
{
    public function __construct(
        public Uuid $id,
        public string $nomCache,
    ) {
        if (trim($this->nomCache) === '') {
            throw new \InvalidArgumentException('Le nom du client dans ClientRef ne peut pas être vide.');
        }
    }

    public function equals(self $autre): bool
    {
        return $this->id->equals($autre->id);
    }
}
