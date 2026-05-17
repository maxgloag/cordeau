<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController
{
    public function __construct(
        private readonly DoctrineAuthTokenRepository $authTokenRepository,
    ) {
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $header = $request->headers->get('Authorization', '');
        $rawToken = substr((string) $header, 7);
        $dotPos = strpos($rawToken, '.');

        if ($dotPos !== false) {
            $selector = substr($rawToken, 0, $dotPos);
            $authToken = $this->authTokenRepository->findBySelector($selector);
            if ($authToken !== null) {
                $authToken->revoquer();
                $this->authTokenRepository->save($authToken);
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
