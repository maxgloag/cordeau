<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Exception\FranchiseAvecTvaException;
use App\Domain\Devis\Exception\RemiseSuperieureAuTotalException;

/**
 * Demande de calcul des totaux d'un devis, cohérente par construction :
 * taux de chaque ligne admis par le régime de TVA, remise globale ≤ total HT brut.
 */
final readonly class DemandeDevis
{
    /**
     * @throws FranchiseAvecTvaException
     * @throws RemiseSuperieureAuTotalException
     */
    public function __construct(
        public RegimeTva $regime,
        public LignesDevis $lignes,
        public ?RemiseGlobale $remiseGlobale,
        public Pourcentage $acompte,
    ) {
        foreach ($lignes->lignes as $index => $ligne) {
            if (!$regime->autorise($ligne->tauxTva)) {
                throw FranchiseAvecTvaException::pourLaLigne($index, $ligne->tauxTva);
            }
        }

        $totalHtBrut = $lignes->totalHtBrut();
        if ($remiseGlobale !== null && $remiseGlobale->depasse($totalHtBrut)) {
            throw RemiseSuperieureAuTotalException::depasseLeTotal($totalHtBrut);
        }
    }
}
