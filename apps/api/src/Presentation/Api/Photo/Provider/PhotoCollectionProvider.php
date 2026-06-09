<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Photo\Entity\Photo;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Photo\Resource\PhotoResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<PhotoResource>
 */
final class PhotoCollectionProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly PhotoRepository $repository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<PhotoResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $chantierId = $this->extractUuid($uriVariables, 'chantierId');

        return array_map(
            static fn (Photo $photo): PhotoResource => PhotoResource::fromEntity($photo),
            $this->repository->findForChantier($chantierId, $user->id),
        );
    }
}
