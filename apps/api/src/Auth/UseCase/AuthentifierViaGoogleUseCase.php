<?php

declare(strict_types=1);

namespace App\Auth\UseCase;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Entity\OAuthAccount;
use App\Auth\Exception\RegistrationClosedException;
use App\Auth\Port\OAuthAccountStore;
use App\Auth\Port\UserStore;
use App\Auth\RegistrationPolicy;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

/**
 * Logique d'auto-link Google (cf ADR 0013) :
 * 1. OAuthAccount existe pour (google, sub) -> login l'user associe
 * 2. Sinon, si emailVerified ET un User existe avec cet email -> creer OAuthAccount, lier, login
 * 3. Sinon -> creer User (sans password) + OAuthAccount, login
 */
final class AuthentifierViaGoogleUseCase
{
    private const PROVIDER = 'google';

    public function __construct(
        private readonly OAuthAccountStore $oauthAccountStore,
        private readonly UserStore $userStore,
        private readonly RegistrationPolicy $registrationPolicy,
    ) {
    }

    public function execute(GoogleUserInfo $googleUser): User
    {
        $existing = $this->oauthAccountStore->findByProviderAndProviderUserId(self::PROVIDER, $googleUser->sub);
        if ($existing !== null) {
            return $existing->user;
        }

        if ($googleUser->emailVerified) {
            $user = $this->userStore->findByEmail($googleUser->email);
            if ($user !== null) {
                $this->oauthAccountStore->save(new OAuthAccount(
                    id: Uuid::v7(),
                    user: $user,
                    provider: self::PROVIDER,
                    providerUserId: $googleUser->sub,
                    email: $googleUser->email,
                ));

                return $user;
            }
        }

        if (!$this->registrationPolicy->selfServiceEnabled()) {
            throw new RegistrationClosedException('Les inscriptions sont actuellement fermees.');
        }

        $user = new User(Uuid::v7(), $googleUser->email, '');
        $this->userStore->save($user);

        $this->oauthAccountStore->save(new OAuthAccount(
            id: Uuid::v7(),
            user: $user,
            provider: self::PROVIDER,
            providerUserId: $googleUser->sub,
            email: $googleUser->email,
        ));

        return $user;
    }
}
