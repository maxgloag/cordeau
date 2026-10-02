#!/usr/bin/env python3
"""Génère vecteurs.json — vecteurs de test cachés de l'expérience B (totaux d'un devis).

Provenance des attendus (aucun n'est produit par reference.py) :
- cas d'erreur : listes d'erreurs écrites à la main d'après la spec ;
- cas « main » (`attendu=` explicite) : calcul détaillé à la main, recopié dans `description` ;
- autres cas de succès : `calcul_independant` ci-dessous, une seconde implémentation écrite
  en arithmétique entière (centimes, millièmes, points de base) avec `fractions`, structurellement
  différente de reference.py (module decimal). Les cas « main » sont aussi recoupés avec elle.

reference.py est ensuite validée contre ces attendus par executer.py (triangulation).

Usage : python3 generer.py  (écrit vecteurs.json à côté du script)
"""

from __future__ import annotations

import json
import random
from fractions import Fraction
from pathlib import Path

ICI = Path(__file__).resolve().parent
MENTION = "TVA non applicable, art. 293 B du CGI"

# ---------------------------------------------------------------------------
# Calcul indépendant (entiers) — n'appelle pas reference.py
# ---------------------------------------------------------------------------
TAUX_DIXIEMES = {"0": 0, "5.5": 55, "10": 100, "20": 200}


def _ent(s: str, echelle: int) -> int:
    f = Fraction(s) * 10**echelle
    assert f.denominator == 1, (s, echelle)
    return int(f)


def _rhu(num: int, den: int) -> int:
    """Arrondi demi-supérieur de num/den (den > 0, num >= 0 dans tous les cas atteints)."""
    assert den > 0 and num >= 0, (num, den)
    return (2 * num + den) // (2 * den)


def _fmt(c: int) -> str:
    signe = "-" if c < 0 else ""
    c = abs(c)
    return f"{signe}{c // 100}.{c % 100:02d}"


def calcul_independant(d: dict) -> dict:
    franchise = d["regime_tva"] == "FRANCHISE_EN_BASE"
    montants: list[int] = []
    bases: dict[str, int] = {}
    for ligne in d["lignes"]:
        q = _ent(ligne["quantite"], 3)  # millièmes d'unité
        p = _ent(ligne["prix_unitaire_ht"], 2)  # centimes
        r_brut = ligne.get("remise_pct")
        r = _ent(r_brut, 2) if r_brut is not None else 0  # centièmes de pourcent
        c = _rhu(q * p * (10000 - r), 1000 * 10000)
        montants.append(c)
        bases[ligne["taux_tva"]] = bases.get(ligne["taux_tva"], 0) + c
    brut = sum(montants)
    ordre = sorted(bases, key=lambda t: TAUX_DIXIEMES[t])
    parts = {t: 0 for t in ordre}
    rg = d.get("remise_globale")
    remise = 0
    if rg is not None:
        v = _ent(rg["valeur"], 2)
        if rg["type"] == "POURCENTAGE":
            for t in ordre:
                parts[t] = _rhu(bases[t] * v, 10000)
            remise = sum(parts.values())
        else:
            assert v <= brut
            if brut > 0:
                for t in ordre:
                    parts[t] = _rhu(v * bases[t], brut)
                ecart = v - sum(parts.values())
                cible = max(ordre, key=lambda t: (bases[t], TAUX_DIXIEMES[t]))
                parts[cible] += ecart
            remise = v
    net = brut - remise
    ventilation = []
    tva_totale = 0
    if not franchise:
        for t in ordre:
            bn = bases[t] - parts[t]
            tva = _rhu(bn * TAUX_DIXIEMES[t], 1000)
            tva_totale += tva
            ventilation.append({"taux": t, "base_ht": _fmt(bn), "montant_tva": _fmt(tva)})
    ttc = net + tva_totale
    a_brut = d.get("acompte_pct")
    a = _ent(a_brut, 2) if a_brut is not None else 0
    acompte = _rhu(ttc * a, 10000)
    return {
        "ok": {
            "lignes": [{"montant_ht": _fmt(m)} for m in montants],
            "total_ht_brut": _fmt(brut),
            "remise_globale": _fmt(remise),
            "total_ht_net": _fmt(net),
            "ventilation_tva": ventilation,
            "total_tva": _fmt(tva_totale),
            "total_ttc": _fmt(ttc),
            "acompte": _fmt(acompte),
            "solde": _fmt(ttc - acompte),
            "mention_tva": MENTION if franchise else None,
        }
    }


# ---------------------------------------------------------------------------
# Constructeurs
# ---------------------------------------------------------------------------
VECTEURS: list[dict] = []
IDS: set[str] = set()


def L(q: str, p: str, taux: str, unite: str = "U", designation: str = "Ligne", remise=..., **extra) -> dict:
    ligne = {"designation": designation, "quantite": q, "unite": unite, "prix_unitaire_ht": p, "taux_tva": taux}
    if remise is not ...:
        ligne["remise_pct"] = remise
    ligne.update(extra)
    return ligne


def D(lignes, regime="NORMAL", remise=..., acompte=..., **extra) -> dict:
    d = {"regime_tva": regime, "lignes": lignes}
    if remise is not ...:
        d["remise_globale"] = remise
    if acompte is not ...:
        d["acompte_pct"] = acompte
    d.update(extra)
    return d


def PCT(v: str) -> dict:
    return {"type": "POURCENTAGE", "valeur": v}


def MNT(v: str) -> dict:
    return {"type": "MONTANT", "valeur": v}


def OK(lignes, brut, remise, net, vent, tva, ttc, acompte, solde, mention=None) -> dict:
    return {
        "ok": {
            "lignes": [{"montant_ht": m} for m in lignes],
            "total_ht_brut": brut,
            "remise_globale": remise,
            "total_ht_net": net,
            "ventilation_tva": [{"taux": t, "base_ht": b, "montant_tva": v} for t, b, v in vent],
            "total_tva": tva,
            "total_ttc": ttc,
            "acompte": acompte,
            "solde": solde,
            "mention_tva": mention,
        }
    }


def ajouter(id_, categorie, description, entree=None, brute=None, attendu=None, erreurs=None, ambigu=False):
    assert id_ not in IDS, f"id dupliqué : {id_}"
    IDS.add(id_)
    if brute is None:
        brute = json.dumps(entree, ensure_ascii=False)
    verif = "auto"
    if erreurs is not None:
        attendu = {"erreurs": [{"code": c, "ligne": l} for c, l in erreurs]}
        code = 1
        verif = "main"
    elif attendu is not None:
        code = 0
        verif = "main"
        calc = calcul_independant(entree)
        if calc != attendu:
            raise SystemExit(f"[{id_}] calcul à la main != calcul indépendant\nmain : {attendu}\ncalc : {calc}")
    else:
        attendu = calcul_independant(entree)
        code = 0
    VECTEURS.append(
        {
            "id": id_,
            "categorie": categorie,
            "description": description,
            "entree_brute": brute,
            "attendu": attendu,
            "code_sortie": code,
            "ambigu": ambigu,
            "attendu_source": "calcul à la main" if verif == "main" else "calcul indépendant (entiers)",
        }
    )


def err(*codes_lignes):
    """err(("CODE", ligne), ...) ou err("CODE") pour une erreur globale."""
    out = []
    for x in codes_lignes:
        out.append((x, None) if isinstance(x, str) else x)
    return out


JSON_INVALIDE = [("JSON_INVALIDE", None)]

# Ligne valide de base réutilisée par les cas d'erreur
LV = L("2", "10.00", "20", designation="Ligne valide")


# ===========================================================================
# 1. Exemple de la spec
# ===========================================================================
ajouter(
    "spec-001",
    "exemple_spec",
    "Exemple vérifié de la spec, recopié tel quel. 12,5 × 45,90 = 573,75 ; × 0,95 = 545,0625 → 545,06. "
    "Remise 3 % : 16,3518 → 16,35 ; base nette 528,71 ; TVA 10 % 52,871 → 52,87 ; TTC 581,58 ; "
    "acompte 30 % 174,474 → 174,47 ; solde 407,11.",
    D([L("12.5", "45.90", "10", "M2", "Pose carrelage sol", remise="5")], remise=PCT("3"), acompte="30"),
    attendu=OK(["545.06"], "545.06", "16.35", "528.71", [("10", "528.71", "52.87")], "52.87", "581.58", "174.47", "407.11"),
)

# ===========================================================================
# 2. Nominal mono-taux / unités
# ===========================================================================
cat = "nominal_mono_taux"
ajouter("nom-001", cat, "Unité U, TVA 20 % : 3 × 12,50 = 37,50 ; TVA 7,50 ; TTC 45,00.",
        D([L("3", "12.50", "20", "U")]),
        attendu=OK(["37.50"], "37.50", "0.00", "37.50", [("20", "37.50", "7.50")], "7.50", "45.00", "0.00", "45.00"))
ajouter("nom-002", cat, "Unité M2, TVA 10 %, sans remise : 12,5 × 45,90 = 573,75 ; TVA 57,375 → 57,38 ; TTC 631,13.",
        D([L("12.5", "45.90", "10", "M2")]),
        attendu=OK(["573.75"], "573.75", "0.00", "573.75", [("10", "573.75", "57.38")], "57.38", "631.13", "0.00", "631.13"))
ajouter("nom-003", cat, "Unité ML, TVA 5,5 % : 7,25 × 18,40 = 133,40 ; TVA 7,337 → 7,34 ; TTC 140,74.",
        D([L("7.25", "18.40", "5.5", "ML")]),
        attendu=OK(["133.40"], "133.40", "0.00", "133.40", [("5.5", "133.40", "7.34")], "7.34", "140.74", "0.00", "140.74"))
ajouter("nom-004", cat, "Unité M3, TVA 20 % : 2,375 × 89 = 211,375 → 211,38 (demi-supérieur) ; TVA 42,276 → 42,28 ; TTC 253,66.",
        D([L("2.375", "89", "20", "M3")]),
        attendu=OK(["211.38"], "211.38", "0.00", "211.38", [("20", "211.38", "42.28")], "42.28", "253.66", "0.00", "253.66"))
ajouter("nom-005", cat, "Unité H, TVA 10 % : 7,5 × 42 = 315,00 ; TVA 31,50 ; TTC 346,50.",
        D([L("7.5", "42", "10", "H")]),
        attendu=OK(["315.00"], "315.00", "0.00", "315.00", [("10", "315.00", "31.50")], "31.50", "346.50", "0.00", "346.50"))
ajouter("nom-006", cat, "Unité FORFAIT, TVA 20 %, acompte 30 % : 1 250,00 ; TVA 250,00 ; TTC 1 500,00 ; acompte 450,00 ; solde 1 050,00.",
        D([L("1", "1250.00", "20", "FORFAIT")], acompte="30"),
        attendu=OK(["1250.00"], "1250.00", "0.00", "1250.00", [("20", "1250.00", "250.00")], "250.00", "1500.00", "450.00", "1050.00"))
ajouter("nom-007", cat, "Plusieurs lignes même taux, unités mélangées, acompte 40 %.",
        D([L("3", "45.00", "10", "H"), L("18.75", "32.40", "10", "M2"), L("1", "120", "10", "FORFAIT"), L("4.2", "11.99", "10", "ML")], acompte="40"))
ajouter("nom-008", cat, "Ligne à prix 0 (fourniture offerte) à côté d'une ligne normale.",
        D([L("1", "0", "20", "FORFAIT", "Déplacement offert"), L("2", "35.50", "20", "H")]))
ajouter("nom-009", cat, "Zéros non significatifs valides : quantite \"007\", prix \"00.50\" → 7 × 0,50 = 3,50.",
        D([L("007", "00.50", "20")]),
        attendu=OK(["3.50"], "3.50", "0.00", "3.50", [("20", "3.50", "0.70")], "0.70", "4.20", "0.00", "4.20"))
_d = D([L("12.5", "45.90", "10", "M2", "Pose carrelage sol", remise="5")], remise=PCT("3"), acompte="30")
ajouter("nom-010", cat, "Exemple de la spec envoyé en JSON indenté avec espaces, tabulations et saut de ligne final (JSON valide).",
        _d, brute=json.dumps(_d, indent="\t", ensure_ascii=False) + "\n\n")
ajouter("nom-011", cat, "Clés racine et de ligne dans un ordre inhabituel (l'ordre des clés JSON est sans importance).",
        _d, brute='{"acompte_pct":"30","remise_globale":{"valeur":"3","type":"POURCENTAGE"},"lignes":[{"remise_pct":"5","taux_tva":"10",'
                  '"prix_unitaire_ht":"45.90","unite":"M2","quantite":"12.5","designation":"Pose carrelage sol"}],"regime_tva":"NORMAL"}')
ajouter("nom-012", cat, "Désignation avec caractères non ASCII et échappements JSON (\\u00e9, \\n) : valide.",
        D([L("2", "19.90", "20", designation="Plinthe chêne\n(échappée)")]),
        brute='{"regime_tva":"NORMAL","lignes":[{"designation":"Plinthe ch\\u00eane\\n(\\u00e9chapp\\u00e9e)","quantite":"2","unite":"U","prix_unitaire_ht":"19.90","taux_tva":"20"}]}')
ajouter("nom-013", cat, "Chaînes décimales écrites avec des échappements JSON (\"\\u0031\\u0032\" = \"12\") : après décodage JSON ce sont des chaînes décimales valides.",
        D([L("12", "10.5", "20")]),
        brute='{"regime_tva":"NORMAL","lignes":[{"designation":"x","quantite":"\\u0031\\u0032","unite":"U","prix_unitaire_ht":"10.5","taux_tva":"20"}]}')
ajouter("nom-014", cat, "Taux 0 en régime NORMAL seul : ventilation présente avec TVA nulle, mention null.",
        D([L("10", "85.00", "0", "H", "Sous-traitance autoliquidée")]),
        attendu=OK(["850.00"], "850.00", "0.00", "850.00", [("0", "850.00", "0.00")], "0.00", "850.00", "0.00", "850.00"))

# ===========================================================================
# 3. Multi-taux et tri de la ventilation
# ===========================================================================
cat = "nominal_multi_taux"
ajouter("mul-001", cat, "Deux taux (10 et 20).",
        D([L("3", "45", "10", "H"), L("1", "250", "20", "FORFAIT")]),
        attendu=OK(["135.00", "250.00"], "385.00", "0.00", "385.00", [("10", "135.00", "13.50"), ("20", "250.00", "50.00")], "63.50", "448.50", "0.00", "448.50"))
