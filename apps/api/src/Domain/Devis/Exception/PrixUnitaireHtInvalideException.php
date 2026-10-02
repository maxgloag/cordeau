<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class PrixUnitaireHtInvalideException extends DevisException
{
    public static function chaineInvalide(ChaineDecimaleInvalideException $cause): self
    {
        return new self('Prix unitaire HT invalide : '.$cause->getMessage(), 0, $cause);
    }

    public static function tropGrand(string $chaine, string $maximum): self
    {
        return new self(\sprintf('Le prix unitaire HT "%s" dépasse le maximum autorisé de %s.', $chaine, $maximum));
    }
}
