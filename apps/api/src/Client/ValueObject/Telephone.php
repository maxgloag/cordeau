<?php

declare(strict_types=1);

namespace App\Client\ValueObject;

use App\Client\Exception\TelephoneInvalideException;

/**
 * Numéro de téléphone français, normalisé en format E.164 (+33XXXXXXXXX).
 *
 * Accepte en entrée : 0612345678, 06 12 34 56 78, 06.12.34.56.78, 06-12-34-56-78,
 * +33 6 12 34 56 78, +33612345678. Toutes ces formes sont normalisées au stockage.
 */
final readonly class Telephone
{
    public string $valeur;

    public function __construct(string $valeur)
    {
        $nettoye = preg_replace('/[\s.\-]/', '', $valeur) ?? '';

        if (preg_match('/^0([1-9]\d{8})$/', $nettoye, $matches) === 1) {
            $this->valeur = '+33' . $matches[1];

            return;
        }

        if (preg_match('/^\+33([1-9]\d{8})$/', $nettoye) === 1) {
            $this->valeur = $nettoye;

            return;
        }

        throw TelephoneInvalideException::format($valeur);
    }

    public function equals(self $autre): bool
    {
        return $this->valeur === $autre->valeur;
    }

    public function __toString(): string
    {
        return $this->valeur;
    }
}
