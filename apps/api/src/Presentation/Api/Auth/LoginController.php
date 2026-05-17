<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth;

use App\Entity\AuthToken;
use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineUserRepository;
use App\Security\TokenGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;


final class LoginController
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository,
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($body['email']) && \is_string($body['email']) ? trim($body['email']) : '';
        $motDePasse = isset($body['motDePasse']) && \is_string($body['motDePasse']) ? $body['motDePasse'] : '';

        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $motDePasse)) {
            return new JsonResponse(['message' => 'Identifiants incorrects.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->creerReponseToken($user, $request->headers->get('X-Device-Info'));
    }

    private function creerReponseToken(User $user, ?string $deviceInfo): JsonResponse
    {
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
            deviceInfo: $deviceInfo,
        );

        $this->authTokenRepository->save($authToken);

        return new JsonResponse([
            'id' => $user->id->toRfc4122(),
            'email' => $user->email,
            'token' => $access['token'],
            'refreshToken' => $refresh['refreshToken'],
            'expiresAt' => $access['expiresAt']->format(\DateTimeInterface::ATOM),
        ]);
    }
}
