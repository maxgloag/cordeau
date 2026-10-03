<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Auth;

use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LoginThrottlingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le pool du limiteur est sur fichiers (il survit aux requêtes) : l'isoler entre les tests.
        $pool = static::getContainer()->get('cache.rate_limiter');
        \assert($pool instanceof CacheItemPoolInterface);
        $pool->clear();
        static::ensureKernelShutdown();
    }

    #[Test]
    public function apres_cinq_echecs_le_login_renvoie_429_meme_avec_le_bon_mot_de_passe(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr']);

        for ($i = 0; $i < 5; ++$i) {
            self::login($client, 'artisan@test.fr', 'Mauvais1');
            self::assertResponseStatusCodeSame(401);
        }

        self::login($client, 'artisan@test.fr', 'Password1');

        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($client->getResponse()->headers->get('Retry-After'));
        self::assertStringNotContainsString('token', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function le_blocage_est_insensible_a_la_casse_de_l_email(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr']);

        for ($i = 0; $i < 5; ++$i) {
            self::login($client, 'Artisan@Test.fr', 'Mauvais1');
        }

        self::login($client, 'artisan@test.fr', 'Password1');

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function le_blocage_d_un_email_n_affecte_pas_les_autres_comptes(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr']);
        UserFactory::createOne(['email' => 'autre@test.fr']);

        for ($i = 0; $i < 6; ++$i) {
            self::login($client, 'artisan@test.fr', 'Mauvais1');
        }

        self::login($client, 'autre@test.fr', 'Password1');

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function un_login_reussi_remet_le_compteur_a_zero(): void
    {
        $client = static::createClient();
        UserFactory::createOne(['email' => 'artisan@test.fr']);

        for ($i = 0; $i < 4; ++$i) {
            self::login($client, 'artisan@test.fr', 'Mauvais1');
        }
        self::login($client, 'artisan@test.fr', 'Password1');
        self::assertResponseStatusCodeSame(200);

        for ($i = 0; $i < 4; ++$i) {
            self::login($client, 'artisan@test.fr', 'Mauvais1');
            self::assertResponseStatusCodeSame(401);
        }

        self::login($client, 'artisan@test.fr', 'Password1');
        self::assertResponseStatusCodeSame(200);
    }

    private static function login(KernelBrowser $client, string $email, string $motDePasse): void
    {
        $client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'motDePasse' => $motDePasse]) ?: '',
        );
    }
}
