<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Repository\ClientRepository;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use App\Presentation\Api\Client\Resource\ClientResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<ClientResource, void>
 */
final class SupprimerClientProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ClientRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $client = $this->repository->find($this->extractUuid($uriVariables));

        if ($client === null) {
            return;
        }

        // Soft-detach : on délie les chantiers avant de supprimer le client (cf ADR 0011).
        $this->em->createQueryBuilder()
            ->update(ChantierDoctrineEntity::class, 'c')
            ->set('c.clientId', 'NULL')
            ->set('c.clientNomCache', 'NULL')
            ->where('c.clientId = :id')
            ->setParameter('id', $client->id)
            ->getQuery()
            ->execute();

        $this->repository->remove($client);
    }
}
