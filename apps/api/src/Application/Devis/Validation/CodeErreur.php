<?php

declare(strict_types=1);

namespace App\Application\Devis\Validation;

/**
 * Codes d'erreur de validation d'une demande de calcul des totaux d'un devis.
 */
enum CodeErreur: string
{
    case JSON_INVALIDE = 'JSON_INVALIDE';
    case REGIME_INVALIDE = 'REGIME_INVALIDE';
    case LIGNES_VIDES = 'LIGNES_VIDES';
    case DESIGNATION_VIDE = 'DESIGNATION_VIDE';
    case QUANTITE_INVALIDE = 'QUANTITE_INVALIDE';
    case UNITE_INVALIDE = 'UNITE_INVALIDE';
    case PRIX_INVALIDE = 'PRIX_INVALIDE';
    case TAUX_INVALIDE = 'TAUX_INVALIDE';
    case REMISE_LIGNE_INVALIDE = 'REMISE_LIGNE_INVALIDE';
    case FRANCHISE_AVEC_TVA = 'FRANCHISE_AVEC_TVA';
    case REMISE_GLOBALE_INVALIDE = 'REMISE_GLOBALE_INVALIDE';
    case REMISE_SUPERIEURE_AU_TOTAL = 'REMISE_SUPERIEURE_AU_TOTAL';
    case ACOMPTE_INVALIDE = 'ACOMPTE_INVALIDE';
}
