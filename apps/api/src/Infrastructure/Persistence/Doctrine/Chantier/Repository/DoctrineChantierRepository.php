<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Repository;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Enum\StatutChantier;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Domain\Chantier\Repository\ChantierRepository;
use App\Domain\Chantier\ValueObject\ClientRef;
use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use App\Entity\User;
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
            $proprietaire = $this->entityManager->getReference(User::class, $chantier->proprietaireId);
            \assert($proprietaire instanceof User);
            $this->entityManager->persist($this->toDoctrine($chantier, $proprietaire));
        } else {
            $existante->adresseRue = $chantier->adresse->rue;
            $existante->adresseCodePostal = $chantier->adresse->codePostal;
            $existante->adresseVille = $chantier->adresse->ville;
            $existante->adressePays = $chantier->adresse->pays;
            $existante->surfaceM2 = $chantier->surface !== null ? (string) $chantier->surface->valeurM2 : null;
            $existante->statut = $chantier->statut;
            $existante->modifieLe = $chantier->modifieLe;
            $existante->clientId = $chantier->client?->id;
            $existante->clientNomCache = $chantier->client?->nomCache;
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

    public function getById(Uuid $id): Chantier
    {
        $chantier = $this->findById($id);

        if ($chantier === null) {
            throw ChantierIntrouvableException::avecId($id);
        }

        return $chantier;
    }

    public function findAllForUser(Uuid $proprietaireId): array
    {
        $entites = $this->entityManager
            ->getRepository(ChantierDoctrineEntity::class)
            ->findBy(
                [
                    'proprietaire' => $proprietaireId,
                    'statut' => array_map(
                        fn (StatutChantier $s) => $s->value,
                        [StatutChantier::EN_PREPARATION, StatutChantier::EN_COURS, StatutChantier::TERMINE],
                    ),
                ],
                ['creeLe' => 'DESC'],
            );

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

    private function toDoctrine(Chantier $chantier, User $proprietaire): ChantierDoctrineEntity
    {
        return new ChantierDoctrineEntity(
            id: $chantier->id,
            proprietaire: $proprietaire,
            adresseRue: $chantier->adresse->rue,
            adresseCodePostal: $chantier->adresse->codePostal,
            adresseVille: $chantier->adresse->ville,
            adressePays: $chantier->adresse->pays,
            surfaceM2: $chantier->surface !== null ? (string) $chantier->surface->valeurM2 : null,
            statut: $chantier->statut,
            creeLe: $chantier->creeLe,
            modifieLe: $chantier->modifieLe,
            clientId: $chantier->client?->id,
            clientNomCache: $chantier->client?->nomCache,
        );
    }

    private function toDomain(ChantierDoctrineEntity $entite): Chantier
    {
        $clientRef = null;
        if ($entite->clientId !== null && $entite->clientNomCache !== null) {
            $clientRef = new ClientRef(id: $entite->clientId, nomCache: $entite->clientNomCache);
        }

        return new Chantier(
            id: $entite->id,
            proprietaireId: $entite->proprietaire->id,
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
            client: $clientRef,
        );
    }
}
