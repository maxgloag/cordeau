<?php

declare(strict_types=1);

namespace App\Application\Chantier\UseCase;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Exception\ChantierDejaExistantException;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\ValueObject\ClientRef;
use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use Symfony\Component\Uid\Uuid;

final class CreerChantierUseCase
{
    public function __construct(private readonly ChantierRepository $repository)
    {
    }

    /**
     * @throws ChantierDejaExistantException si $id est fourni et qu'un chantier avec cet id existe deja
     */
    public function execute(
        Uuid $proprietaireId,
        Adresse $adresse,
        ?Surface $surface = null,
        ?ClientRef $client = null,
        ?Uuid $id = null,
    ): Chantier {
        if ($id !== null && $this->repository->findById($id) !== null) {
            throw new ChantierDejaExistantException($id);
        }

        $chantier = Chantier::creer($proprietaireId, $adresse, $surface, client: $client, id: $id);
        $this->repository->save($chantier);

        return $chantier;
    }
}
