<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Domain\Chantier\Repository\ChantierRepository;
use Symfony\Component\Uid\Uuid;

final class ArchiverChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    public function execute(Uuid $id, Uuid $proprietaireId): void
    {
        $chantier = $this->repository->getById($id);
        if (!$chantier->appartientA($proprietaireId)) {
            throw ChantierIntrouvableException::avecId($id);
        }

        $this->repository->save($chantier->archiver());
    }
}
