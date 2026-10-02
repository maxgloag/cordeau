#!/usr/bin/env python3
"""Implémentation de référence (oracle) — calcul des totaux d'un devis.

Expérience B (A/B PHP vs Rust), spec : docs/etudes/rust-agentique/experience/spec-totaux-devis.md

Contrat CLI : lit un JSON sur stdin, écrit un JSON sur stdout.
Code de sortie 0 en cas de succès, 1 en cas d'erreurs de validation (y compris JSON_INVALIDE).

Arithmétique : module `decimal` uniquement, jamais de float pour les montants.
Le contexte global a une précision de 200 chiffres significatifs et lève une exception sur
toute opération inexacte (trap `Inexact`) : toutes les opérations sont donc prouvées exactes,
sauf la division de la répartition MONTANT (M × base / total), seule opération potentiellement
non terminante, effectuée dans un contexte dédié à 200 chiffres puis arrondie au centime
demi-supérieur (la distance d'un quotient non décimal à une frontière x.xx5 est au moins
1 / (200 × total_en_centimes), très supérieure à l'erreur 1e-200 de la division).
"""

from __future__ import annotations

import json
import re
import sys
from decimal import (
    ROUND_HALF_EVEN,
    ROUND_HALF_UP,
    Context,
    Decimal,
    DivisionByZero,
    Inexact,
    InvalidOperation,
    Overflow,
    setcontext,
)

# Contexte exact : toute perte de précision lève une exception.
CONTEXTE_EXACT = Context(
    prec=200,
    rounding=ROUND_HALF_EVEN,
    traps=[InvalidOperation, DivisionByZero, Overflow, Inexact],
)
# Contexte d'arrondi et de division (répartition MONTANT) : pas de trap Inexact.
CONTEXTE_ARRONDI = Context(
    prec=200,
    rounding=ROUND_HALF_EVEN,
    traps=[InvalidOperation, DivisionByZero, Overflow],
)
setcontext(CONTEXTE_EXACT)

# `[0-9]` (et non `\d`) : uniquement les chiffres ASCII ; `fullmatch` : pas de `$` laxiste
# qui accepterait un saut de ligne final.
FORMAT_DECIMAL = re.compile(r"[0-9]+(?:\.[0-9]+)?")

REGIMES = ("NORMAL", "FRANCHISE_EN_BASE")
UNITES = ("U", "M2", "ML", "M3", "H", "FORFAIT")
TAUX = {"0": Decimal("0"), "5.5": Decimal("5.5"), "10": Decimal("10"), "20": Decimal("20")}
TYPES_REMISE = ("POURCENTAGE", "MONTANT")
MENTION_FRANCHISE = "TVA non applicable, art. 293 B du CGI"

CENT = Decimal("0.01")
ZERO = Decimal("0.00")
CENT_POUR_CENT = Decimal("100")
QUANTITE_MAX = Decimal("1000000")
PRIX_MAX = Decimal("10000000")

CODES_LIGNE_NON_OBJET = (
    "DESIGNATION_VIDE",
    "QUANTITE_INVALIDE",
    "UNITE_INVALIDE",
    "PRIX_INVALIDE",
    "TAUX_INVALIDE",
)


class _Absent:
    """Sentinelle distinguant « clé absente » de `null`."""


ABSENT = _Absent()


def chaine_decimale(valeur: object, max_decimales: int) -> Decimal | None:
    """Décimal si `valeur` est une chaîne décimale conforme avec au plus `max_decimales`, sinon None."""
    if type(valeur) is not str:
        return None
    if FORMAT_DECIMAL.fullmatch(valeur) is None:
        return None
    if "." in valeur and len(valeur.split(".", 1)[1]) > max_decimales:
        return None
    return Decimal(valeur)


def arrondi(x: Decimal) -> Decimal:
    """Arrondi au centime, demi-supérieur (0,005 -> 0,01)."""
    return x.quantize(CENT, rounding=ROUND_HALF_UP, context=CONTEXTE_ARRONDI)


def arrondi_division(numerateur: Decimal, denominateur: Decimal) -> Decimal:
    quotient = CONTEXTE_ARRONDI.divide(numerateur, denominateur)
    return quotient.quantize(CENT, rounding=ROUND_HALF_UP, context=CONTEXTE_ARRONDI)


def montant(x: Decimal) -> str:
    """Chaîne à exactement 2 décimales, sans exposant ni séparateur de milliers."""
    x = x.quantize(CENT)  # exact : lève Inexact si x avait plus de 2 décimales
    if x == 0:
        x = ZERO  # pas de "-0.00"
    return format(x, "f")


