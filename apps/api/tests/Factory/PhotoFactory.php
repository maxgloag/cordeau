<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Photo\Entity\Photo;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Photo>
 */
final class PhotoFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Photo::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();
        $key = 'photos/' . Uuid::v7()->toRfc4122();

        return [
            'id' => Uuid::v7(),
            'proprietaire' => UserFactory::new(),
            'chantierId' => Uuid::v7(),
            'lotId' => null,
            'tacheId' => null,
            'remoteKey' => $key,
            'photoUrl' => 'https://photos.example.com/' . $key,
            'thumbnailUrl' => null,
            'uploadeLe' => $now,
            'creeLe' => $now,
            'legende' => null,
        ];
    }
}
