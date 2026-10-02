<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class QuantiteInvalideException extends DevisException
{
    public static function chaineInvalide(ChaineDecimaleInvalideException $cause): self
    {
        return new self('Quantité invalide : '.$cause->getMessage(), 0, $cause);
    }

    public static function nonStrictementPositive(string $chaine): self
    {
        return new self(\sprintf('La quantité doit être strictement positive, "%s" reçu.', $chaine));
    }

    public static function tropGrande(string $chaine, string $maximum): self
    {
        return new self(\sprintf('La quantité "%s" dépasse le maximum autorisé de %s.', $chaine, $maximum));
    }
}
