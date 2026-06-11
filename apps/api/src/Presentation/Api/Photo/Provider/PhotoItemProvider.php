<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Photo\Resource\PhotoResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PhotoResource>
 */
final class PhotoItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly PhotoRepository $repository,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PhotoResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $id = $this->extractUuid($uriVariables);
        $photo = $this->repository->find($id);

        if ($photo === null || !$photo->proprietaire->id->equals($user->id)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        return PhotoResource::fromEntity($photo);
    }
}
