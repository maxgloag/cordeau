<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertIsArray($response);
        self::assertSame('ok', $response['status']);
        self::assertArrayHasKey('timestamp', $response);
        self::assertArrayHasKey('version', $response);
        self::assertSame('0.0.0', $response['version']);
    }
}
