<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Photo\Entity\Photo;
use App\Presentation\Api\Photo\Payload\ConfirmUploadPayload;
use App\Presentation\Api\Photo\Payload\ModifierLegendePayload;
use App\Presentation\Api\Photo\Processor\ConfirmUploadProcessor;
use App\Presentation\Api\Photo\Processor\ModifierLegendeProcessor;
use App\Presentation\Api\Photo\Processor\SupprimerPhotoProcessor;
use App\Presentation\Api\Photo\Provider\PhotoCollectionProvider;
use App\Presentation\Api\Photo\Provider\PhotoItemProvider;

#[ApiResource(
    shortName: 'Photo',
    operations: [
        new Post(
            uriTemplate: '/photos/confirm',
            input: ConfirmUploadPayload::class,
            processor: ConfirmUploadProcessor::class,
            status: 201,
        ),
        new GetCollection(
            uriTemplate: '/chantiers/{chantierId}/photos',
            uriVariables: [
                'chantierId' => new Link(parameterName: 'chantierId'),
            ],
            provider: PhotoCollectionProvider::class,
        ),
        new Patch(
            provider: PhotoItemProvider::class,
            input: ModifierLegendePayload::class,
            processor: ModifierLegendeProcessor::class,
        ),
        new Delete(
            provider: PhotoItemProvider::class,
            processor: SupprimerPhotoProcessor::class,
        ),
    ],
)]
final class PhotoResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $chantierId,
        public readonly ?string $lotId,
        public readonly ?string $tacheId,
        public readonly string $remoteKey,
        public readonly string $photoUrl,
        public readonly ?string $thumbnailUrl,
        public readonly string $creeLe,
        public readonly ?string $legende,
    ) {
    }

    public static function fromEntity(Photo $photo): self
    {
        return new self(
            id: $photo->id->toRfc4122(),
            chantierId: $photo->chantierId->toRfc4122(),
            lotId: $photo->lotId?->toRfc4122(),
            tacheId: $photo->tacheId?->toRfc4122(),
            remoteKey: $photo->remoteKey,
            photoUrl: $photo->photoUrl,
            thumbnailUrl: $photo->thumbnailUrl,
            creeLe: $photo->creeLe->format(\DateTimeInterface::ATOM),
            legende: $photo->legende,
        );
    }
}
