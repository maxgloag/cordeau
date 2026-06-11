<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Messenger\Message\DeleteR2ObjectMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteR2ObjectHandler
{
    public function __construct(
        private readonly StorageAdapterInterface $storage,
    ) {
    }

    public function __invoke(DeleteR2ObjectMessage $message): void
    {
        $this->storage->delete($message->remoteKey);
        $this->storage->delete('thumbs/' . $message->remoteKey);
    }
}
