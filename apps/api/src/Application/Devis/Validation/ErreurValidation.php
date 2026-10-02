<?php

declare(strict_types=1);

namespace App\Application\Devis\Validation;

final readonly class ErreurValidation
{
    /**
     * @param int<0, max>|null $ligne index (base 0) de la ligne concernée, null pour une erreur hors ligne
     */
    public function __construct(
        public CodeErreur $code,
        public ?int $ligne = null,
    ) {
    }
}
