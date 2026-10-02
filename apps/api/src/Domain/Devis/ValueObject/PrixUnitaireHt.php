<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use App\Domain\Devis\Exception\PrixUnitaireHtInvalideException;
use BcMath\Number;

/**
 * Prix unitaire hors taxes, positif ou nul (le format « chaîne décimale » exclut le signe).
 */
final readonly class PrixUnitaireHt
{
    public const string MAXIMUM = '10000000';
    public const int DECIMALES_MAX = 2;

    private function __construct(
        public Number $valeur,
    ) {
    }

    /**
     * @throws PrixUnitaireHtInvalideException
     */
    public static function depuisChaine(string $chaine): self
    {
        try {
            $valeur = ChaineDecimale::versNombre($chaine, self::DECIMALES_MAX);
        } catch (ChaineDecimaleInvalideException $e) {
            throw PrixUnitaireHtInvalideException::chaineInvalide($e);
        }

        if ($valeur->compare(self::MAXIMUM) > 0) {
            throw PrixUnitaireHtInvalideException::tropGrand($chaine, self::MAXIMUM);
        }

        return new self($valeur);
    }
}
