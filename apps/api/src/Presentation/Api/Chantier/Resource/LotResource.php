<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\ValueObject\Imprevu;
use App\Presentation\Api\Chantier\Payload\CreerLotPayload;
use App\Presentation\Api\Chantier\Payload\ModifierLotPayload;
use App\Presentation\Api\Chantier\Processor\CreerLotProcessor;
use App\Presentation\Api\Chantier\Processor\ModifierLotProcessor;
use App\Presentation\Api\Chantier\Processor\SupprimerLotProcessor;
use App\Presentation\Api\Chantier\Provider\LotCollectionProvider;
use App\Presentation\Api\Chantier\Provider\LotItemProvider;

#[ApiResource(
    shortName: 'Lot',
    operations: [
        new GetCollection(
            uriTemplate: '/chantiers/{chantierId}/lots',
            uriVariables: [
                'chantierId' => new Link(parameterName: 'chantierId'),
            ],
            provider: LotCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/chantiers/{chantierId}/lots',
            uriVariables: [
                'chantierId' => new Link(parameterName: 'chantierId'),
            ],
            read: false,
            input: CreerLotPayload::class,
            processor: CreerLotProcessor::class,
        ),
        new Get(
            provider: LotItemProvider::class,
        ),
        new Patch(
            provider: LotItemProvider::class,
            input: ModifierLotPayload::class,
            processor: ModifierLotProcessor::class,
        ),
        new Delete(
            provider: LotItemProvider::class,
            processor: SupprimerLotProcessor::class,
        ),
    ],
)]
final class LotResource
{
    /**
     * @param list<array{note: string, horodatage: string}> $imprevus
     */
    public function __construct(
        public readonly string $id,
        public readonly string $chantierId,
        public readonly string $nom,
        public readonly string $mode,
        /** Unité de l'estimation et du réel, dérivée du mode (ADR 0024). */
        public readonly string $unite,
        public readonly ?float $estimation,
        public readonly ?float $reel,
        public readonly int $ordre,
        public readonly array $imprevus,
    ) {
    }

    public static function fromDomain(Lot $lot): self
    {
        return new self(
            id: $lot->id->toRfc4122(),
            chantierId: $lot->chantierId->toRfc4122(),
            nom: $lot->nom,
            mode: $lot->mode->value,
            unite: $lot->mode->unite(),
            estimation: $lot->estimation?->valeur,
            reel: $lot->reel?->valeur,
            ordre: $lot->ordre,
            imprevus: array_map(
                static fn (Imprevu $imprevu): array => [
                    'note' => $imprevu->note,
                    'horodatage' => $imprevu->horodatage->format(\DateTimeInterface::ATOM),
                ],
                $lot->imprevus,
            ),
        );
    }
}
