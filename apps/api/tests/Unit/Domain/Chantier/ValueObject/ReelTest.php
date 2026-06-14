<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\ValeurLotInvalideException;
use App\Domain\Chantier\ValueObject\Reel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReelTest extends TestCase
{
    #[Test]
    public function il_se_construit_avec_une_valeur_positive(): void
    {
        self::assertSame(8.0, (new Reel(8.0))->valeur);
    }

    #[Test]
    public function il_accepte_une_valeur_a_zero(): void
    {
        self::assertSame(0.0, (new Reel(0.0))->valeur);
    }

    #[Test]
    public function il_refuse_une_valeur_negative(): void
    {
        self::expectException(ValeurLotInvalideException::class);
        new Reel(-1.0);
    }
}
