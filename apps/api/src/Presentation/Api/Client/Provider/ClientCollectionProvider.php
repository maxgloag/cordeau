<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Client\Entity\Client;
use App\Client\Repository\ClientRepository;
use App\Entity\User;
use App\Presentation\Api\Client\Resource\ClientResource;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ClientResource>
 */
final class ClientCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ClientRepository $repository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<ClientResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return array_map(
            static fn (Client $client): ClientResource => ClientResource::fromEntity($client),
            $this->repository->findAllForUser($user->id),
        );
    }
}
