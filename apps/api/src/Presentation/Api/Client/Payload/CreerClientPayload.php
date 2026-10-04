<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Payload;

use App\Shared\ValueObject\Adresse;
use Symfony\Component\Validator\Constraints as Assert;

final class CreerClientPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $nom = '',
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public readonly ?string $email = null,
        public readonly ?string $telephone = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $adresseRue = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public readonly string $adresseCodePostal = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $adresseVille = '',
        #[Assert\Length(exactly: 2)]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public readonly string $adressePays = 'FR',
        public readonly ?string $notes = null,
        #[Assert\Uuid]
        public readonly ?string $uuid = null,
    ) {
    }

    public function toAdresse(): Adresse
    {
        return new Adresse(
            rue: $this->adresseRue,
            codePostal: $this->adresseCodePostal,
            ville: $this->adresseVille,
            pays: $this->adressePays,
        );
    }
}
