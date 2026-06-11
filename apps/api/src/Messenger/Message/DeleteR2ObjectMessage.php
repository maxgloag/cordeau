<?php

declare(strict_types=1);

namespace App\Messenger\Message;

final class DeleteR2ObjectMessage
{
    public function __construct(
        public readonly string $remoteKey,
    ) {
    }
}
