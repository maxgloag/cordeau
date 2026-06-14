<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Repository;

use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use App\Domain\Chantier\Repository\TacheRepository;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\TacheDoctrineEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineTacheRepository implements TacheRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Tache $tache): void
    {
        $repository = $this->entityManager->getRepository(TacheDoctrineEntity::class);
        $existante = $repository->find($tache->id);

        if ($existante === null) {
            $this->entityManager->persist($this->toDoctrine($tache));
        } else {
            $existante->lotId = $tache->lotId;
            $existante->libelle = $tache->libelle;
            $existante->faite = $tache->faite;
            $existante->faiteLe = $tache->faiteLe;
            $existante->ordre = $tache->ordre;
        }

        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Tache
    {
        $entite = $this->entityManager
            ->getRepository(TacheDoctrineEntity::class)
            ->find($id);

        return $entite !== null ? $this->toDomain($entite) : null;
    }

    public function getById(Uuid $id): Tache
    {
        $tache = $this->findById($id);

        if ($tache === null) {
            throw TacheIntrouvableException::avecId($id);
        }

        return $tache;
    }

    public function findAllForLot(Uuid $lotId): array
    {
        $entites = $this->entityManager
            ->getRepository(TacheDoctrineEntity::class)
            ->findBy(['lotId' => $lotId], ['ordre' => 'ASC']);

        return array_map(fn (TacheDoctrineEntity $e): Tache => $this->toDomain($e), $entites);
    }

    public function delete(Uuid $id): void
    {
        $entite = $this->entityManager
            ->getRepository(TacheDoctrineEntity::class)
            ->find($id);

        if ($entite === null) {
            return;
        }

        $this->entityManager->remove($entite);
        $this->entityManager->flush();
    }

    private function toDoctrine(Tache $tache): TacheDoctrineEntity
    {
        return new TacheDoctrineEntity(
            id: $tache->id,
            lotId: $tache->lotId,
            libelle: $tache->libelle,
            faite: $tache->faite,
            faiteLe: $tache->faiteLe,
            ordre: $tache->ordre,
        );
    }

    private function toDomain(TacheDoctrineEntity $entite): Tache
    {
        return new Tache(
            id: $entite->id,
            lotId: $entite->lotId,
            libelle: $entite->libelle,
            faite: $entite->faite,
            faiteLe: $entite->faiteLe,
            ordre: $entite->ordre,
        );
    }
}
