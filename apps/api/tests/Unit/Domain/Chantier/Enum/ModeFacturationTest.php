<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\Enum;

use App\Domain\Chantier\Enum\ModeFacturation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModeFacturationTest extends TestCase
{
    #[Test]
    public function les_valeurs_serialisees_sont_stables(): void
    {
        self::assertSame('temps', ModeFacturation::TEMPS->value);
        self::assertSame('surface', ModeFacturation::SURFACE->value);
        self::assertSame('forfait', ModeFacturation::FORFAIT->value);
    }

    #[Test]
    public function chaque_mode_porte_son_unite(): void
    {
        self::assertSame('h', ModeFacturation::TEMPS->unite());
        self::assertSame('m²', ModeFacturation::SURFACE->unite());
        self::assertSame('€', ModeFacturation::FORFAIT->unite());
    }
}
