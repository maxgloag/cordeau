<?php

declare(strict_types=1);

namespace App\Presentation\Api\Auth;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return new JsonResponse([
            'id' => $user->id->toRfc4122(),
            'email' => $user->email,
        ]);
    }
}
