<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class ModifierClientPayload
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $nom = null,
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public readonly ?string $email = null,
        public readonly ?string $telephone = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $adresseRue = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 20)]
        public readonly ?string $adresseCodePostal = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $adresseVille = null,
        #[Assert\Length(exactly: 2)]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public readonly ?string $adressePays = null,
        public readonly ?string $notes = null,
    ) {
    }
}
