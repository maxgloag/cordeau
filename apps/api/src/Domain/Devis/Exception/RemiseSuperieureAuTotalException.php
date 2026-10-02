<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

use App\Domain\Devis\ValueObject\Montant;

final class RemiseSuperieureAuTotalException extends DevisException
{
    public static function depasseLeTotal(Montant $totalHtBrut): self
    {
        return new self(\sprintf('La remise globale dépasse le total HT brut de %s €.', $totalHtBrut->enChaine()));
    }
}
