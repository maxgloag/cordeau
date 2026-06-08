<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Photo;

use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PhotoApiTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function prepare_retourne_upload_url_et_remote_key(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        $mockStorage->method('generatePresignedPutUrl')
            ->willReturn('https://r2.example.com/presigned?sig=test');
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        $httpClient->request(
            'POST',
            '/api/photos/prepare',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['chantierId' => $chantier->id->toRfc4122()]) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $body = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertArrayHasKey('uploadUrl', $body);
        self::assertArrayHasKey('remoteKey', $body);
        self::assertStringStartsWith('photos/', $body['remoteKey']);
        self::assertSame('https://r2.example.com/presigned?sig=test', $body['uploadUrl']);
    }

    #[Test]
    public function prepare_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/photos/prepare',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: '{"chantierId":"00000000-0000-0000-0000-000000000001"}',
        );
        self::assertResponseStatusCodeSame(401);
    }
}
