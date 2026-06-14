<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tache')]
#[ORM\Index(columns: ['lot_id', 'ordre'], name: 'idx_tache_lot_ordre')]
class TacheDoctrineEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        public readonly Uuid $id,
        #[ORM\Column(name: 'lot_id', type: UuidType::NAME)]
        public Uuid $lotId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public string $libelle,
        #[ORM\Column(type: Types::BOOLEAN)]
        public bool $faite,
        #[ORM\Column(name: 'faite_le', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?\DateTimeImmutable $faiteLe,
        #[ORM\Column(type: Types::INTEGER)]
        public int $ordre,
    ) {
    }
}
