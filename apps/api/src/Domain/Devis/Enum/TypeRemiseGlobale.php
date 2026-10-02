<?php

declare(strict_types=1);

namespace App\Domain\Devis\Enum;

enum TypeRemiseGlobale: string
{
    case POURCENTAGE = 'POURCENTAGE';
    case MONTANT = 'MONTANT';
}
