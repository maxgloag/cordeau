<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Chantier\Entity;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'chantier')]
#[ORM\Index(columns: ['statut'], name: 'idx_chantier_statut')]
#[ORM\Index(columns: ['client_id'], name: 'idx_chantier_client')]
class ChantierDoctrineEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        public readonly Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'proprietaire_id', nullable: false)]
        public User $proprietaire,
        #[ORM\Column(name: 'adresse_rue', type: Types::STRING, length: 255)]
        public string $adresseRue,
        #[ORM\Column(name: 'adresse_code_postal', type: Types::STRING, length: 20)]
        public string $adresseCodePostal,
        #[ORM\Column(name: 'adresse_ville', type: Types::STRING, length: 255)]
        public string $adresseVille,
        #[ORM\Column(name: 'adresse_pays', type: Types::STRING, length: 2, options: ['fixed' => true])]
        public string $adressePays,
        #[ORM\Column(name: 'surface_m2', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
        public ?string $surfaceM2,
        #[ORM\Column(type: Types::STRING, length: 32, enumType: StatutChantier::class)]
        public StatutChantier $statut,
        #[ORM\Column(name: 'cree_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public readonly \DateTimeImmutable $creeLe,
        #[ORM\Column(name: 'modifie_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public \DateTimeImmutable $modifieLe,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $clientId = null,
        #[ORM\Column(name: 'client_nom_cache', type: Types::STRING, length: 255, nullable: true)]
        public ?string $clientNomCache = null,
    ) {
    }
}
