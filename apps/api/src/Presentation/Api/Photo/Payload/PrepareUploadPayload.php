<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class PrepareUploadPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $chantierId,
    ) {
    }
}
