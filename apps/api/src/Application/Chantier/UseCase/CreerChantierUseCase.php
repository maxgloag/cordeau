<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;

final class CreerChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    public function execute(
        Adresse $adresse,
        ?Surface $surface = null,
    ): Chantier {
        $chantier = Chantier::creer($adresse, $surface);
        $this->repository->save($chantier);

        return $chantier;
    }
}
