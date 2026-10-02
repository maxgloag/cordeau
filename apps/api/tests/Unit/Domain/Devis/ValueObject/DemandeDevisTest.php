<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Exception\FranchiseAvecTvaException;
use App\Domain\Devis\Exception\LignesDevisVidesException;
use App\Domain\Devis\Exception\RemiseSuperieureAuTotalException;
use App\Domain\Devis\ValueObject\BaseHt;
use App\Domain\Devis\ValueObject\LignesDevis;
use App\Domain\Devis\ValueObject\Montant;
use App\Tests\Unit\Domain\Devis\DevisTestBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemandeDevisTest extends TestCase
{
    #[Test]
    public function un_devis_sans_ligne_est_refuse(): void
    {
        $this->expectException(LignesDevisVidesException::class);

        new LignesDevis([]);
    }

    #[Test]
    public function les_lignes_exposent_leur_total_et_leurs_bases_par_taux_croissant(): void
    {
        $lignes = DevisTestBuilder::lignes([['1', '20', '20', '0'], ['2', '5', '10', '0'], ['1', '0.01', '20', '0']]);

        self::assertSame('30.01', $lignes->totalHtBrut()->enChaine());
        self::assertSame(
            [['10', '10.00'], ['20', '20.01']],
            array_map(static fn (BaseHt $base): array => [$base->taux->value, $base->montant->enChaine()], $lignes->basesHt()),
        );
        self::assertSame(['20.00', '10.00', '0.01'], array_map(static fn (Montant $montant): string => $montant->enChaine(), $lignes->montantsHt()));
    }

    #[Test]
    public function la_franchise_en_base_refuse_une_ligne_avec_tva(): void
    {
        $this->expectException(FranchiseAvecTvaException::class);

        DevisTestBuilder::demande(RegimeTva::FRANCHISE_EN_BASE, [['1', '10', '0', '0'], ['1', '10', '5.5', '0']]);
    }

    #[Test]
    public function la_franchise_en_base_accepte_des_lignes_au_taux_zero(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::FRANCHISE_EN_BASE, [['1', '10', '0', '0']]);

        self::assertSame(TauxTva::ZERO, $demande->lignes->lignes[0]->tauxTva);
    }

    #[Test]
    public function une_remise_en_montant_superieure_au_total_ht_brut_est_refusee(): void
    {
        $this->expectException(RemiseSuperieureAuTotalException::class);

        DevisTestBuilder::demande(RegimeTva::NORMAL, [['1', '10', '20', '0']], ['M', '10.01']);
    }

    #[Test]
    public function une_remise_en_montant_strictement_positive_sur_un_total_nul_est_refusee(): void
    {
        $this->expectException(RemiseSuperieureAuTotalException::class);

        DevisTestBuilder::demande(RegimeTva::NORMAL, [['1', '0', '20', '0']], ['M', '0.01']);
    }

    #[Test]
    public function une_remise_en_montant_egale_au_total_est_acceptee(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['1', '10', '20', '0']], ['M', '10']);

        self::assertNotNull($demande->remiseGlobale);
    }
}
