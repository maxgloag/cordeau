<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\OAuthAccount;
use App\Auth\Port\OAuthAccountStore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthAccount>
 */
final class OAuthAccountRepository extends ServiceEntityRepository implements OAuthAccountStore
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthAccount::class);
    }

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?OAuthAccount
    {
        return $this->findOneBy(['provider' => $provider, 'providerUserId' => $providerUserId]);
    }

    public function save(OAuthAccount $account): void
    {
        $this->getEntityManager()->persist($account);
        $this->getEntityManager()->flush();
    }
}
