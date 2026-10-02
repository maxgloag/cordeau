<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\ValueObject\Montant;
use App\Domain\Devis\ValueObject\Pourcentage;
use App\Domain\Devis\ValueObject\RemiseGlobale;
use App\Domain\Devis\ValueObject\RemiseGlobaleMontant;
use App\Domain\Devis\ValueObject\RemiseGlobalePourcentage;
use App\Tests\Unit\Domain\Devis\DevisTestBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemiseGlobaleTest extends TestCase
{
    /**
     * @return iterable<string, array{non-empty-list<array{string, string, string, string}>, string, list<string>}>
     */
    public static function ventilationsMontant(): iterable
    {
        yield 'prorata exact' => [
            [['1', '300', '10', '0'], ['1', '100', '20', '0']], '40.00', ['30.00', '10.00'],
        ];
        yield 'ecart negatif sur la base la plus elevee, a egalite le taux le plus eleve' => [
            [['1', '100', '10', '0'], ['1', '100', '20', '0']], '0.01', ['0.01', '0.00'],
        ];
        yield 'ecart negatif pouvant rendre la part negative' => [
            [['1', '1', '0', '0'], ['1', '1', '5.5', '0'], ['1', '1', '10', '0'], ['1', '1', '20', '0']], '0.02', ['0.01', '0.01', '0.01', '-0.01'],
        ];
        yield 'ecart positif, egalite tranchee par le taux le plus eleve' => [
            [['1', '1', '0', '0'], ['1', '1', '10', '0'], ['1', '1', '20', '0']], '1.00', ['0.33', '0.33', '0.34'],
        ];
        yield 'ecart sur la base la plus elevee meme si son taux est le plus bas' => [
            [['1', '150', '5.5', '0'], ['1', '75', '10', '0'], ['1', '75', '20', '0']], '0.10', ['0.04', '0.03', '0.03'],
        ];
        yield 'total nul et remise nulle' => [
            [['1', '0', '10', '0'], ['1', '0', '20', '0']], '0', ['0.00', '0.00'],
        ];
        yield 'remise egale au total' => [
            [['1', '33.33', '5.5', '0'], ['1', '66.67', '20', '0']], '100.00', ['33.33', '66.67'],
        ];
    }

    /**
     * @param non-empty-list<array{string, string, string, string}> $lignes
     * @param list<string>                                          $partsAttendues
     */
    #[Test]
    #[DataProvider('ventilationsMontant')]
    public function la_remise_en_montant_est_ventilee_et_l_ecart_porte_sur_la_plus_grande_base(array $lignes, string $montant, array $partsAttendues): void
    {
        $lignesDevis = DevisTestBuilder::lignes($lignes);
        $remise = new RemiseGlobaleMontant(Montant::depuisChaine($montant));

        $parts = $remise->ventiler($lignesDevis->basesHt());

        self::assertSame($partsAttendues, self::enChaines($parts));
        self::assertSame($montant === '0' ? '0.00' : $montant, self::somme($parts));
    }

    #[Test]
    public function la_remise_en_pourcentage_est_arrondie_sur_chaque_base(): void
    {
        $lignesDevis = DevisTestBuilder::lignes([['1', '0.50', '10', '0'], ['1', '0.50', '20', '0']]);
        $remise = new RemiseGlobalePourcentage(Pourcentage::depuisChaine('1'));

        // 0.005 → 0.01 sur chaque base (et non arrondi(0.01) global).
        self::assertSame(['0.01', '0.01'], self::enChaines($remise->ventiler($lignesDevis->basesHt())));
    }

    #[Test]
    public function seule_une_remise_en_montant_peut_depasser_le_total(): void
    {
        $total = Montant::depuisChaine('100.00');

        self::assertTrue((new RemiseGlobaleMontant(Montant::depuisChaine('100.01')))->depasse($total));
        self::assertFalse((new RemiseGlobaleMontant(Montant::depuisChaine('100.00')))->depasse($total));
        self::assertFalse((new RemiseGlobalePourcentage(Pourcentage::depuisChaine('100')))->depasse($total));
    }

    #[Test]
    public function les_deux_formes_de_remise_sont_des_remises_globales(): void
    {
        self::assertInstanceOf(RemiseGlobale::class, new RemiseGlobaleMontant(Montant::zero()));
        self::assertInstanceOf(RemiseGlobale::class, new RemiseGlobalePourcentage(Pourcentage::zero()));
    }

    /**
     * @param list<Montant> $parts
     *
     * @return list<string>
     */
    private static function enChaines(array $parts): array
    {
        return array_map(static fn (Montant $part): string => $part->enChaine(), $parts);
    }

    /**
     * @param list<Montant> $parts
     */
    private static function somme(array $parts): string
    {
        return array_reduce($parts, static fn (Montant $somme, Montant $part): Montant => $somme->plus($part), Montant::zero())->enChaine();
    }
}
