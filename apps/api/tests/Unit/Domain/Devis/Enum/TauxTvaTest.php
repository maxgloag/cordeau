<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\Enum;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Enum\TauxTva;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TauxTvaTest extends TestCase
{
    #[Test]
    public function les_taux_sont_exactement_les_quatre_chaines_autorisees(): void
    {
        self::assertSame(['0', '5.5', '10', '20'], array_map(static fn (TauxTva $taux): string => $taux->value, TauxTva::parOrdreCroissant()));
        self::assertNull(TauxTva::tryFrom('5.50'));
        self::assertNull(TauxTva::tryFrom('20.0'));
        self::assertNull(TauxTva::tryFrom('00'));
    }

    #[Test]
    public function chaque_taux_expose_son_pourcentage_exact(): void
    {
        self::assertSame('5.5', (string) TauxTva::REDUIT->pourcentage()->valeur);
        self::assertSame('20', (string) TauxTva::NORMAL->pourcentage()->valeur);
    }

    #[Test]
    public function le_regime_normal_autorise_tous_les_taux_y_compris_zero(): void
    {
        foreach (TauxTva::cases() as $taux) {
            self::assertTrue(RegimeTva::NORMAL->autorise($taux));
        }
        self::assertNull(RegimeTva::NORMAL->mentionTva());
    }

    #[Test]
    public function la_franchise_en_base_n_autorise_que_le_taux_zero_et_porte_la_mention_legale(): void
    {
        self::assertTrue(RegimeTva::FRANCHISE_EN_BASE->autorise(TauxTva::ZERO));
        self::assertFalse(RegimeTva::FRANCHISE_EN_BASE->autorise(TauxTva::REDUIT));
        self::assertFalse(RegimeTva::FRANCHISE_EN_BASE->autorise(TauxTva::INTERMEDIAIRE));
        self::assertFalse(RegimeTva::FRANCHISE_EN_BASE->autorise(TauxTva::NORMAL));
        self::assertSame('TVA non applicable, art. 293 B du CGI', RegimeTva::FRANCHISE_EN_BASE->mentionTva());
    }
}
