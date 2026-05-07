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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository,
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenGenerator $tokenGenerator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($body['email']) && \is_string($body['email']) ? trim($body['email']) : '';
        $motDePasse = isset($body['motDePasse']) && \is_string($body['motDePasse']) ? $body['motDePasse'] : '';

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ]);

        if (\count($violations) > 0) {
            return new JsonResponse(['message' => 'Email invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $violations = $this->validator->validate($motDePasse, [
            new Assert\NotBlank(),
            new Assert\Length(min: 8, max: 255),
            new Assert\Regex(pattern: '/[A-Z]/', message: 'Le mot de passe doit contenir au moins une majuscule.'),
            new Assert\Regex(pattern: '/[0-9]/', message: 'Le mot de passe doit contenir au moins un chiffre.'),
        ]);

        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $v) {
                $messages[] = $v->getMessage();
            }

            return new JsonResponse(['message' => implode(' ', $messages)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->userRepository->emailExiste($email)) {
            return new JsonResponse(['message' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $user = new User(Uuid::v7(), $email, '');
        $user->motDePasseHash = $this->passwordHasher->hashPassword($user, $motDePasse);
        $this->userRepository->save($user);

        $estMobile = $request->headers->get('X-Client-Type') === 'mobile';

        if ($estMobile) {
            return $this->creerReponseToken($user, $request->headers->get('X-Device-Info'));
        }

        return new JsonResponse(['id' => $user->id->toRfc4122(), 'email' => $user->email], Response::HTTP_CREATED);
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
            'token' => $access['token'],
            'refreshToken' => $refresh['refreshToken'],
            'expiresAt' => $access['expiresAt']->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
