<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\ValeurLotInvalideException;
use App\Domain\Chantier\ValueObject\Estimation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EstimationTest extends TestCase
{
    #[Test]
    public function elle_se_construit_avec_une_valeur_positive(): void
    {
        self::assertSame(12.5, (new Estimation(12.5))->valeur);
    }

    #[Test]
    public function elle_accepte_une_valeur_a_zero(): void
    {
        self::assertSame(0.0, (new Estimation(0.0))->valeur);
    }

    #[Test]
    public function elle_refuse_une_valeur_negative(): void
    {
        self::expectException(ValeurLotInvalideException::class);
        new Estimation(-0.1);
    }
}
