<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use App\Domain\Devis\Exception\MontantInvalideException;
use BcMath\Number;

/**
 * Montant en euros, exact au centime : toujours porté à l'échelle 2.
 *
 * Seule porte d'entrée d'un calcul exact vers un montant : arrondi(), qui applique
 * l'arrondi au centime demi-supérieur (0,005 → 0,01 ; −0,005 → −0,01, soit
 * RoundingMode::HalfAwayFromZero, l'équivalent de ROUND_HALF_UP).
 * Peut être négatif (ex. ajustement d'une part de remise ventilée).
 */
final readonly class Montant
{
    public const int ECHELLE = 2;
    public const int DECIMALES_MAX = 2;

    private function __construct(
        public Number $valeur,
    ) {
        if ($valeur->scale !== self::ECHELLE) {
            throw new \LogicException(\sprintf('Un montant doit être à l\'échelle %d, échelle %d reçue.', self::ECHELLE, $valeur->scale));
        }
    }

    /**
     * @throws MontantInvalideException
     */
    public static function depuisChaine(string $chaine): self
    {
        try {
            $valeur = ChaineDecimale::versNombre($chaine, self::DECIMALES_MAX);
        } catch (ChaineDecimaleInvalideException $e) {
            throw MontantInvalideException::chaineInvalide($e);
        }

        // Au plus 2 décimales : round(2) ne fait que compléter l'échelle, sans perte.
        return self::arrondi($valeur);
    }

    public static function arrondi(Number $exact): self
    {
        return new self($exact->round(self::ECHELLE, \RoundingMode::HalfAwayFromZero));
    }

    public static function zero(): self
    {
        return new self(new Number('0.00'));
    }

    public function plus(self $autre): self
    {
        return new self($this->valeur->add($autre->valeur));
    }

    public function moins(self $autre): self
    {
        return new self($this->valeur->sub($autre->valeur));
    }

    public function estSuperieurA(self $autre): bool
    {
        return $this->valeur->compare($autre->valeur) > 0;
    }

    public function estEgalA(self $autre): bool
    {
        return $this->valeur->compare($autre->valeur) === 0;
    }

    public function estNul(): bool
    {
        return $this->valeur->compare(0) === 0;
    }

    /**
     * Représentation canonique : exactement 2 décimales, point décimal, sans séparateur de milliers.
     */
    public function enChaine(): string
    {
        return (string) $this->valeur;
    }
}
