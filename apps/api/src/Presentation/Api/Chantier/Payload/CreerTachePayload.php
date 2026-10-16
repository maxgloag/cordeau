<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use App\Presentation\Api\Support\BornesColonnes;
use Symfony\Component\Validator\Constraints as Assert;

final class CreerTachePayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public readonly string $libelle = '',
        #[Assert\Range(min: 0, max: BornesColonnes::ENTIER_MAX)]
        public readonly int $ordre = 0,
        #[Assert\Uuid]
        public readonly ?string $uuid = null,
    ) {
    }
}
