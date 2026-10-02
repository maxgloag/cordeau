<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

final readonly class TotauxDevis
{
    /**
     * @param non-empty-list<Montant> $montantsHtLignes dans l'ordre de saisie
     * @param list<VentilationTva>    $ventilationTva   par taux croissant, vide en franchise en base
     */
    public function __construct(
        public array $montantsHtLignes,
        public Montant $totalHtBrut,
        public Montant $remiseGlobale,
        public Montant $totalHtNet,
        public array $ventilationTva,
        public Montant $totalTva,
        public Montant $totalTtc,
        public Montant $acompte,
        public Montant $solde,
        public ?string $mentionTva,
    ) {
    }
}
