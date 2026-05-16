<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth\OAuth;

use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Port\GoogleUserResolver;
use App\Auth\UseCase\AuthentifierViaGoogleUseCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleCallbackController
{
    public function __construct(
        private readonly GoogleUserResolver $resolver,
        private readonly AuthentifierViaGoogleUseCase $useCase,
        private readonly Security $security,
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

        $this->security->login($user, firewallName: 'main');

        return new RedirectResponse($this->webAppUrl . '/');
    }
}
