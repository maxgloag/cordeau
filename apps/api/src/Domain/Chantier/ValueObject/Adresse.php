<?php

declare(strict_types=1);

namespace App\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\AdresseInvalideException;

final readonly class Adresse
{
    public function __construct(
        public string $rue,
        public string $codePostal,
        public string $ville,
        public string $pays = 'FR',
    ) {
        if (trim($this->rue) === '') {
            throw AdresseInvalideException::rueVide();
        }

        if (trim($this->ville) === '') {
            throw AdresseInvalideException::villeVide();
        }

        if (preg_match('/^[A-Z]{2}$/', $this->pays) !== 1) {
            throw AdresseInvalideException::paysInvalide($this->pays);
        }

        if ($this->pays === 'FR' && preg_match('/^\d{5}$/', $this->codePostal) !== 1) {
            throw AdresseInvalideException::codePostalInvalide($this->codePostal, $this->pays);
        }

        if (trim($this->codePostal) === '') {
            throw AdresseInvalideException::codePostalInvalide($this->codePostal, $this->pays);
        }
    }

    public function equals(self $autre): bool
    {
        return $this->rue === $autre->rue
            && $this->codePostal === $autre->codePostal
            && $this->ville === $autre->ville
            && $this->pays === $autre->pays;
    }
}
