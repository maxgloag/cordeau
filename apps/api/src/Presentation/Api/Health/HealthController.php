<?php

declare(strict_types=1);

namespace App\Presentation\Api\Health;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $dbStatus = $this->checkDatabase();

        $status = $dbStatus ? 'ok' : 'degraded';
        $httpStatus = $dbStatus ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse([
            'status' => $status,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'version' => '0.0.0',
            'services' => [
                'database' => $dbStatus ? 'ok' : 'unreachable',
            ],
        ], $httpStatus);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            return true;
        } catch (Exception) {
            return false;
        }
    }
}
