<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Repository;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineChantierRepository implements ChantierRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Chantier $chantier): void
    {
        $repository = $this->entityManager->getRepository(ChantierDoctrineEntity::class);
        $existante = $repository->find($chantier->id);

        if ($existante === null) {
            $this->entityManager->persist($this->toDoctrine($chantier));
        } else {
            $existante->adresseRue = $chantier->adresse->rue;
            $existante->adresseCodePostal = $chantier->adresse->codePostal;
            $existante->adresseVille = $chantier->adresse->ville;
            $existante->adressePays = $chantier->adresse->pays;
            $existante->surfaceM2 = $chantier->surface !== null ? (string) $chantier->surface->valeurM2 : null;
            $existante->statut = $chantier->statut;
            $existante->modifieLe = $chantier->modifieLe;
        }

        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Chantier
    {
        $entite = $this->entityManager
            ->getRepository(ChantierDoctrineEntity::class)
            ->find($id);

        return $entite !== null ? $this->toDomain($entite) : null;
    }

    public function findAll(): array
    {
        $entites = $this->entityManager
            ->getRepository(ChantierDoctrineEntity::class)
            ->findBy([], ['creeLe' => 'DESC']);

        return array_map(fn (ChantierDoctrineEntity $e): Chantier => $this->toDomain($e), $entites);
    }

    public function delete(Uuid $id): void
    {
        $entite = $this->entityManager
            ->getRepository(ChantierDoctrineEntity::class)
            ->find($id);

        if ($entite === null) {
            return;
        }

        $this->entityManager->remove($entite);
        $this->entityManager->flush();
    }

    private function toDoctrine(Chantier $chantier): ChantierDoctrineEntity
    {
        return new ChantierDoctrineEntity(
            id: $chantier->id,
            adresseRue: $chantier->adresse->rue,
            adresseCodePostal: $chantier->adresse->codePostal,
            adresseVille: $chantier->adresse->ville,
            adressePays: $chantier->adresse->pays,
            surfaceM2: $chantier->surface !== null ? (string) $chantier->surface->valeurM2 : null,
            statut: $chantier->statut,
            creeLe: $chantier->creeLe,
            modifieLe: $chantier->modifieLe,
        );
    }

    private function toDomain(ChantierDoctrineEntity $entite): Chantier
    {
        return new Chantier(
            id: $entite->id,
            adresse: new Adresse(
                rue: $entite->adresseRue,
                codePostal: $entite->adresseCodePostal,
                ville: $entite->adresseVille,
                pays: $entite->adressePays,
            ),
            surface: $entite->surfaceM2 !== null ? new Surface((float) $entite->surfaceM2) : null,
            statut: $entite->statut,
            creeLe: $entite->creeLe,
            modifieLe: $entite->modifieLe,
        );
    }
}
