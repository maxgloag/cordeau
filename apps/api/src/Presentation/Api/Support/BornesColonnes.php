<?php

declare(strict_types=1);

namespace App\Presentation\Api\Support;

/**
 * Bornes des colonnes SQL, à rappeler dans les payloads : une valeur hors colonne répond 422,
 * pas 500 à l'écriture.
 */
final class BornesColonnes
{
    /** INTEGER PostgreSQL. */
    public const int ENTIER_MAX = 2_147_483_647;

    /** NUMERIC(12, 2), sans la partie décimale : l'arrondi à 2 décimales ne peut pas la dépasser. */
    public const float DECIMAL_12_2_MAX = 9_999_999_999.0;

    private function __construct()
    {
    }
}
