<?php

declare(strict_types=1);

namespace App\Client\EventListener;

use App\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;

#[AsEntityListener(event: Events::preUpdate, entity: Client::class)]
final class ClientRenameListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function preUpdate(Client $client, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('nom')) {
            return;
        }

        // Propagation synchrone du nouveau nom sur tous les chantiers liés (cf ADR 0011).
        // Un UPDATE groupé évite le N+1 qu'un loop de persist/flush produirait.
        $this->em->createQueryBuilder()
            ->update(ChantierDoctrineEntity::class, 'c')
            ->set('c.clientNomCache', ':nom')
            ->where('c.clientId = :id')
            ->setParameter('nom', $client->nom)
            ->setParameter('id', $client->id)
            ->getQuery()
            ->execute();
    }
}
