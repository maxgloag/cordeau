<?php

declare(strict_types=1);

namespace App\Client\Repository;

use App\Client\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Client>
 */
final class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return list<Client>
     */
    public function findAllForUser(Uuid $proprietaireId): array
    {
        return array_values(
            $this->findBy(['proprietaire' => $proprietaireId], ['nom' => 'ASC']),
        );
    }

    public function save(Client $client): void
    {
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    public function remove(Client $client): void
    {
        $this->getEntityManager()->remove($client);
        $this->getEntityManager()->flush();
    }
}
