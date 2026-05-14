<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Payload;

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
        #[Assert\Regex(
            pattern: '/^[+0][\d\s.\-]{8,18}$/',
            message: 'Format de téléphone invalide. Exemples : 0612345678, +33 6 12 34 56 78.',
        )]
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
    ) {
    }
}
