<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\Montant;
use BcMath\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MontantTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function chainesValides(): iterable
    {
        yield 'zero' => ['0', '0.00'];
        yield 'une decimale' => ['1234.5', '1234.50'];
        yield 'deux decimales' => ['16.35', '16.35'];
        yield 'zeros de tete' => ['0007.10', '7.10'];
        yield 'grand montant sans separateur' => ['12345678901', '12345678901.00'];
    }

    #[Test]
    #[DataProvider('chainesValides')]
    public function il_se_formate_toujours_avec_exactement_deux_decimales(string $chaine, string $attendu): void
    {
        self::assertSame($attendu, Montant::depuisChaine($chaine)->enChaine());
    }

    #[Test]
    public function il_refuse_plus_de_deux_decimales(): void
    {
        $this->expectException(DevisException::class);

        Montant::depuisChaine('1.234');
    }

    #[Test]
    public function il_refuse_un_format_invalide(): void
    {
        $this->expectException(DevisException::class);

        Montant::depuisChaine('-1');
    }

    /**
     * @return iterable<string, array{numeric-string, string}>
     */
    public static function arrondis(): iterable
    {
        yield 'exemple de la spec ligne' => ['545.0625', '545.06'];
        yield 'demi centime monte' => ['0.005', '0.01'];
        yield 'juste sous le demi centime descend' => ['0.0049999999', '0.00'];
        yield 'demi superieur et non demi pair' => ['0.045', '0.05'];
        yield 'demi superieur sur centime impair' => ['2.675', '2.68'];
        yield 'demi superieur sur centime pair' => ['2.665', '2.67'];
        yield 'entier' => ['545', '545.00'];
        yield 'exemple de la spec tva' => ['52.871', '52.87'];
        yield 'exemple de la spec acompte' => ['174.474', '174.47'];
        yield 'demi centime negatif s eloigne de zero' => ['-0.005', '-0.01'];
    }

    /**
     * @param numeric-string $exact
     */
    #[Test]
    #[DataProvider('arrondis')]
    public function il_arrondit_au_centime_demi_superieur(string $exact, string $attendu): void
    {
        self::assertSame($attendu, Montant::arrondi(new Number($exact))->enChaine());
    }

    #[Test]
    public function il_additionne_et_soustrait_exactement(): void
    {
        $a = Montant::depuisChaine('0.10');
        $b = Montant::depuisChaine('0.20');

        self::assertSame('0.30', $a->plus($b)->enChaine());
        self::assertSame('-0.10', $a->moins($b)->enChaine());
        self::assertSame('0.00', $a->moins($a)->enChaine());
    }

    #[Test]
    public function il_se_compare_a_un_autre_montant(): void
    {
        $petit = Montant::depuisChaine('16.35');
        $grand = Montant::depuisChaine('16.36');

        self::assertTrue($grand->estSuperieurA($petit));
        self::assertFalse($petit->estSuperieurA($grand));
        self::assertFalse($petit->estSuperieurA(Montant::arrondi(new Number('16.350'))));
        self::assertTrue($petit->estEgalA(Montant::depuisChaine('16.35')));
        self::assertFalse($petit->estEgalA($grand));
    }

    #[Test]
    public function le_montant_nul_vaut_zero(): void
    {
        self::assertSame('0.00', Montant::zero()->enChaine());
        self::assertTrue(Montant::zero()->estNul());
        self::assertFalse(Montant::depuisChaine('0.01')->estNul());
    }
}
