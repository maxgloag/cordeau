<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Domain\Chantier\Exception\LotDejaExistantException;
use App\Domain\Chantier\Repository\LotRepository;
use App\Domain\Chantier\ValueObject\Estimation;
use Symfony\Component\Uid\Uuid;

final class CreerLotUseCase
{
    public function __construct(
        private readonly LotRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @throws ChantierIntrouvableException si le chantier n'appartient pas à $proprietaireId
     * @throws LotDejaExistantException     si $id est fourni et qu'un lot avec cet id existe déjà
     */
    public function execute(
        Uuid $chantierId,
        Uuid $proprietaireId,
        string $nom,
        ModeFacturation $mode,
        int $ordre = 0,
        ?Estimation $estimation = null,
        ?Uuid $id = null,
    ): Lot {
        $this->acces->chantier($chantierId, $proprietaireId);

        // Sans ce contrôle, save() écraserait le lot existant, y compris celui d'un autre artisan.
        if ($id !== null && $this->repository->findById($id) !== null) {
            throw new LotDejaExistantException($id);
        }

        $lot = Lot::creer($chantierId, $nom, $mode, $ordre, $id);
        if ($estimation !== null) {
            $lot = $lot->definirEstimation($estimation);
        }

        $this->repository->save($lot);

        return $lot;
    }
}
