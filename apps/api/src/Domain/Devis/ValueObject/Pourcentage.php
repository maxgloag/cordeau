<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use App\Domain\Devis\Exception\PourcentageInvalideException;
use BcMath\Number;

/**
 * Pourcentage compris entre 0 et 100 inclus, au plus 2 décimales
 * (remise de ligne, remise globale en pourcentage, acompte).
 */
final readonly class Pourcentage
{
    public const string MAXIMUM = '100';
    public const int DECIMALES_MAX = 2;

    private function __construct(
        public Number $valeur,
    ) {
    }

    /**
     * @throws PourcentageInvalideException
     */
    public static function depuisChaine(string $chaine): self
    {
        try {
            $valeur = ChaineDecimale::versNombre($chaine, self::DECIMALES_MAX);
        } catch (ChaineDecimaleInvalideException $e) {
            throw PourcentageInvalideException::chaineInvalide($e);
        }

        if ($valeur->compare(self::MAXIMUM) > 0) {
            throw PourcentageInvalideException::superieurA100($chaine);
        }

        return new self($valeur);
    }

    public static function zero(): self
    {
        return new self(new Number('0'));
    }

    /**
     * Valeur exacte (sans arrondi) de ce pourcentage appliqué à $nombre.
     * Multiplier par 0.01 plutôt que diviser par 100 : le résultat est exact
     * quelle que soit l'échelle, sans dépendre de l'échelle par défaut de div().
     */
    public function appliquerA(Number $nombre): Number
    {
        return $nombre->mul($this->valeur)->mul('0.01');
    }

    /**
     * 100 − ce pourcentage (ex. remise de 5 % → 95 % du prix).
     */
    public function complement(): self
    {
        return new self((new Number(self::MAXIMUM))->sub($this->valeur));
    }
}
