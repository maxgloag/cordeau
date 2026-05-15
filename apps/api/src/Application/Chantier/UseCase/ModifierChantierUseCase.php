<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\ValueObject\ClientRef;
use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use Symfony\Component\Uid\Uuid;

final class ModifierChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    public function execute(
        Uuid $id,
        ?Adresse $nouvelleAdresse = null,
        ?Surface $nouvelleSurface = null,
        ?ClientRef $nouveauClient = null,
    ): Chantier {
        $chantier = $this->repository->getById($id);

        if ($nouvelleAdresse !== null) {
            $chantier = $chantier->modifierAdresse($nouvelleAdresse);
        }

        if ($nouvelleSurface !== null) {
            $chantier = $chantier->renseignerSurface($nouvelleSurface);
        }

        if ($nouveauClient !== null) {
            $chantier = $chantier->lierClient($nouveauClient);
        }

        $this->repository->save($chantier);

        return $chantier;
    }
}
