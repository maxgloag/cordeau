<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Photo\Payload\ModifierLegendePayload;
use App\Presentation\Api\Photo\Resource\PhotoResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ModifierLegendePayload, PhotoResource>
 */
final class ModifierLegendeProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly PhotoRepository $repository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PhotoResource
    {
        \assert($data instanceof ModifierLegendePayload);

        // L'ownership est déjà vérifié par PhotoItemProvider (404 si non-propriétaire).
        $id = $this->extractUuid($uriVariables);
        $photo = $this->repository->find($id);
        if ($photo === null) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $photo->legende = $data->legende;
        $this->repository->save($photo);

        return PhotoResource::fromEntity($photo);
    }
}
