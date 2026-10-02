<?php

declare(strict_types=1);

namespace App\Application\Devis\UseCase;

use App\Application\Devis\Validation\DemandeDevisInvalideException;
use App\Application\Devis\Validation\DemandeDevisJsonParser;
use App\Domain\Devis\Service\CalculateurTotauxDevis;
use App\Domain\Devis\ValueObject\TotauxDevis;

final class CalculerTotauxDevisUseCase
{
    public function __construct(
        private readonly DemandeDevisJsonParser $parser,
        private readonly CalculateurTotauxDevis $calculateur,
    ) {
    }

    /**
     * @throws DemandeDevisInvalideException toutes les erreurs de validation de la demande
     */
    public function execute(string $demandeJson): TotauxDevis
    {
        return $this->calculateur->calculer($this->parser->parse($demandeJson));
    }
}
