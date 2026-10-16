<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Exception\TacheDejaExistantException;
use App\Domain\Chantier\Repository\TacheRepository;
use Symfony\Component\Uid\Uuid;

final class CreerTacheUseCase
{
    public function __construct(
        private readonly TacheRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @throws LotIntrouvableException    si le lot n'appartient pas à $proprietaireId
     * @throws TacheDejaExistantException si $id est fourni et qu'une tâche avec cet id existe déjà
     */
    public function execute(Uuid $lotId, Uuid $proprietaireId, string $libelle, int $ordre = 0, ?Uuid $id = null): Tache
    {
        $this->acces->lot($lotId, $proprietaireId);

        // Sans ce contrôle, save() écraserait la tâche existante, y compris celle d'un autre artisan.
        if ($id !== null && $this->repository->findById($id) !== null) {
            throw new TacheDejaExistantException($id);
        }

        $tache = Tache::creer($lotId, $libelle, $ordre, $id);
        $this->repository->save($tache);

        return $tache;
    }
}
