<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Repository\TacheRepository;
use Symfony\Component\Uid\Uuid;

final class ListerTacheUseCase
{
    public function __construct(
        private readonly TacheRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @return list<Tache>
     *
     * @throws LotIntrouvableException si le lot n'appartient pas à $proprietaireId
     */
    public function execute(Uuid $lotId, Uuid $proprietaireId): array
    {
        $this->acces->lot($lotId, $proprietaireId);

        return $this->repository->findAllForLot($lotId);
    }
}
