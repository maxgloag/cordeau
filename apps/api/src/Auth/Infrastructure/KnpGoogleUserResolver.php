<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleUserResolver;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;

final class KnpGoogleUserResolver implements GoogleUserResolver
{
    public function __construct(private readonly ClientRegistry $clientRegistry)
    {
    }

    public function resolveFromCurrentRequest(): GoogleUserInfo
    {
        $client = $this->clientRegistry->getClient('google');
        \assert($client instanceof GoogleClient);

        try {
            $accessToken = $client->getAccessToken();
            \assert($accessToken instanceof AccessToken);
            $googleUser = $client->fetchUserFromToken($accessToken);
        } catch (IdentityProviderException $e) {
            throw new GoogleAuthenticationFailedException('Echec de l\'authentification Google : ' . $e->getMessage(), previous: $e);
        }

        \assert($googleUser instanceof GoogleUser);

        $email = $googleUser->getEmail();
        if ($email === null || $email === '') {
            throw new GoogleAuthenticationFailedException('Google n\'a pas retourne d\'email.');
        }

        $sub = $googleUser->getId();
        if (!\is_string($sub) && !\is_int($sub)) {
            throw new GoogleAuthenticationFailedException('Google n\'a pas retourne d\'identifiant valide.');
        }

        return new GoogleUserInfo(
            sub: (string) $sub,
            email: $email,
            emailVerified: (bool) ($googleUser->toArray()['email_verified'] ?? false),
            name: $googleUser->getName(),
        );
    }
}
