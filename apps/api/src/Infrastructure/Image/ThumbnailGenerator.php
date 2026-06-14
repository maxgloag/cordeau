<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use Imagick;
use ImagickPixel;

/**
 * Génère une vignette carrée JPEG à partir de données image brutes.
 *
 * Utilise Imagick (ImageMagick + libheif) plutôt que GD : décode HEIC/HEIF (format
 * iPhone) en plus de JPEG/PNG/WebP. Le durcissement ImageMagick (coders autorisés,
 * limites de ressources) est appliqué via policy.xml côté image Docker (cf ADR 0023).
 */
final class ThumbnailGenerator
{
    /**
     * @param string $imageData données image brutes (JPEG, PNG, WebP, HEIC...)
     * @param int    $size      côté de la vignette carrée en pixels
     *
     * @return string données JPEG de la vignette
     */
    public function generateSquareJpeg(string $imageData, int $size): string
    {
        $image = new Imagick();
        try {
            $image->readImageBlob($imageData);
            // HEIC peut contenir plusieurs frames (ex. Live Photo) : on garde la première.
            $image->setFirstIterator();
            // Applique l'orientation EXIF aux pixels (les iPhone stockent l'orientation en tag).
            // Imagick::autoOrientImage() n'est pas dispo sur toutes les builds → on gère les
            // orientations courantes à la main (rotations 90/180/270, cas iPhone 1/3/6/8).
            $this->applyExifOrientation($image);

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $crop = min($width, $height);
            $image->cropImage(
                $crop,
                $crop,
                intdiv($width - $crop, 2),
                intdiv($height - $crop, 2),
            );
            // Réinitialise le canvas virtuel après crop (sinon la page garde l'offset).
            $image->setImagePage(0, 0, 0, 0);

            $image->thumbnailImage($size, $size);

            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(85);
            // Retire les métadonnées (EXIF, GPS) : la vignette est servie publiquement.
            $image->stripImage();

            return $image->getImageBlob();
        } finally {
            $image->clear();
        }
    }

    private function applyExifOrientation(Imagick $image): void
    {
        switch ($image->getImageOrientation()) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage(new ImagickPixel('none'), 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage(new ImagickPixel('none'), 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage(new ImagickPixel('none'), 270);
                break;
        }
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }
}
