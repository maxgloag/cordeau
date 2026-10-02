<?php

declare(strict_types=1);

namespace App\Domain\Devis\Enum;

enum Unite: string
{
    case U = 'U';
    case M2 = 'M2';
    case ML = 'ML';
    case M3 = 'M3';
    case H = 'H';
    case FORFAIT = 'FORFAIT';
}
