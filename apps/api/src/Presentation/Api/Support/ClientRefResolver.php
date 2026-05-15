<?php

declare(strict_types=1);

namespace App\Presentation\Api\Support;

use App\Client\Repository\ClientRepository;
use App\Domain\Chantier\ValueObject\ClientRef;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final class ClientRefResolver
{
    public function __construct(private readonly ClientRepository $clientRepository)
    {
    }

    public function resoudre(string $clientId, Uuid $proprietaireId): ClientRef
    {
        $client = $this->clientRepository->find(Uuid::fromString($clientId));

        if ($client === null || !$client->proprietaire->id->equals($proprietaireId)) {
            throw new UnprocessableEntityHttpException('Client introuvable ou non autorisé.');
        }

        return new ClientRef(id: $client->id, nomCache: $client->nom);
    }
}
