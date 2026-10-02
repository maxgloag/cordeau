<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\Service;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Service\CalculateurTotauxDevis;
use App\Tests\Unit\Domain\Devis\DevisTestBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs attendues recalculées indépendamment avec Python `decimal` (ROUND_HALF_UP).
 */
final class CalculateurTotauxDevisTest extends TestCase
{
    private const array LIGNES_MULTI_TAUX = [
        ['2', '100.00', '20', '0'],
        ['1.5', '33.33', '5.5', '10'],
        ['3', '19.99', '20', '0'],
        ['1', '10', '10', '0'],
    ];

    private CalculateurTotauxDevis $calculateur;

    protected function setUp(): void
    {
        $this->calculateur = new CalculateurTotauxDevis();
    }

    #[Test]
    public function il_reproduit_l_exemple_verifie_de_la_spec(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['12.5', '45.90', '10', '5']], ['P', '3'], '30');

        self::assertSame([
            'lignes' => ['545.06'],
            'total_ht_brut' => '545.06',
            'remise_globale' => '16.35',
            'total_ht_net' => '528.71',
            'ventilation_tva' => [['taux' => '10', 'base_ht' => '528.71', 'montant_tva' => '52.87']],
            'total_tva' => '52.87',
            'total_ttc' => '581.58',
            'acompte' => '174.47',
            'solde' => '407.11',
            'mention_tva' => null,
        ], DevisTestBuilder::enTableau($this->calculateur->calculer($demande)));
    }

    #[Test]
    public function la_remise_en_pourcentage_est_arrondie_base_par_base(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, self::LIGNES_MULTI_TAUX, ['P', '7.5'], '33.33');

        self::assertSame([
            'lignes' => ['200.00', '45.00', '59.97', '10.00'],
            'total_ht_brut' => '314.97',
            'remise_globale' => '23.63',
            'total_ht_net' => '291.34',
            'ventilation_tva' => [
                ['taux' => '5.5', 'base_ht' => '41.62', 'montant_tva' => '2.29'],
                ['taux' => '10', 'base_ht' => '9.25', 'montant_tva' => '0.93'],
                ['taux' => '20', 'base_ht' => '240.47', 'montant_tva' => '48.09'],
            ],
            'total_tva' => '51.31',
            'total_ttc' => '342.65',
            'acompte' => '114.21',
            'solde' => '228.44',
            'mention_tva' => null,
        ], DevisTestBuilder::enTableau($this->calculateur->calculer($demande)));
    }

    #[Test]
    public function la_remise_en_montant_est_ventilee_au_prorata_des_bases(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, self::LIGNES_MULTI_TAUX, ['M', '100.00'], '30');

        self::assertSame([
            'lignes' => ['200.00', '45.00', '59.97', '10.00'],
            'total_ht_brut' => '314.97',
            'remise_globale' => '100.00',
            'total_ht_net' => '214.97',
            'ventilation_tva' => [
                ['taux' => '5.5', 'base_ht' => '30.71', 'montant_tva' => '1.69'],
                ['taux' => '10', 'base_ht' => '6.83', 'montant_tva' => '0.68'],
                ['taux' => '20', 'base_ht' => '177.43', 'montant_tva' => '35.49'],
            ],
            'total_tva' => '37.86',
            'total_ttc' => '252.83',
            'acompte' => '75.85',
            'solde' => '176.98',
            'mention_tva' => null,
        ], DevisTestBuilder::enTableau($this->calculateur->calculer($demande)));
    }

    #[Test]
    public function la_tva_est_calculee_par_base_et_jamais_par_ligne(): void
    {
        // Par ligne : 2 × arrondi(0.006) = 0.02 ; par base : arrondi(0.012) = 0.01.
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['1', '0.03', '20', '0'], ['1', '0.03', '20', '0']]);

        $totaux = DevisTestBuilder::enTableau($this->calculateur->calculer($demande));

        self::assertSame([['taux' => '20', 'base_ht' => '0.06', 'montant_tva' => '0.01']], $totaux['ventilation_tva']);
        self::assertSame('0.01', $totaux['total_tva']);
        self::assertSame('0.07', $totaux['total_ttc']);
        self::assertSame('0.00', $totaux['remise_globale']);
    }

    #[Test]
    public function la_ventilation_est_triee_par_taux_croissant_et_les_lignes_restent_dans_l_ordre_d_entree(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [
            ['1', '20', '20', '0'],
            ['1', '0', '0', '0'],
            ['1', '10', '10', '0'],
            ['1', '5', '5.5', '0'],
        ]);

        $totaux = DevisTestBuilder::enTableau($this->calculateur->calculer($demande));

        self::assertSame(['20.00', '0.00', '10.00', '5.00'], $totaux['lignes']);
        self::assertSame([
            ['taux' => '0', 'base_ht' => '0.00', 'montant_tva' => '0.00'],
            ['taux' => '5.5', 'base_ht' => '5.00', 'montant_tva' => '0.28'],
            ['taux' => '10', 'base_ht' => '10.00', 'montant_tva' => '1.00'],
            ['taux' => '20', 'base_ht' => '20.00', 'montant_tva' => '4.00'],
        ], $totaux['ventilation_tva']);
    }

    #[Test]
    public function en_franchise_en_base_la_ventilation_est_vide_la_tva_nulle_et_la_mention_presente(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::FRANCHISE_EN_BASE, [['2', '50.25', '0', '0'], ['1', '10', '0', '0']], ['P', '10'], '50');

        self::assertSame([
            'lignes' => ['100.50', '10.00'],
            'total_ht_brut' => '110.50',
            'remise_globale' => '11.05',
            'total_ht_net' => '99.45',
            'ventilation_tva' => [],
            'total_tva' => '0.00',
            'total_ttc' => '99.45',
            'acompte' => '49.73',
            'solde' => '49.72',
            'mention_tva' => 'TVA non applicable, art. 293 B du CGI',
        ], DevisTestBuilder::enTableau($this->calculateur->calculer($demande)));
    }

    #[Test]
    public function une_remise_en_montant_egale_au_total_annule_le_devis(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['2', '50.25', '20', '0'], ['1', '10', '10', '0']], ['M', '110.50'], '100');

        self::assertSame([
            'lignes' => ['100.50', '10.00'],
            'total_ht_brut' => '110.50',
            'remise_globale' => '110.50',
            'total_ht_net' => '0.00',
            'ventilation_tva' => [
                ['taux' => '10', 'base_ht' => '0.00', 'montant_tva' => '0.00'],
                ['taux' => '20', 'base_ht' => '0.00', 'montant_tva' => '0.00'],
            ],
            'total_tva' => '0.00',
            'total_ttc' => '0.00',
            'acompte' => '0.00',
            'solde' => '0.00',
            'mention_tva' => null,
        ], DevisTestBuilder::enTableau($this->calculateur->calculer($demande)));
    }

    #[Test]
    public function un_devis_a_total_nul_avec_remise_montant_nulle_a_des_parts_nulles(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['3', '0', '20', '0'], ['1', '0', '5.5', '0']], ['M', '0'], '40');

        $totaux = DevisTestBuilder::enTableau($this->calculateur->calculer($demande));

        self::assertSame('0.00', $totaux['total_ht_brut']);
        self::assertSame('0.00', $totaux['remise_globale']);
        self::assertSame('0.00', $totaux['total_ttc']);
        self::assertSame([
            ['taux' => '5.5', 'base_ht' => '0.00', 'montant_tva' => '0.00'],
            ['taux' => '20', 'base_ht' => '0.00', 'montant_tva' => '0.00'],
        ], $totaux['ventilation_tva']);
    }

    #[Test]
    public function l_acompte_a_100_pourcent_laisse_un_solde_nul(): void
    {
        $demande = DevisTestBuilder::demande(RegimeTva::NORMAL, [['1', '99.99', '20', '0']], null, '100');

        $totaux = DevisTestBuilder::enTableau($this->calculateur->calculer($demande));

        self::assertSame('119.99', $totaux['total_ttc']);
        self::assertSame('119.99', $totaux['acompte']);
        self::assertSame('0.00', $totaux['solde']);
    }
}
