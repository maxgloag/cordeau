<?php

declare(strict_types=1);

namespace App\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\ImprevuInvalideException;

/**
 * Note libre horodatée signalant un écart sur un lot. VO, pas entité (ADR 0015) :
 * persistée dans la colonne JSON `imprevus` du lot (ADR 0024).
 */
final readonly class Imprevu
{
    public function __construct(
        public string $note,
        public \DateTimeImmutable $horodatage,
    ) {
        if (trim($this->note) === '') {
            throw ImprevuInvalideException::noteVide();
        }
    }
}
