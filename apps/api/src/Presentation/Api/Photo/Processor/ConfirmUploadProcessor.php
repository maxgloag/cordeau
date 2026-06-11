<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Messenger\Message\GenerateThumbnailMessage;
use App\Photo\Entity\Photo;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Photo\Payload\ConfirmUploadPayload;
use App\Presentation\Api\Photo\Resource\PhotoResource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ConfirmUploadPayload, PhotoResource>
 */
final class ConfirmUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PhotoRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly StorageAdapterInterface $storage,
        private readonly MessageBusInterface $bus,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PhotoResource
    {
        \assert($data instanceof ConfirmUploadPayload);
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $chantierId = Uuid::fromString($data->chantierId);
        $chantier = $this->em->find(ChantierDoctrineEntity::class, $chantierId);

        if ($chantier === null || !$chantier->proprietaire->id->equals($user->id)) {
            throw new AccessDeniedHttpException('Chantier introuvable ou accès refusé.');
        }

        $expectedPrefix = 'photos/' . $user->id->toRfc4122() . '/';
        if (!str_starts_with($data->remoteKey, $expectedPrefix)) {
            throw new AccessDeniedHttpException('Clé R2 invalide ou non autorisée.');
        }

        $now = new \DateTimeImmutable();
        $photo = new Photo(
            id: Uuid::v7(),
            proprietaire: $user,
            chantierId: $chantierId,
            lotId: null,
            tacheId: null,
            remoteKey: $data->remoteKey,
            photoUrl: $this->storage->getPublicUrl($data->remoteKey),
            thumbnailUrl: null,
            uploadeLe: $now,
            creeLe: $now,
        );

        $this->repository->save($photo);
        $this->bus->dispatch(new GenerateThumbnailMessage($photo->id->toRfc4122(), $data->remoteKey));

        return PhotoResource::fromEntity($photo);
    }
}
