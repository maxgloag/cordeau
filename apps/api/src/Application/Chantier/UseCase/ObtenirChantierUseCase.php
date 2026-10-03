<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use Symfony\Component\Uid\Uuid;

final class ObtenirChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    /**
     * Un chantier qui n'appartient pas à `$proprietaireId` est traité comme introuvable :
     * on ne révèle pas son existence.
     */
    public function execute(Uuid $id, Uuid $proprietaireId): ?Chantier
    {
        $chantier = $this->repository->findById($id);

        return $chantier !== null && $chantier->appartientA($proprietaireId) ? $chantier : null;
    }
}
