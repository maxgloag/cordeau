<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use App\Domain\Devis\Exception\QuantiteInvalideException;
use BcMath\Number;

final readonly class Quantite
{
    public const string MAXIMUM = '1000000';
    public const int DECIMALES_MAX = 3;

    private function __construct(
        public Number $valeur,
    ) {
    }

    /**
     * @throws QuantiteInvalideException
     */
    public static function depuisChaine(string $chaine): self
    {
        try {
            $valeur = ChaineDecimale::versNombre($chaine, self::DECIMALES_MAX);
        } catch (ChaineDecimaleInvalideException $e) {
            throw QuantiteInvalideException::chaineInvalide($e);
        }

        if ($valeur->compare(0) <= 0) {
            throw QuantiteInvalideException::nonStrictementPositive($chaine);
        }

        if ($valeur->compare(self::MAXIMUM) > 0) {
            throw QuantiteInvalideException::tropGrande($chaine, self::MAXIMUM);
        }

        return new self($valeur);
    }
}
