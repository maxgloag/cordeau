<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Contrat CLI de l'expérience B : JSON sur stdin → JSON sur stdout, code de sortie 0 / 1.
 * Aucune base de données requise.
 */
final class CalculerTotauxDevisCommandTest extends KernelTestCase
{
    private const string EXEMPLE_SPEC = '{"regime_tva":"NORMAL","lignes":[{"designation":"Pose carrelage sol","quantite":"12.5","unite":"M2","prix_unitaire_ht":"45.90","taux_tva":"10","remise_pct":"5"}],"remise_globale":{"type":"POURCENTAGE","valeur":"3"},"acompte_pct":"30"}';

    #[Test]
    public function il_ecrit_les_totaux_de_l_exemple_verifie_et_sort_en_0(): void
    {
        [$codeSortie, $sortie] = $this->executer(self::EXEMPLE_SPEC);

        self::assertSame(0, $codeSortie);
        self::assertSame([
            'ok' => [
                'lignes' => [['montant_ht' => '545.06']],
                'total_ht_brut' => '545.06',
                'remise_globale' => '16.35',
                'total_ht_net' => '528.71',
                'ventilation_tva' => [['taux' => '10', 'base_ht' => '528.71', 'montant_tva' => '52.87']],
                'total_tva' => '52.87',
                'total_ttc' => '581.58',
                'acompte' => '174.47',
                'solde' => '407.11',
                'mention_tva' => null,
            ],
        ], json_decode($sortie, true, 512, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function en_franchise_en_base_la_ventilation_est_un_tableau_vide_et_la_mention_une_chaine(): void
    {
        [$codeSortie, $sortie] = $this->executer('{"regime_tva":"FRANCHISE_EN_BASE","lignes":[{"designation":"Peinture","quantite":"1","unite":"FORFAIT","prix_unitaire_ht":"800","taux_tva":"0"}]}');

        self::assertSame(0, $codeSortie);
        self::assertStringContainsString('"ventilation_tva":[]', $sortie);
        self::assertStringContainsString('"mention_tva":"TVA non applicable, art. 293 B du CGI"', $sortie);
        self::assertStringContainsString('"total_ttc":"800.00"', $sortie);
    }

    #[Test]
    public function il_ecrit_toutes_les_erreurs_et_sort_en_1(): void
    {
        [$codeSortie, $sortie] = $this->executer('{"regime_tva":"NORMAL","lignes":[{"designation":"x","quantite":"0","unite":"M2","prix_unitaire_ht":"1","taux_tva":"10"}],"acompte_pct":"101"}');

        self::assertSame(1, $codeSortie);
        $erreurs = json_decode($sortie, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($erreurs);
        self::assertSame(['erreurs'], array_keys($erreurs));
        self::assertEqualsCanonicalizing([
            ['code' => 'QUANTITE_INVALIDE', 'ligne' => 0],
            ['code' => 'ACOMPTE_INVALIDE', 'ligne' => null],
        ], $erreurs['erreurs']);
    }

    #[Test]
    public function une_entree_non_json_produit_json_invalide_et_sort_en_1(): void
    {
        [$codeSortie, $sortie] = $this->executer('pas du json');

        self::assertSame(1, $codeSortie);
        self::assertSame('{"erreurs":[{"code":"JSON_INVALIDE","ligne":null}]}', trim($sortie));
    }

    /**
     * @return array{int, string}
     */
    private function executer(string $entree): array
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:experience:totaux-devis'));
        $tester->setInputs([$entree]);

        $codeSortie = $tester->execute([], ['interactive' => false]);

        return [$codeSortie, $tester->getDisplay()];
    }
}
