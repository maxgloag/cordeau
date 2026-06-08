<?php

declare(strict_types=1);

namespace App\Photo\Entity;

use App\Entity\User;
use App\Photo\Repository\PhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'photo')]
#[ORM\Index(columns: ['chantier_id'], name: 'idx_photo_chantier')]
#[ORM\Index(columns: ['proprietaire_id'], name: 'idx_photo_proprietaire')]
class Photo
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        public readonly Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'proprietaire_id', nullable: false)]
        public User $proprietaire,
        #[ORM\Column(name: 'chantier_id', type: UuidType::NAME)]
        public Uuid $chantierId,
        #[ORM\Column(name: 'lot_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $lotId,
        #[ORM\Column(name: 'tache_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $tacheId,
        #[ORM\Column(name: 'remote_key', type: Types::STRING, length: 500)]
        public string $remoteKey,
        #[ORM\Column(name: 'photo_url', type: Types::STRING, length: 500)]
        public string $photoUrl,
        #[ORM\Column(name: 'thumbnail_url', type: Types::STRING, length: 500, nullable: true)]
        public ?string $thumbnailUrl,
        #[ORM\Column(name: 'uploade_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public readonly \DateTimeImmutable $uploadeLe,
        #[ORM\Column(name: 'cree_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public readonly \DateTimeImmutable $creeLe,
    ) {
    }
}
