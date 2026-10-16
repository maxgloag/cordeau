<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Domain\Chantier\Repository\LotRepository;
use Symfony\Component\Uid\Uuid;

final class ListerLotUseCase
{
    public function __construct(
        private readonly LotRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @return list<Lot>
     *
     * @throws ChantierIntrouvableException si le chantier n'appartient pas à $proprietaireId
     */
    public function execute(Uuid $chantierId, Uuid $proprietaireId): array
    {
        $this->acces->chantier($chantierId, $proprietaireId);

        return $this->repository->findAllForChantier($chantierId);
    }
}
