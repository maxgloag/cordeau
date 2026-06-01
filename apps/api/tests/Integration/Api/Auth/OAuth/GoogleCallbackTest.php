<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Auth\OAuth;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Entity\OAuthAccount;
use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleUserResolver;
use App\Auth\RegistrationPolicy;
use App\Auth\Repository\OAuthAccountRepository;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineUserRepository;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class GoogleCallbackTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function callback_cree_user_et_oauth_account_si_aucune_correspondance(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $container->set(GoogleUserResolver::class, new class implements GoogleUserResolver {
            public function resolveFromCurrentRequest(): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-new',
                    email: 'newuser@example.com',
                    emailVerified: true,
                    name: 'New User',
                );
            }
        });

        $httpClient->request('GET', '/auth/oauth/google/callback?code=fake&state=fake');

        self::assertResponseRedirects();
        self::assertStringContainsString('/?login_code=', $httpClient->getResponse()->headers->get('Location') ?? '');

        $userRepo = $container->get(DoctrineUserRepository::class);
        \assert($userRepo instanceof DoctrineUserRepository);
        $user = $userRepo->findByEmail('newuser@example.com');
        self::assertNotNull($user);
        self::assertSame('', $user->motDePasseHash);

        $oauthRepo = $container->get(OAuthAccountRepository::class);
        \assert($oauthRepo instanceof OAuthAccountRepository);
        $account = $oauthRepo->findByProviderAndProviderUserId('google', 'google-sub-new');
        self::assertNotNull($account);
        self::assertSame($user->id->toRfc4122(), $account->user->id->toRfc4122());
    }

    #[Test]
    public function callback_auto_link_si_email_verifie_et_user_existant(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $existing = UserFactory::createOne(['email' => 'bob@example.com']);

        $container->set(GoogleUserResolver::class, new class implements GoogleUserResolver {
            public function resolveFromCurrentRequest(): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-bob',
                    email: 'bob@example.com',
                    emailVerified: true,
                );
            }
        });

        $httpClient->request('GET', '/auth/oauth/google/callback?code=fake&state=fake');

        self::assertResponseRedirects();

        $oauthRepo = $container->get(OAuthAccountRepository::class);
        \assert($oauthRepo instanceof OAuthAccountRepository);
        $account = $oauthRepo->findByProviderAndProviderUserId('google', 'google-sub-bob');
        self::assertNotNull($account);
        self::assertSame($existing->id->toRfc4122(), $account->user->id->toRfc4122());
    }

    #[Test]
    public function callback_existing_oauth_account_login_le_user_lie(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $user = UserFactory::createOne(['email' => 'alice@example.com']);

        // Pre-creer un OAuthAccount lie
        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $oauthAccount = new OAuthAccount(
            id: \Symfony\Component\Uid\Uuid::v7(),
            user: $user->_real(),
            provider: 'google',
            providerUserId: 'google-sub-existing',
            email: 'alice@example.com',
        );
        $entityManager->persist($oauthAccount);
        $entityManager->flush();

        $container->set(GoogleUserResolver::class, new class implements GoogleUserResolver {
            public function resolveFromCurrentRequest(): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-existing',
                    email: 'alice@example.com',
                    emailVerified: true,
                );
            }
        });

        $httpClient->request('GET', '/auth/oauth/google/callback?code=fake&state=fake');

        self::assertResponseRedirects();

        // Pas de nouvel OAuthAccount cree
        $count = $entityManager->getRepository(OAuthAccount::class)->count(['provider' => 'google']);
        self::assertSame(1, $count);
    }

    #[Test]
    public function callback_redirect_avec_erreur_et_ne_cree_aucun_compte_si_inscription_fermee(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $container->set(RegistrationPolicy::class, new RegistrationPolicy(false));
        $container->set(GoogleUserResolver::class, new class implements GoogleUserResolver {
            public function resolveFromCurrentRequest(): GoogleUserInfo
            {
                return new GoogleUserInfo(
                    sub: 'google-sub-ferme',
                    email: 'ferme@example.com',
                    emailVerified: true,
                );
            }
        });

        $httpClient->request('GET', '/auth/oauth/google/callback?code=fake&state=fake');

        self::assertResponseRedirects();
        $location = $httpClient->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('oauth_error=registration_closed', $location);

        $userRepo = $container->get(DoctrineUserRepository::class);
        \assert($userRepo instanceof DoctrineUserRepository);
        self::assertNull($userRepo->findByEmail('ferme@example.com'));
    }

    #[Test]
    public function callback_redirect_vers_login_si_provider_echec(): void
    {
        $httpClient = static::createClient();
        $container = static::getContainer();

        $container->set(GoogleUserResolver::class, new class implements GoogleUserResolver {
            public function resolveFromCurrentRequest(): GoogleUserInfo
            {
                throw new GoogleAuthenticationFailedException('echec');
            }
        });

        $httpClient->request('GET', '/auth/oauth/google/callback?code=fake&state=fake');

        self::assertResponseRedirects();
        $location = $httpClient->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('/login?oauth_error=provider', $location);
    }

    #[Test]
    public function start_redirige_vers_google(): void
    {
        $httpClient = static::createClient();

        $httpClient->request('GET', '/auth/oauth/google/start');

        self::assertResponseRedirects();
        $location = $httpClient->getResponse()->headers->get('Location') ?? '';
        self::assertStringContainsString('accounts.google.com', $location);
    }
}
