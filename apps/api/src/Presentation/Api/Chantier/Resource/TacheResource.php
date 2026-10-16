<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Chantier\Entity\Tache;
use App\Presentation\Api\Chantier\Payload\CreerTachePayload;
use App\Presentation\Api\Chantier\Payload\ModifierTachePayload;
use App\Presentation\Api\Chantier\Processor\CreerTacheProcessor;
use App\Presentation\Api\Chantier\Processor\ModifierTacheProcessor;
use App\Presentation\Api\Chantier\Processor\SupprimerTacheProcessor;
use App\Presentation\Api\Chantier\Provider\TacheCollectionProvider;
use App\Presentation\Api\Chantier\Provider\TacheItemProvider;

#[ApiResource(
    shortName: 'Tache',
    operations: [
        new GetCollection(
            uriTemplate: '/lots/{lotId}/taches',
            uriVariables: [
                'lotId' => new Link(parameterName: 'lotId'),
            ],
            provider: TacheCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/lots/{lotId}/taches',
            uriVariables: [
                'lotId' => new Link(parameterName: 'lotId'),
            ],
            read: false,
            input: CreerTachePayload::class,
            processor: CreerTacheProcessor::class,
        ),
        new Patch(
            provider: TacheItemProvider::class,
            input: ModifierTachePayload::class,
            processor: ModifierTacheProcessor::class,
        ),
        new Delete(
            provider: TacheItemProvider::class,
            processor: SupprimerTacheProcessor::class,
        ),
    ],
)]
final class TacheResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $lotId,
        public readonly string $libelle,
        public readonly bool $faite,
        public readonly ?string $faiteLe,
        public readonly int $ordre,
    ) {
    }

    public static function fromDomain(Tache $tache): self
    {
        return new self(
            id: $tache->id->toRfc4122(),
            lotId: $tache->lotId->toRfc4122(),
            libelle: $tache->libelle,
            faite: $tache->faite,
            faiteLe: $tache->faiteLe?->format(\DateTimeInterface::ATOM),
            ordre: $tache->ordre,
        );
    }
}
