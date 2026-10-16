<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

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

    /**
     * @param array<string, mixed> $payload
     */
    private static function postJson(KernelBrowser $client, string $uri, array $payload): void
    {
        $client->request(
            'POST',
            $uri,
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload) ?: '',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function patchJson(KernelBrowser $client, string $uri, array $payload): void
    {
        $client->request(
            'PATCH',
            $uri,
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode($payload) ?: '',
        );
    }
}
