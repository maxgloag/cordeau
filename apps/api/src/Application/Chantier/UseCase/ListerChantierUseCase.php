<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use Symfony\Component\Uid\Uuid;

final class ListerChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    /**
     * @return list<Chantier>
     */
    public function execute(Uuid $proprietaireId): array
    {
        return $this->repository->findAllForUser($proprietaireId);
    }
}
