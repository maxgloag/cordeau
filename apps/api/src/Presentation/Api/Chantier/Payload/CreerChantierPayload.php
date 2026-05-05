<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class CreerChantierPayload
{
    public function __construct(
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
        #[Assert\Positive]
        public readonly ?float $surfaceM2 = null,
    ) {
    }
}
