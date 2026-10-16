<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Repository\LotRepository;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Reel;
use Symfony\Component\Uid\Uuid;

final class ModifierLotUseCase
{
    public function __construct(
        private readonly LotRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * Le mode change en premier : il réinitialise estimation et réel (ADR 0024), une valeur
     * fournie dans la même requête s'entend donc dans le nouveau mode.
     *
     * @throws LotIntrouvableException si le lot n'appartient pas à $proprietaireId
     */
    public function execute(
        Uuid $id,
        Uuid $proprietaireId,
        ?string $nouveauNom = null,
        ?ModeFacturation $nouveauMode = null,
        ?Estimation $nouvelleEstimation = null,
        ?Reel $nouveauReel = null,
    ): Lot {
        $lot = $this->acces->lot($id, $proprietaireId);

        if ($nouveauNom !== null) {
            $lot = $lot->renommer($nouveauNom);
        }

        if ($nouveauMode !== null) {
            $lot = $lot->changerMode($nouveauMode);
        }

        if ($nouvelleEstimation !== null) {
            $lot = $lot->definirEstimation($nouvelleEstimation);
        }

        if ($nouveauReel !== null) {
            $lot = $lot->enregistrerReel($nouveauReel);
        }

        $this->repository->save($lot);

        return $lot;
    }
}
