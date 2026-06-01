<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\UseCase;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Entity\OAuthAccount;
use App\Auth\Exception\RegistrationClosedException;
use App\Auth\Port\OAuthAccountStore;
use App\Auth\Port\UserStore;
use App\Auth\RegistrationPolicy;
use App\Auth\UseCase\AuthentifierViaGoogleUseCase;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AuthentifierViaGoogleUseCaseTest extends TestCase
{
    #[Test]
    public function login_si_oauth_account_existe_deja_pour_ce_sub(): void
    {
        $existingUser = new User(Uuid::v7(), 'bob@example.com', 'hash');
        $existingAccount = new OAuthAccount(Uuid::v7(), $existingUser, 'google', 'google-sub-123', 'bob@example.com');

        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->expects(self::once())
            ->method('findByProviderAndProviderUserId')
            ->with('google', 'google-sub-123')
            ->willReturn($existingAccount);
        $oauthRepo->expects(self::never())->method('save');

        $userRepo = $this->createMock(UserStore::class);
        $userRepo->expects(self::never())->method('findByEmail');
        $userRepo->expects(self::never())->method('save');

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(true));

        $result = $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-123',
            email: 'bob@example.com',
            emailVerified: true,
        ));

        self::assertSame($existingUser, $result);
    }

    #[Test]
    public function auto_link_si_email_verifie_et_user_existant(): void
    {
        $existingUser = new User(Uuid::v7(), 'bob@example.com', 'hash');

        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->method('findByProviderAndProviderUserId')->willReturn(null);
        $oauthRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (OAuthAccount $oa) => $oa->user === $existingUser
                && $oa->provider === 'google'
                && $oa->providerUserId === 'google-sub-456'));

        $userRepo = $this->createMock(UserStore::class);
        $userRepo->expects(self::once())
            ->method('findByEmail')
            ->with('bob@example.com')
            ->willReturn($existingUser);
        $userRepo->expects(self::never())->method('save');

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(true));

        $result = $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-456',
            email: 'bob@example.com',
            emailVerified: true,
        ));

        self::assertSame($existingUser, $result);
    }

    #[Test]
    public function cree_user_et_oauth_si_aucune_correspondance(): void
    {
        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->method('findByProviderAndProviderUserId')->willReturn(null);
        $oauthRepo->expects(self::once())->method('save');

        $userRepo = $this->createMock(UserStore::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $createdUser = null;
        $userRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (User $u) use (&$createdUser) {
                $createdUser = $u;
                return $u->email === 'alice@example.com' && $u->motDePasseHash === '';
            }));

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(true));

        $result = $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-789',
            email: 'alice@example.com',
            emailVerified: true,
        ));

        self::assertNotNull($createdUser);
        self::assertSame($createdUser, $result);
    }

    #[Test]
    public function refuse_la_creation_dun_nouveau_user_si_self_service_desactive(): void
    {
        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->method('findByProviderAndProviderUserId')->willReturn(null);
        $oauthRepo->expects(self::never())->method('save');

        $userRepo = $this->createMock(UserStore::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $userRepo->expects(self::never())->method('save');

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(false));

        $this->expectException(RegistrationClosedException::class);

        $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-inconnu',
            email: 'inconnu@example.com',
            emailVerified: true,
        ));
    }

    #[Test]
    public function autorise_le_login_dun_oauth_account_existant_meme_si_self_service_desactive(): void
    {
        $existingUser = new User(Uuid::v7(), 'connu@example.com', 'hash');
        $existingAccount = new OAuthAccount(Uuid::v7(), $existingUser, 'google', 'google-sub-connu', 'connu@example.com');

        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->method('findByProviderAndProviderUserId')->willReturn($existingAccount);
        $oauthRepo->expects(self::never())->method('save');

        $userRepo = $this->createMock(UserStore::class);
        $userRepo->expects(self::never())->method('save');

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(false));

        $result = $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-connu',
            email: 'connu@example.com',
            emailVerified: true,
        ));

        self::assertSame($existingUser, $result);
    }

    #[Test]
    public function cree_nouveau_user_si_email_non_verifie_meme_si_email_existant_en_db(): void
    {
        // Cas defensif : Google renvoie email_verified=false (rare mais possible).
        // On NE doit PAS auto-link, on cree un nouveau User -> erreur de contrainte unique
        // attendue plus loin dans la stack (test integration). Ici on verifie juste
        // que le UseCase tente la creation et ne touche pas a l'user existant.
        $existingUserInDb = new User(Uuid::v7(), 'eve@example.com', 'hash');

        $oauthRepo = $this->createMock(OAuthAccountStore::class);
        $oauthRepo->method('findByProviderAndProviderUserId')->willReturn(null);

        $userRepo = $this->createMock(UserStore::class);
        // emailVerified=false -> la branche auto-link est SHORT-CIRCUITEE,
        // findByEmail ne doit PAS etre appele
        $userRepo->expects(self::never())->method('findByEmail');
        $userRepo->expects(self::once())->method('save');
        $oauthRepo->expects(self::once())->method('save');

        $useCase = new AuthentifierViaGoogleUseCase($oauthRepo, $userRepo, new RegistrationPolicy(true));

        $result = $useCase->execute(new GoogleUserInfo(
            sub: 'google-sub-eve',
            email: 'eve@example.com',
            emailVerified: false,
        ));

        self::assertNotSame($existingUserInDb, $result);
        self::assertSame('eve@example.com', $result->email);
    }
}
