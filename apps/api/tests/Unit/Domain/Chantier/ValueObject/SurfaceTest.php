<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\SurfaceInvalideException;
use App\Domain\Chantier\ValueObject\Surface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SurfaceTest extends TestCase
{
    #[Test]
    public function elle_se_construit_avec_une_valeur_strictement_positive(): void
    {
        $surface = new Surface(42.5);

        self::assertSame(42.5, $surface->valeurM2);
    }

    #[Test]
    public function elle_refuse_une_valeur_a_zero(): void
    {
        self::expectException(SurfaceInvalideException::class);
        new Surface(0.0);
    }

    #[Test]
    public function elle_refuse_une_valeur_negative(): void
    {
        self::expectException(SurfaceInvalideException::class);
        new Surface(-1.0);
    }

    #[Test]
    public function elle_refuse_une_valeur_au_dessus_du_maximum(): void
    {
        self::expectException(SurfaceInvalideException::class);
        new Surface(Surface::MAXIMUM_M2 + 1);
    }

    #[Test]
    public function elle_accepte_la_valeur_maximale(): void
    {
        $surface = new Surface(Surface::MAXIMUM_M2);

        self::assertSame(Surface::MAXIMUM_M2, $surface->valeurM2);
    }

    #[Test]
    public function elle_egale_une_surface_avec_la_meme_valeur(): void
    {
        self::assertTrue((new Surface(50.0))->equals(new Surface(50.0)));
    }

    #[Test]
    public function elle_n_egale_pas_une_surface_avec_une_valeur_differente(): void
    {
        self::assertFalse((new Surface(50.0))->equals(new Surface(51.0)));
    }
}
