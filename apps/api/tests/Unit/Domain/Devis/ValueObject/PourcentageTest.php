<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\Pourcentage;
use BcMath\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PourcentageTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pourcentagesValides(): iterable
    {
        yield 'zero' => ['0', '0'];
        yield 'entier' => ['5', '5'];
        yield 'deux decimales' => ['33.33', '33.33'];
        yield 'cent' => ['100', '100'];
        yield 'cent avec decimales nulles' => ['100.00', '100.00'];
    }

    #[Test]
    #[DataProvider('pourcentagesValides')]
    public function il_accepte_un_pourcentage_entre_0_et_100_avec_au_plus_2_decimales(string $chaine, string $attendu): void
    {
        self::assertSame($attendu, (string) Pourcentage::depuisChaine($chaine)->valeur);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pourcentagesInvalides(): iterable
    {
        yield 'au dessus de 100 de peu' => ['100.01'];
        yield 'au dessus de 100' => ['101'];
        yield 'trois decimales' => ['1.234'];
        yield 'trois decimales nulles' => ['100.000'];
        yield 'negatif' => ['-1'];
        yield 'format invalide' => ['5%'];
    }

    #[Test]
    #[DataProvider('pourcentagesInvalides')]
    public function il_refuse_un_pourcentage_invalide(string $chaine): void
    {
        $this->expectException(DevisException::class);

        Pourcentage::depuisChaine($chaine);
    }

    #[Test]
    public function le_pourcentage_nul_vaut_zero(): void
    {
        self::assertSame(0, Pourcentage::zero()->valeur->compare(new Number('0')));
    }

    #[Test]
    public function il_s_applique_exactement_a_un_nombre(): void
    {
        // 545.06 x 3 % = 16.3518, sans arrondi
        self::assertSame('16.3518', (string) Pourcentage::depuisChaine('3')->appliquerA(new Number('545.06')));
    }

    #[Test]
    public function son_complement_a_100_est_exact(): void
    {
        self::assertSame('94.25', (string) Pourcentage::depuisChaine('5.75')->complement()->valeur);
    }
}
