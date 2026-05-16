<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth\OAuth;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleStartController
{
    public function __construct(private readonly ClientRegistry $clientRegistry)
    {
    }

    #[Route('/auth/oauth/google/start', name: 'auth_oauth_google_start', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }
}
