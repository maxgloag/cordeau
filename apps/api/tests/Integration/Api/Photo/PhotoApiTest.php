<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Photo;

use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\PhotoFactory;
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
            ->with(self::anything(), 'image/jpeg')
            ->willReturn('https://r2.example.com/presigned?sig=test');
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        $httpClient->request(
            'POST',
            '/api/photos/prepare',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'chantierId' => $chantier->id->toRfc4122(),
                'contentType' => 'image/jpeg',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $body = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertArrayHasKey('uploadUrl', $body);
        self::assertArrayHasKey('remoteKey', $body);
        $uploadUrl = $body['uploadUrl'];
        \assert(\is_string($uploadUrl));
        $remoteKey = $body['remoteKey'];
        \assert(\is_string($remoteKey));
        self::assertSame('https://r2.example.com/presigned?sig=test', $uploadUrl);
        self::assertStringStartsWith('photos/', $remoteKey);
    }

    #[Test]
    public function prepare_chantier_autre_user_retourne_403(): void
    {
        $httpClient = static::createClient();
        $autreUser = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $autreUser]);

        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        $httpClient->request(
            'POST',
            '/api/photos/prepare',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'chantierId' => $chantier->id->toRfc4122(),
                'contentType' => 'image/jpeg',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function prepare_content_type_non_image_retourne_422(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        $mockStorage->expects($this->never())->method('generatePresignedPutUrl');
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        // text/html accepté = hébergement de contenu arbitraire sur le domaine public photos
        $httpClient->request(
            'POST',
            '/api/photos/prepare',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'chantierId' => $chantier->id->toRfc4122(),
                'contentType' => 'text/html',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
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

    #[Test]
    public function confirm_cree_photo_en_db_et_retourne_resource(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        $mockStorage->method('getPublicUrl')
            ->willReturnCallback(fn (string $key) => 'https://photos.example.com/' . $key);
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        $userId = $user->_real()->id->toRfc4122();
        $remoteKey = 'photos/' . $userId . '/00000000-0000-7000-8000-000000000001';

        $httpClient->request(
            'POST',
            '/api/photos/confirm',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'remoteKey' => $remoteKey,
                'chantierId' => $chantier->id->toRfc4122(),
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $body = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertArrayHasKey('id', $body);
        self::assertSame($remoteKey, $body['remoteKey']);
        self::assertSame('https://photos.example.com/' . $remoteKey, $body['photoUrl']);
        self::assertNull($body['thumbnailUrl']);
    }

    #[Test]
    public function confirm_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/photos/confirm',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: '{"remoteKey":"photos/test","chantierId":"00000000-0000-0000-0000-000000000001"}',
        );
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function confirm_chantier_autre_user_retourne_403(): void
    {
        $httpClient = static::createClient();
        $owner = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $owner]);
        $httpClient->loginUser($autreUser->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        // autreUser envoie une clé appartenant à owner : IDOR doit être refusé
        $ownerKey = 'photos/' . $owner->_real()->id->toRfc4122() . '/00000000-0000-7000-8000-000000000001';

        $httpClient->request(
            'POST',
            '/api/photos/confirm',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'remoteKey' => $ownerKey,
                'chantierId' => $chantier->id->toRfc4122(),
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function get_collection_retourne_les_photos_du_chantier(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $autreChantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        PhotoFactory::createMany(3, ['chantierId' => $chantier->id, 'proprietaire' => $user]);
        PhotoFactory::createOne(['chantierId' => $autreChantier->id, 'proprietaire' => $user]);

        $httpClient->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122() . '/photos',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(200);
        $body = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertCount(3, $body);
    }

    #[Test]
    public function patch_legende_retourne_200(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $photo = PhotoFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $httpClient->request(
            'PATCH',
            '/api/photos/' . $photo->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['legende' => 'Fissure mur porteur salon']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $body = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertSame('Fissure mur porteur salon', $body['legende']);
    }

    #[Test]
    public function patch_legende_autre_user_retourne_404(): void
    {
        $httpClient = static::createClient();
        $owner = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $photo = PhotoFactory::createOne(['proprietaire' => $owner]);
        $httpClient->loginUser($autreUser->_real());

        $httpClient->request(
            'PATCH',
            '/api/photos/' . $photo->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['legende' => 'Tentative IDOR']) ?: '',
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function patch_legende_trop_longue_retourne_422(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $photo = PhotoFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $httpClient->request(
            'PATCH',
            '/api/photos/' . $photo->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['legende' => str_repeat('a', 281)]) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function delete_supprime_la_photo_de_la_db(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $photo = PhotoFactory::createOne(['proprietaire' => $user]);
        $httpClient->loginUser($user->_real());

        $mockStorage = $this->createMock(StorageAdapterInterface::class);
        $mockStorage->expects($this->never())->method('delete'); // deletion is async
        static::getContainer()->set(StorageAdapterInterface::class, $mockStorage);

        $httpClient->request('DELETE', '/api/photos/' . $photo->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(204);
    }
}
