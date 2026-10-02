<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Enum\Unite;

final readonly class LigneDevis
{
    public function __construct(
        public Designation $designation,
        public Quantite $quantite,
        public Unite $unite,
        public PrixUnitaireHt $prixUnitaireHt,
        public TauxTva $tauxTva,
        public Pourcentage $remise,
    ) {
    }

    /**
     * arrondi(quantite × prix_unitaire_ht × (1 − remise / 100)) : produit exact,
     * un seul arrondi au centime.
     */
    public function montantHt(): Montant
    {
        $brut = $this->quantite->valeur->mul($this->prixUnitaireHt->valeur);

        return Montant::arrondi($this->remise->complement()->appliquerA($brut));
    }
}
