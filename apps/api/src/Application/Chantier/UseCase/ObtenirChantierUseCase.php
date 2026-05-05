<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use Symfony\Component\Uid\Uuid;

final class ObtenirChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    public function execute(Uuid $id): ?Chantier
    {
        return $this->repository->findById($id);
    }
}
