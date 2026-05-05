<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ListerChantierUseCase;
use App\Presentation\Api\Chantier\Resource\ChantierResource;

/**
 * @implements ProviderInterface<ChantierResource>
 */
final class ChantierCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly ListerChantierUseCase $useCase)
    {
    }

    /**
     * @return list<ChantierResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn ($chantier) => ChantierResource::fromDomain($chantier),
            $this->useCase->execute(),
        );
    }
}
