<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Auth\Repository;

use App\Entity\AuthToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthToken>
 */
final class DoctrineAuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function findBySelector(string $selector): ?AuthToken
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    public function findByRefreshToken(string $refreshToken): ?AuthToken
    {
        $hash = hash('sha256', $refreshToken);

        return $this->findOneBy(['refreshTokenHash' => $hash]);
    }

    public function save(AuthToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }
}
