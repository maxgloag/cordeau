<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Image;

use App\Infrastructure\Image\ThumbnailGenerator;
use Imagick;
use PHPUnit\Framework\TestCase;

final class ThumbnailGeneratorTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../Fixtures/images';

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('ext-imagick non chargée');
        }
    }

    public function testGenereUneVignetteCarreeJpegDepuisUnJpeg(): void
    {
        $data = (string) file_get_contents(self::FIXTURES . '/sample.jpg');

        $thumb = (new ThumbnailGenerator())->generateSquareJpeg($data, 400);

        self::assertVignette($thumb, 400);
    }

    public function testGenereUneVignetteCarreeJpegDepuisUnHeic(): void
    {
        if (Imagick::queryFormats('HEIC') === []) {
            self::markTestSkipped('Delegate HEIC absent de cet ImageMagick (vérifié sur l\'image prod)');
        }

        $data = (string) file_get_contents(self::FIXTURES . '/sample.heic');

        $thumb = (new ThumbnailGenerator())->generateSquareJpeg($data, 400);

        self::assertVignette($thumb, 400);
    }

    private static function assertVignette(string $jpegData, int $expectedSize): void
    {
        $probe = new Imagick();
        $probe->readImageBlob($jpegData);
        try {
            self::assertSame('JPEG', $probe->getImageFormat());
            self::assertSame($expectedSize, $probe->getImageWidth());
            self::assertSame($expectedSize, $probe->getImageHeight());
        } finally {
            $probe->clear();
        }
    }
}
