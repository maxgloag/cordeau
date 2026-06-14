<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Enum;

enum ModeFacturation: string
{
    case TEMPS = 'temps';
    case SURFACE = 'surface';
    case FORFAIT = 'forfait';

    /**
     * Unité de la valeur estimée/réelle, dérivée du mode (jamais stockée — cf ADR 0024).
     */
    public function unite(): string
    {
        return match ($this) {
            self::TEMPS => 'h',
            self::SURFACE => 'm²',
            self::FORFAIT => '€',
        };
    }
}
