<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Entity\Client;
use App\Client\Repository\ClientRepository;
use App\Client\ValueObject\Telephone;
use App\Entity\User;
use App\Presentation\Api\Client\Payload\CreerClientPayload;
use App\Presentation\Api\Client\Resource\ClientResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreerClientPayload, ClientResource>
 */
final class CreerClientProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ClientRepository $repository,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $now = new \DateTimeImmutable();

        $client = new Client(
            id: Uuid::v7(),
            proprietaire: $user,
            nom: $data->nom,
            email: $data->email,
            telephone: $data->telephone !== null ? (new Telephone($data->telephone))->valeur : null,
            adresseRue: $data->adresseRue,
            adresseCodePostal: $data->adresseCodePostal,
            adresseVille: $data->adresseVille,
            adressePays: $data->adressePays,
            notes: $data->notes,
            creeLe: $now,
            modifieLe: $now,
        );

        $this->repository->save($client);

        return ClientResource::fromEntity($client);
    }
}
