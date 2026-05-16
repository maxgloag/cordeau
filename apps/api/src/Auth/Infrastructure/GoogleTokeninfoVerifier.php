<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure;

use App\Auth\Dto\GoogleUserInfo;
use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleIdTokenVerifier;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifie un id_token via l'endpoint Google tokeninfo. Approche simple
 * (zero dep JWT), un appel HTTP par verification. Acceptable en V1 vu
 * que le mobile sign-in n'est pas un cas tres frequent. Migration vers
 * une verif JWT locale via JWKS si charge constatee.
 */
final class GoogleTokeninfoVerifier implements GoogleIdTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    /** @var list<string> */
    private readonly array $allowedAudiences;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $allowedAudiencesCsv,
    ) {
        $this->allowedAudiences = array_values(array_filter(
            array_map('trim', explode(',', $allowedAudiencesCsv)),
            static fn (string $aud): bool => $aud !== '',
        ));
    }

    public function verify(string $idToken): GoogleUserInfo
    {
        if ($idToken === '') {
            throw new GoogleAuthenticationFailedException('id_token vide.');
        }

        try {
            $response = $this->httpClient->request('GET', self::TOKENINFO_URL, [
                'query' => ['id_token' => $idToken],
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new GoogleAuthenticationFailedException('Echec contact Google tokeninfo : ' . $e->getMessage(), previous: $e);
        }

        if ($status !== 200) {
            throw new GoogleAuthenticationFailedException(\sprintf('Google tokeninfo a repondu %d.', $status));
        }

        $aud = isset($data['aud']) && \is_string($data['aud']) ? $data['aud'] : '';
        if ($this->allowedAudiences === [] || !\in_array($aud, $this->allowedAudiences, true)) {
            throw new GoogleAuthenticationFailedException(\sprintf('Audience "%s" non autorisee.', $aud));
        }

        $sub = isset($data['sub']) && \is_string($data['sub']) ? $data['sub'] : '';
        $email = isset($data['email']) && \is_string($data['email']) ? $data['email'] : '';
        if ($sub === '' || $email === '') {
            throw new GoogleAuthenticationFailedException('id_token sans sub ou email.');
        }

        $emailVerified = isset($data['email_verified'])
            && ($data['email_verified'] === true || $data['email_verified'] === 'true');

        $name = isset($data['name']) && \is_string($data['name']) ? $data['name'] : null;

        return new GoogleUserInfo(
            sub: $sub,
            email: $email,
            emailVerified: $emailVerified,
            name: $name,
        );
    }
}
