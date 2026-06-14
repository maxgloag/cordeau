<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\ImprevuInvalideException;
use App\Domain\Chantier\ValueObject\Imprevu;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImprevuTest extends TestCase
{
    #[Test]
    public function il_porte_une_note_et_un_horodatage(): void
    {
        $horodatage = new \DateTimeImmutable('2026-06-14 10:00:00');
        $imprevu = new Imprevu('Mur porteur non prévu', $horodatage);

        self::assertSame('Mur porteur non prévu', $imprevu->note);
        self::assertSame($horodatage, $imprevu->horodatage);
    }

    #[Test]
    public function il_refuse_une_note_vide(): void
    {
        self::expectException(ImprevuInvalideException::class);
        new Imprevu('   ', new \DateTimeImmutable());
    }
}
