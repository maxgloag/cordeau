<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class TokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly DoctrineAuthTokenRepository $authTokenRepository)
    {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with((string) $header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('Authorization', '');
        $rawToken = substr((string) $header, 7);

        $dotPos = strpos($rawToken, '.');
        if ($dotPos === false) {
            throw new CustomUserMessageAuthenticationException('Token invalide.');
        }

        $selector = substr($rawToken, 0, $dotPos);
        $verifier = substr($rawToken, $dotPos + 1);

        $authToken = $this->authTokenRepository->findBySelector($selector);

        if ($authToken === null || !$authToken->estValide()) {
            throw new CustomUserMessageAuthenticationException('Token invalide ou expiré.');
        }

        if (!password_verify($verifier, $authToken->verifierHash)) {
            throw new CustomUserMessageAuthenticationException('Token invalide ou expiré.');
        }

        $email = $authToken->utilisateur->email;

        return new SelfValidatingPassport(new UserBadge($email));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }
}
