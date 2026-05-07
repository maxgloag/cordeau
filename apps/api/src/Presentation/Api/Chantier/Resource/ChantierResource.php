<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Resource;

use App\Presentation\Api\Chantier\Payload\CreerChantierPayload;
use App\Presentation\Api\Chantier\Payload\ModifierChantierPayload;
use App\Presentation\Api\Chantier\Processor\ArchiverChantierProcessor;
use App\Presentation\Api\Chantier\Processor\CreerChantierProcessor;
use App\Presentation\Api\Chantier\Processor\ModifierChantierProcessor;
use App\Presentation\Api\Chantier\Provider\ChantierCollectionProvider;
use App\Presentation\Api\Chantier\Provider\ChantierItemProvider;
use App\Domain\Chantier\Entity\Chantier;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'Chantier',
    operations: [
        new GetCollection(
            provider: ChantierCollectionProvider::class,
        ),
        new Get(
            provider: ChantierItemProvider::class,
        ),
        new Post(
            input: CreerChantierPayload::class,
            processor: CreerChantierProcessor::class,
        ),
        new Patch(
            provider: ChantierItemProvider::class,
            input: ModifierChantierPayload::class,
            processor: ModifierChantierProcessor::class,
        ),
        new Delete(
            provider: ChantierItemProvider::class,
            processor: ArchiverChantierProcessor::class,
        ),
    ],
)]
final class ChantierResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $adresseRue,
        public readonly string $adresseCodePostal,
        public readonly string $adresseVille,
        public readonly string $adressePays,
        public readonly ?float $surfaceM2,
        public readonly string $statut,
        public readonly string $creeLe,
        public readonly string $modifieLe,
    ) {
    }

    public static function fromDomain(Chantier $chantier): self
    {
        return new self(
            id: $chantier->id->toRfc4122(),
            adresseRue: $chantier->adresse->rue,
            adresseCodePostal: $chantier->adresse->codePostal,
            adresseVille: $chantier->adresse->ville,
            adressePays: $chantier->adresse->pays,
            surfaceM2: $chantier->surface?->valeurM2,
            statut: $chantier->statut->value,
            creeLe: $chantier->creeLe->format(\DateTimeInterface::ATOM),
            modifieLe: $chantier->modifieLe->format(\DateTimeInterface::ATOM),
        );
    }
}
