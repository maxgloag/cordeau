<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Repository\ChantierRepository;
use Symfony\Component\Uid\Uuid;

final class ArchiverChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    public function execute(Uuid $id): void
    {
        $this->repository->save(
            $this->repository->getById($id)->archiver(),
        );
    }
}
