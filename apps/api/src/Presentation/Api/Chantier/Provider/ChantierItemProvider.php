<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ObtenirChantierUseCase;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ChantierResource>
 */
final class ChantierItemProvider implements ProviderInterface
{
    public function __construct(private readonly ObtenirChantierUseCase $useCase)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ChantierResource
    {
        $rawId = $uriVariables['id'] ?? null;
        \assert(\is_string($rawId));
        $id = Uuid::fromString($rawId);
        $chantier = $this->useCase->execute($id);

        return $chantier !== null ? ChantierResource::fromDomain($chantier) : null;
    }
}
