<?php

declare(strict_types=1);

namespace App\Domain\Devis\Service;

use App\Domain\Devis\ValueObject\BaseHt;
use App\Domain\Devis\ValueObject\DemandeDevis;
use App\Domain\Devis\ValueObject\Montant;
use App\Domain\Devis\ValueObject\TotauxDevis;
use App\Domain\Devis\ValueObject\VentilationTva;

/**
 * Calcul des totaux d'un devis. Fonction pure : calculs intermédiaires exacts
 * (BcMath\Number), arrondis au centime demi-supérieur uniquement aux étapes prévues
 * (montant de ligne, remise par base, TVA par base, acompte).
 */
final class CalculateurTotauxDevis
{
    public function calculer(DemandeDevis $demande): TotauxDevis
    {
        $lignes = $demande->lignes;
        $totalHtBrut = $lignes->totalHtBrut();
        $bases = $lignes->basesHt();

        $parts = $demande->remiseGlobale?->ventiler($bases)
            ?? array_map(static fn (BaseHt $base): Montant => Montant::zero(), $bases);
        if (\count($parts) !== \count($bases)) {
            throw new \LogicException('La ventilation de la remise globale doit produire une part par base HT.');
        }

        $remiseGlobale = self::somme($parts);
        $totalHtNet = $totalHtBrut->moins($remiseGlobale);

        $ventilationTva = [];
        if ($demande->regime->factureLaTva()) {
            foreach ($bases as $index => $base) {
                $baseNette = $base->montant->moins($parts[$index]);
                $ventilationTva[] = new VentilationTva(
                    $base->taux,
                    $baseNette,
                    Montant::arrondi($base->taux->pourcentage()->appliquerA($baseNette->valeur)),
                );
            }
        }

        $totalTva = self::somme(array_map(static fn (VentilationTva $ventilation): Montant => $ventilation->montantTva, $ventilationTva));
        $totalTtc = $totalHtNet->plus($totalTva);
        $acompte = Montant::arrondi($demande->acompte->appliquerA($totalTtc->valeur));

        return new TotauxDevis(
            montantsHtLignes: $lignes->montantsHt(),
            totalHtBrut: $totalHtBrut,
            remiseGlobale: $remiseGlobale,
            totalHtNet: $totalHtNet,
            ventilationTva: $ventilationTva,
            totalTva: $totalTva,
            totalTtc: $totalTtc,
            acompte: $acompte,
            solde: $totalTtc->moins($acompte),
            mentionTva: $demande->regime->mentionTva(),
        );
    }

    /**
     * @param list<Montant> $montants
     */
    private static function somme(array $montants): Montant
    {
        return array_reduce($montants, static fn (Montant $somme, Montant $montant): Montant => $somme->plus($montant), Montant::zero());
    }
}
