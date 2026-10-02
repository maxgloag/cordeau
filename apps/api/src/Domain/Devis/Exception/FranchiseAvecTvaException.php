<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

use App\Domain\Devis\Enum\TauxTva;

final class FranchiseAvecTvaException extends DevisException
{
    public static function pourLaLigne(int $index, TauxTva $taux): self
    {
        return new self(\sprintf('En franchise en base de TVA, la ligne %d ne peut pas porter le taux "%s" : seul le taux "0" est admis.', $index, $taux->value));
    }
}
