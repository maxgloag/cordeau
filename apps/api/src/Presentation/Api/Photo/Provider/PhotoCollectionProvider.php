<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/** @implements ProviderInterface<object> */
final class PhotoCollectionProvider implements ProviderInterface
{
    /** @return array<object> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return [];
    }
}
