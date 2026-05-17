<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth\OAuth;

use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineOAuthLoginCodeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OAuthCodeExchangeController
{
    public function __construct(
        private readonly DoctrineOAuthLoginCodeRepository $loginCodeRepository,
    ) {
    }

    #[Route('/auth/oauth/code/exchange', name: 'auth_oauth_code_exchange', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        $code = \is_array($body) && isset($body['code']) && \is_string($body['code']) ? $body['code'] : '';

        if ($code === '') {
            return new JsonResponse(['message' => 'Code manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $loginCode = $this->loginCodeRepository->findByCode($code);

        if ($loginCode === null || !$loginCode->estValide()) {
            return new JsonResponse(['message' => 'Code invalide ou expiré.'], Response::HTTP_UNAUTHORIZED);
        }

        $loginCode->utiliser();
        $this->loginCodeRepository->save($loginCode);

        $user = $loginCode->authToken->utilisateur;
        \assert($user instanceof User);

        return new JsonResponse([
            'id' => $user->id->toRfc4122(),
            'email' => $user->email,
            'token' => $loginCode->tokenRaw,
            'refreshToken' => $loginCode->refreshTokenRaw,
            'expiresAt' => $loginCode->authToken->expiresAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
