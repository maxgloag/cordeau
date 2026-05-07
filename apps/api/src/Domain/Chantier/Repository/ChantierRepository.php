<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Repository;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use Symfony\Component\Uid\Uuid;

interface ChantierRepository
{
    public function save(Chantier $chantier): void;

    public function findById(Uuid $id): ?Chantier;

    /**
     * @throws ChantierIntrouvableException
     */
    public function getById(Uuid $id): Chantier;

    /**
     * @return list<Chantier>
     */
    public function findAll(): array;

    public function delete(Uuid $id): void;
}
