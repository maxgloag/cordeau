#!/usr/bin/env python3
"""Exécute les vecteurs cachés contre une commande CLI (expérience B — totaux d'un devis).

Usage :
    python3 executer.py "<commande_wrapper>" [--json rapport.json] [--vecteurs vecteurs.json]
                        [--jobs N] [--timeout 20] [--ids id1,id2] [--max-echecs N]

La commande est lancée via le shell, une fois par vecteur, avec `entree_brute` (UTF-8) sur stdin.

Règles de comparaison :
- succès attendu (`attendu` = {"ok": ...}) : la sortie stdout doit être un JSON strictement égal
  (mêmes clés, mêmes types, mêmes valeurs — "1.00" ≠ 1.0, false ≠ 0) ;
- erreurs attendues (`attendu` = {"erreurs": [...]}) : stdout doit être un objet JSON dont la clé
  "erreurs" est une liste d'objets {code: str, ligne: int|null} ; on compare l'ENSEMBLE des paires
  (code, ligne) (ordre et doublons ignorés, champs supplémentaires des erreurs ignorés) ;
- le code de sortie doit être égal à `code_sortie`.
Toute sortie non JSON, timeout ou crash compte comme un échec.

Les vecteurs `"ambigu": true` sont exécutés et rapportés à part, hors score principal.
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

ICI = Path(__file__).resolve().parent


def egal_strict(a: object, b: object) -> bool:
    """Égalité JSON stricte : distingue bool/int/float/str (contrairement à == en Python)."""
    if type(a) is not type(b):
        return False
    if isinstance(a, dict):
        return a.keys() == b.keys() and all(egal_strict(a[k], b[k]) for k in a)  # type: ignore[index]
    if isinstance(a, list):
        return len(a) == len(b) and all(egal_strict(x, y) for x, y in zip(a, b))  # type: ignore[arg-type]
    return a == b


def differences(attendu: object, obtenu: object, chemin: str = "$") -> list[str]:
    """Liste lisible des différences entre deux valeurs JSON."""
    if type(attendu) is not type(obtenu):
        return [f"{chemin} : attendu {json.dumps(attendu, ensure_ascii=False)} , obtenu {json.dumps(obtenu, ensure_ascii=False)}"]
    if isinstance(attendu, dict):
        diffs = []
        for k in attendu:
            if k not in obtenu:  # type: ignore[operator]
                diffs.append(f"{chemin}.{k} : clé manquante (attendu {json.dumps(attendu[k], ensure_ascii=False)})")
            else:
                diffs += differences(attendu[k], obtenu[k], f"{chemin}.{k}")  # type: ignore[index]
        for k in obtenu:  # type: ignore[union-attr]
            if k not in attendu:
                diffs.append(f"{chemin}.{k} : clé inattendue (obtenu {json.dumps(obtenu[k], ensure_ascii=False)})")  # type: ignore[index]
        return diffs
    if isinstance(attendu, list):
        diffs = []
        if len(attendu) != len(obtenu):  # type: ignore[arg-type]
            diffs.append(f"{chemin} : longueur attendue {len(attendu)}, obtenue {len(obtenu)}")  # type: ignore[arg-type]
        for i, (x, y) in enumerate(zip(attendu, obtenu)):  # type: ignore[arg-type]
            diffs += differences(x, y, f"{chemin}[{i}]")
        return diffs
    if attendu != obtenu:
        return [f"{chemin} : attendu {json.dumps(attendu, ensure_ascii=False)} , obtenu {json.dumps(obtenu, ensure_ascii=False)}"]
    return []


def paires_erreurs(sortie: object) -> tuple[set[tuple[str, int | None]] | None, str | None]:
    """Extrait l'ensemble des paires (code, ligne) ; None + motif si la forme est invalide."""
    if not isinstance(sortie, dict):
        return None, "la sortie n'est pas un objet JSON"
    if "erreurs" not in sortie:
        return None, f"clé \"erreurs\" absente (clés : {sorted(sortie)})"
    if "ok" in sortie:
        return None, "la sortie contient à la fois \"ok\" et \"erreurs\""
    erreurs = sortie["erreurs"]
    if not isinstance(erreurs, list):
        return None, "\"erreurs\" n'est pas une liste"
    paires: set[tuple[str, int | None]] = set()
    for e in erreurs:
        if not isinstance(e, dict) or type(e.get("code")) is not str or "ligne" not in e:
            return None, f"erreur mal formée : {json.dumps(e, ensure_ascii=False)}"
        ligne = e["ligne"]
        if not (ligne is None or type(ligne) is int):
            return None, f"\"ligne\" doit être un entier ou null : {json.dumps(e, ensure_ascii=False)}"
        paires.add((e["code"], ligne))
    return paires, None


def fmt_paires(paires: set[tuple[str, int | None]]) -> str:
    return ", ".join(f"{c}@{'null' if l is None else l}" for c, l in sorted(paires, key=lambda p: (p[0], -1 if p[1] is None else p[1])))


