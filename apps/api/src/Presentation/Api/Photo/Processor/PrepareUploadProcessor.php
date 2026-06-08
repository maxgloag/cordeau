<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Presentation\Api\Photo\Payload\PrepareUploadPayload;
use App\Presentation\Api\Photo\Resource\PrepareUploadResource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PrepareUploadPayload, PrepareUploadResource>
 */
final class PrepareUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly StorageAdapterInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PrepareUploadResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $chantierId = Uuid::fromString($data->chantierId);
        $chantier = $this->em->find(ChantierDoctrineEntity::class, $chantierId);

        if ($chantier === null || !$chantier->proprietaire->id->equals($user->id)) {
            throw new AccessDeniedHttpException('Chantier introuvable ou accès refusé.');
        }

        $key = 'photos/' . Uuid::v7()->toRfc4122();
        $uploadUrl = $this->storage->generatePresignedPutUrl($key);

        return new PrepareUploadResource(uploadUrl: $uploadUrl, remoteKey: $key);
    }
}
