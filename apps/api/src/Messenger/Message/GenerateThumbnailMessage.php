<?php

declare(strict_types=1);

namespace App\Messenger\Message;

final class GenerateThumbnailMessage
{
    public function __construct(
        public readonly string $photoId,
        public readonly string $remoteKey,
    ) {
    }
}