ajouter("mul-002", cat, "Trois taux (5,5 / 10 / 20) avec plusieurs lignes par taux.",
        D([L("10", "12.30", "5.5", "M2"), L("2", "48", "10", "H"), L("3.5", "22.10", "5.5", "ML"), L("1", "99.99", "20"), L("6", "8.45", "10")]))
ajouter("mul-003", cat, "Quatre taux saisis dans l'ordre 20, 5.5, 10, 0 : ventilation triée par taux NUMÉRIQUE croissant 0, 5.5, 10, 20 "
        "(un tri lexicographique donnerait 0, 10, 20, 5.5). Lignes : 100 ; 200 ; 300 ; 400.",
        D([L("1", "100", "20"), L("1", "200", "5.5"), L("1", "300", "10"), L("1", "400", "0")]),
        attendu=OK(["100.00", "200.00", "300.00", "400.00"], "1000.00", "0.00", "1000.00",
                   [("0", "400.00", "0.00"), ("5.5", "200.00", "11.00"), ("10", "300.00", "30.00"), ("20", "100.00", "20.00")],
                   "61.00", "1061.00", "0.00", "1061.00"))
ajouter("mul-004", cat, "Taux saisis 10, 20, 5.5 : ventilation 5.5, 10, 20 (piège du tri lexicographique qui mettrait 5.5 en dernier).",
        D([L("2", "50", "10"), L("1", "80", "20"), L("4", "25", "5.5")]))
ajouter("mul-005", cat, "Lignes de taux entrelacés + remise globale % + acompte.",
        D([L("1.5", "60", "20", "H"), L("12", "15.75", "10", "M2", remise="10"), L("2", "33.33", "20"), L("8", "4.99", "5.5", "U"),
           L("1", "750", "10", "FORFAIT", remise="2.5")], remise=PCT("4"), acompte="25"))
ajouter("mul-006", cat, "Taux 0 et 20 en régime NORMAL : le taux 0 apparaît dans la ventilation avec TVA 0.00.",
        D([L("1", "1000", "0", "FORFAIT"), L("1", "500", "20", "FORFAIT")]),
        attendu=OK(["1000.00", "500.00"], "1500.00", "0.00", "1500.00", [("0", "1000.00", "0.00"), ("20", "500.00", "100.00")], "100.00", "1600.00", "0.00", "1600.00"))
ajouter("mul-007", cat, "Taux présent uniquement via une ligne à 0 € : il apparaît quand même dans la ventilation (base 0.00, TVA 0.00).",
        D([L("1", "0", "5.5", designation="Gratuit"), L("2", "40", "20")]),
        attendu=OK(["0.00", "80.00"], "80.00", "0.00", "80.00", [("5.5", "0.00", "0.00"), ("20", "80.00", "16.00")], "16.00", "96.00", "0.00", "96.00"))
ajouter("mul-008", cat, "Dix lignes sur 4 taux, remise MONTANT et acompte.",
        D([L(str(i + 1), f"{7 * i + 3}.{(13 * i) % 100:02d}", ["0", "5.5", "10", "20"][i % 4], ["U", "M2", "ML", "M3", "H"][i % 5]) for i in range(10)],
          remise=MNT("37.21"), acompte="15.5"))

# ===========================================================================
# 4. Franchise en base
# ===========================================================================
cat = "franchise"
ajouter("fra-001", cat, "FRANCHISE_EN_BASE, une ligne à 0 : ventilation vide, TVA 0, mention exacte. 4 × 37,50 = 150,00.",
        D([L("4", "37.50", "0", "H")], regime="FRANCHISE_EN_BASE"),
        attendu=OK(["150.00"], "150.00", "0.00", "150.00", [], "0.00", "150.00", "0.00", "150.00", MENTION))
ajouter("fra-002", cat, "FRANCHISE, plusieurs lignes, remise globale % et acompte.",
        D([L("12.5", "22.40", "0", "M2"), L("3", "45", "0", "H", remise="10"), L("1", "89.90", "0", "FORFAIT")],
          regime="FRANCHISE_EN_BASE", remise=PCT("5"), acompte="30"))
ajouter("fra-003", cat, "FRANCHISE + remise MONTANT (une seule base « 0 » : part = M). 300 + 150 = 450 ; M = 25,50 ; net 424,50 ; acompte 20 % = 84,90 ; solde 339,60.",
        D([L("2", "150", "0"), L("1", "150", "0")], regime="FRANCHISE_EN_BASE", remise=MNT("25.50"), acompte="20"),
        attendu=OK(["300.00", "150.00"], "450.00", "25.50", "424.50", [], "0.00", "424.50", "84.90", "339.60", MENTION))
ajouter("fra-004", cat, "FRANCHISE avec une ligne à 20 % : FRANCHISE_AVEC_TVA sur la ligne 0.",
        D([L("1", "100", "20")], regime="FRANCHISE_EN_BASE"), erreurs=[("FRANCHISE_AVEC_TVA", 0)])
ajouter("fra-005", cat, "FRANCHISE, lignes taux 0 / 5.5 / 0 / 10 : FRANCHISE_AVEC_TVA sur les lignes 1 et 3.",
        D([L("1", "10", "0"), L("1", "10", "5.5"), L("1", "10", "0"), L("1", "10", "10")], regime="FRANCHISE_EN_BASE"),
        erreurs=[("FRANCHISE_AVEC_TVA", 1), ("FRANCHISE_AVEC_TVA", 3)])
ajouter("fra-006", cat, "FRANCHISE avec un taux invalide \"7\" : TAUX_INVALIDE seulement (FRANCHISE_AVEC_TVA exige un taux VALIDE ≠ 0).",
        D([L("1", "10", "7")], regime="FRANCHISE_EN_BASE"), erreurs=[("TAUX_INVALIDE", 0)])
ajouter("fra-007", cat, "FRANCHISE avec taux null : TAUX_INVALIDE seulement.",
        D([L("1", "10", None)], regime="FRANCHISE_EN_BASE"), erreurs=[("TAUX_INVALIDE", 0)])
ajouter("fra-008", cat, "FRANCHISE, ligne taux 20 avec remise de ligne invalide : FRANCHISE_AVEC_TVA et REMISE_LIGNE_INVALIDE sur la même ligne.",
        D([L("1", "10", "20", remise="150")], regime="FRANCHISE_EN_BASE"),
        erreurs=[("FRANCHISE_AVEC_TVA", 0), ("REMISE_LIGNE_INVALIDE", 0)])
ajouter("fra-009", cat, "Régime invalide + ligne à 20 % : REGIME_INVALIDE seulement (pas de FRANCHISE_AVEC_TVA sans régime FRANCHISE valide).",
        D([L("1", "10", "20")], regime="FRANCHISE"), erreurs=[("REGIME_INVALIDE", None)])
ajouter("fra-010", cat, "FRANCHISE, ligne à 20 % avec quantité invalide : FRANCHISE_AVEC_TVA et QUANTITE_INVALIDE.",
        D([L("0", "10", "20")], regime="FRANCHISE_EN_BASE"),
        erreurs=[("FRANCHISE_AVEC_TVA", 0), ("QUANTITE_INVALIDE", 0)])
ajouter("fra-011", cat, "Même lignes à taux 0 en NORMAL (cf fra-001) : ventilation non vide et mention null.",
        D([L("4", "37.50", "0", "H")], regime="NORMAL"),
        attendu=OK(["150.00"], "150.00", "0.00", "150.00", [("0", "150.00", "0.00")], "0.00", "150.00", "0.00", "150.00"))

# ===========================================================================
# 5. Arrondis au niveau ligne (frontières x.xx5, pièges du flottant binaire)
# ===========================================================================
cat = "arrondi_ligne"
ajouter("arl-001", cat, "Piège flottant 1.005 : quantité 1,005 × 1 = 1,005 → 1,01 (en float : 1.00). TVA 20 % 0,202 → 0,20 ; TTC 1,21.",
        D([L("1.005", "1", "20")]),
        attendu=OK(["1.01"], "1.01", "0.00", "1.01", [("20", "1.01", "0.20")], "0.20", "1.21", "0.00", "1.21"))
ajouter("arl-002", cat, "Piège flottant 2.675 : 2,675 × 1,00 → 2,68 (float : 2.67). TVA 10 % 0,268 → 0,27 ; TTC 2,95 ; acompte 50 % 1,475 → 1,48 ; solde 1,47.",
        D([L("2.675", "1.00", "10")], acompte="50"),
        attendu=OK(["2.68"], "2.68", "0.00", "2.68", [("10", "2.68", "0.27")], "0.27", "2.95", "1.48", "1.47"))
ajouter("arl-003", cat, "Piège flottant 0.285 : 0,285 × 1 → 0,29 (float : 0.28) ; taux 0 ; TTC 0,29 ; acompte 50 % = 0,145 → 0,15 (float : 0.14) ; solde 0,14.",
        D([L("0.285", "1", "0")], acompte="50"),
        attendu=OK(["0.29"], "0.29", "0.00", "0.29", [("0", "0.29", "0.00")], "0.00", "0.29", "0.15", "0.14"))
ajouter("arl-004", cat, "Piège flottant 1.015 : 1,015 × 1 → 1,02 (float : 1.01). TVA 20 % 0,204 → 0,20 ; TTC 1,22.",
        D([L("1.015", "1", "20")]),
        attendu=OK(["1.02"], "1.02", "0.00", "1.02", [("20", "1.02", "0.20")], "0.20", "1.22", "0.00", "1.22"))
ajouter("arl-005", cat, "0,5 × 2,01 = 1,005 → 1,01 (float 0.5*2.01 = 1.00499… → 1.00). TVA 20 % 0,202 → 0,20 ; TTC 1,21.",
        D([L("0.5", "2.01", "20")]),
        attendu=OK(["1.01"], "1.01", "0.00", "1.01", [("20", "1.01", "0.20")], "0.20", "1.21", "0.00", "1.21"))
ajouter("arl-006", cat, "8,325 × 1 → 8,33 (float : 8.32). TVA 10 % 0,833 → 0,83 ; TTC 9,16.",
        D([L("8.325", "1", "10")]),
        attendu=OK(["8.33"], "8.33", "0.00", "8.33", [("10", "8.33", "0.83")], "0.83", "9.16", "0.00", "9.16"))
ajouter("arl-007", cat, "0,3 × 3,35 = 1,005 → 1,01 (float : 1.00). TVA 5,5 % : 1,01 × 0,055 = 0,05555 → 0,06 ; TTC 1,07.",
        D([L("0.3", "3.35", "5.5")]),
        attendu=OK(["1.01"], "1.01", "0.00", "1.01", [("5.5", "1.01", "0.06")], "0.06", "1.07", "0.00", "1.07"))
ajouter("arl-008", cat, "2,5 × 0,41 = 1,025 → 1,03 (float : 1.02). TVA 20 % 0,206 → 0,21 ; TTC 1,24.",
        D([L("2.5", "0.41", "20")]),
        attendu=OK(["1.03"], "1.03", "0.00", "1.03", [("20", "1.03", "0.21")], "0.21", "1.24", "0.00", "1.24"))
ajouter("arl-009", cat, "0,7 × 1,15 = 0,805 → 0,81 (float 0.8049999… → 0.80). TVA 20 % 0,162 → 0,16 ; TTC 0,97.",
        D([L("0.7", "1.15", "20")]),
        attendu=OK(["0.81"], "0.81", "0.00", "0.81", [("20", "0.81", "0.16")], "0.16", "0.97", "0.00", "0.97"))
ajouter("arl-010", cat, "1,1 × 1,15 = 1,265 → 1,27 (float : 1.26). TVA 20 % 0,254 → 0,25 ; TTC 1,52.",
        D([L("1.1", "1.15", "20")]),
        attendu=OK(["1.27"], "1.27", "0.00", "1.27", [("20", "1.27", "0.25")], "0.25", "1.52", "0.00", "1.52"))
ajouter("arl-011", cat, "Arrondi PAR LIGNE puis somme : 2 lignes 0,005 × 1,00 = 0,005 → 0,01 chacune ; brut 0,02 (la somme non arrondie 0,010 donnerait 0,01). "
        "TVA 20 % 0,004 → 0,00 ; TTC 0,02.",
        D([L("0.005", "1.00", "20"), L("0.005", "1.00", "20")]),
        attendu=OK(["0.01", "0.01"], "0.02", "0.00", "0.02", [("20", "0.02", "0.00")], "0.00", "0.02", "0.00", "0.02"))
