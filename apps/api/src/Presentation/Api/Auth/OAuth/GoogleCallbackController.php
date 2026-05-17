<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth\OAuth;

use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleUserResolver;
use App\Auth\UseCase\AuthentifierViaGoogleUseCase;
use App\Entity\AuthToken;
use App\Entity\OAuthLoginCode;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineOAuthLoginCodeRepository;
use App\Security\TokenGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class GoogleCallbackController
{
    public function __construct(
        private readonly GoogleUserResolver $resolver,
        private readonly AuthentifierViaGoogleUseCase $useCase,
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
        private readonly DoctrineOAuthLoginCodeRepository $loginCodeRepository,
        private readonly TokenGenerator $tokenGenerator,
        private readonly string $webAppUrl,
    ) {
    }

    #[Route('/auth/oauth/google/callback', name: 'auth_oauth_google_callback', methods: ['GET'])]
    public function __invoke(): Response
    {
        try {
            $googleInfo = $this->resolver->resolveFromCurrentRequest();
        } catch (GoogleAuthenticationFailedException) {
            return new RedirectResponse($this->webAppUrl . '/login?oauth_error=provider');
        }

        $user = $this->useCase->execute($googleInfo);

        $access = $this->tokenGenerator->generateAccessToken();
        $refresh = $this->tokenGenerator->generateRefreshToken();

        $authToken = new AuthToken(
            id: Uuid::v7(),
            utilisateur: $user,
            selector: $access['selector'],
            verifierHash: $access['verifierHash'],
            refreshTokenHash: $refresh['refreshTokenHash'],
            expiresAt: $access['expiresAt'],
            refreshExpiresAt: $refresh['refreshExpiresAt'],
            deviceInfo: 'web-oauth-google',
        );
        $this->authTokenRepository->save($authToken);

        $code = bin2hex(random_bytes(32));
        $loginCode = new OAuthLoginCode(
            id: Uuid::v7(),
            code: $code,
            authToken: $authToken,
            tokenRaw: $access['token'],
            refreshTokenRaw: $refresh['refreshToken'],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );
        $this->loginCodeRepository->save($loginCode);

        return new RedirectResponse($this->webAppUrl . '/?login_code=' . urlencode($code));
    }
}
