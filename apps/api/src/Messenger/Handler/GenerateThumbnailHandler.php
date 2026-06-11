<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

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
    ) {
    }

    public function __invoke(GenerateThumbnailMessage $message): void
    {
        $photo = $this->repository->find(Uuid::fromString($message->photoId));
        if ($photo === null) {
            return;
        }

        $originalUrl = $photo->photoUrl;
        $data = file_get_contents($originalUrl);
        if ($data === false) {
            return;
        }

        $source = imagecreatefromstring($data);
        if ($source === false) {
            return;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $size = min($srcW, $srcH);
        $srcX = (int)(($srcW - $size) / 2);
        $srcY = (int)(($srcH - $size) / 2);

        $thumb = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
        \assert($thumb !== false);
        imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, self::THUMB_SIZE, self::THUMB_SIZE, $size, $size);
        imagedestroy($source);

        ob_start();
        imagejpeg($thumb, null, 85);
        $thumbData = (string) ob_get_clean();
        imagedestroy($thumb);

        $thumbKey = 'thumbs/' . $message->remoteKey;
        $this->storage->uploadBinary($thumbKey, $thumbData, 'image/jpeg');

        $thumbUrl = $this->storage->getPublicUrl($thumbKey);
        $photo->thumbnailUrl = $thumbUrl;
        $this->repository->save($photo);
    }
}
