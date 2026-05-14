<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Client\Repository\ClientRepository;
use App\Entity\User;
use App\Presentation\Api\Client\Resource\ClientResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ClientResource>
 */
final class ClientItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ClientRepository $repository,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ClientResource
    {
        $id = $this->extractUuid($uriVariables);
        $client = $this->repository->find($id);

        if ($client === null) {
            throw new NotFoundHttpException();
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        if (!$client->proprietaire->id->equals($user->id)) {
            throw new AccessDeniedHttpException();
        }

        return ClientResource::fromEntity($client);
    }
}
