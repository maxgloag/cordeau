<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\Quantite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuantiteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function quantitesValides(): iterable
    {
        yield 'decimale' => ['12.5', '12.5'];
        yield 'plus petite valeur' => ['0.001', '0.001'];
        yield 'borne superieure entiere' => ['1000000', '1000000'];
        yield 'borne superieure avec decimales nulles' => ['1000000.000', '1000000.000'];
        yield 'zeros de tete' => ['0001', '1'];
    }

    #[Test]
    #[DataProvider('quantitesValides')]
    public function elle_accepte_une_quantite_valide(string $chaine, string $attendu): void
    {
        self::assertSame($attendu, (string) Quantite::depuisChaine($chaine)->valeur);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quantitesInvalides(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero avec decimales' => ['0.000'];
        yield 'au dessus de la borne de peu' => ['1000000.001'];
        yield 'au dessus de la borne' => ['1000001'];
        yield 'quatre decimales' => ['1.0000'];
        yield 'quatre decimales significatives' => ['0.0001'];
        yield 'format invalide' => ['1,5'];
        yield 'negative' => ['-1'];
    }

    #[Test]
    #[DataProvider('quantitesInvalides')]
    public function elle_refuse_une_quantite_invalide(string $chaine): void
    {
        $this->expectException(DevisException::class);

        Quantite::depuisChaine($chaine);
    }
}
