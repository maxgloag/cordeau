<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use BcMath\Number;

/**
 * Conversion stricte d'une « chaîne décimale » (chiffres ASCII, point optionnel suivi
 * d'au moins un chiffre, sans signe, exposant ni espace) en nombre décimal exact.
 *
 * Le format est vérifié AVANT de construire le BcMath\Number : son constructeur
 * accepte des formes que la règle métier refuse (".5", "5.").
 */
final class ChaineDecimale
{
    // \z et non $ : en PCRE, $ accepte un "\n" final.
    private const string FORMAT = '/\A[0-9]+(?:\.([0-9]+))?\z/';

    private function __construct()
    {
    }

    /**
     * @throws ChaineDecimaleInvalideException
     */
    public static function versNombre(string $chaine, int $decimalesMax): Number
    {
        // is_numeric() est redondant avec le format (plus strict) mais affine le type
        // en numeric-string pour le constructeur de Number.
        if (preg_match(self::FORMAT, $chaine, $correspondances) !== 1 || !is_numeric($chaine)) {
            throw ChaineDecimaleInvalideException::formatInvalide($chaine);
        }

        // Les zéros finaux comptent : "1.500" a 3 décimales.
        $decimales = \strlen($correspondances[1] ?? '');
        if ($decimales > $decimalesMax) {
            throw ChaineDecimaleInvalideException::tropDeDecimales($chaine, $decimalesMax);
        }

        return new Number($chaine);
    }
}
