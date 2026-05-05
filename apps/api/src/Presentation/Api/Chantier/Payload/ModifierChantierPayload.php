<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class ModifierChantierPayload
{
    public function __construct(
        #[Assert\Length(max: 255)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseRue = null,
        #[Assert\Length(max: 20)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseCodePostal = null,
        #[Assert\Length(max: 255)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseVille = null,
        #[Assert\Length(exactly: 2)]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public readonly ?string $adressePays = null,
        #[Assert\Positive]
        public readonly ?float $surfaceM2 = null,
    ) {
    }
}
