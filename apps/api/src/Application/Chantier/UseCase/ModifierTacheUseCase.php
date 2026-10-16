<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Application\Chantier\AccesProprietaire;
use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use App\Domain\Chantier\Repository\TacheRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Cocher / décocher (1 tap mobile) et renommer une tâche.
 */
final class ModifierTacheUseCase
{
    public function __construct(
        private readonly TacheRepository $repository,
        private readonly AccesProprietaire $acces,
    ) {
    }

    /**
     * @throws TacheIntrouvableException si la tâche n'appartient pas à $proprietaireId
     */
    public function execute(Uuid $id, Uuid $proprietaireId, ?bool $faite = null, ?string $nouveauLibelle = null): Tache
    {
        $tache = $this->acces->tache($id, $proprietaireId);

        if ($nouveauLibelle !== null) {
            $tache = $tache->renommer($nouveauLibelle);
        }

        // Re-cocher une tâche déjà faite garde sa date d'origine.
        if ($faite === true && !$tache->faite) {
            $tache = $tache->cocher();
        } elseif ($faite === false && $tache->faite) {
            $tache = $tache->decocher();
        }

        $this->repository->save($tache);

        return $tache;
    }
}
