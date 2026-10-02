<?php

declare(strict_types=1);

namespace App\Application\Devis\Validation;

use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Enum\TypeRemiseGlobale;
use App\Domain\Devis\Enum\Unite;
use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\DemandeDevis;
use App\Domain\Devis\ValueObject\Designation;
use App\Domain\Devis\ValueObject\LigneDevis;
use App\Domain\Devis\ValueObject\LignesDevis;
use App\Domain\Devis\ValueObject\Montant;
use App\Domain\Devis\ValueObject\Pourcentage;
use App\Domain\Devis\ValueObject\PrixUnitaireHt;
use App\Domain\Devis\ValueObject\Quantite;
use App\Domain\Devis\ValueObject\RemiseGlobale;
use App\Domain\Devis\ValueObject\RemiseGlobaleMontant;
use App\Domain\Devis\ValueObject\RemiseGlobalePourcentage;

/**
 * Lit une demande de devis JSON et la convertit en DemandeDevis du domaine, en
 * rapportant TOUTES les erreurs détectables (DemandeDevisInvalideException).
 *
 * Règles de lecture :
 * - le JSON est décodé en objets (stdClass) et non en tableaux associatifs, seul
 *   moyen de distinguer un objet JSON d'un tableau JSON ({} / [] / {"0": …}) ;
 * - pour tous les champs, « absent » et null sont équivalents : invalide pour un
 *   champ obligatoire, valeur par défaut pour un champ optionnel ;
 * - une valeur attendue sous forme de chaîne (décimale ou énumérée) doit être une
 *   chaîne JSON : un nombre, un booléen, un objet ou un tableau est invalide ;
 * - les champs inconnus sont ignorés.
 */
final class DemandeDevisJsonParser
{
    /**
     * Profondeur d'imbrication JSON acceptée : un JSON valide doit être lu même si un
     * champ inconnu est profondément imbriqué (la valeur par défaut de PHP est 512).
     */
    private const int PROFONDEUR_MAX = 10_000;

    private const array CODES_LIGNE_NON_OBJET = [
        CodeErreur::DESIGNATION_VIDE,
        CodeErreur::QUANTITE_INVALIDE,
        CodeErreur::UNITE_INVALIDE,
        CodeErreur::PRIX_INVALIDE,
        CodeErreur::TAUX_INVALIDE,
    ];

