<?php

declare(strict_types=1);

namespace App\Photo\Repository;

use App\Photo\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Photo>
 */
final class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * @return list<Photo>
     */
    public function findForChantier(Uuid $chantierId, Uuid $proprietaireId): array
    {
        return array_values(
            $this->findBy(
                ['chantierId' => $chantierId, 'proprietaire' => $proprietaireId],
                ['creeLe' => 'DESC'],
            ),
        );
    }

    public function save(Photo $photo): void
    {
        $this->getEntityManager()->persist($photo);
        $this->getEntityManager()->flush();
    }

    public function remove(Photo $photo): void
    {
        $this->getEntityManager()->remove($photo);
        $this->getEntityManager()->flush();
    }
}
