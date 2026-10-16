<?php

declare(strict_types=1);

namespace App\Application\Chantier;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\Repository\LotRepository;
use App\Domain\Chantier\Repository\TacheRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Un lot et ses tâches appartiennent au propriétaire de leur chantier. Hors de son périmètre,
 * l'élément est introuvable : on ne révèle pas son existence.
 */
final class AccesProprietaire
{
    public function __construct(
        private readonly ChantierRepository $chantierRepository,
        private readonly LotRepository $lotRepository,
        private readonly TacheRepository $tacheRepository,
    ) {
    }

    /**
     * @throws ChantierIntrouvableException
     */
    public function chantier(Uuid $chantierId, Uuid $proprietaireId): Chantier
    {
        $chantier = $this->chantierRepository->findById($chantierId);

        if ($chantier === null || !$chantier->appartientA($proprietaireId)) {
            throw ChantierIntrouvableException::avecId($chantierId);
        }

        return $chantier;
    }

    /**
     * @throws LotIntrouvableException
     */
    public function lot(Uuid $lotId, Uuid $proprietaireId): Lot
    {
        return $this->lotDe($lotId, $proprietaireId) ?? throw LotIntrouvableException::avecId($lotId);
    }

    /**
     * @throws TacheIntrouvableException
     */
    public function tache(Uuid $tacheId, Uuid $proprietaireId): Tache
    {
        $tache = $this->tacheRepository->findById($tacheId);

        if ($tache === null || $this->lotDe($tache->lotId, $proprietaireId) === null) {
            throw TacheIntrouvableException::avecId($tacheId);
        }

        return $tache;
    }

    private function lotDe(Uuid $lotId, Uuid $proprietaireId): ?Lot
    {
        $lot = $this->lotRepository->findById($lotId);
        if ($lot === null) {
            return null;
        }

        return $this->chantierRepository->findById($lot->chantierId)?->appartientA($proprietaireId) === true ? $lot : null;
    }
}
