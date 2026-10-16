<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class ModifierTachePayload
{
    public function __construct(
        public readonly ?bool $faite = null,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public readonly ?string $libelle = null,
    ) {
    }
}