def executer_vecteur(commande: str, v: dict, delai: float) -> dict:
    debut = time.monotonic()
    resultat = {"id": v["id"], "categorie": v["categorie"], "ambigu": v.get("ambigu", False), "ok": False, "motifs": []}
    try:
        proc = subprocess.run(
            commande,
            shell=True,
            input=v["entree_brute"].encode("utf-8"),
            capture_output=True,
            timeout=delai,
        )
    except subprocess.TimeoutExpired:
        resultat["motifs"].append(f"timeout (> {delai:g} s)")
        resultat["duree_ms"] = round((time.monotonic() - debut) * 1000)
        return resultat
    resultat["duree_ms"] = round((time.monotonic() - debut) * 1000)
    stdout = proc.stdout.decode("utf-8", errors="replace")
    stderr = proc.stderr.decode("utf-8", errors="replace")
    resultat["code_sortie"] = proc.returncode
    resultat["stdout"] = stdout[:2000]
    if stderr.strip():
        resultat["stderr"] = stderr[:1000]

    if proc.returncode != v["code_sortie"]:
        resultat["motifs"].append(f"code de sortie attendu {v['code_sortie']}, obtenu {proc.returncode}")

    try:
        sortie = json.loads(stdout)
    except ValueError as exc:
        resultat["motifs"].append(f"sortie non JSON ({exc.__class__.__name__}: {exc}) : {stdout[:200]!r}")
        return resultat

    attendu = v["attendu"]
    if "erreurs" in attendu:
        attendues = {(e["code"], e["ligne"]) for e in attendu["erreurs"]}
        obtenues, motif = paires_erreurs(sortie)
        if obtenues is None:
            resultat["motifs"].append(f"forme d'erreur invalide : {motif} ; sortie : {stdout[:300]!r}")
        elif obtenues != attendues:
            manquantes = attendues - obtenues
            en_trop = obtenues - attendues
            if manquantes:
                resultat["motifs"].append(f"erreurs manquantes : {fmt_paires(manquantes)}")
            if en_trop:
                resultat["motifs"].append(f"erreurs en trop : {fmt_paires(en_trop)}")
    else:
        if not egal_strict(attendu, sortie):
            diffs = differences(attendu, sortie)
            resultat["motifs"] += diffs[:15] or ["sortie différente (types JSON différents)"]
            if len(diffs) > 15:
                resultat["motifs"].append(f"... {len(diffs) - 15} autres différences")

    resultat["ok"] = not resultat["motifs"]
    return resultat


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("commande", help="commande shell lisant le JSON sur stdin (ex. \"python3 reference.py\")")
    ap.add_argument("--json", dest="rapport_json", help="écrit le rapport détaillé en JSON dans ce fichier")
    ap.add_argument("--vecteurs", default=str(ICI / "vecteurs.json"))
    ap.add_argument("--jobs", type=int, default=1, help="exécutions parallèles (défaut 1)")
    ap.add_argument("--timeout", type=float, default=20.0, help="délai par cas en secondes (défaut 20)")
    ap.add_argument("--ids", help="liste d'ids séparés par des virgules (filtre)")
    ap.add_argument("--max-echecs", type=int, default=200, help="nombre max d'échecs détaillés affichés")
    args = ap.parse_args()

    vecteurs = json.loads(Path(args.vecteurs).read_text(encoding="utf-8"))
    if args.ids:
        voulus = set(args.ids.split(","))
        vecteurs = [v for v in vecteurs if v["id"] in voulus]

    debut = time.monotonic()
    with ThreadPoolExecutor(max_workers=max(1, args.jobs)) as pool:
        resultats = list(pool.map(lambda v: executer_vecteur(args.commande, v, args.timeout), vecteurs))
    duree = time.monotonic() - debut

    principaux = [r for r in resultats if not r["ambigu"]]
    ambigus = [r for r in resultats if r["ambigu"]]

    # Rapport par catégorie (hors ambigus)
    categories: dict[str, list[int]] = {}
    for r in principaux:
        c = categories.setdefault(r["categorie"], [0, 0])
        c[1] += 1
        c[0] += r["ok"]
    print(f"Commande : {args.commande}")
    print(f"Vecteurs : {len(vecteurs)} ({len(principaux)} principaux, {len(ambigus)} ambigus) — durée {duree:.1f} s\n")
    print(f"{'catégorie':34} {'réussis':>9} {'total':>6} {'taux':>7}")
    print("-" * 60)
    for nom, (ok, total) in categories.items():
        print(f"{nom:34} {ok:>9} {total:>6} {100 * ok / total:>6.1f}%")
    print("-" * 60)
    nb_ok = sum(r["ok"] for r in principaux)
    taux = 100 * nb_ok / len(principaux) if principaux else 0.0
    print(f"{'TOTAL (hors ambigus)':34} {nb_ok:>9} {len(principaux):>6} {taux:>6.1f}%")
    if ambigus:
        nb_amb = sum(r["ok"] for r in ambigus)
        print(f"{'ambigus (hors score)':34} {nb_amb:>9} {len(ambigus):>6}")

    echecs = [r for r in principaux if not r["ok"]]
    if echecs:
        print(f"\nÉchecs ({len(echecs)}) :")
        for r in echecs[: args.max_echecs]:
            print(f"\n✗ {r['id']} [{r['categorie']}]")
            for m in r["motifs"]:
                print(f"    - {m}")
            if r.get("stderr"):
                print(f"    stderr : {r['stderr'][:300]!r}")
    amb_echecs = [r for r in ambigus if not r["ok"]]
    if amb_echecs:
        print(f"\nDivergences sur cas ambigus ({len(amb_echecs)}, hors score) :")
        for r in amb_echecs:
            print(f"  ~ {r['id']} : " + " | ".join(r["motifs"]))

    if args.rapport_json:
        rapport = {
            "commande": args.commande,
            "duree_s": round(duree, 2),
            "total_principal": len(principaux),
            "reussis_principal": nb_ok,
            "taux_principal": round(taux, 2),
            "par_categorie": {k: {"reussis": v[0], "total": v[1]} for k, v in categories.items()},
            "ambigus": {"total": len(ambigus), "reussis": sum(r["ok"] for r in ambigus)},
            "resultats": resultats,
        }
        Path(args.rapport_json).write_text(json.dumps(rapport, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
        print(f"\nRapport JSON : {args.rapport_json}")

    return 0 if not echecs else 1


if __name__ == "__main__":
    sys.exit(main())
