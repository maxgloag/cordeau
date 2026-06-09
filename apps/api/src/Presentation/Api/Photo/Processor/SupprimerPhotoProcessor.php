<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Messenger\Message\DeleteR2ObjectMessage;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Photo\Resource\PhotoResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PhotoResource, void>
 */
final class SupprimerPhotoProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PhotoRepository $repository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        \assert($data instanceof PhotoResource);

        $photo = $this->repository->find(Uuid::fromString($data->id));
        if ($photo === null) {
            return;
        }

        $remoteKey = $photo->remoteKey;
        $this->repository->remove($photo);
        $this->bus->dispatch(new DeleteR2ObjectMessage($remoteKey));
    }
}
