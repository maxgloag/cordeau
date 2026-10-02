<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Enum\Unite;
use App\Domain\Devis\ValueObject\DemandeDevis;
use App\Domain\Devis\ValueObject\Designation;
use App\Domain\Devis\ValueObject\LigneDevis;
use App\Domain\Devis\ValueObject\LignesDevis;
use App\Domain\Devis\ValueObject\Montant;
use App\Domain\Devis\ValueObject\Pourcentage;
use App\Domain\Devis\ValueObject\PrixUnitaireHt;
use App\Domain\Devis\ValueObject\Quantite;
use App\Domain\Devis\ValueObject\RemiseGlobale;
use App\Domain\Devis\ValueObject\RemiseGlobaleMontant;
use App\Domain\Devis\ValueObject\RemiseGlobalePourcentage;
use App\Domain\Devis\ValueObject\TotauxDevis;
use App\Domain\Devis\ValueObject\VentilationTva;

/**
 * Raccourcis de construction pour les tests du domaine Devis.
 */
final class DevisTestBuilder
{
    /**
     * @param array{string, string, string, string} $ligne [quantite, prix, taux, remise]
     */
    public static function ligne(array $ligne): LigneDevis
    {
        [$quantite, $prix, $taux, $remise] = $ligne;

        return new LigneDevis(
            Designation::depuisChaine('Ligne'),
            Quantite::depuisChaine($quantite),
            Unite::U,
            PrixUnitaireHt::depuisChaine($prix),
            TauxTva::from($taux),
            Pourcentage::depuisChaine($remise),
        );
    }

    /**
     * @param non-empty-list<array{string, string, string, string}> $lignes
     */
    public static function lignes(array $lignes): LignesDevis
    {
        return new LignesDevis(array_map(self::ligne(...), $lignes));
    }

    /**
     * @param non-empty-list<array{string, string, string, string}> $lignes
     * @param array{'P'|'M', string}|null                           $remise
     */
    public static function demande(RegimeTva $regime, array $lignes, ?array $remise = null, string $acompte = '0'): DemandeDevis
    {
        return new DemandeDevis(regime: $regime, lignes: self::lignes($lignes), remiseGlobale: self::remise($remise), acompte: Pourcentage::depuisChaine($acompte));
    }

    /**
     * @param array{'P'|'M', string}|null $remise
     */
    public static function remise(?array $remise): ?RemiseGlobale
    {
        if ($remise === null) {
            return null;
        }

        return match ($remise[0]) {
            'P' => new RemiseGlobalePourcentage(Pourcentage::depuisChaine($remise[1])),
            'M' => new RemiseGlobaleMontant(Montant::depuisChaine($remise[1])),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function enTableau(TotauxDevis $totaux): array
    {
        return [
            'lignes' => array_map(static fn (Montant $montant): string => $montant->enChaine(), $totaux->montantsHtLignes),
            'total_ht_brut' => $totaux->totalHtBrut->enChaine(),
            'remise_globale' => $totaux->remiseGlobale->enChaine(),
            'total_ht_net' => $totaux->totalHtNet->enChaine(),
            'ventilation_tva' => array_map(static fn (VentilationTva $ventilation): array => [
                'taux' => $ventilation->taux->value,
                'base_ht' => $ventilation->baseHt->enChaine(),
                'montant_tva' => $ventilation->montantTva->enChaine(),
            ], $totaux->ventilationTva),
            'total_tva' => $totaux->totalTva->enChaine(),
            'total_ttc' => $totaux->totalTtc->enChaine(),
            'acompte' => $totaux->acompte->enChaine(),
            'solde' => $totaux->solde->enChaine(),
            'mention_tva' => $totaux->mentionTva,
        ];
    }
}
