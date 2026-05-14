<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\ValueObject;

use App\Client\Exception\TelephoneInvalideException;
use App\Client\ValueObject\Telephone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TelephoneTest extends TestCase
{
    #[Test]
    #[DataProvider('formatsValides')]
    public function il_normalise_les_formats_valides_en_e164(string $entree, string $attendu): void
    {
        $telephone = new Telephone($entree);

        self::assertSame($attendu, $telephone->valeur);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formatsValides(): iterable
    {
        yield 'national colle' => ['0612345678', '+33612345678'];
        yield 'national avec espaces' => ['06 12 34 56 78', '+33612345678'];
        yield 'national avec points' => ['06.12.34.56.78', '+33612345678'];
        yield 'national avec tirets' => ['06-12-34-56-78', '+33612345678'];
        yield 'international colle' => ['+33612345678', '+33612345678'];
        yield 'international avec espaces' => ['+33 6 12 34 56 78', '+33612345678'];
        yield 'fixe 01' => ['0123456789', '+33123456789'];
        yield 'fixe 09' => ['0987654321', '+33987654321'];
    }

    #[Test]
    #[DataProvider('formatsInvalides')]
    public function il_rejette_les_formats_invalides(string $entree): void
    {
        $this->expectException(TelephoneInvalideException::class);

        new Telephone($entree);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formatsInvalides(): iterable
    {
        yield 'vide' => [''];
        yield 'trop court' => ['061234567'];
        yield 'trop long' => ['06123456789'];
        yield 'commence par 00' => ['0012345678'];
        yield 'lettres' => ['0612ABCD78'];
        yield 'autre prefixe international' => ['+44612345678'];
        yield 'sans prefixe ni 0' => ['612345678'];
    }

    #[Test]
    public function deux_telephones_normalises_pareils_sont_egaux(): void
    {
        $a = new Telephone('06 12 34 56 78');
        $b = new Telephone('+33612345678');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function il_se_convertit_en_chaine_via_la_valeur_normalisee(): void
    {
        $telephone = new Telephone('06.12.34.56.78');

        self::assertSame('+33612345678', (string) $telephone);
    }
}
