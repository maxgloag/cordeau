<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth;

use App\Entity\AuthToken;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use App\Security\TokenGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class RefreshController
{
    public function __construct(
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $body */
        $body = json_decode((string) $request->getContent(), true);
        $refreshToken = \is_array($body) && isset($body['refreshToken']) && \is_string($body['refreshToken'])
            ? $body['refreshToken']
            : '';

        if ($refreshToken === '') {
            return new JsonResponse(['message' => 'Refresh token manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $authToken = $this->authTokenRepository->findByRefreshToken($refreshToken);

        if ($authToken === null
            || $authToken->refreshExpiresAt === null
            || $authToken->refreshExpiresAt <= new \DateTimeImmutable()
            || $authToken->revokedAt !== null
        ) {
            return new JsonResponse(['message' => 'Refresh token invalide ou expiré.'], Response::HTTP_UNAUTHORIZED);
        }

        $authToken->revoquer();
        $this->authTokenRepository->save($authToken);

        $access = $this->tokenGenerator->generateAccessToken();
        $refresh = $this->tokenGenerator->generateRefreshToken();

        $nouveauToken = new AuthToken(
            id: Uuid::v7(),
            utilisateur: $authToken->utilisateur,
            selector: $access['selector'],
            verifierHash: $access['verifierHash'],
            refreshTokenHash: $refresh['refreshTokenHash'],
            expiresAt: $access['expiresAt'],
            refreshExpiresAt: $refresh['refreshExpiresAt'],
            deviceInfo: $authToken->deviceInfo,
        );

        $this->authTokenRepository->save($nouveauToken);

        return new JsonResponse([
            'token' => $access['token'],
            'refreshToken' => $refresh['refreshToken'],
            'expiresAt' => $access['expiresAt']->format(\DateTimeInterface::ATOM),
        ]);
    }
}
