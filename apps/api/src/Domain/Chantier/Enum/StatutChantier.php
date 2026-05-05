<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Enum;

enum StatutChantier: string
{
    case EN_PREPARATION = 'en_preparation';
    case EN_COURS = 'en_cours';
    case TERMINE = 'termine';
    case ARCHIVE = 'archive';

    public function estTerminal(): bool
    {
        return $this === self::ARCHIVE;
    }
}
