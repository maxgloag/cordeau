<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

trait JsonTestHelper
{
    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        \assert(\is_array($decoded));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
