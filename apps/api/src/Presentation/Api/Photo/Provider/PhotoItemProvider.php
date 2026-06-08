<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/** @implements ProviderInterface<object> */
final class PhotoItemProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return null;
    }
}
