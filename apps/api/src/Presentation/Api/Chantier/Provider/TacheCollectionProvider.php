<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ListerTacheUseCase;
use App\Domain\Chantier\Entity\Tache;
use App\Entity\User;
use App\Presentation\Api\Chantier\Resource\TacheResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<TacheResource>
 */
final class TacheCollectionProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ListerTacheUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<TacheResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return array_map(
            static fn (Tache $tache): TacheResource => TacheResource::fromDomain($tache),
            $this->useCase->execute($this->extractUuid($uriVariables, 'lotId'), $user->id),
        );
    }
}
