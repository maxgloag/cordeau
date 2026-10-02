<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class MontantInvalideException extends DevisException
{
    public static function chaineInvalide(ChaineDecimaleInvalideException $cause): self
    {
        return new self('Montant invalide : '.$cause->getMessage(), 0, $cause);
    }
}
