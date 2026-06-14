<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Infrastructure\Image\ThumbnailGenerator;
use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Messenger\Message\GenerateThumbnailMessage;
use App\Photo\Repository\PhotoRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class GenerateThumbnailHandler
{
    private const THUMB_SIZE = 400;

    public function __construct(
        private readonly StorageAdapterInterface $storage,
        private readonly PhotoRepository $repository,
        private readonly ThumbnailGenerator $thumbnailGenerator,
    ) {
    }

    public function __invoke(GenerateThumbnailMessage $message): void
    {
        $photo = $this->repository->find(Uuid::fromString($message->photoId));
        if ($photo === null) {
            return;
        }

        $data = file_get_contents($photo->photoUrl);
        if ($data === false) {
            return;
        }

        $thumbData = $this->thumbnailGenerator->generateSquareJpeg($data, self::THUMB_SIZE);

        $thumbKey = 'thumbs/' . $message->remoteKey;
        $this->storage->uploadBinary($thumbKey, $thumbData, 'image/jpeg');

        $photo->thumbnailUrl = $this->storage->getPublicUrl($thumbKey);
        $this->repository->save($photo);
    }
}
