<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Auth\Repository;

use App\Entity\OAuthLoginCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthLoginCode>
 */
final class DoctrineOAuthLoginCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthLoginCode::class);
    }

    public function findByCode(string $code): ?OAuthLoginCode
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function save(OAuthLoginCode $loginCode): void
    {
        $this->getEntityManager()->persist($loginCode);
        $this->getEntityManager()->flush();
    }
}
