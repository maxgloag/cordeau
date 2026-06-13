<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class ModifierLegendePayload
{
    public function __construct(
        #[Assert\Length(max: 280)]
        public readonly ?string $legende = null,
    ) {
    }
}
