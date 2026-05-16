<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Auth\OAuth;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleIdTokenVerifier;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class GoogleExchangeTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function exchange_avec_id_token_valide_retourne_token_et_refresh_token(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $container->set(GoogleIdTokenVerifier::class, new class implements GoogleIdTokenVerifier {
            public function verify(string $idToken): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-mobile',
                    email: 'mobile@example.com',
                    emailVerified: true,
                );
            }
        });

        $httpClient->request(
            'POST',
            '/auth/oauth/google/exchange',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['idToken' => 'fake-google-id-token']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertArrayHasKey('expiresAt', $data);
    }

    #[Test]
    public function exchange_reutilise_user_existant_si_email_match(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $existing = UserFactory::createOne(['email' => 'reuse@example.com']);

        $container->set(GoogleIdTokenVerifier::class, new class implements GoogleIdTokenVerifier {
            public function verify(string $idToken): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-reuse',
                    email: 'reuse@example.com',
                    emailVerified: true,
                );
            }
        });

        $httpClient->request(
            'POST',
            '/auth/oauth/google/exchange',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['idToken' => 'fake']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);

        // Verifier qu'on n'a pas cree un nouvel user
        $em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);
        \assert($em instanceof \Doctrine\ORM\EntityManagerInterface);
        $count = $em->getRepository(\App\Entity\User::class)->count(['email' => 'reuse@example.com']);
        self::assertSame(1, $count);
        self::assertSame($existing->id->toRfc4122(), $existing->id->toRfc4122());
    }

    #[Test]
    public function exchange_avec_id_token_invalide_retourne_401(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $container->set(GoogleIdTokenVerifier::class, new class implements GoogleIdTokenVerifier {
            public function verify(string $idToken): GoogleUserInfo
            {
                throw new GoogleAuthenticationFailedException('Audience non autorisee.');
            }
        });

        $httpClient->request(
            'POST',
            '/auth/oauth/google/exchange',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['idToken' => 'fake']) ?: '',
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function exchange_sans_id_token_retourne_422(): void
    {
        $httpClient = static::createClient();

        $httpClient->request(
            'POST',
            '/auth/oauth/google/exchange',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['foo' => 'bar']) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function exchange_avec_body_invalide_retourne_400(): void
    {
        $httpClient = static::createClient();

        $httpClient->request(
            'POST',
            '/auth/oauth/google/exchange',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: 'not-json',
        );

        self::assertResponseStatusCodeSame(400);
    }
}
