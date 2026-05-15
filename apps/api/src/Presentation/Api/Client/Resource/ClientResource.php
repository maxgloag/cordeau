<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Client\Entity\Client;
use App\Presentation\Api\Client\Payload\CreerClientPayload;
use App\Presentation\Api\Client\Payload\ModifierClientPayload;
use App\Presentation\Api\Client\Processor\CreerClientProcessor;
use App\Presentation\Api\Client\Processor\ModifierClientProcessor;
use App\Presentation\Api\Client\Processor\SupprimerClientProcessor;
use App\Presentation\Api\Client\Provider\ClientCollectionProvider;
use App\Presentation\Api\Client\Provider\ClientItemProvider;

#[ApiResource(
    shortName: 'Client',
    operations: [
        new GetCollection(
            provider: ClientCollectionProvider::class,
        ),
        new Get(
            provider: ClientItemProvider::class,
        ),
        new Post(
            input: CreerClientPayload::class,
            processor: CreerClientProcessor::class,
        ),
        new Patch(
            provider: ClientItemProvider::class,
            input: ModifierClientPayload::class,
            processor: ModifierClientProcessor::class,
        ),
        new Delete(
            provider: ClientItemProvider::class,
            processor: SupprimerClientProcessor::class,
        ),
    ],
)]
final class ClientResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $nom,
        public readonly ?string $email,
        public readonly ?string $telephone,
        public readonly string $adresseRue,
        public readonly string $adresseCodePostal,
        public readonly string $adresseVille,
        public readonly string $adressePays,
        public readonly ?string $notes,
        public readonly string $creeLe,
        public readonly string $modifieLe,
    ) {
    }

    public static function fromEntity(Client $client): self
    {
        return new self(
            id: $client->id->toRfc4122(),
            nom: $client->nom,
            email: $client->email,
            telephone: $client->telephone,
            adresseRue: $client->adresseRue,
            adresseCodePostal: $client->adresseCodePostal,
            adresseVille: $client->adresseVille,
            adressePays: $client->adressePays,
            notes: $client->notes,
            creeLe: $client->creeLe->format(\DateTimeInterface::ATOM),
            modifieLe: $client->modifieLe->format(\DateTimeInterface::ATOM),
        );
    }
}
