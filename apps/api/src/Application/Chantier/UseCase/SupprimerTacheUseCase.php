<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use App\Domain\Chantier\Repository\TacheRepository;
use Symfony\Component\Uid\Uuid;

final class SupprimerTacheUseCase
{
    public function __construct(
        private readonly TacheRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @throws TacheIntrouvableException si la tâche n'appartient pas à $proprietaireId
     */
    public function execute(Uuid $id, Uuid $proprietaireId): void
    {
        $this->acces->tache($id, $proprietaireId);

        $this->repository->delete($id);
    }
}
