<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class PourcentageInvalideException extends DevisException
{
    public static function chaineInvalide(ChaineDecimaleInvalideException $cause): self
    {
        return new self('Pourcentage invalide : '.$cause->getMessage(), 0, $cause);
    }

    public static function superieurA100(string $chaine): self
    {
        return new self(\sprintf('Le pourcentage "%s" dépasse 100.', $chaine));
    }
}
