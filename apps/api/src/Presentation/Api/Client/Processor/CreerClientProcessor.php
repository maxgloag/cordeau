<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Entity\Client;
use App\Client\Exception\TelephoneInvalideException;
use App\Client\Repository\ClientRepository;
use App\Client\ValueObject\Telephone;
use App\Entity\User;
use App\Presentation\Api\Client\Payload\CreerClientPayload;
use App\Presentation\Api\Client\Resource\ClientResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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

        $id = $data->uuid !== null ? Uuid::fromString($data->uuid) : Uuid::v7();
        if ($data->uuid !== null && $this->repository->find($id) !== null) {
            throw new ConflictHttpException(
                \sprintf('Un client avec l\'identifiant "%s" existe déjà.', $id->toRfc4122()),
            );
        }

        $client = new Client(
            id: $id,
            proprietaire: $user,
            nom: $data->nom,
            email: $data->email,
            telephone: $this->normaliserTelephone($data->telephone),
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

    private function normaliserTelephone(?string $telephone): ?string
    {
        if ($telephone === null) {
            return null;
        }

        try {
            return (new Telephone($telephone))->valeur;
        } catch (TelephoneInvalideException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
