<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Repository;

use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\Repository\LotRepository;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Imprevu;
use App\Domain\Chantier\ValueObject\Reel;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\LotDoctrineEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineLotRepository implements LotRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(Lot $lot): void
    {
        $repository = $this->entityManager->getRepository(LotDoctrineEntity::class);
        $existante = $repository->find($lot->id);

        if ($existante === null) {
            $this->entityManager->persist($this->toDoctrine($lot));
        } else {
            $existante->chantierId = $lot->chantierId;
            $existante->nom = $lot->nom;
            $existante->mode = $lot->mode;
            $existante->estimation = self::valeurEnColonne($lot->estimation?->valeur);
            $existante->reel = self::valeurEnColonne($lot->reel?->valeur);
            $existante->ordre = $lot->ordre;
            $existante->imprevus = self::imprevusEnColonne($lot->imprevus);
        }

        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Lot
    {
        $entite = $this->entityManager
            ->getRepository(LotDoctrineEntity::class)
            ->find($id);

        return $entite !== null ? $this->toDomain($entite) : null;
    }

    public function getById(Uuid $id): Lot
    {
        $lot = $this->findById($id);

        if ($lot === null) {
            throw LotIntrouvableException::avecId($id);
        }

        return $lot;
    }

    public function findAllForChantier(Uuid $chantierId): array
    {
        $entites = $this->entityManager
            ->getRepository(LotDoctrineEntity::class)
            ->findBy(['chantierId' => $chantierId], ['ordre' => 'ASC']);

        return array_map(fn (LotDoctrineEntity $e): Lot => $this->toDomain($e), $entites);
    }

    public function delete(Uuid $id): void
    {
        $entite = $this->entityManager
            ->getRepository(LotDoctrineEntity::class)
            ->find($id);

        if ($entite === null) {
            return;
        }

        $this->entityManager->remove($entite);
        $this->entityManager->flush();
    }

    private function toDoctrine(Lot $lot): LotDoctrineEntity
    {
        return new LotDoctrineEntity(
            id: $lot->id,
            chantierId: $lot->chantierId,
            nom: $lot->nom,
            mode: $lot->mode,
            estimation: self::valeurEnColonne($lot->estimation?->valeur),
            reel: self::valeurEnColonne($lot->reel?->valeur),
            ordre: $lot->ordre,
            imprevus: self::imprevusEnColonne($lot->imprevus),
        );
    }

    private function toDomain(LotDoctrineEntity $entite): Lot
    {
        return new Lot(
            id: $entite->id,
            chantierId: $entite->chantierId,
            nom: $entite->nom,
            mode: $entite->mode,
            estimation: $entite->estimation !== null ? new Estimation((float) $entite->estimation) : null,
            reel: $entite->reel !== null ? new Reel((float) $entite->reel) : null,
            ordre: $entite->ordre,
            imprevus: array_map(
                static fn (array $i): Imprevu => new Imprevu(
                    note: $i['note'],
                    horodatage: new \DateTimeImmutable($i['horodatage']),
                ),
                $entite->imprevus,
            ),
        );
    }

    private static function valeurEnColonne(?float $valeur): ?string
    {
        return $valeur !== null ? (string) $valeur : null;
    }

    /**
     * @param list<Imprevu> $imprevus
     *
     * @return list<array{note: string, horodatage: string}>
     */
    private static function imprevusEnColonne(array $imprevus): array
    {
        return array_map(
            static fn (Imprevu $i): array => [
                'note' => $i->note,
                'horodatage' => $i->horodatage->format(\DateTimeInterface::ATOM),
            ],
            $imprevus,
        );
    }
}
