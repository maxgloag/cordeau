<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ObtenirChantierUseCase;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;

/**
 * @implements ProviderInterface<ChantierResource>
 */
final class ChantierItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(private readonly ObtenirChantierUseCase $useCase)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ChantierResource
    {
        $chantier = $this->useCase->execute($this->extractUuid($uriVariables));

        return $chantier !== null ? ChantierResource::fromDomain($chantier) : null;
    }
}
