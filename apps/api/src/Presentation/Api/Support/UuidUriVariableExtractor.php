<?php

declare(strict_types=1);

namespace App\Presentation\Api\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

trait UuidUriVariableExtractor
{
    /**
     * @param array<string, mixed> $uriVariables
     */
    private function extractUuid(array $uriVariables, string $key = 'id'): Uuid
    {
        $raw = $uriVariables[$key] ?? null;

        if (!\is_string($raw)) {
            throw new NotFoundHttpException();
        }

        try {
            return Uuid::fromString($raw);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException();
        }
    }
}
