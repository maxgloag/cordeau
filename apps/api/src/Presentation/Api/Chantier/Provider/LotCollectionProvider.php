<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ListerLotUseCase;
use App\Domain\Chantier\Entity\Lot;
use App\Entity\User;
use App\Presentation\Api\Chantier\Resource\LotResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<LotResource>
 */
final class LotCollectionProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ListerLotUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<LotResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return array_map(
            static fn (Lot $lot): LotResource => LotResource::fromDomain($lot),
            $this->useCase->execute($this->extractUuid($uriVariables, 'chantierId'), $user->id),
        );
    }
}
