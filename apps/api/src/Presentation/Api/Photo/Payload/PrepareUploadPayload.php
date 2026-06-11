<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final class PrepareUploadPayload
{
    public const array CONTENT_TYPES_AUTORISES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $chantierId,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: self::CONTENT_TYPES_AUTORISES)]
        public readonly string $contentType,
    ) {
    }
}