ajouter("arl-012", cat, "Juste sous la frontière : 0,001 × 4,99 = 0,00499 → 0,00. Tout est nul.",
        D([L("0.001", "4.99", "20")]),
        attendu=OK(["0.00"], "0.00", "0.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("arl-013", cat, "Frontière exacte : 0,001 × 5,00 = 0,005 → 0,01. TVA 20 % 0,002 → 0,00 ; TTC 0,01.",
        D([L("0.001", "5.00", "20")]),
        attendu=OK(["0.01"], "0.01", "0.00", "0.01", [("20", "0.01", "0.00")], "0.00", "0.01", "0.00", "0.01"))
ajouter("arl-014", cat, "0,125 × 0,04 = 0,005 → 0,01 ; taux 10 % : 0,001 → 0,00 ; TTC 0,01.",
        D([L("0.125", "0.04", "10")]),
        attendu=OK(["0.01"], "0.01", "0.00", "0.01", [("10", "0.01", "0.00")], "0.00", "0.01", "0.00", "0.01"))
ajouter("arl-015", cat, "999,999 × 0,01 = 9,99999 → 10,00 ; TVA 20 % 2,00 ; TTC 12,00.",
        D([L("999.999", "0.01", "20")]),
        attendu=OK(["10.00"], "10.00", "0.00", "10.00", [("20", "10.00", "2.00")], "2.00", "12.00", "0.00", "12.00"))
ajouter("arl-016", cat, "Série de lignes à frontières x.xx5 sur 3 taux (arrondis par ligne, cumul).",
        D([L("1.005", "1", "20"), L("2.675", "1", "10"), L("0.285", "1", "5.5"), L("1.015", "1", "20"), L("8.325", "1", "10"), L("0.3", "3.35", "5.5")]))

# ===========================================================================
# 6. Arrondis avec remise de ligne
# ===========================================================================
cat = "arrondi_remise_ligne"
ajouter("arr-001", cat, "1 × 2,03 × (1 − 50/100) = 1,015 → 1,02 (float : 1.01). TVA 5,5 % : 1,02 × 0,055 = 0,0561 → 0,06 ; TTC 1,08.",
        D([L("1", "2.03", "5.5", remise="50")]),
        attendu=OK(["1.02"], "1.02", "0.00", "1.02", [("5.5", "1.02", "0.06")], "0.06", "1.08", "0.00", "1.08"))
ajouter("arr-002", cat, "1 × 10,04 × 0,875 (remise 12,5 %) = 8,785 → 8,79. TVA 20 % 1,758 → 1,76 ; TTC 10,55.",
        D([L("1", "10.04", "20", remise="12.5")]),
        attendu=OK(["8.79"], "8.79", "0.00", "8.79", [("20", "8.79", "1.76")], "1.76", "10.55", "0.00", "10.55"))
ajouter("arr-003", cat, "1 × 2,01 × 0,5 = 1,005 → 1,01 (float : 1.00). TVA 10 % 0,101 → 0,10 ; TTC 1,11.",
        D([L("1", "2.01", "10", remise="50")]),
        attendu=OK(["1.01"], "1.01", "0.00", "1.01", [("10", "1.01", "0.10")], "0.10", "1.11", "0.00", "1.11"))
ajouter("arr-004", cat, "3 × 1,15 × 0,9 = 3,105 → 3,11 (float : 3.10). TVA 20 % 0,622 → 0,62 ; TTC 3,73.",
        D([L("3", "1.15", "20", remise="10")]),
        attendu=OK(["3.11"], "3.11", "0.00", "3.11", [("20", "3.11", "0.62")], "0.62", "3.73", "0.00", "3.73"))
ajouter("arr-005", cat, "1 × 0,10 × 0,05 (remise 95 %) = 0,005 → 0,01 ; TVA 20 % 0,002 → 0,00 ; TTC 0,01.",
        D([L("1", "0.10", "20", remise="95")]),
        attendu=OK(["0.01"], "0.01", "0.00", "0.01", [("20", "0.01", "0.00")], "0.00", "0.01", "0.00", "0.01"))
ajouter("arr-006", cat, "1 × 0,10 × 0,0499 (remise 95,01 %) = 0,00499 → 0,00.",
        D([L("1", "0.10", "20", remise="95.01")]),
        attendu=OK(["0.00"], "0.00", "0.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("arr-007", cat, "7 × 13,50 = 94,5 ; × 0,6667 (remise 33,33 %) = 63,00315 → 63,00 ; TVA 10 % 6,30 ; TTC 69,30.",
        D([L("7", "13.50", "10", remise="33.33")]),
        attendu=OK(["63.00"], "63.00", "0.00", "63.00", [("10", "63.00", "6.30")], "6.30", "69.30", "0.00", "69.30"))
ajouter("arr-008", cat, "Quantité à 3 décimales + remise fractionnaire : 2,333 × 19,99 × 0,925.",
        D([L("2.333", "19.99", "10", "M2", remise="7.5")]))
ajouter("arr-009", cat, "UN SEUL arrondi sur q × p × (1 − r) : ligne 0 : 0,5 × 2,01 × 0,5 = 0,5025 → 0,50 (double arrondi : q×p = 1,005 → 1,01 puis × 0,5 = 0,505 → 0,51 FAUX ; "
        "prix net unitaire d'abord : 2,01 × 0,5 = 1,005 → 1,01 × 0,5 → 0,51 FAUX). Ligne 1 : 3 × 0,35 × 0,5 = 0,525 → 0,53 (prix net unitaire arrondi d'abord : "
        "0,175 → 0,18 × 3 = 0,54 FAUX). Brut 1,03 ; TVA 20 % 0,206 → 0,21 ; TTC 1,24.",
        D([L("0.5", "2.01", "20", remise="50"), L("3", "0.35", "20", remise="50")]),
        attendu=OK(["0.50", "0.53"], "1.03", "0.00", "1.03", [("20", "1.03", "0.21")], "0.21", "1.24", "0.00", "1.24"))
ajouter("arr-010", cat, "Arrondi unique sur q × p × (1 − r) : 0,333 × 4,50 × 0,9 = 1,34865 → 1,35 ; "
        "0,111 × 4,50 × 0,7 = 0,34965 → 0,35. TVA 20 % sur 1,70 = 0,34 ; TTC 2,04.",
        D([L("0.333", "4.50", "20", remise="10"), L("0.111", "4.50", "20", remise="30")]),
        attendu=OK(["1.35", "0.35"], "1.70", "0.00", "1.70", [("20", "1.70", "0.34")], "0.34", "2.04", "0.00", "2.04"))

# ===========================================================================
# 7. Remise de ligne (valeurs limites valides)
# ===========================================================================
cat = "remise_ligne"
ajouter("rel-001", cat, "remise_pct \"0\" explicite : identique à absente. 2 × 50 = 100 ; TVA 20 ; TTC 120.",
        D([L("2", "50", "20", remise="0")]),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("rel-002", cat, "remise_pct \"100\" : ligne à 0.00, l'autre ligne inchangée. 0 + 100 ; TVA 20 ; TTC 120.",
        D([L("1", "500", "20", remise="100"), L("2", "50", "20")]),
        attendu=OK(["0.00", "100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("rel-003", cat, "remise_pct \"100.00\" sur l'unique ligne : tout est nul, acompte 30 % = 0.00.",
        D([L("3", "75.25", "10", remise="100.00")], acompte="30"),
        attendu=OK(["0.00"], "0.00", "0.00", "0.00", [("10", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rel-004", cat, "remise_pct null : équivaut à absente. 2 × 50 = 100.",
        D([L("2", "50", "20", remise=None)]),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("rel-005", cat, "remise_pct \"0.01\" sur 1000 → 999,90.",
        D([L("1", "1000", "20", remise="0.01")]),
        attendu=OK(["999.90"], "999.90", "0.00", "999.90", [("20", "999.90", "199.98")], "199.98", "1199.88", "0.00", "1199.88"))
ajouter("rel-006", cat, "remise_pct \"99.99\" sur 1000 → 0,10 ; TVA 20 % 0,02 ; TTC 0,12.",
        D([L("1", "1000", "20", remise="99.99")]),
        attendu=OK(["0.10"], "0.10", "0.00", "0.10", [("20", "0.10", "0.02")], "0.02", "0.12", "0.00", "0.12"))
ajouter("rel-007", cat, "remise_pct \"5.5\" : 12 × 37,80 = 453,60 × 0,945 = 428,652 → 428,65 ; TVA 10 % 42,865 → 42,87 ; TTC 471,52.",
        D([L("12", "37.80", "10", remise="5.5")]),
        attendu=OK(["428.65"], "428.65", "0.00", "428.65", [("10", "428.65", "42.87")], "42.87", "471.52", "0.00", "471.52"))
ajouter("rel-008", cat, "remise_pct \"000.50\" (zéros de tête, 2 décimales) valide : 100 × 0,995 = 99,50.",
        D([L("1", "100", "20", remise="000.50")]),
        attendu=OK(["99.50"], "99.50", "0.00", "99.50", [("20", "99.50", "19.90")], "19.90", "119.40", "0.00", "119.40"))
ajouter("rel-009", cat, "Remises de ligne variées sur plusieurs lignes et taux.",
        D([L("4", "125.40", "20", remise="15"), L("2.5", "18", "10", remise="0"), L("1", "999.99", "5.5", remise="33.33"), L("10", "3.21", "20")]))

# ===========================================================================
# 8. Remise globale POURCENTAGE
# ===========================================================================
cat = "remise_globale_pourcentage"
ajouter("rgp-001", cat, "Piège flottant sur la remise : base 10 % = 33,50 ; 3 % → 1,005 → 1,01 (float : 1.00). Base nette 32,49 ; TVA 3,249 → 3,25 ; "
        "TTC 35,74 ; acompte 10 % 3,574 → 3,57 ; solde 32,17.",
        D([L("1", "33.50", "10")], remise=PCT("3"), acompte="10"),
        attendu=OK(["33.50"], "33.50", "1.01", "32.49", [("10", "32.49", "3.25")], "3.25", "35.74", "3.57", "32.17"))
ajouter("rgp-002", cat, "Arrondi de la remise PAR BASE : bases 10 % et 20 % de 10,10 ; 5 % → 0,505 → 0,51 chacune ; remise 1,02 "
        "(une remise globale arrondie une fois donnerait 20,20 × 5 % = 1,01). Bases nettes 9,59 ; TVA 0,959 → 0,96 et 1,918 → 1,92 ; "
        "total TVA 2,88 ; net 19,18 ; TTC 22,06.",
        D([L("1", "10.10", "10"), L("1", "10.10", "20")], remise=PCT("5")),
        attendu=OK(["10.10", "10.10"], "20.20", "1.02", "19.18", [("10", "9.59", "0.96"), ("20", "9.59", "1.92")], "2.88", "22.06", "0.00", "22.06"))
ajouter("rgp-003", cat, "2,5 % sur bases 10 % = 200,00 et 20 % = 150,10 : 5,00 et 3,7525 → 3,75 ; remise 8,75 ; bases nettes 195,00 / 146,35 ; "
        "TVA 19,50 / 29,27 = 48,77 ; net 341,35 ; TTC 390,12 ; acompte 40 % 156,048 → 156,05 ; solde 234,07.",
        D([L("1", "200.00", "10"), L("1", "150.10", "20")], remise=PCT("2.5"), acompte="40"),
        attendu=OK(["200.00", "150.10"], "350.10", "8.75", "341.35", [("10", "195.00", "19.50"), ("20", "146.35", "29.27")], "48.77", "390.12", "156.05", "234.07"))
ajouter("rgp-004", cat, "Remise POURCENTAGE \"0\" : remise 0.00, rien ne change (multi-taux).",
        D([L("3", "45", "10", "H"), L("1", "250", "20", "FORFAIT")], remise=PCT("0")),
        attendu=OK(["135.00", "250.00"], "385.00", "0.00", "385.00", [("10", "135.00", "13.50"), ("20", "250.00", "50.00")], "63.50", "448.50", "0.00", "448.50"))
ajouter("rgp-005", cat, "Remise POURCENTAGE \"100\" multi-taux : bases nettes nulles (toujours listées), TVA 0, TTC 0, acompte 50 % = 0.",
        D([L("1", "123.45", "10"), L("1", "67.89", "20")], remise=PCT("100"), acompte="50"),
        attendu=OK(["123.45", "67.89"], "191.34", "191.34", "0.00", [("10", "0.00", "0.00"), ("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgp-006", cat, "Remise POURCENTAGE \"100.00\" mono-taux.",
        D([L("2", "19.99", "5.5")], remise=PCT("100.00")),
        attendu=OK(["39.98"], "39.98", "39.98", "0.00", [("5.5", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgp-007", cat, "Remise POURCENTAGE fractionnaire 12,75 % sur 3 taux.",
        D([L("14.5", "38.90", "10", "M2"), L("2", "412.37", "20"), L("6", "17.25", "5.5", "ML")], remise=PCT("12.75"), acompte="35"))
ajouter("rgp-008", cat, "0,01 % : base 10 % = 50,00 → 0,005 → 0,01 ; base 20 % = 49,99 → 0,004999 → 0,00 ; remise 0,01. "
        "Bases nettes 49,99 / 49,99 ; TVA 4,999 → 5,00 et 9,998 → 10,00 ; net 99,98 ; TTC 114,98.",
        D([L("1", "50.00", "10"), L("1", "49.99", "20")], remise=PCT("0.01")),
        attendu=OK(["50.00", "49.99"], "99.99", "0.01", "99.98", [("10", "49.99", "5.00"), ("20", "49.99", "10.00")], "15.00", "114.98", "0.00", "114.98"))
ajouter("rgp-009", cat, "33,33 % sur 4 taux.",
        D([L("1", "100", "0"), L("1", "99.99", "5.5"), L("3", "33.33", "10"), L("7", "14.29", "20")], remise=PCT("33.33")))
ajouter("rgp-010", cat, "Remise POURCENTAGE 10 % avec total brut nul (prix 0) : tout est nul.",
        D([L("5", "0", "10"), L("1", "0.00", "20")], remise=PCT("10")),
        attendu=OK(["0.00", "0.00"], "0.00", "0.00", "0.00", [("10", "0.00", "0.00"), ("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgp-011", cat, "3 bases de 0,15 (5,5 / 10 / 20), 10 % : 0,015 → 0,02 par base, remise 0,06 (et non 0,045 → 0,05). "
        "Bases nettes 0,13 ; TVA 0,00715 → 0,01 ; 0,013 → 0,01 ; 0,026 → 0,03 ; total TVA 0,05 ; net 0,39 ; TTC 0,44.",
        D([L("1", "0.15", "5.5"), L("1", "0.15", "10"), L("1", "0.15", "20")], remise=PCT("10")),
        attendu=OK(["0.15", "0.15", "0.15"], "0.45", "0.06", "0.39", [("5.5", "0.13", "0.01"), ("10", "0.13", "0.01"), ("20", "0.13", "0.03")], "0.05", "0.44", "0.00", "0.44"))

# ===========================================================================
# 9. Remise globale MONTANT (répartition au prorata, écart)
# ===========================================================================
cat = "remise_globale_montant"
ajouter("rgm-001", cat, "Bases égales 100 / 100 / 100 (5,5 / 10 / 20), M = 10 : parts 3,333 → 3,33 ×3 = 9,99 ; écart +0,01 → égalité de base → taux le plus élevé (20) : 3,34. "
        "Bases nettes 96,67 / 96,67 / 96,66 ; TVA 5,31685 → 5,32 ; 9,667 → 9,67 ; 19,332 → 19,33 ; total 34,32 ; net 290,00 ; TTC 324,32.",
        D([L("1", "100", "5.5"), L("1", "100", "10"), L("1", "100", "20")], remise=MNT("10")),
        attendu=OK(["100.00", "100.00", "100.00"], "300.00", "10.00", "290.00",
                   [("5.5", "96.67", "5.32"), ("10", "96.67", "9.67"), ("20", "96.66", "19.33")], "34.32", "324.32", "0.00", "324.32"))
ajouter("rgm-002", cat, "Écart attribué à la base la plus élevée même si son taux est le plus bas : bases 5,5 % = 100,01 ; 10 % = 100,00 ; 20 % = 100,00 ; total 300,01 ; M = 10. "
        "Parts : 1000,1/300,01 = 3,33356 → 3,33 ; 1000/300,01 = 3,33322 → 3,33 (×2) ; somme 9,99 ; écart +0,01 → base 5,5 % → 3,34. "
        "Bases nettes 96,67 ×3 ; TVA 5,32 / 9,67 / 19,33 (19,334) = 34,32 ; net 290,01 ; TTC 324,33.",
        D([L("1", "100.01", "5.5"), L("1", "100.00", "10"), L("1", "100.00", "20")], remise=MNT("10")),
        attendu=OK(["100.01", "100.00", "100.00"], "300.01", "10.00", "290.01",
                   [("5.5", "96.67", "5.32"), ("10", "96.67", "9.67"), ("20", "96.67", "19.33")], "34.32", "324.33", "0.00", "324.33"))
ajouter("rgm-003", cat, "Écart NÉGATIF : bases 10 % = 1,00 et 20 % = 1,00 ; M = 0,01 ; parts 0,005 → 0,01 chacune (demi-supérieur) ; somme 0,02 ; écart −0,01 → "
        "égalité → taux 20 : 0,00. Bases nettes 0,99 / 1,00 ; TVA 0,099 → 0,10 et 0,20 ; total 0,30 ; net 1,99 ; TTC 2,29.",
        D([L("1", "1.00", "10"), L("1", "1.00", "20")], remise=MNT("0.01")),
        attendu=OK(["1.00", "1.00"], "2.00", "0.01", "1.99", [("10", "0.99", "0.10"), ("20", "1.00", "0.20")], "0.30", "2.29", "0.00", "2.29"))
ajouter("rgm-004", cat, "Remise MONTANT = total HT brut (autorisé) : 2 × 50 (20 %) + 25,50 (10 %) = 125,50 ; M = 125,50 ; parts = bases ; "
        "bases nettes 0 ; TVA 0 ; TTC 0 ; acompte 30 % = 0 ; solde 0. Ventilation triée 10 puis 20.",
        D([L("2", "50", "20"), L("1", "25.50", "10")], remise=MNT("125.50"), acompte="30"),
        attendu=OK(["100.00", "25.50"], "125.50", "125.50", "0.00", [("10", "0.00", "0.00"), ("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgm-005", cat, "Cas pathologique mais littéral : 4 bases égales de 1,00 (0 / 5,5 / 10 / 20), M = 0,02. Parts 0,005 → 0,01 ×4 = 0,04 ; écart −0,02 → "
        "égalité → taux 20 : 0,01 − 0,02 = −0,01 (part négative, aucune règle de plancher dans la spec). Bases nettes 0,99 / 0,99 / 0,99 / 1,01 ; "
        "TVA 0 ; 0,05445 → 0,05 ; 0,099 → 0,10 ; 0,202 → 0,20 ; total 0,35 ; net 3,98 ; TTC 4,33.",
        D([L("1", "1.00", "0"), L("1", "1.00", "5.5"), L("1", "1.00", "10"), L("1", "1.00", "20")], remise=MNT("0.02")),
        attendu=OK(["1.00", "1.00", "1.00", "1.00"], "4.00", "0.02", "3.98",
                   [("0", "0.99", "0.00"), ("5.5", "0.99", "0.05"), ("10", "0.99", "0.10"), ("20", "1.01", "0.20")], "0.35", "4.33", "0.00", "4.33"))
ajouter("rgm-006", cat, "Écart négatif attribué à la base la plus élevée (taux 10, pas 20) : bases 20 % = 1,00 et 10 % = 3,00 ; M = 0,02 ; "
        "parts 0,005 → 0,01 (20 %) et 0,015 → 0,02 (10 %) ; somme 0,03 ; écart −0,01 → base 10 % : 0,01. Bases nettes 10 % = 2,99 ; 20 % = 0,99 ; "
        "TVA 0,299 → 0,30 et 0,198 → 0,20 ; total 0,50 ; net 3,98 ; TTC 4,48.",
        D([L("1", "1.00", "20"), L("1", "3.00", "10")], remise=MNT("0.02")),
        attendu=OK(["1.00", "3.00"], "4.00", "0.02", "3.98", [("10", "2.99", "0.30"), ("20", "0.99", "0.20")], "0.50", "4.48", "0.00", "4.48"))
ajouter("rgm-007", cat, "Égalité de base la plus élevée entre 5,5 % et 10 % (le 20 % est plus petit) : bases 50 / 50 / 20 ; M = 1. "
        "Parts 0,41667 → 0,42 ; 0,42 ; 0,16667 → 0,17 ; somme 1,01 ; écart −0,01 → base max à égalité → taux le plus élevé PARMI elles = 10 % : 0,41. "
        "Bases nettes 49,58 / 49,59 / 19,83 ; TVA 2,7269 → 2,73 ; 4,959 → 4,96 ; 3,966 → 3,97 ; total 11,66 ; net 119,00 ; TTC 130,66.",
        D([L("1", "50", "5.5"), L("1", "50", "10"), L("1", "20", "20")], remise=MNT("1")),
        attendu=OK(["50.00", "50.00", "20.00"], "120.00", "1.00", "119.00",
                   [("5.5", "49.58", "2.73"), ("10", "49.59", "4.96"), ("20", "19.83", "3.97")], "11.66", "130.66", "0.00", "130.66"))
ajouter("rgm-008", cat, "Mono-taux : part = M. 3 × 99,90 = 299,70 ; M = 49,70 ; net 250,00 ; TVA 20 % 50,00 ; TTC 300,00.",
        D([L("3", "99.90", "20")], remise=MNT("49.70")),
        attendu=OK(["299.70"], "299.70", "49.70", "250.00", [("20", "250.00", "50.00")], "50.00", "300.00", "0.00", "300.00"))
ajouter("rgm-009", cat, "Remise MONTANT \"0\" multi-taux : rien ne change.",
        D([L("3", "45", "10", "H"), L("1", "250", "20", "FORFAIT")], remise=MNT("0")),
        attendu=OK(["135.00", "250.00"], "385.00", "0.00", "385.00", [("10", "135.00", "13.50"), ("20", "250.00", "50.00")], "63.50", "448.50", "0.00", "448.50"))
ajouter("rgm-010", cat, "Total HT brut nul et M = 0 : toutes les parts valent 0 (pas de division par zéro).",
        D([L("1", "0", "10"), L("2", "0.00", "20")], remise=MNT("0")),
        attendu=OK(["0.00", "0.00"], "0.00", "0.00", "0.00", [("10", "0.00", "0.00"), ("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgm-011", cat, "Total HT brut nul (remise de ligne 100 %) et M = \"0.00\" : parts nulles.",
        D([L("1", "80", "5.5", remise="100")], remise=MNT("0.00"), acompte="50"),
        attendu=OK(["0.00"], "0.00", "0.00", "0.00", [("5.5", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rgm-012", cat, "Prorata sans écart : bases 10 % = 100, 20 % = 200 ; M = 10 ; parts 3,333 → 3,33 et 6,667 → 6,67 (somme 10,00). "
        "Bases nettes 96,67 / 193,33 ; TVA 9,667 → 9,67 et 38,666 → 38,67 ; total 48,34 ; net 290,00 ; TTC 338,34.",
        D([L("1", "100", "10"), L("2", "100", "20")], remise=MNT("10")),
        attendu=OK(["100.00", "200.00"], "300.00", "10.00", "290.00", [("10", "96.67", "9.67"), ("20", "193.33", "38.67")], "48.34", "338.34", "0.00", "338.34"))
ajouter("rgm-013", cat, "Écart vers la base la plus élevée au taux 0 : bases 0 % = 100,02 ; 10 % = 100 ; 20 % = 100 ; M = 1. Parts 0,33338 → 0,33 ; 0,33331 → 0,33 ×2 ; "
        "écart +0,01 → base 0 % : 0,34. Bases nettes 99,68 / 99,67 / 99,67 ; TVA 0 ; 9,967 → 9,97 ; 19,934 → 19,93 ; total 29,90 ; net 299,02 ; TTC 328,92.",
        D([L("1", "100.00", "20"), L("1", "100.00", "10"), L("1", "100.02", "0")], remise=MNT("1")),
        attendu=OK(["100.00", "100.00", "100.02"], "300.02", "1.00", "299.02",
                   [("0", "99.68", "0.00"), ("10", "99.67", "9.97"), ("20", "99.67", "19.93")], "29.90", "328.92", "0.00", "328.92"))
ajouter("rgm-014", cat, "Une base nulle (prix 0 à 10 %) : sa part vaut 0 ; base 20 % = 100 reçoit M = 10. Bases nettes 0,00 / 90,00 ; TVA 0 / 18 ; TTC 108.",
        D([L("1", "0", "10"), L("1", "100", "20")], remise=MNT("10")),
        attendu=OK(["0.00", "100.00"], "100.00", "10.00", "90.00", [("10", "0.00", "0.00"), ("20", "90.00", "18.00")], "18.00", "108.00", "0.00", "108.00"))
ajouter("rgm-015", cat, "Trois bases dont deux égales à 1,00 et une à 2,00 (5,5 / 10 / 20), M = 0,02 : parts 0,005 → 0,01 ; 0,01 ; 0,01 ; somme 0,03 ; "
        "écart −0,01 → base la plus élevée (20 %) : 0,00. Bases nettes 0,99 / 0,99 / 2,00 ; TVA 0,05 ; 0,10 ; 0,40 ; total 0,55 ; net 3,98 ; TTC 4,53.",
        D([L("1", "1.00", "5.5"), L("1", "1.00", "10"), L("1", "2.00", "20")], remise=MNT("0.02")),
        attendu=OK(["1.00", "1.00", "2.00"], "4.00", "0.02", "3.98", [("5.5", "0.99", "0.05"), ("10", "0.99", "0.10"), ("20", "2.00", "0.40")], "0.55", "4.53", "0.00", "4.53"))
ajouter("rgm-016", cat, "Répartition MONTANT sur 4 taux avec quotients non décimaux (division exacte requise).",
        D([L("3", "33.33", "0"), L("7", "14.29", "5.5"), L("1", "1234.56", "10"), L("2.5", "77.77", "20")], remise=MNT("123.45"), acompte="30"))
ajouter("rgm-017", cat, "Répartition MONTANT : plusieurs lignes par taux (la base = somme des lignes du taux).",
        D([L("1", "10.01", "20"), L("1", "20.02", "10"), L("1", "30.03", "20"), L("1", "40.04", "10"), L("1", "50.05", "5.5")], remise=MNT("15.15")))
ajouter("rgm-018", cat, "Remise MONTANT avec centimes sur 2 taux et acompte.",
        D([L("12.5", "45.90", "10", "M2", remise="5"), L("1", "380", "20", "FORFAIT")], remise=MNT("75.33"), acompte="30"))

# ===========================================================================
# 10. Taux 0 en régime NORMAL
# ===========================================================================
cat = "taux_zero_normal"
ajouter("tz-001", cat, "NORMAL, taux 0 et 5,5 avec remise %.",
        D([L("10", "85.00", "0", "H"), L("3", "21.30", "5.5", "M2")], remise=PCT("7")))
ajouter("tz-002", cat, "NORMAL, taux 0 (base la plus élevée) et 20 avec remise MONTANT.",
        D([L("1", "2500", "0", "FORFAIT"), L("4", "120.33", "20", "H")], remise=MNT("100.01"), acompte="20"))
ajouter("tz-003", cat, "NORMAL, plusieurs lignes à taux 0 seulement : une seule entrée de ventilation \"0\".",
        D([L("1", "100", "0"), L("2", "50.25", "0")]),
        attendu=OK(["100.00", "100.50"], "200.50", "0.00", "200.50", [("0", "200.50", "0.00")], "0.00", "200.50", "0.00", "200.50"))

# ===========================================================================
# 11. Arrondis de TVA (par base, jamais par ligne)
# ===========================================================================
cat = "arrondi_tva"
ajouter("atv-001", cat, "TVA 10 % sur 10,05 = 1,005 → 1,01 (float : 1.00). TTC 11,06 ; acompte 100 % = 11,06 ; solde 0,00.",
        D([L("1", "10.05", "10")], acompte="100"),
        attendu=OK(["10.05"], "10.05", "0.00", "10.05", [("10", "10.05", "1.01")], "1.01", "11.06", "11.06", "0.00"))
ajouter("atv-002", cat, "TVA 5,5 % sur 3,00 = 0,165 → 0,17 ; TTC 3,17 ; acompte 33,33 % = 1,056561 → 1,06 ; solde 2,11.",
        D([L("3", "1", "5.5")], acompte="33.33"),
        attendu=OK(["3.00"], "3.00", "0.00", "3.00", [("5.5", "3.00", "0.17")], "0.17", "3.17", "1.06", "2.11"))
ajouter("atv-003", cat, "TVA PAR BASE : 2 lignes de 10,05 à 10 % → base 20,10 → TVA 2,01 (par ligne on obtiendrait 1,01 + 1,01 = 2,02). TTC 22,11.",
        D([L("1", "10.05", "10"), L("1", "10.05", "10")]),
        attendu=OK(["10.05", "10.05"], "20.10", "0.00", "20.10", [("10", "20.10", "2.01")], "2.01", "22.11", "0.00", "22.11"))
ajouter("atv-004", cat, "TVA 5,5 % sur 1,00 = 0,055 → 0,06 ; TTC 1,06.",
        D([L("1", "1.00", "5.5")]),
        attendu=OK(["1.00"], "1.00", "0.00", "1.00", [("5.5", "1.00", "0.06")], "0.06", "1.06", "0.00", "1.06"))
ajouter("atv-005", cat, "TVA 5,5 % sur 0,10 = 0,0055 → 0,01 ; TTC 0,11.",
        D([L("1", "0.10", "5.5")]),
        attendu=OK(["0.10"], "0.10", "0.00", "0.10", [("5.5", "0.10", "0.01")], "0.01", "0.11", "0.00", "0.11"))
ajouter("atv-006", cat, "TVA 5,5 % sur 0,09 = 0,00495 → 0,00 ; TTC 0,09.",
        D([L("1", "0.09", "5.5")]),
        attendu=OK(["0.09"], "0.09", "0.00", "0.09", [("5.5", "0.09", "0.00")], "0.00", "0.09", "0.00", "0.09"))
ajouter("atv-007", cat, "TVA 10 % sur 10,15 = 1,015 → 1,02 (float : 1.01) ; TTC 11,17.",
        D([L("1", "10.15", "10")]),
        attendu=OK(["10.15"], "10.15", "0.00", "10.15", [("10", "10.15", "1.02")], "1.02", "11.17", "0.00", "11.17"))
ajouter("atv-008", cat, "TVA calculée sur la base NETTE : base 22,28 à 10 %, remise 10 % = 2,228 → 2,23 ; base nette 20,05 ; TVA 2,005 → 2,01 ; TTC 22,06.",
        D([L("1", "22.28", "10")], remise=PCT("10")),
        attendu=OK(["22.28"], "22.28", "2.23", "20.05", [("10", "20.05", "2.01")], "2.01", "22.06", "0.00", "22.06"))
ajouter("atv-009", cat, "TVA 5,5 % sur 21,00 = 1,155 → 1,16 ; TTC 22,16.",
        D([L("1", "21.00", "5.5")]),
        attendu=OK(["21.00"], "21.00", "0.00", "21.00", [("5.5", "21.00", "1.16")], "1.16", "22.16", "0.00", "22.16"))
ajouter("atv-010", cat, "TVA PAR BASE : 3 lignes de 1,00 à 5,5 % → base 3,00 → 0,165 → 0,17 (par ligne : 3 × 0,06 = 0,18). TTC 3,17.",
        D([L("1", "1.00", "5.5"), L("1", "1.00", "5.5"), L("1", "1.00", "5.5")]),
        attendu=OK(["1.00", "1.00", "1.00"], "3.00", "0.00", "3.00", [("5.5", "3.00", "0.17")], "0.17", "3.17", "0.00", "3.17"))

# ===========================================================================
# 12. Arrondis d'acompte
# ===========================================================================
cat = "arrondi_acompte"
ajouter("aac-001", cat, "FRANCHISE, TTC 100,50, acompte 1 % = 1,005 → 1,01 (float : 1.00) ; solde 99,49.",
        D([L("1", "100.50", "0")], regime="FRANCHISE_EN_BASE", acompte="1"),
        attendu=OK(["100.50"], "100.50", "0.00", "100.50", [], "0.00", "100.50", "1.01", "99.49", MENTION))
ajouter("aac-002", cat, "FRANCHISE, TTC 1,00, acompte 50,5 % = 0,505 → 0,51 ; solde 0,49.",
        D([L("1", "1.00", "0")], regime="FRANCHISE_EN_BASE", acompte="50.5"),
        attendu=OK(["1.00"], "1.00", "0.00", "1.00", [], "0.00", "1.00", "0.51", "0.49", MENTION))
ajouter("aac-003", cat, "FRANCHISE, TTC 2,01, acompte 50 % = 1,005 → 1,01 (float : 1.00) ; solde 1,00.",
        D([L("1", "2.01", "0")], regime="FRANCHISE_EN_BASE", acompte="50"),
        attendu=OK(["2.01"], "2.01", "0.00", "2.01", [], "0.00", "2.01", "1.01", "1.00", MENTION))
ajouter("aac-004", cat, "FRANCHISE, TTC 50,00, acompte 0,01 % = 0,005 → 0,01 ; solde 49,99.",
        D([L("1", "50.00", "0")], regime="FRANCHISE_EN_BASE", acompte="0.01"),
        attendu=OK(["50.00"], "50.00", "0.00", "50.00", [], "0.00", "50.00", "0.01", "49.99", MENTION))
ajouter("aac-005", cat, "FRANCHISE, TTC 49,99, acompte 0,01 % = 0,004999 → 0,00 ; solde 49,99.",
        D([L("1", "49.99", "0")], regime="FRANCHISE_EN_BASE", acompte="0.01"),
        attendu=OK(["49.99"], "49.99", "0.00", "49.99", [], "0.00", "49.99", "0.00", "49.99", MENTION))
ajouter("aac-006", cat, "NORMAL : 0,85 à 20 % → TVA 0,17 ; TTC 1,02 ; acompte 12,5 % = 0,1275 → 0,13 ; solde 0,89.",
        D([L("1", "0.85", "20")], acompte="12.5"),
        attendu=OK(["0.85"], "0.85", "0.00", "0.85", [("20", "0.85", "0.17")], "0.17", "1.02", "0.13", "0.89"))
ajouter("aac-007", cat, "FRANCHISE, TTC 0,15, acompte 33,33 % = 0,049995 → 0,05 ; solde 0,10.",
        D([L("1", "0.15", "0")], regime="FRANCHISE_EN_BASE", acompte="33.33"),
        attendu=OK(["0.15"], "0.15", "0.00", "0.15", [], "0.00", "0.15", "0.05", "0.10", MENTION))
ajouter("aac-008", cat, "Acompte calculé sur le TTC (pas le HT) : 100 HT à 20 % → TTC 120 ; acompte 30 % = 36,00 ; solde 84,00.",
        D([L("1", "100", "20")], acompte="30"),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "36.00", "84.00"))

# ===========================================================================
# 13. Acompte et champs optionnels
# ===========================================================================
cat = "acompte_optionnels"
ajouter("aco-001", cat, "acompte_pct \"0\" : acompte 0.00, solde = TTC.",
        D([L("1", "100", "20")], acompte="0"),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("aco-002", cat, "acompte_pct \"100\" : acompte = TTC, solde 0.00.",
        D([L("3", "33.33", "10")], acompte="100"),
        attendu=OK(["99.99"], "99.99", "0.00", "99.99", [("10", "99.99", "10.00")], "10.00", "109.99", "109.99", "0.00"))
ajouter("aco-003", cat, "acompte_pct \"100.00\".",
        D([L("1", "0.01", "20")], acompte="100.00"),
        attendu=OK(["0.01"], "0.01", "0.00", "0.01", [("20", "0.01", "0.00")], "0.00", "0.01", "0.01", "0.00"))
ajouter("aco-004", cat, "acompte_pct null : équivaut à absent.",
        D([L("1", "100", "20")], acompte=None),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("aco-005", cat, "acompte_pct \"0.00\".",
        D([L("1", "100", "20")], acompte="0.00"),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("aco-006", cat, "acompte_pct \"12.34\" multi-taux.",
        D([L("2", "456.78", "20"), L("3", "123.45", "10"), L("1", "99", "5.5")], acompte="12.34"))
ajouter("aco-007", cat, "remise_globale null, acompte_pct null, remise_pct null : tout équivaut à absent.",
        D([L("2", "50", "20", remise=None)], remise=None, acompte=None),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "0.00", "120.00"))
ajouter("aco-008", cat, "remise_globale null avec acompte présent.",
        D([L("2", "50", "20")], remise=None, acompte="25"),
        attendu=OK(["100.00"], "100.00", "0.00", "100.00", [("20", "100.00", "20.00")], "20.00", "120.00", "30.00", "90.00"))

SUPPR = object()


def LX(base=None, **changements) -> dict:
    """Variante de la ligne valide LV (ou de `base`) ; SUPPR retire la clé."""
    ligne = dict(LV if base is None else base)
    for k, v in changements.items():
        if v is SUPPR:
            ligne.pop(k, None)
        else:
            ligne[k] = v
    return ligne


def RX(base: dict, **changements) -> dict:
    racine = dict(base)
    for k, v in changements.items():
        if v is SUPPR:
            racine.pop(k, None)
        else:
            racine[k] = v
    return racine


BASE = D([LV])  # devis minimal valide : 2 × 10,00 à 20 % → TTC 24,00

# ===========================================================================
# 14. Bornes
# ===========================================================================
cat = "bornes"
ajouter("bor-001", cat, "Quantité 1 000 000 (borne incluse) × 1 → 1 000 000,00 ; TVA 20 % 200 000,00 ; TTC 1 200 000,00.",
        D([L("1000000", "1", "20")]),
        attendu=OK(["1000000.00"], "1000000.00", "0.00", "1000000.00", [("20", "1000000.00", "200000.00")], "200000.00", "1200000.00", "0.00", "1200000.00"))
ajouter("bor-002", cat, "Quantité \"1000000.000\" (= 1 000 000, 3 décimales) : valide.",
        D([L("1000000.000", "1", "20")]),
        attendu=OK(["1000000.00"], "1000000.00", "0.00", "1000000.00", [("20", "1000000.00", "200000.00")], "200000.00", "1200000.00", "0.00", "1200000.00"))
ajouter("bor-003", cat, "Quantité \"1000000.001\" > 1 000 000 : QUANTITE_INVALIDE.",
        D([LX(quantite="1000000.001")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("bor-004", cat, "Quantité \"1000001\" : QUANTITE_INVALIDE.",
        D([LX(quantite="1000001")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("bor-005", cat, "Quantité minimale \"0.001\" × 1000 = 1,00 ; TVA 0,20 ; TTC 1,20.",
        D([L("0.001", "1000", "20")]),
        attendu=OK(["1.00"], "1.00", "0.00", "1.00", [("20", "1.00", "0.20")], "0.20", "1.20", "0.00", "1.20"))
ajouter("bor-006", cat, "Quantité \"0\" (≤ 0) : QUANTITE_INVALIDE.",
        D([LX(quantite="0")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("bor-007", cat, "Quantité \"0.000\" (≤ 0) : QUANTITE_INVALIDE.",
        D([LX(quantite="0.000")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("bor-008", cat, "Prix 10 000 000 (borne incluse) : 10 000 000,00 ; TVA 10 % 1 000 000,00 ; TTC 11 000 000,00.",
        D([L("1", "10000000", "10")]),
        attendu=OK(["10000000.00"], "10000000.00", "0.00", "10000000.00", [("10", "10000000.00", "1000000.00")], "1000000.00", "11000000.00", "0.00", "11000000.00"))
ajouter("bor-009", cat, "Prix \"10000000.00\" : valide (= borne).",
        D([L("1", "10000000.00", "10")]),
        attendu=OK(["10000000.00"], "10000000.00", "0.00", "10000000.00", [("10", "10000000.00", "1000000.00")], "1000000.00", "11000000.00", "0.00", "11000000.00"))
ajouter("bor-010", cat, "Prix \"10000000.01\" > 10 000 000 : PRIX_INVALIDE.",
        D([LX(prix_unitaire_ht="10000000.01")]), erreurs=[("PRIX_INVALIDE", 0)])
ajouter("bor-011", cat, "Prix \"10000001\" : PRIX_INVALIDE.",
        D([LX(prix_unitaire_ht="10000001")]), erreurs=[("PRIX_INVALIDE", 0)])
ajouter("bor-012", cat, "Prix \"0\" et \"0.00\" valides (≥ 0) : montants nuls.",
        D([L("3", "0", "20"), L("1", "0.00", "20")]),
        attendu=OK(["0.00", "0.00"], "0.00", "0.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("bor-013", cat, "remise_pct \"100.01\" > 100 : REMISE_LIGNE_INVALIDE.",
        D([LX(remise_pct="100.01")]), erreurs=[("REMISE_LIGNE_INVALIDE", 0)])
ajouter("bor-014", cat, "remise_pct \"101\" : REMISE_LIGNE_INVALIDE.",
        D([LX(remise_pct="101")]), erreurs=[("REMISE_LIGNE_INVALIDE", 0)])
ajouter("bor-015", cat, "acompte_pct \"100.01\" > 100 : ACOMPTE_INVALIDE.",
        RX(BASE, acompte_pct="100.01"), erreurs=[("ACOMPTE_INVALIDE", None)])
ajouter("bor-016", cat, "acompte_pct \"1000\" : ACOMPTE_INVALIDE.",
        RX(BASE, acompte_pct="1000"), erreurs=[("ACOMPTE_INVALIDE", None)])
ajouter("bor-017", cat, "Remise globale POURCENTAGE \"100.01\" > 100 : REMISE_GLOBALE_INVALIDE.",
        RX(BASE, remise_globale=PCT("100.01")), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])
ajouter("bor-018", cat, "Remise globale POURCENTAGE \"150\" : REMISE_GLOBALE_INVALIDE.",
        RX(BASE, remise_globale=PCT("150")), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])
ajouter("bor-019", cat, "Remise MONTANT sans borne propre : M = total brut maximal d'une ligne (10 000 000 000 000,00) → tout est nul.",
        D([L("1000000", "10000000", "20")], remise=MNT("10000000000000.00")),
        attendu=OK(["10000000000000.00"], "10000000000000.00", "10000000000000.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("bor-020", cat, "Prix \"010000000\" (zéro de tête, = 10 000 000) : valide.",
        D([L("1", "010000000", "10")]),
        attendu=OK(["10000000.00"], "10000000.00", "0.00", "10000000.00", [("10", "10000000.00", "1000000.00")], "1000000.00", "11000000.00", "0.00", "11000000.00"))
ajouter("bor-021", cat, "Quantité \"0001000000.000\" (= 1 000 000) : valide.",
        D([L("0001000000.000", "1", "20")]),
        attendu=OK(["1000000.00"], "1000000.00", "0.00", "1000000.00", [("20", "1000000.00", "200000.00")], "200000.00", "1200000.00", "0.00", "1200000.00"))
ajouter("bor-022", cat, "Quantité de 30 chiffres : QUANTITE_INVALIDE (sans débordement ni plantage).",
        D([LX(quantite="123456789012345678901234567890")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("bor-023", cat, "Prix de 40 chiffres : PRIX_INVALIDE (sans débordement ni plantage).",
        D([LX(prix_unitaire_ht="9999999999999999999999999999999999999999.99")]), erreurs=[("PRIX_INVALIDE", 0)])
ajouter("bor-024", cat, "acompte_pct de 25 chiffres : ACOMPTE_INVALIDE (sans plantage).",
        RX(BASE, acompte_pct="1000000000000000000000000"), erreurs=[("ACOMPTE_INVALIDE", None)])
ajouter("bor-025", cat, "remise_pct \"100\" avec 0 décimale mais zéros de tête \"0100.00\" : = 100, valide → ligne à 0.",
        D([L("1", "50", "20", remise="0100.00"), L("1", "50", "20")]),
        attendu=OK(["0.00", "50.00"], "50.00", "0.00", "50.00", [("20", "50.00", "10.00")], "10.00", "60.00", "0.00", "60.00"))

# ===========================================================================
# 15. Grandes valeurs (pas de débordement)
# ===========================================================================
cat = "grandes_valeurs"
ajouter("gv-001", cat, "Ligne maximale 1 000 000 × 10 000 000 = 10 000 000 000 000,00 ; TVA 20 % 2 000 000 000 000,00 ; TTC 12 000 000 000 000,00 ; "
        "acompte 33,33 % = 3 999 600 000 000,00 ; solde 8 000 400 000 000,00. (En centimes × points de base : 1,2e15 × 3333 ≈ 4e18, proche de la limite i64.)",
        D([L("1000000", "10000000", "20")], acompte="33.33"),
        attendu=OK(["10000000000000.00"], "10000000000000.00", "0.00", "10000000000000.00", [("20", "10000000000000.00", "2000000000000.00")],
                   "2000000000000.00", "12000000000000.00", "3999600000000.00", "8000400000000.00"))
ajouter("gv-002", cat, "999 999,999 × 9 999 999,99 × 0,9999 à 5,5 %, acompte 99,99 % (produit exact à ~22 chiffres significatifs).",
        D([L("999999.999", "9999999.99", "5.5", remise="0.01")], acompte="99.99"))
ajouter("gv-003", cat, "Quatre lignes quasi maximales sur 4 taux + remise MONTANT à centimes + acompte (M × base ≈ 1e26 en euros).",
        D([L("1000000", "10000000", "0"), L("999999.999", "9999999.99", "5.5"), L("987654.321", "9876543.21", "10", remise="12.34"),
           L("1000000", "9999999.97", "20", remise="0.01")], remise=MNT("12345678901234.56"), acompte="45.67"))
ajouter("gv-004", cat, "8 000 lignes maximales à 20 % : brut 8e16 € ; TTC 9,6e16 € = 9,6e18 centimes > i64::MAX (9,22e18) ; acompte 99,99 %. "
        "Aucune borne sur le nombre de lignes dans la spec : le calcul doit rester exact.",
        D([L("1000000", "10000000", "20", designation=f"L{i}") for i in range(8000)], acompte="99.99"))
ajouter("gv-005", cat, "500 lignes variées, 4 taux, remise POURCENTAGE 7,77 % et acompte 30 %.",
        D([L(f"{(i * 37) % 1000 + 1}.{(i * 7) % 1000:03d}", f"{(i * 7919) % 100000}.{(i * 13) % 100:02d}", ["0", "5.5", "10", "20"][i % 4],
             ["U", "M2", "ML", "M3", "H", "FORFAIT"][i % 6], remise=f"{(i * 3) % 100}.{i % 100:02d}" if i % 3 == 0 else ...) for i in range(500)],
          remise=PCT("7.77"), acompte="30"))
ajouter("gv-006", cat, "Trois lignes maximales (5,5 / 10 / 20) + une ligne à 0,01 € ; remise MONTANT 9 999 999 999 999,99 (répartition avec écart sur de grandes bases).",
        D([L("1000000", "10000000", "5.5"), L("1000000", "10000000", "10"), L("1000000", "10000000", "20"), L("1", "0.01", "20")],
          remise=MNT("9999999999999.99"), acompte="99.99"))

# ===========================================================================
# 16. Précision (nombre de décimales, zéros finaux)
# ===========================================================================
cat = "precision"
ajouter("pre-001", cat, "Quantité \"1.500\" : 3 décimales (zéros finaux comptés) → valide. 1,5 × 10 = 15,00 ; TVA 3,00 ; TTC 18,00.",
        D([L("1.500", "10", "20")]),
        attendu=OK(["15.00"], "15.00", "0.00", "15.00", [("20", "15.00", "3.00")], "3.00", "18.00", "0.00", "18.00"))
ajouter("pre-002", cat, "Quantité \"1.5000\" : 4 décimales → QUANTITE_INVALIDE (même si la valeur vaut 1,5).",
        D([LX(quantite="1.5000")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("pre-003", cat, "Quantité \"0.1234\" : 4 décimales → QUANTITE_INVALIDE.",
        D([LX(quantite="0.1234")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("pre-004", cat, "Prix \"45.900\" : 3 décimales → PRIX_INVALIDE.",
        D([LX(prix_unitaire_ht="45.900")]), erreurs=[("PRIX_INVALIDE", 0)])
ajouter("pre-005", cat, "Prix \"45.9\" et \"45.90\" : valides et équivalents. 2 × 45,90 = 91,80 par ligne ; TVA 20 % 36,72 ; TTC 220,32.",
        D([L("2", "45.9", "20"), L("2", "45.90", "20")]),
        attendu=OK(["91.80", "91.80"], "183.60", "0.00", "183.60", [("20", "183.60", "36.72")], "36.72", "220.32", "0.00", "220.32"))
ajouter("pre-006", cat, "remise_pct \"5.000\" : 3 décimales → REMISE_LIGNE_INVALIDE.",
        D([LX(remise_pct="5.000")]), erreurs=[("REMISE_LIGNE_INVALIDE", 0)])
ajouter("pre-007", cat, "remise_pct \"5.00\" : valide. 2 × 10 × 0,95 = 19,00 ; TVA 3,80 ; TTC 22,80.",
        D([LX(remise_pct="5.00")]),
        attendu=OK(["19.00"], "19.00", "0.00", "19.00", [("20", "19.00", "3.80")], "3.80", "22.80", "0.00", "22.80"))
ajouter("pre-008", cat, "acompte_pct \"30.000\" : 3 décimales → ACOMPTE_INVALIDE.",
        RX(BASE, acompte_pct="30.000"), erreurs=[("ACOMPTE_INVALIDE", None)])
ajouter("pre-009", cat, "Remise globale POURCENTAGE \"3.000\" : 3 décimales → REMISE_GLOBALE_INVALIDE.",
        RX(BASE, remise_globale=PCT("3.000")), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])
ajouter("pre-010", cat, "Remise globale MONTANT \"10.000\" : 3 décimales → REMISE_GLOBALE_INVALIDE.",
        RX(BASE, remise_globale=MNT("10.000")), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])
ajouter("pre-011", cat, "Remise globale MONTANT \"10.50\" : valide. 20,00 − 10,50 = 9,50 ; TVA 1,90 ; TTC 11,40.",
        RX(BASE, remise_globale=MNT("10.50")),
        attendu=OK(["20.00"], "20.00", "10.50", "9.50", [("20", "9.50", "1.90")], "1.90", "11.40", "0.00", "11.40"))
ajouter("pre-012", cat, "acompte_pct \"33.333\" : 3 décimales → ACOMPTE_INVALIDE.",
        RX(BASE, acompte_pct="33.333"), erreurs=[("ACOMPTE_INVALIDE", None)])
ajouter("pre-013", cat, "Quantité \"1000000.0001\" : 4 décimales → QUANTITE_INVALIDE.",
        D([LX(quantite="1000000.0001")]), erreurs=[("QUANTITE_INVALIDE", 0)])
ajouter("pre-014", cat, "Prix \"0.001\" : 3 décimales → PRIX_INVALIDE.",
        D([LX(prix_unitaire_ht="0.001")]), erreurs=[("PRIX_INVALIDE", 0)])
ajouter("pre-015", cat, "Zéros finaux partout dans les limites : quantite \"2.000\", prix \"3.00\", remise \"0.00\", acompte \"0.00\", remise globale \"0.00\" % → valide. "
        "6,00 ; TVA 1,20 ; TTC 7,20.",
        D([L("2.000", "3.00", "20", remise="0.00")], remise=PCT("0.00"), acompte="0.00"),
        attendu=OK(["6.00"], "6.00", "0.00", "6.00", [("20", "6.00", "1.20")], "1.20", "7.20", "0.00", "7.20"))
ajouter("pre-016", cat, "Quantité \"0.0001\" : 4 décimales → QUANTITE_INVALIDE.",
        D([LX(quantite="0.0001")]), erreurs=[("QUANTITE_INVALIDE", 0)])

# ===========================================================================
# 17. Formats invalides de chaîne décimale
# ===========================================================================
cat = "format_invalide"
_n = 0


def fmt_cas(champ: str, valeur, code: str, ligne, note: str = "") -> None:
    global _n
    _n += 1
    if champ in ("acompte_pct",):
        entree = RX(BASE, acompte_pct=valeur)
    elif champ == "remise_globale.valeur":
        entree = RX(BASE, remise_globale={"type": "MONTANT", "valeur": valeur})
    else:
        entree = D([LX(**{champ: valeur})])
    rep = json.dumps(valeur, ensure_ascii=False)
    ajouter(f"fmt-{_n:03d}", cat, f"{champ} = {rep} : {code}.{(' ' + note) if note else ''}", entree, erreurs=[(code, ligne)])


for _v, _note in [(".5", ""), ("5.", ""), ("-1", "signe interdit"), ("1e3", "exposant interdit"), ("1,5", "virgule"), (" 1", "espace de tête"),
                  ("1 ", "espace final"), ("+1", "signe +"), ("", "chaîne vide"), ("1.2.3", ""), ("abc", ""), ("NaN", ""), ("Infinity", ""),
                  ("0x10", "hexadécimal"), ("1_000", "séparateur"), ("١٢", "chiffres arabes-indiens (non ASCII)"), ("１", "chiffre pleine chasse (non ASCII)"),
                  ("1\n", "saut de ligne final (piège du `$` PCRE sans modificateur D)"), ("-0", ""), ("1e-3", "")]:
    fmt_cas("quantite", _v, "QUANTITE_INVALIDE", 0, _note)
for _v, _note in [(12.5, "nombre JSON au lieu d'une chaîne"), (12, "entier JSON au lieu d'une chaîne"), (True, "booléen"), ([], "tableau"), ({}, "objet")]:
    fmt_cas("quantite", _v, "QUANTITE_INVALIDE", 0, _note)
for _v, _note in [(".50", ""), ("10.", ""), ("-10", ""), ("1E2", ""), ("12,50", ""), (45.9, "nombre JSON"), (10, "entier JSON"), (False, "booléen"),
                  (" 10", ""), ("10.00 ", "")]:
    fmt_cas("prix_unitaire_ht", _v, "PRIX_INVALIDE", 0, _note)
for _v, _note in [("-5", ""), ("5%", ""), (5, "nombre JSON"), (True, "booléen"), ("", "chaîne vide (≠ absent)"), ("1e1", ""), (".5", "")]:
    fmt_cas("remise_pct", _v, "REMISE_LIGNE_INVALIDE", 0, _note)
for _v, _note in [("-10", ""), (30, "nombre JSON"), ("30%", ""), ("", "chaîne vide (≠ absent)"), (True, "booléen"), (" 30", ""), ("3e1", "")]:
    fmt_cas("acompte_pct", _v, "ACOMPTE_INVALIDE", None, _note)
for _v, _note in [(3, "nombre JSON"), ("-3", ""), ("3e0", ""), (None, "null (valeur obligatoire dans l'objet)"), (".5", ""), ("", "")]:
    fmt_cas("remise_globale.valeur", _v, "REMISE_GLOBALE_INVALIDE", None, _note)

# ===========================================================================
# 18. Champs obligatoires absents ou null
# ===========================================================================
cat = "obligatoires_absent_null"
ajouter("obl-001", cat, "regime_tva absent : REGIME_INVALIDE.", RX(BASE, regime_tva=SUPPR), erreurs=[("REGIME_INVALIDE", None)])
ajouter("obl-002", cat, "regime_tva null : REGIME_INVALIDE.", RX(BASE, regime_tva=None), erreurs=[("REGIME_INVALIDE", None)])
for _i, (_champ, _code) in enumerate([("designation", "DESIGNATION_VIDE"), ("quantite", "QUANTITE_INVALIDE"), ("unite", "UNITE_INVALIDE"),
                                      ("prix_unitaire_ht", "PRIX_INVALIDE"), ("taux_tva", "TAUX_INVALIDE")]):
    ajouter(f"obl-{3 + 2 * _i:03d}", cat, f"{_champ} absent : {_code}.", D([LX(**{_champ: SUPPR})]), erreurs=[(_code, 0)])
    ajouter(f"obl-{4 + 2 * _i:03d}", cat, f"{_champ} null : {_code}.", D([LX(**{_champ: None})]), erreurs=[(_code, 0)])
ajouter("obl-013", cat, "Champ obligatoire null sur la 3e ligne seulement : erreur indexée 2.",
        D([LV, LV, LX(unite=None)]), erreurs=[("UNITE_INVALIDE", 2)])

# ===========================================================================
# 19. Valeurs hors énumération
# ===========================================================================
cat = "enum_invalide"
_n = 0
for _v in ["normal", "Normal", "", 1, "FRANCHISE", "NORMAL ", ["NORMAL"], "FRANCHISE_EN_BASE\n"]:
    _n += 1
    ajouter(f"enu-{_n:03d}", cat, f"regime_tva = {json.dumps(_v, ensure_ascii=False)} : REGIME_INVALIDE.", RX(BASE, regime_tva=_v), erreurs=[("REGIME_INVALIDE", None)])
for _v in ["m2", "M²", "KG", "", 2, "u", " U", "FORFAIT "]:
    _n += 1
    ajouter(f"enu-{_n:03d}", cat, f"unite = {json.dumps(_v, ensure_ascii=False)} : UNITE_INVALIDE.", D([LX(unite=_v)]), erreurs=[("UNITE_INVALIDE", 0)])
for _v in ["5.50", "05.5", "19.6", 20, 5.5, "20 ", "20.0", "", "7", "2.1", "10%", "00", "5,5"]:
    _n += 1
    ajouter(f"enu-{_n:03d}", cat, f"taux_tva = {json.dumps(_v, ensure_ascii=False)} : TAUX_INVALIDE (chaîne exacte parmi \"0\", \"5.5\", \"10\", \"20\").",
            D([LX(taux_tva=_v)]), erreurs=[("TAUX_INVALIDE", 0)])
for _v in ["pourcentage", "MONTANT ", "REMISE", 1]:
    _n += 1
    ajouter(f"enu-{_n:03d}", cat, f"remise_globale.type = {json.dumps(_v, ensure_ascii=False)} : REMISE_GLOBALE_INVALIDE.",
            RX(BASE, remise_globale={"type": _v, "valeur": "5"}), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])

# ===========================================================================
# 20. Désignation
# ===========================================================================
cat = "designation"
ajouter("des-001", cat, "designation \"\" : DESIGNATION_VIDE.", D([LX(designation="")]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-002", cat, "designation \"   \" (espaces ASCII seulement) : DESIGNATION_VIDE après trim.", D([LX(designation="   ")]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-003", cat, "designation \"  Pose  \" : valide (non vide après trim).", D([LX(designation="  Pose  ")]),
        attendu=OK(["20.00"], "20.00", "0.00", "20.00", [("20", "20.00", "4.00")], "4.00", "24.00", "0.00", "24.00"))
ajouter("des-004", cat, "designation \" a \" (un caractère) : valide.", D([LX(designation=" a ")]),
        attendu=OK(["20.00"], "20.00", "0.00", "20.00", [("20", "20.00", "4.00")], "4.00", "24.00", "0.00", "24.00"))
ajouter("des-005", cat, "designation tableau : DESIGNATION_VIDE (non chaîne).", D([LX(designation=["Pose"])]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-006", cat, "designation nombre 123 : DESIGNATION_VIDE (non chaîne).", D([LX(designation=123)]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-007", cat, "designation booléen : DESIGNATION_VIDE.", D([LX(designation=True)]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-008", cat, "designation objet : DESIGNATION_VIDE.", D([LX(designation={"fr": "Pose"})]), erreurs=[("DESIGNATION_VIDE", 0)])
ajouter("des-009", cat, "designation \"0\" (chaîne non vide, falsy dans certains langages) : valide.", D([LX(designation="0")]),
        attendu=OK(["20.00"], "20.00", "0.00", "20.00", [("20", "20.00", "4.00")], "4.00", "24.00", "0.00", "24.00"))

# ===========================================================================
# 21. lignes absent / non tableau / vide
# ===========================================================================
cat = "lignes_vides"
_n = 0
for _v, _note in [(SUPPR, "absent"), (None, "null"), ([], "tableau vide"), ({}, "objet vide"), ("texte", "chaîne"), (0, "nombre"), (True, "booléen"),
                  ({"0": LV}, "objet indexé (non tableau)")]:
    _n += 1
    ajouter(f"lv-{_n:03d}", cat, f"lignes {_note} : LIGNES_VIDES.", RX(BASE, lignes=_v), erreurs=[("LIGNES_VIDES", None)])
ajouter("lv-009", cat, "lignes vide + remise MONTANT 1000 : LIGNES_VIDES seulement (REMISE_SUPERIEURE_AU_TOTAL non vérifiée sans lignes).",
        RX(BASE, lignes=[], remise_globale=MNT("1000")), erreurs=[("LIGNES_VIDES", None)])

# ===========================================================================
# 22. Ligne non objet
# ===========================================================================
cat = "ligne_non_objet"
CINQ = ["DESIGNATION_VIDE", "QUANTITE_INVALIDE", "UNITE_INVALIDE", "PRIX_INVALIDE", "TAUX_INVALIDE"]
_n = 0
for _v, _note in [(None, "null"), (1, "nombre"), ("ligne", "chaîne"), ([], "tableau"), (True, "booléen"), ([LV], "tableau contenant une ligne valide")]:
    _n += 1
    ajouter(f"lno-{_n:03d}", cat, f"Ligne {_note} : les 5 codes DESIGNATION/QUANTITE/UNITE/PRIX/TAUX sur l'index 0 (pas REMISE_LIGNE_INVALIDE).",
            D([_v]), erreurs=[(c, 0) for c in CINQ])
ajouter("lno-007", cat, "Lignes [valide, null, valide] : 5 codes sur l'index 1 seulement.", D([LV, None, LV]), erreurs=[(c, 1) for c in CINQ])
ajouter("lno-008", cat, "FRANCHISE + ligne 42 : 5 codes, pas de FRANCHISE_AVEC_TVA.", D([42], regime="FRANCHISE_EN_BASE"), erreurs=[(c, 0) for c in CINQ])
ajouter("lno-009", cat, "Ligne objet vide {} : 5 codes (remise_pct absente = 0, pas d'erreur de remise).", D([{}]), erreurs=[(c, 0) for c in CINQ])

# ===========================================================================
# 23. Racine JSON : syntaxe invalide ou racine non objet
# ===========================================================================
cat = "json_invalide"
_VALIDE = json.dumps(BASE, ensure_ascii=False)
_n = 0
for _brute, _note in [
    ("", "entrée vide"),
    ("   \n", "blancs seulement"),
    ("[]", "racine tableau vide"),
    ("[" + _VALIDE + "]", "racine tableau contenant un devis valide"),
    ('"texte"', "racine chaîne"),
    ("42", "racine nombre"),
    ("  3.14  ", "racine nombre entouré de blancs"),
    ("null", "racine null"),
    ("true", "racine booléen"),
    ("{", "objet tronqué"),
    ('{"regime_tva": "NORMAL",}', "virgule finale"),
    ("{'regime_tva': 'NORMAL'}", "guillemets simples"),
    (_VALIDE.replace('"2"', "NaN"), "littéral NaN (non JSON)"),
    (_VALIDE.replace('"2"', "Infinity"), "littéral Infinity (non JSON)"),
    ("{} {}", "deux valeurs JSON successives"),
    ("// commentaire\n" + _VALIDE, "commentaire"),
    ('{regime_tva: "NORMAL", lignes: []}', "clés non quotées"),
    (_VALIDE + " x", "devis valide suivi de texte"),
    (_VALIDE[:-1], "devis valide tronqué (accolade finale manquante)"),
    ('{"regime_tva": "NORMAL", "lignes": [}', "tableau mal fermé"),
    (_VALIDE.replace('"Ligne valide"', '"Ligne\tvalide"'), "tabulation brute (non échappée) dans une chaîne"),
    (_VALIDE + "]", "crochet fermant en trop"),
    ('{"regime_tva": "NORMAL" "lignes": []}', "virgule manquante"),
    (_VALIDE.replace('"2"', "02"), "nombre JSON à zéro de tête (02) interdit par la RFC"),
]:
    _n += 1
    ajouter(f"jsn-{_n:03d}", cat, f"{_note} : JSON_INVALIDE comme seule erreur.", brute=_brute, erreurs=JSON_INVALIDE)

# ===========================================================================
# 24. Remise globale invalide (objet mal formé)
# ===========================================================================
cat = "remise_globale_invalide"
_n = 0
for _v, _note in [
    ("10", "chaîne au lieu d'un objet"),
    ([], "tableau"),
    (10, "nombre"),
    (False, "booléen false (seul null équivaut à absent)"),
    ("", "chaîne vide"),
    ({}, "objet vide (type absent)"),
    ({"valeur": "10"}, "type absent"),
    ({"type": "POURCENTAGE"}, "valeur absente"),
    ({"type": None, "valeur": "5"}, "type null"),
    ({"type": "POURCENTAGE", "valeur": "-5"}, "pourcentage négatif"),
    ({"type": "X", "valeur": "abc"}, "type ET valeur invalides (une seule erreur globale attendue, comparaison ensembliste)"),
    ({"type": ["POURCENTAGE"], "valeur": "5"}, "type tableau"),
    ({"TYPE": "MONTANT", "VALEUR": "5"}, "clés en majuscules (type et valeur absents)"),
    ({"type": "POURCENTAGE", "valeur": 5.5}, "valeur nombre JSON"),
]:
    _n += 1
    ajouter(f"rgi-{_n:03d}", cat, f"remise_globale = {json.dumps(_v, ensure_ascii=False)} ({_note}) : REMISE_GLOBALE_INVALIDE.",
            RX(BASE, remise_globale=_v), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])

# ===========================================================================
# 25. REMISE_SUPERIEURE_AU_TOTAL (règles conditionnelles)
# ===========================================================================
cat = "remise_superieure_au_total"
ajouter("rst-001", cat, "Total brut 20,00 ; M = 20,01 : REMISE_SUPERIEURE_AU_TOTAL.", RX(BASE, remise_globale=MNT("20.01")),
        erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-002", cat, "Total brut 20,00 ; M = 20,00 (égalité autorisée) : net 0, TVA 0, TTC 0.", RX(BASE, remise_globale=MNT("20.00")),
        attendu=OK(["20.00"], "20.00", "20.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rst-003", cat, "Régime invalide + M > total (lignes valides) : REGIME_INVALIDE et REMISE_SUPERIEURE_AU_TOTAL (indépendante du régime).",
        RX(BASE, regime_tva="AUTRE", remise_globale=MNT("50")), erreurs=[("REGIME_INVALIDE", None), ("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-004", cat, "Acompte invalide + M > total : ACOMPTE_INVALIDE et REMISE_SUPERIEURE_AU_TOTAL (indépendante de l'acompte).",
        RX(BASE, acompte_pct="200", remise_globale=MNT("50")), erreurs=[("ACOMPTE_INVALIDE", None), ("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-005", cat, "Erreur de ligne + M > total : seule l'erreur de ligne (REMISE_SUPERIEURE non vérifiée).",
        D([LV, LX(quantite="0")], remise=MNT("1000")), erreurs=[("QUANTITE_INVALIDE", 1)])
ajouter("rst-006", cat, "FRANCHISE + ligne à 20 % + M > total : FRANCHISE_AVEC_TVA seulement (c'est une erreur de ligne).",
        D([LX(taux_tva="20")], regime="FRANCHISE_EN_BASE", remise=MNT("1000")), erreurs=[("FRANCHISE_AVEC_TVA", 0)])
ajouter("rst-007", cat, "Ligne non objet + M > total : les 5 codes de ligne seulement.",
        D([LV, "x"], remise=MNT("1000")), erreurs=[(c, 1) for c in CINQ])
ajouter("rst-008", cat, "M mal formé (3 décimales) et supérieur au total : REMISE_GLOBALE_INVALIDE seulement.",
        RX(BASE, remise_globale=MNT("1000.001")), erreurs=[("REMISE_GLOBALE_INVALIDE", None)])
ajouter("rst-009", cat, "FRANCHISE, lignes valides à taux 0, M > total : REMISE_SUPERIEURE_AU_TOTAL.",
        D([LX(taux_tva="0")], regime="FRANCHISE_EN_BASE", remise=MNT("20.01")), erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-010", cat, "Régime invalide \"FRANCHISE\" + ligne à 20 % (pas d'erreur de ligne car régime invalide) + M > total : REGIME_INVALIDE et REMISE_SUPERIEURE_AU_TOTAL.",
        RX(BASE, regime_tva="FRANCHISE", remise_globale=MNT("20.01")), erreurs=[("REGIME_INVALIDE", None), ("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-011", cat, "Comparaison au total brut ARRONDI : 0,001 × 4,99 = 0,00499 → 0,00 ; M = 0,01 > 0,00 : REMISE_SUPERIEURE_AU_TOTAL.",
        D([L("0.001", "4.99", "20")], remise=MNT("0.01")), erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-012", cat, "Comparaison au total brut ARRONDI : 0,005 × 1 = 0,005 → 0,01 ; M = 0,01 = total : valide, tout est nul.",
        D([L("0.005", "1", "20")], remise=MNT("0.01")),
        attendu=OK(["0.01"], "0.01", "0.01", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rst-013", cat, "Total brut nul (remise de ligne 100 %) et M = 0,01 : REMISE_SUPERIEURE_AU_TOTAL.",
        D([LX(remise_pct="100")], remise=MNT("0.01")), erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-014", cat, "Total multi-lignes 3 × 33,33 + 0,01 = 100,00 ; M = 100,01 : REMISE_SUPERIEURE_AU_TOTAL.",
        D([L("3", "33.33", "10"), L("1", "0.01", "20")], remise=MNT("100.01")), erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-015", cat, "Régime invalide + acompte invalide + M > total : les trois erreurs globales.",
        RX(BASE, regime_tva=None, acompte_pct="-1", remise_globale=MNT("99")),
        erreurs=[("REGIME_INVALIDE", None), ("ACOMPTE_INVALIDE", None), ("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-016", cat, "FRANCHISE, seule erreur = FRANCHISE_AVEC_TVA sur la ligne 1 (ligne par ailleurs valide) + M > total : FRANCHISE_AVEC_TVA@1 seulement.",
        D([LX(taux_tva="0"), LX(taux_tva="10")], regime="FRANCHISE_EN_BASE", remise=MNT("500")), erreurs=[("FRANCHISE_AVEC_TVA", 1)])
ajouter("rst-017", cat, "Comparaison NUMÉRIQUE (pas lexicographique) : total 100,00 ; M = \"9.50\" (> \"100.00\" en ordre de chaîne) → valide. "
        "Net 90,50 ; TVA 20 % 18,10 ; TTC 108,60.",
        D([L("1", "100", "20")], remise=MNT("9.50")),
        attendu=OK(["100.00"], "100.00", "9.50", "90.50", [("20", "90.50", "18.10")], "18.10", "108.60", "0.00", "108.60"))
ajouter("rst-018", cat, "M avec zéros de tête \"0020.01\" (= 20,01) > total 20,00 : REMISE_SUPERIEURE_AU_TOTAL.",
        RX(BASE, remise_globale=MNT("0020.01")), erreurs=[("REMISE_SUPERIEURE_AU_TOTAL", None)])
ajouter("rst-019", cat, "M > total mais remise POURCENTAGE (pas MONTANT) : pas de REMISE_SUPERIEURE ; 100 % → net 0.",
        RX(BASE, remise_globale=PCT("100")),
        attendu=OK(["20.00"], "20.00", "20.00", "0.00", [("20", "0.00", "0.00")], "0.00", "0.00", "0.00", "0.00"))
ajouter("rst-020", cat, "M > total + lignes absentes : LIGNES_VIDES seulement.",
        RX(BASE, lignes=SUPPR, remise_globale=MNT("5")), erreurs=[("LIGNES_VIDES", None)])

# ===========================================================================
# 26. Erreurs multiples combinées
# ===========================================================================
cat = "erreurs_multiples"
ajouter("emu-001", cat, "Ligne 0 quantité 0, ligne 1 prix à 3 décimales, acompte 101 : QUANTITE@0, PRIX@1, ACOMPTE@null.",
        D([LX(quantite="0"), LX(prix_unitaire_ht="1.234")], acompte="101"),
        erreurs=[("QUANTITE_INVALIDE", 0), ("PRIX_INVALIDE", 1), ("ACOMPTE_INVALIDE", None)])
ajouter("emu-002", cat, "Régime inconnu, lignes vides, remise de type inconnu, acompte non numérique : 4 erreurs globales.",
        {"regime_tva": "TVA", "lignes": [], "remise_globale": {"type": "X", "valeur": "1"}, "acompte_pct": "abc"},
        erreurs=[("REGIME_INVALIDE", None), ("LIGNES_VIDES", None), ("REMISE_GLOBALE_INVALIDE", None), ("ACOMPTE_INVALIDE", None)])
ajouter("emu-003", cat, "Une ligne avec les 6 champs invalides (désignation blanche, quantité négative, unité minuscule, prix à virgule, taux 19.6, remise 101) : 6 codes @0.",
        D([{"designation": " ", "quantite": "-1", "unite": "m2", "prix_unitaire_ht": "1,5", "taux_tva": "19.6", "remise_pct": "101"}]),
        erreurs=[("DESIGNATION_VIDE", 0), ("QUANTITE_INVALIDE", 0), ("UNITE_INVALIDE", 0), ("PRIX_INVALIDE", 0), ("TAUX_INVALIDE", 0), ("REMISE_LIGNE_INVALIDE", 0)])
ajouter("emu-004", cat, "FRANCHISE ; lignes [taux 20, taux \"7\", taux 0 avec quantité invalide] : FRANCHISE@0, TAUX@1, QUANTITE@2.",
        D([LX(taux_tva="20"), LX(taux_tva="7"), LX(taux_tva="0", quantite="1e2")], regime="FRANCHISE_EN_BASE"),
        erreurs=[("FRANCHISE_AVEC_TVA", 0), ("TAUX_INVALIDE", 1), ("QUANTITE_INVALIDE", 2)])
ajouter("emu-005", cat, "4 lignes, erreurs sur les index 0 et 3 seulement : QUANTITE@0, UNITE@3.",
        D([LX(quantite="abc"), LV, LV, LX(unite="KG")]), erreurs=[("QUANTITE_INVALIDE", 0), ("UNITE_INVALIDE", 3)])
ajouter("emu-006", cat, "Objet vide {} : REGIME_INVALIDE et LIGNES_VIDES.", brute="{}", erreurs=[("REGIME_INVALIDE", None), ("LIGNES_VIDES", None)])
ajouter("emu-007", cat, "lignes objet (non tableau) + acompte invalide : LIGNES_VIDES et ACOMPTE_INVALIDE.",
        RX(BASE, lignes={"0": LV}, acompte_pct="50.5.5"), erreurs=[("LIGNES_VIDES", None), ("ACOMPTE_INVALIDE", None)])
ajouter("emu-008", cat, "Tout est faux : régime, ligne (5 champs + remise), remise globale, acompte.",
        {"regime_tva": "normal", "lignes": [{"designation": "", "quantite": 1, "unite": "M4", "prix_unitaire_ht": "-3", "taux_tva": "21", "remise_pct": "-1"}],
         "remise_globale": {"type": "MONTANT", "valeur": "x"}, "acompte_pct": 10},
        erreurs=[("REGIME_INVALIDE", None), ("DESIGNATION_VIDE", 0), ("QUANTITE_INVALIDE", 0), ("UNITE_INVALIDE", 0), ("PRIX_INVALIDE", 0),
                 ("TAUX_INVALIDE", 0), ("REMISE_LIGNE_INVALIDE", 0), ("REMISE_GLOBALE_INVALIDE", None), ("ACOMPTE_INVALIDE", None)])
ajouter("emu-009", cat, "FRANCHISE ; lignes [null, taux 20] : 5 codes @0 + FRANCHISE_AVEC_TVA@1.",
        D([None, LX(taux_tva="20")], regime="FRANCHISE_EN_BASE"), erreurs=[(c, 0) for c in CINQ] + [("FRANCHISE_AVEC_TVA", 1)])
ajouter("emu-010", cat, "Même erreur sur 3 lignes : DESIGNATION_VIDE@0, @1, @2.",
        D([LX(designation=""), LX(designation="  "), LX(designation=None)]),
        erreurs=[("DESIGNATION_VIDE", 0), ("DESIGNATION_VIDE", 1), ("DESIGNATION_VIDE", 2)])
ajouter("emu-011", cat, "Régime invalide + erreur sur la ligne 1 + M > total : REGIME_INVALIDE et TAUX@1 (pas de REMISE_SUPERIEURE).",
        D([LV, LX(taux_tva="15")], regime="", remise=MNT("999")), erreurs=[("REGIME_INVALIDE", None), ("TAUX_INVALIDE", 1)])
ajouter("emu-012", cat, "12 lignes, erreurs variées sur les index 1, 4, 7, 10, 11.",
        D([LV, LX(prix_unitaire_ht="10.001"), LV, LV, LX(remise_pct="100.5"), LV, LV, LX(unite=None), LV, LV, LX(designation=SUPPR), LX(taux_tva="5.5 ")]),
        erreurs=[("PRIX_INVALIDE", 1), ("REMISE_LIGNE_INVALIDE", 4), ("UNITE_INVALIDE", 7), ("DESIGNATION_VIDE", 10), ("TAUX_INVALIDE", 11)])
ajouter("emu-013", cat, "Quantité ET prix ET remise invalides sur la même ligne : 3 codes @0.",
        D([LX(quantite="0", prix_unitaire_ht="1.001", remise_pct="abc")]),
        erreurs=[("QUANTITE_INVALIDE", 0), ("PRIX_INVALIDE", 0), ("REMISE_LIGNE_INVALIDE", 0)])
ajouter("emu-014", cat, "lignes absent + régime absent + remise globale invalide : 3 erreurs globales.",
        {"remise_globale": {"type": "POURCENTAGE", "valeur": "101"}},
        erreurs=[("REGIME_INVALIDE", None), ("LIGNES_VIDES", None), ("REMISE_GLOBALE_INVALIDE", None)])
ajouter("emu-015", cat, "FRANCHISE, 3 lignes à taux 5.5 / 10 / 20 + acompte invalide : FRANCHISE@0,1,2 + ACOMPTE.",
        D([LX(taux_tva="5.5"), LX(taux_tva="10"), LX(taux_tva="20")], regime="FRANCHISE_EN_BASE", acompte="abc"),
        erreurs=[("FRANCHISE_AVEC_TVA", 0), ("FRANCHISE_AVEC_TVA", 1), ("FRANCHISE_AVEC_TVA", 2), ("ACOMPTE_INVALIDE", None)])

# ===========================================================================
# 27. Champs inconnus ignorés
# ===========================================================================
cat = "champs_inconnus"
_attendu_base = OK(["20.00"], "20.00", "0.00", "20.00", [("20", "20.00", "4.00")], "4.00", "24.00", "0.00", "24.00")
ajouter("inc-001", cat, "Champs inconnus à la racine (objet, null, nombre, chaîne invalide) : ignorés.",
        RX(BASE, client={"nom": "Dupont", "tel": None}, notes=None, version=2, remise_globale_bis="abc", acompte="xyz"), attendu=_attendu_base)
ajouter("inc-002", cat, "Champs inconnus dans la ligne (tva nombre, remise chaîne invalide, ref null) : ignorés.",
        D([LX(tva=20, remise="abc", ref=None, quantite_bis="-1")]), attendu=_attendu_base)
ajouter("inc-003", cat, "Champ inconnu dans remise_globale : ignoré. Remise 10 % de 20 = 2 ; net 18 ; TVA 3,60 ; TTC 21,60.",
        RX(BASE, remise_globale={"type": "POURCENTAGE", "valeur": "10", "libelle": "Geste commercial", "devise": 1}),
        attendu=OK(["20.00"], "20.00", "2.00", "18.00", [("20", "18.00", "3.60")], "3.60", "21.60", "0.00", "21.60"))
ajouter("inc-004", cat, "Clé mal orthographiée \"remise_pourcent\" : inconnue donc ignorée (pas de remise appliquée).",
        D([LX(remise_pourcent="50")]), attendu=_attendu_base)
ajouter("inc-005", cat, "Clés \"ok\" et \"erreurs\" en entrée : inconnues, ignorées.",
        RX(BASE, ok=False, erreurs=[{"code": "X"}]), attendu=_attendu_base)

# ===========================================================================
# 28. Devis aléatoires valides (graine fixe)
# ===========================================================================
cat = "aleatoire_valide"
_rng = random.Random(20261002)


def _rdec(max_ent: int, max_dec: int, positif: bool = False) -> str:
    while True:
        e = _rng.randint(0, max_ent)
        nd = _rng.randint(0, max_dec)
        s = str(e) if nd == 0 else f"{e}.{_rng.randint(0, 10**nd - 1):0{nd}d}"
        if not positif or Fraction(s) > 0:
            return s


for _k in range(30):
    _franchise = _rng.random() < 0.2
    _lignes = []
    for _j in range(_rng.randint(1, 8)):
        _taux = "0" if _franchise else _rng.choice(["0", "5.5", "10", "10", "20", "20", "20"])
        _r = ...
        if _rng.random() < 0.35:
            _r = _rng.choice(["0", "100", _rdec(99, 2), _rdec(99, 2), None])
        _lignes.append(L(_rdec(_rng.choice([1, 10, 100, 1000]), 3, True), _rdec(_rng.choice([10, 100, 1000, 10000]), 2), _taux,
                         _rng.choice(["U", "M2", "ML", "M3", "H", "FORFAIT"]), f"Poste {_j}", remise=_r))
    _d = D(_lignes, regime="FRANCHISE_EN_BASE" if _franchise else "NORMAL")
    _choix = _rng.random()
    if _choix < 0.35:
        _d["remise_globale"] = PCT(_rng.choice(["100", _rdec(30, 2), _rdec(99, 2)]))
    elif _choix < 0.7:
        _brut = sum(int(Fraction(m["montant_ht"]) * 100) for m in calcul_independant(_d)["ok"]["lignes"])
        _d["remise_globale"] = MNT(_fmt(_rng.randint(0, _brut)))
    if _rng.random() < 0.7:
        _d["acompte_pct"] = _rng.choice(["100", _rdec(60, 2), _rdec(99, 2)])
    ajouter(f"alea-{_k + 1:03d}", cat, f"Devis aléatoire valide n° {_k + 1} (graine 20261002), {len(_lignes)} ligne(s).", _d)

# ===========================================================================
# 29. Cas AMBIGUS (exclus du score principal) — attendu = comportement de la référence
# ===========================================================================
cat = "ambigu"
ajouter("amb-001", cat, "AMBIGU : designation \"\\t\\n\" (blancs non-espace). La spec dit « suppression des espaces » sans définir l'ensemble "
        "(espace ASCII seul ? blancs ASCII ? Unicode White_Space ?). Référence : trim des blancs → DESIGNATION_VIDE.",
        D([LX(designation="\t\n")]), erreurs=[("DESIGNATION_VIDE", 0)], ambigu=True)
ajouter("amb-002", cat, "AMBIGU : designation \"\\u00a0\" (espace insécable). trim() PHP ne le retire pas, str::trim() Rust et str.strip() Python oui. "
        "Référence : DESIGNATION_VIDE.", D([LX(designation="\u00a0")]), erreurs=[("DESIGNATION_VIDE", 0)], ambigu=True)
ajouter("amb-003", cat, "AMBIGU : JSON précédé d'un BOM UTF-8. La RFC 8259 interdit d'en émettre mais permet de l'ignorer. Référence : JSON_INVALIDE.",
        brute="\ufeff" + _VALIDE, erreurs=JSON_INVALIDE, ambigu=True)
ajouter("amb-004", cat, "AMBIGU : clé dupliquée regime_tva (\"X\" puis \"NORMAL\"). La spec ne dit rien ; la référence (comme json_decode PHP et serde_json::Value) "
        "garde la dernière → succès.",
        BASE, brute='{"regime_tva":"X","regime_tva":"NORMAL","lignes":[{"designation":"Ligne valide","quantite":"2","unite":"U","prix_unitaire_ht":"10.00","taux_tva":"20"}]}',
        attendu=_attendu_base, ambigu=True)
ajouter("amb-005", cat, "AMBIGU : quantite = nombre JSON 1e400 (hors plage double). Un nombre JSON est invalide pour une chaîne décimale (QUANTITE_INVALIDE), "
        "mais certains parseurs (serde_json) rejettent tout le document. Référence : QUANTITE_INVALIDE.",
        brute=_VALIDE.replace('"2"', "1e400"), erreurs=[("QUANTITE_INVALIDE", 0)], ambigu=True)
ajouter("amb-006", cat, "AMBIGU : champ inconnu imbriqué sur 200 niveaux. « Champs inconnus ignorés » vs limites de profondeur des parseurs "
        "(serde_json : 128 par défaut ; json_decode PHP : 512). Référence : ignoré → succès.",
        BASE, brute=_VALIDE[:-1] + ',"meta":' + "[" * 200 + "]" * 200 + "}", attendu=_attendu_base, ambigu=True)

# @@ SUITE DES VECTEURS @@

# ===========================================================================
# Écriture
# ===========================================================================
if __name__ == "__main__":
    sortie = ICI / "vecteurs.json"
    # Un vecteur par ligne : diff et grep lisibles, fichier compact.
    sortie.write_text("[\n" + ",\n".join(json.dumps(v, ensure_ascii=False) for v in VECTEURS) + "\n]\n", encoding="utf-8")
    par_cat: dict[str, int] = {}
    for v in VECTEURS:
        par_cat[v["categorie"]] = par_cat.get(v["categorie"], 0) + 1
    print(f"{len(VECTEURS)} vecteurs écrits dans {sortie}")
    for c, n in par_cat.items():
        print(f"  {c:32} {n}")
    print(f"  ambigus : {sum(v['ambigu'] for v in VECTEURS)}")