def cle_taux(taux: str) -> Decimal:
    return TAUX[taux]


def traiter(racine: object) -> tuple[dict, int]:
    if not isinstance(racine, dict):
        return {"erreurs": [{"code": "JSON_INVALIDE", "ligne": None}]}, 1

    erreurs: list[tuple[str, int | None]] = []

    def erreur(code: str, ligne: int | None = None) -> None:
        if (code, ligne) not in erreurs:
            erreurs.append((code, ligne))

    # --- regime_tva
    regime = racine.get("regime_tva", ABSENT)
    regime_valide = type(regime) is str and regime in REGIMES
    if not regime_valide:
        erreur("REGIME_INVALIDE")
    franchise = regime_valide and regime == "FRANCHISE_EN_BASE"

    # --- lignes
    lignes = racine.get("lignes", ABSENT)
    lignes_tableau_non_vide = isinstance(lignes, list) and len(lignes) > 0
    if not lignes_tableau_non_vide:
        erreur("LIGNES_VIDES")

    lignes_valides: list[tuple[Decimal, Decimal, Decimal, str]] = []
    erreur_de_ligne = False
    if lignes_tableau_non_vide:
        for index, ligne in enumerate(lignes):
            if not isinstance(ligne, dict):
                for code in CODES_LIGNE_NON_OBJET:
                    erreur(code, index)
                erreur_de_ligne = True
                continue
            ligne_ok = True

            designation = ligne.get("designation", ABSENT)
            if type(designation) is not str or designation.strip() == "":
                erreur("DESIGNATION_VIDE", index)
                ligne_ok = False

            quantite = chaine_decimale(ligne.get("quantite", ABSENT), 3)
            if quantite is None or quantite <= 0 or quantite > QUANTITE_MAX:
                erreur("QUANTITE_INVALIDE", index)
                ligne_ok = False

            unite = ligne.get("unite", ABSENT)
            if not (type(unite) is str and unite in UNITES):
                erreur("UNITE_INVALIDE", index)
                ligne_ok = False

            prix = chaine_decimale(ligne.get("prix_unitaire_ht", ABSENT), 2)
            if prix is None or prix > PRIX_MAX:
                erreur("PRIX_INVALIDE", index)
                ligne_ok = False

            taux = ligne.get("taux_tva", ABSENT)
            taux_valide = type(taux) is str and taux in TAUX
            if not taux_valide:
                erreur("TAUX_INVALIDE", index)
                ligne_ok = False
            elif franchise and taux != "0":
                erreur("FRANCHISE_AVEC_TVA", index)
                ligne_ok = False

            remise_brute = ligne.get("remise_pct", ABSENT)
            if remise_brute is ABSENT or remise_brute is None:
                remise_ligne: Decimal | None = Decimal("0")
            else:
                remise_ligne = chaine_decimale(remise_brute, 2)
                if remise_ligne is not None and remise_ligne > CENT_POUR_CENT:
                    remise_ligne = None
            if remise_ligne is None:
                erreur("REMISE_LIGNE_INVALIDE", index)
                ligne_ok = False

            if ligne_ok:
                assert quantite is not None and prix is not None and remise_ligne is not None
                lignes_valides.append((quantite, prix, remise_ligne, taux))  # type: ignore[arg-type]
            else:
                erreur_de_ligne = True

    # --- remise_globale
    remise_globale = racine.get("remise_globale", ABSENT)
    type_remise: str | None = None
    valeur_remise: Decimal | None = None
    if remise_globale is ABSENT or remise_globale is None:
        pass
    elif not isinstance(remise_globale, dict):
        erreur("REMISE_GLOBALE_INVALIDE")
    else:
        type_brut = remise_globale.get("type", ABSENT)
        valeur = chaine_decimale(remise_globale.get("valeur", ABSENT), 2)
        if not (type(type_brut) is str and type_brut in TYPES_REMISE):
            erreur("REMISE_GLOBALE_INVALIDE")
        elif valeur is None:
            erreur("REMISE_GLOBALE_INVALIDE")
        elif type_brut == "POURCENTAGE" and valeur > CENT_POUR_CENT:
            erreur("REMISE_GLOBALE_INVALIDE")
        else:
            type_remise = type_brut
            valeur_remise = valeur

    # --- acompte_pct
    acompte_brut = racine.get("acompte_pct", ABSENT)
    if acompte_brut is ABSENT or acompte_brut is None:
        acompte_pct: Decimal | None = Decimal("0")
    else:
        acompte_pct = chaine_decimale(acompte_brut, 2)
        if acompte_pct is not None and acompte_pct > CENT_POUR_CENT:
            acompte_pct = None
    if acompte_pct is None:
        erreur("ACOMPTE_INVALIDE")

    # --- montants de ligne (calculables dès que toutes les lignes sont valides)
    montants_lignes: list[Decimal] = []
    total_ht_brut = ZERO
    if lignes_tableau_non_vide and not erreur_de_ligne:
        for quantite, prix, remise_ligne, _ in lignes_valides:
            m = arrondi(quantite * prix * (CENT_POUR_CENT - remise_ligne) / CENT_POUR_CENT)
            montants_lignes.append(m)
            total_ht_brut += m

        # REMISE_SUPERIEURE_AU_TOTAL : indépendante de regime_tva et acompte_pct
        if type_remise == "MONTANT" and valeur_remise is not None and valeur_remise > total_ht_brut:
            erreur("REMISE_SUPERIEURE_AU_TOTAL")

    if erreurs:
        return {"erreurs": [{"code": c, "ligne": l} for c, l in erreurs]}, 1

    # --- calcul
    assert acompte_pct is not None
    bases: dict[str, Decimal] = {}
    for (_, _, _, taux), m in zip(lignes_valides, montants_lignes):
        bases[taux] = bases.get(taux, ZERO) + m
    taux_presents = sorted(bases, key=cle_taux)

    parts: dict[str, Decimal] = {t: ZERO for t in taux_presents}
    remise_totale = ZERO
    if type_remise == "POURCENTAGE":
        assert valeur_remise is not None
        for t in taux_presents:
            parts[t] = arrondi(bases[t] * valeur_remise / CENT_POUR_CENT)
        remise_totale = sum(parts.values(), ZERO)
    elif type_remise == "MONTANT":
        assert valeur_remise is not None
        if total_ht_brut != 0:
            for t in taux_presents:
                parts[t] = arrondi_division(valeur_remise * bases[t], total_ht_brut)
            ecart = valeur_remise - sum(parts.values(), ZERO)
            # base la plus élevée ; à égalité, taux le plus élevé
            cible = max(taux_presents, key=lambda t: (bases[t], cle_taux(t)))
            parts[cible] += ecart
        # total_ht_brut == 0 => M == 0 (sinon REMISE_SUPERIEURE_AU_TOTAL) : parts nulles
        remise_totale = valeur_remise

    total_ht_net = total_ht_brut - remise_totale

    ventilation = []
    total_tva = ZERO
    if not franchise:
        for t in taux_presents:
            base_nette = bases[t] - parts[t]
            tva = arrondi(base_nette * TAUX[t] / CENT_POUR_CENT)
            total_tva += tva
            ventilation.append({"taux": t, "base_ht": montant(base_nette), "montant_tva": montant(tva)})

    total_ttc = total_ht_net + total_tva
    acompte = arrondi(total_ttc * acompte_pct / CENT_POUR_CENT)
    solde = total_ttc - acompte

    sortie = {
        "ok": {
            "lignes": [{"montant_ht": montant(m)} for m in montants_lignes],
            "total_ht_brut": montant(total_ht_brut),
            "remise_globale": montant(remise_totale),
            "total_ht_net": montant(total_ht_net),
            "ventilation_tva": ventilation,
            "total_tva": montant(total_tva),
            "total_ttc": montant(total_ttc),
            "acompte": montant(acompte),
            "solde": montant(solde),
            "mention_tva": MENTION_FRANCHISE if franchise else None,
        }
    }
    return sortie, 0


def _refuser_constante(nom: str) -> object:
    # NaN / Infinity / -Infinity ne sont pas du JSON (RFC 8259), le module json les accepterait.
    raise ValueError(f"constante JSON non standard : {nom}")


def analyser(brut: bytes) -> object:
    """Retourne la racine JSON, ou lève ValueError si l'entrée n'est pas du JSON valide."""
    texte = brut.decode("utf-8")  # UnicodeDecodeError est une ValueError
    return json.loads(texte, parse_constant=_refuser_constante)


def main() -> int:
    brut = sys.stdin.buffer.read()
    try:
        racine = analyser(brut)
    except (ValueError, RecursionError):
        sortie, code = {"erreurs": [{"code": "JSON_INVALIDE", "ligne": None}]}, 1
    else:
        sortie, code = traiter(racine)
    sys.stdout.write(json.dumps(sortie, ensure_ascii=False) + "\n")
    sys.stdout.flush()
    return code


if __name__ == "__main__":
    sys.exit(main())
