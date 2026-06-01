<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth\OAuth;

use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Exception\RegistrationClosedException;
use App\Auth\Port\GoogleIdTokenVerifier;
use App\Auth\UseCase\AuthentifierViaGoogleUseCase;
use App\Entity\AuthToken;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use App\Security\TokenGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Endpoint mobile : recoit un id_token Google (obtenu via expo-auth-session
 * + PKCE cote app), le verifie, applique le use case d'auto-link, et retourne
 * un AuthToken (cf ADR 0003) pour authentifier les requetes suivantes.
 */
final class GoogleExchangeController
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $verifier,
        private readonly AuthentifierViaGoogleUseCase $useCase,
        private readonly TokenGenerator $tokenGenerator,
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
    ) {
    }

    #[Route('/auth/oauth/google/exchange', name: 'auth_oauth_google_exchange', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $idToken = isset($body['idToken']) && \is_string($body['idToken']) ? $body['idToken'] : '';
        if ($idToken === '') {
            return new JsonResponse(['message' => 'idToken requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $googleInfo = $this->verifier->verify($idToken);
        } catch (GoogleAuthenticationFailedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $user = $this->useCase->execute($googleInfo);
        } catch (RegistrationClosedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

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
            deviceInfo: $request->headers->get('X-Device-Info'),
        );
        $this->authTokenRepository->save($authToken);

        return new JsonResponse([
            'token' => $access['token'],
            'refreshToken' => $refresh['refreshToken'],
            'expiresAt' => $access['expiresAt']->format(\DateTimeInterface::ATOM),
        ]);
    }
}
