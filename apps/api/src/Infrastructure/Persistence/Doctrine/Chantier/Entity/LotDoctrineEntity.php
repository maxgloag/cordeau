<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Entity;

use App\Domain\Chantier\Enum\ModeFacturation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lot')]
#[ORM\Index(columns: ['chantier_id', 'ordre'], name: 'idx_lot_chantier_ordre')]
class LotDoctrineEntity
{
    /**
     * @param list<array{note: string, horodatage: string}> $imprevus
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        public readonly Uuid $id,
        #[ORM\Column(name: 'chantier_id', type: UuidType::NAME)]
        public Uuid $chantierId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public string $nom,
        #[ORM\Column(type: Types::STRING, length: 16, enumType: ModeFacturation::class)]
        public ModeFacturation $mode,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
        public ?string $estimation,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
        public ?string $reel,
        #[ORM\Column(type: Types::INTEGER)]
        public int $ordre,
        #[ORM\Column(type: Types::JSON)]
        public array $imprevus,
    ) {
    }
}
