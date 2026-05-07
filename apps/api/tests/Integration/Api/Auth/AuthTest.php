<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Auth;

use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AuthTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

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

    #[Test]
    public function register_cree_un_compte_et_retourne_201(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'artisan@test.fr', 'motDePasse' => 'Password1']) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('artisan@test.fr', $data['email']);
        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function register_avec_email_deja_utilise_retourne_409(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'existant@test.fr']);
        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'existant@test.fr', 'motDePasse' => 'Password1']) ?: '',
        );

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function register_avec_mot_de_passe_invalide_retourne_422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'artisan@test.fr', 'motDePasse' => 'court']) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function login_retourne_200_et_les_infos_user(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr', 'motDePasseHash' => password_hash('Password1', PASSWORD_BCRYPT, ['cost' => 4])]);
        $client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'artisan@test.fr', 'motDePasse' => 'Password1']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('artisan@test.fr', $data['email']);
    }

    #[Test]
    public function login_avec_mauvais_mot_de_passe_retourne_401(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr']);
        $client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'artisan@test.fr', 'motDePasse' => 'Mauvais1']) ?: '',
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function login_mobile_retourne_un_token(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'mobile@test.fr', 'motDePasseHash' => password_hash('Password1', PASSWORD_BCRYPT, ['cost' => 4])]);
        $client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CLIENT_TYPE' => 'mobile'],
            content: json_encode(['email' => 'mobile@test.fr', 'motDePasse' => 'Password1']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertArrayHasKey('expiresAt', $data);
    }

    #[Test]
    public function me_retourne_le_user_authentifie(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne(['email' => 'artisan@test.fr']);
        $client->loginUser($user->_real());

        $client->request('GET', '/auth/me');

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('artisan@test.fr', $data['email']);
    }

    #[Test]
    public function me_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function token_mobile_permet_acces_api_chantiers(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'mobile@test.fr', 'motDePasseHash' => password_hash('Password1', PASSWORD_BCRYPT, ['cost' => 4])]);

        $client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CLIENT_TYPE' => 'mobile'],
            content: json_encode(['email' => 'mobile@test.fr', 'motDePasse' => 'Password1']) ?: '',
        );

        $loginData = self::decodeJson((string) $client->getResponse()->getContent());
        $token = $loginData['token'];
        \assert(\is_string($token));

        $client->request(
            'GET',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        self::assertResponseStatusCodeSame(200);
    }
}
