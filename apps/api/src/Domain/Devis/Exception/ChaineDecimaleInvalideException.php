<?php

declare(strict_types=1);

namespace App\Domain\Devis\Exception;

final class ChaineDecimaleInvalideException extends DevisException
{
    public static function formatInvalide(string $chaine): self
    {
        return new self(\sprintf('"%s" n\'est pas une chaîne décimale valide (format attendu : chiffres, éventuellement suivis d\'un point et de chiffres).', $chaine));
    }

    public static function tropDeDecimales(string $chaine, int $decimalesMax): self
    {
        return new self(\sprintf('"%s" comporte plus de %d décimale(s).', $chaine, $decimalesMax));
    }
}
