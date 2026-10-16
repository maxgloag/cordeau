<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Repository\LotRepository;
use Symfony\Component\Uid\Uuid;

final class SupprimerLotUseCase
{
    public function __construct(
        private readonly LotRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * Les tâches du lot partent avec lui (FK ON DELETE CASCADE, doublée dans le repository).
     *
     * @throws LotIntrouvableException si le lot n'appartient pas à $proprietaireId
     */
    public function execute(Uuid $id, Uuid $proprietaireId): void
    {
        $this->acces->lot($id, $proprietaireId);

        $this->repository->delete($id);
    }
}
