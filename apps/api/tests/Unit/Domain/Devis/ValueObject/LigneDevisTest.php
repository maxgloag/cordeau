<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Enum\Unite;
use App\Domain\Devis\ValueObject\Designation;
use App\Domain\Devis\ValueObject\LigneDevis;
use App\Domain\Devis\ValueObject\Pourcentage;
use App\Domain\Devis\ValueObject\PrixUnitaireHt;
use App\Domain\Devis\ValueObject\Quantite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LigneDevisTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function montants(): iterable
    {
        yield 'exemple verifie de la spec' => ['12.5', '45.90', '5', '545.06'];
        yield 'sans remise' => ['12.5', '45.90', '0', '573.75'];
        yield 'remise totale' => ['3', '99.99', '100', '0.00'];
        yield 'prix nul' => ['3', '0', '0', '0.00'];
        yield 'demi centime arrondi au superieur' => ['0.5', '0.01', '0', '0.01'];
        yield 'demi superieur et non bancaire' => ['1', '0.05', '10', '0.05'];
        yield 'juste sous le demi centime' => ['0.001', '4.99', '0', '0.00'];
        yield 'bornes maximales' => ['1000000', '10000000', '0', '10000000000000.00'];
        yield 'bornes maximales avec remise fine' => ['999999.999', '9999999.99', '0.01', '9998999980002.00'];
        yield 'remise a deux decimales' => ['7', '13.37', '12.34', '82.04'];
    }

    #[Test]
    #[DataProvider('montants')]
    public function son_montant_ht_est_arrondi_au_centime_une_seule_fois(string $quantite, string $prix, string $remise, string $attendu): void
    {
        $ligne = new LigneDevis(
            Designation::depuisChaine('Ligne'),
            Quantite::depuisChaine($quantite),
            Unite::M2,
            PrixUnitaireHt::depuisChaine($prix),
            TauxTva::INTERMEDIAIRE,
            Pourcentage::depuisChaine($remise),
        );

        self::assertSame($attendu, $ligne->montantHt()->enChaine());
    }
}
