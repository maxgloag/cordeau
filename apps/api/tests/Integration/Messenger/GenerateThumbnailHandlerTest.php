<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messenger;

use App\Infrastructure\Image\ThumbnailGenerator;
use App\Infrastructure\Storage\StorageAdapterInterface;
use App\Messenger\Handler\GenerateThumbnailHandler;
use App\Messenger\Message\GenerateThumbnailMessage;
use App\Photo\Repository\PhotoRepository;
use App\Tests\Factory\PhotoFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class GenerateThumbnailHandlerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const string FIXTURE = __DIR__ . '/../../Fixtures/images/sample.jpg';

    #[Test]
    public function renseigne_la_vignette_de_la_photo_et_la_televerse_sur_r2(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('ext-imagick non chargée');
        }

        self::bootKernel();
        $photo = PhotoFactory::createOne([
            'remoteKey' => 'photos/u/p.jpg',
            'photoUrl' => self::FIXTURE,
            'thumbnailUrl' => null,
        ]);

        $storage = $this->createMock(StorageAdapterInterface::class);
        $storage->expects(self::once())
            ->method('uploadBinary')
            ->with('thumbs/photos/u/p.jpg', self::isString(), 'image/jpeg');
        $storage->method('getPublicUrl')
            ->willReturnCallback(static fn (string $key): string => 'https://photos.example.com/' . $key);

        $handler = $this->handler($storage);

        $handler(new GenerateThumbnailMessage($photo->id->toRfc4122(), 'photos/u/p.jpg'));

        self::assertSame(
            'https://photos.example.com/thumbs/photos/u/p.jpg',
            $photo->_refresh()->thumbnailUrl,
        );
    }

    #[Test]
    public function ignore_une_photo_supprimee_entre_l_emission_et_le_traitement(): void
    {
        self::bootKernel();
        $storage = $this->createMock(StorageAdapterInterface::class);
        $storage->expects(self::never())->method('uploadBinary');

        $handler = $this->handler($storage);

        $handler(new GenerateThumbnailMessage('00000000-0000-7000-8000-00000000dead', 'photos/u/absente.jpg'));
    }

    private function handler(StorageAdapterInterface $storage): GenerateThumbnailHandler
    {
        $repository = static::getContainer()->get(PhotoRepository::class);
        self::assertInstanceOf(PhotoRepository::class, $repository);

        return new GenerateThumbnailHandler($storage, $repository, new ThumbnailGenerator());
    }
}
