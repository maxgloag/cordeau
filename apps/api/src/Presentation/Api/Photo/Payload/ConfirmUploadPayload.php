<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmUploadPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^photos\/[0-9a-f-]{36}\/[0-9a-f-]{36}$/', message: 'Format de clé invalide.')]
        public readonly string $remoteKey,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $chantierId,
    ) {
    }
}