    /**
     * @throws DemandeDevisInvalideException
     */
    public function parse(string $json): DemandeDevis
    {
        try {
            $racine = json_decode($json, false, self::PROFONDEUR_MAX, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new DemandeDevisInvalideException([new ErreurValidation(CodeErreur::JSON_INVALIDE)]);
        }

        if (!$racine instanceof \stdClass) {
            throw new DemandeDevisInvalideException([new ErreurValidation(CodeErreur::JSON_INVALIDE)]);
        }

        $champs = get_object_vars($racine);
        $erreurs = new CollecteurErreurs();

        $regime = self::lire($champs['regime_tva'] ?? null, RegimeTva::tryFrom(...));
        if ($regime === null) {
            $erreurs->ajouter(CodeErreur::REGIME_INVALIDE);
        }

        $lignes = self::lireLignes($champs['lignes'] ?? null, $regime, $erreurs);

        $remiseGlobale = null;
        if (($champs['remise_globale'] ?? null) !== null) {
            $remiseGlobale = self::lireRemiseGlobale($champs['remise_globale']);
            if ($remiseGlobale === null) {
                $erreurs->ajouter(CodeErreur::REMISE_GLOBALE_INVALIDE);
            }
        }

        $acompte = self::lireOptionnel($champs['acompte_pct'] ?? null, Pourcentage::depuisChaine(...), Pourcentage::zero());
        if ($acompte === null) {
            $erreurs->ajouter(CodeErreur::ACOMPTE_INVALIDE);
        }

        // Vérifiée seulement si toutes les lignes sont valides (aucune erreur portant un
        // index de ligne, franchise comprise), indépendamment du régime et de l'acompte.
        if ($lignes !== null && !$erreurs->contientUneErreurDeLigne() && $remiseGlobale?->depasse($lignes->totalHtBrut()) === true) {
            $erreurs->ajouter(CodeErreur::REMISE_SUPERIEURE_AU_TOTAL);
        }

        $erreurs->leverSiNonVide();
        if ($regime === null || $lignes === null || $acompte === null) {
            throw new \LogicException('Demande incomplète sans erreur de validation collectée.');
        }

        return new DemandeDevis($regime, $lignes, $remiseGlobale, $acompte);
    }

    /**
     * @return LignesDevis|null null si `lignes` est invalide ou si une ligne est invalide
     */
    private static function lireLignes(mixed $brut, ?RegimeTva $regime, CollecteurErreurs $erreurs): ?LignesDevis
    {
        if (!\is_array($brut) || !array_is_list($brut) || $brut === []) {
            $erreurs->ajouter(CodeErreur::LIGNES_VIDES);

            return null;
        }

        $lignes = [];
        foreach ($brut as $index => $ligneBrute) {
            $lignes[] = self::lireLigne($ligneBrute, $index, $regime, $erreurs);
        }

        $lignesValides = array_values(array_filter($lignes, static fn (?LigneDevis $ligne): bool => $ligne !== null));

        return \count($lignesValides) === \count($lignes) ? new LignesDevis($lignesValides) : null;
    }

    /**
     * @param int<0, max> $index
     *
     * @return LigneDevis|null null si au moins un champ de la ligne est invalide
     */
    private static function lireLigne(mixed $brut, int $index, ?RegimeTva $regime, CollecteurErreurs $erreurs): ?LigneDevis
    {
        if (!$brut instanceof \stdClass) {
            foreach (self::CODES_LIGNE_NON_OBJET as $code) {
                $erreurs->ajouter($code, $index);
            }

            return null;
        }

        $champs = get_object_vars($brut);

        $designation = self::lire($champs['designation'] ?? null, Designation::depuisChaine(...));
        if ($designation === null) {
            $erreurs->ajouter(CodeErreur::DESIGNATION_VIDE, $index);
        }

        $quantite = self::lire($champs['quantite'] ?? null, Quantite::depuisChaine(...));
        if ($quantite === null) {
            $erreurs->ajouter(CodeErreur::QUANTITE_INVALIDE, $index);
        }

        $unite = self::lire($champs['unite'] ?? null, Unite::tryFrom(...));
        if ($unite === null) {
            $erreurs->ajouter(CodeErreur::UNITE_INVALIDE, $index);
        }

        $prix = self::lire($champs['prix_unitaire_ht'] ?? null, PrixUnitaireHt::depuisChaine(...));
        if ($prix === null) {
            $erreurs->ajouter(CodeErreur::PRIX_INVALIDE, $index);
        }

        $taux = self::lire($champs['taux_tva'] ?? null, TauxTva::tryFrom(...));
        if ($taux === null) {
            $erreurs->ajouter(CodeErreur::TAUX_INVALIDE, $index);
        } elseif ($regime !== null && !$regime->autorise($taux)) {
            $erreurs->ajouter(CodeErreur::FRANCHISE_AVEC_TVA, $index);
        }

        $remise = self::lireOptionnel($champs['remise_pct'] ?? null, Pourcentage::depuisChaine(...), Pourcentage::zero());
        if ($remise === null) {
            $erreurs->ajouter(CodeErreur::REMISE_LIGNE_INVALIDE, $index);
        }

        if ($designation === null || $quantite === null || $unite === null || $prix === null || $taux === null || $remise === null) {
            return null;
        }

        return new LigneDevis($designation, $quantite, $unite, $prix, $taux, $remise);
    }

    /**
     * @return RemiseGlobale|null null si l'objet est mal formé ou sa valeur invalide
     */
    private static function lireRemiseGlobale(mixed $brut): ?RemiseGlobale
    {
        if (!$brut instanceof \stdClass) {
            return null;
        }

        $champs = get_object_vars($brut);
        $valeur = $champs['valeur'] ?? null;

        return match (self::lire($champs['type'] ?? null, TypeRemiseGlobale::tryFrom(...))) {
            TypeRemiseGlobale::POURCENTAGE => ($pourcentage = self::lire($valeur, Pourcentage::depuisChaine(...))) !== null
                ? new RemiseGlobalePourcentage($pourcentage)
                : null,
            TypeRemiseGlobale::MONTANT => ($montant = self::lire($valeur, Montant::depuisChaine(...))) !== null
                ? new RemiseGlobaleMontant($montant)
                : null,
            null => null,
        };
    }

    /**
     * Lit une valeur obligatoire attendue sous forme de chaîne JSON.
     *
     * @template T of object
     *
     * @param callable(string): (T|null) $fabrique renvoie null ou lève une DevisException si la chaîne est invalide
     *
     * @return T|null null si la valeur est absente, n'est pas une chaîne ou est invalide
     */
    private static function lire(mixed $brut, callable $fabrique): ?object
    {
        if (!\is_string($brut)) {
            return null;
        }

        try {
            return $fabrique($brut);
        } catch (DevisException) {
            return null;
        }
    }

    /**
     * Lit une valeur optionnelle : absente ou null → $parDefaut.
     *
     * @template T of object
     *
     * @param callable(string): T $fabrique
     * @param T                   $parDefaut
     *
     * @return T|null null si la valeur est présente mais invalide
     */
    private static function lireOptionnel(mixed $brut, callable $fabrique, object $parDefaut): ?object
    {
        return $brut === null ? $parDefaut : self::lire($brut, $fabrique);
    }
}
