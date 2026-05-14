<?php

declare(strict_types=1);

namespace App\Client\Entity;

use App\Client\Repository\ClientRepository;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[ORM\Index(columns: ['proprietaire_id', 'nom'], name: 'idx_client_proprietaire_nom')]
class Client
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        public readonly Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'proprietaire_id', nullable: false)]
        public User $proprietaire,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public string $nom,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        public ?string $email,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        public ?string $telephone,
        #[ORM\Column(name: 'adresse_rue', type: Types::STRING, length: 255)]
        public string $adresseRue,
        #[ORM\Column(name: 'adresse_code_postal', type: Types::STRING, length: 20)]
        public string $adresseCodePostal,
        #[ORM\Column(name: 'adresse_ville', type: Types::STRING, length: 255)]
        public string $adresseVille,
        #[ORM\Column(name: 'adresse_pays', type: Types::STRING, length: 2, options: ['fixed' => true])]
        public string $adressePays,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        public ?string $notes,
        #[ORM\Column(name: 'cree_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public readonly \DateTimeImmutable $creeLe,
        #[ORM\Column(name: 'modifie_le', type: Types::DATETIMETZ_IMMUTABLE)]
        public \DateTimeImmutable $modifieLe,
    ) {
    }
}
