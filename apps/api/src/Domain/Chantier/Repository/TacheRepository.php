<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Repository;

use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use Symfony\Component\Uid\Uuid;

interface TacheRepository
{
    public function save(Tache $tache): void;

    public function findById(Uuid $id): ?Tache;

    /**
     * @throws TacheIntrouvableException
     */
    public function getById(Uuid $id): Tache;

    /**
     * @return list<Tache>
     */
    public function findAllForLot(Uuid $lotId): array;

    public function delete(Uuid $id): void;
}
