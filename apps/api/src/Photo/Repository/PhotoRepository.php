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

    /**
     * Compte les photos par chantier en une seule requête groupée (pas de N+1).
     *
     * @param list<Uuid> $chantierIds
     *
     * @return array<string, int> map chantierId (rfc4122) => nombre de photos
     */
    public function countByChantierIds(array $chantierIds): array
    {
        if ($chantierIds === []) {
            return [];
        }

        /** @var list<array{chantierId: Uuid, cnt: int}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.chantierId AS chantierId, COUNT(p.id) AS cnt')
            ->where('p.chantierId IN (:ids)')
            ->setParameter('ids', $chantierIds)
            ->groupBy('p.chantierId')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['chantierId']->toRfc4122()] = (int) $row['cnt'];
        }

        return $counts;
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
