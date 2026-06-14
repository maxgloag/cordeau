<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Repository;

use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use Symfony\Component\Uid\Uuid;

interface LotRepository
{
    public function save(Lot $lot): void;

    public function findById(Uuid $id): ?Lot;

    /**
     * @throws LotIntrouvableException
     */
    public function getById(Uuid $id): Lot;

    /**
     * @return list<Lot>
     */
    public function findAllForChantier(Uuid $chantierId): array;

    public function delete(Uuid $id): void;
}
