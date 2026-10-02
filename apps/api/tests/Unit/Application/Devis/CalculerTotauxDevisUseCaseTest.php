<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Devis;

use App\Application\Devis\UseCase\CalculerTotauxDevisUseCase;
use App\Application\Devis\Validation\CodeErreur;
use App\Application\Devis\Validation\DemandeDevisInvalideException;
use App\Application\Devis\Validation\DemandeDevisJsonParser;
use App\Domain\Devis\Service\CalculateurTotauxDevis;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalculerTotauxDevisUseCaseTest extends TestCase
{
    private CalculerTotauxDevisUseCase $useCase;

    protected function setUp(): void
    {
        $this->useCase = new CalculerTotauxDevisUseCase(new DemandeDevisJsonParser(), new CalculateurTotauxDevis());
    }

    #[Test]
    public function il_calcule_les_totaux_de_l_exemple_verifie_de_la_spec(): void
    {
        $totaux = $this->useCase->execute('{"regime_tva":"NORMAL","lignes":[{"designation":"Pose carrelage sol","quantite":"12.5","unite":"M2","prix_unitaire_ht":"45.90","taux_tva":"10","remise_pct":"5"}],"remise_globale":{"type":"POURCENTAGE","valeur":"3"},"acompte_pct":"30"}');

        self::assertSame('545.06', $totaux->montantsHtLignes[0]->enChaine());
        self::assertSame('528.71', $totaux->totalHtNet->enChaine());
        self::assertSame('581.58', $totaux->totalTtc->enChaine());
        self::assertSame('174.47', $totaux->acompte->enChaine());
        self::assertSame('407.11', $totaux->solde->enChaine());
    }

    #[Test]
    public function il_rejette_une_demande_invalide_sans_calculer(): void
    {
        try {
            $this->useCase->execute('pas du json');
            self::fail('Une DemandeDevisInvalideException était attendue.');
        } catch (DemandeDevisInvalideException $e) {
            self::assertCount(1, $e->erreurs);
            self::assertSame(CodeErreur::JSON_INVALIDE, $e->erreurs[0]->code);
            self::assertNull($e->erreurs[0]->ligne);
        }
    }
}
