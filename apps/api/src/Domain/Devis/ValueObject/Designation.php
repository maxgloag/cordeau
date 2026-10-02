<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DesignationInvalideException;

/**
 * Libellé d'une ligne de devis, débarrassé des espaces de début et de fin.
 * « Espace » au sens Unicode (\s en mode /u : espace, tabulations, retours ligne,
 * espace insécable, espaces typographiques…).
 */
final readonly class Designation
{
    private function __construct(
        public string $libelle,
    ) {
    }

    /**
     * @throws DesignationInvalideException
     */
    public static function depuisChaine(string $chaine): self
    {
        // Découpage en caractères puis parcours depuis chaque extrémité : linéaire.
        // Une regex /\s+\z/u devient quadratique sans JIT PCRE sur de longues suites
        // d'espaces internes (mesuré : 33 s pour 100 000 espaces).
        $caracteres = preg_split('//u', $chaine, -1, \PREG_SPLIT_NO_EMPTY);
        if ($caracteres === false) {
            throw DesignationInvalideException::encodageInvalide();
        }

        $debut = 0;
        $fin = \count($caracteres);
        while ($debut < $fin && self::estUnEspace($caracteres[$debut])) {
            ++$debut;
        }
        while ($fin > $debut && self::estUnEspace($caracteres[$fin - 1])) {
            --$fin;
        }

        if ($debut === $fin) {
            throw DesignationInvalideException::vide();
        }

        return new self(implode('', \array_slice($caracteres, $debut, $fin - $debut)));
    }

    private static function estUnEspace(string $caractere): bool
    {
        return preg_match('/\A\s\z/u', $caractere) === 1;
    }
}
