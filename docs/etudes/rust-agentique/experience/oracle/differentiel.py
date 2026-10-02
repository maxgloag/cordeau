#!/usr/bin/env python3
"""Test différentiel : envoie des entrées aléatoires aux 3 implémentations
(référence Python de l'oracle, B-PHP, B-Rust) et relève les désaccords.

Usage : python3 differentiel.py N graine
"""
import json
import random
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor

ORACLE = "/private/tmp/claude-503/-Users-maxime-cordeau/44a31780-719d-4515-b424-f2d87d57d7eb/scratchpad/oracle"
IMPLS = {
    "python": f"python3 {ORACLE}/reference.py",
    "php": "/Users/maxime/cordeau/.claude/worktrees/agent-ab6ecd713ce85860f/experience/run.sh",
    "rust": "/Users/maxime/cordeau/.claude/worktrees/agent-a10cbcf5100a20564/experience/run.sh",
}

UNITES = ["U", "M2", "ML", "M3", "H", "FORFAIT"]
TAUX = ["0", "5.5", "10", "20"]


def dec(r, entier_max, decimales_max):
    """Chaîne décimale aléatoire, avec des valeurs proches des frontières d'arrondi."""
    choix = r.random()
    if choix < 0.15:
        return r.choice(["0", "1", "0.005", "0.015", "1.005", "2.675", "0.285", "0.5", "100", "99.99"])
    entier = r.randint(0, entier_max)
    nb = r.randint(0, decimales_max)
    if nb == 0:
        return str(entier)
    frac = "".join(r.choice("0123456789") for _ in range(nb))
    if r.random() < 0.3:
        frac = frac[:-1] + "5"
    return f"{entier}.{frac}"


def ligne_valide(r, franchise):
    ligne = {
        "designation": r.choice(["Pose carrelage", "Peinture", "Dépose", "x", "Main d'œuvre"]),
        "quantite": dec(r, 500, 3),
        "unite": r.choice(UNITES),
        "prix_unitaire_ht": dec(r, 5000, 2),
        "taux_tva": "0" if franchise else r.choice(TAUX),
    }
    if ligne["quantite"] in ("0", "0.0", "0.00", "0.000") or float(ligne["quantite"]) == 0:
        ligne["quantite"] = "1"
    if r.random() < 0.4:
        ligne["remise_pct"] = dec(r, 100, 2) if r.random() < 0.9 else "100"
        if float(ligne["remise_pct"]) > 100:
            ligne["remise_pct"] = "100"
    return ligne


PERTURBATIONS = [
    lambda v: None,
    lambda v: 12,
    lambda v: 1.5,
    lambda v: True,
    lambda v: [],
    lambda v: {},
    lambda v: "",
    lambda v: " 1",
    lambda v: "1 ",
    lambda v: "-1",
    lambda v: ".5",
    lambda v: "5.",
    lambda v: "1e3",
    lambda v: "1,5",
    lambda v: "0",
    lambda v: "0.0001",
    lambda v: "100.01",
    lambda v: "1000000.001",
    lambda v: "10000000.01",
    lambda v: "99999999999999999999",
    lambda v: "1.500",
    lambda v: "  ",
    lambda v: " x ",
    lambda v: "20.0",
    lambda v: "5.50",
    lambda v: "m2",
]


def demande(r):
    franchise = r.random() < 0.2
    d = {
        "regime_tva": "FRANCHISE_EN_BASE" if franchise else "NORMAL",
        "lignes": [ligne_valide(r, franchise) for _ in range(r.randint(1, 6))],
    }
    if r.random() < 0.5:
        if r.random() < 0.5:
            d["remise_globale"] = {"type": "POURCENTAGE", "valeur": dec(r, 100, 2)}
            if float(d["remise_globale"]["valeur"]) > 100:
                d["remise_globale"]["valeur"] = "100"
        else:
            d["remise_globale"] = {"type": "MONTANT", "valeur": dec(r, 300, 2)}
    if r.random() < 0.5:
        d["acompte_pct"] = dec(r, 100, 2)
        if float(d["acompte_pct"]) > 100:
            d["acompte_pct"] = "30"
    # Perturbations aléatoires (≈ 50 % des cas)
    for _ in range(r.choice([0, 0, 1, 1, 2, 3])):
        cible = r.random()
        p = r.choice(PERTURBATIONS)
        objets = [l for l in d["lignes"] if isinstance(l, dict)]
        if cible < 0.6 and objets:
            ligne = r.choice(objets)
            champ = r.choice(["designation", "quantite", "unite", "prix_unitaire_ht", "taux_tva", "remise_pct"])
            ligne[champ] = p(ligne.get(champ))
        elif cible < 0.7 and cible >= 0.6:
            d["regime_tva"] = p(d["regime_tva"]) if r.random() < 0.7 else ("NORMAL" if franchise else "FRANCHISE_EN_BASE")
        elif cible < 0.8:
            d["remise_globale"] = r.choice([p(None), {"type": r.choice(["POURCENTAGE", "MONTANT", "X", None]), "valeur": p(None)}])
        elif cible < 0.9:
            d["acompte_pct"] = p(d.get("acompte_pct"))
        else:
            idx = r.randrange(len(d["lignes"]))
            d["lignes"][idx] = p(None)
    return d


def executer(commande, entree):
    try:
        res = subprocess.run(commande, shell=True, input=entree.encode(), capture_output=True, timeout=30)
        return res.returncode, res.stdout.decode(errors="replace").strip()
    except subprocess.TimeoutExpired:
        return -1, "TIMEOUT"


def normaliser(code, sortie):
    try:
        obj = json.loads(sortie)
    except json.JSONDecodeError:
        return (code, "NON_JSON:" + sortie[:200])
    if isinstance(obj, dict) and "erreurs" in obj:
        return (code, ("erreurs", tuple(sorted((e.get("code"), -1 if e.get("ligne") is None else e.get("ligne")) for e in obj["erreurs"]))))
    return (code, json.dumps(obj, sort_keys=True))


def cas(i, graine):
    r = random.Random(graine * 1_000_003 + i)
    entree = json.dumps(demande(r), ensure_ascii=False)
    resultats = {nom: normaliser(*executer(cmd, entree)) for nom, cmd in IMPLS.items()}
    return entree, resultats


def main():
    n = int(sys.argv[1]) if len(sys.argv) > 1 else 1000
    graine = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    desaccords = []
    nb_ok = nb_err = 0
    with ThreadPoolExecutor(max_workers=8) as ex:
        for entree, res in ex.map(lambda i: cas(i, graine), range(n)):
            valeurs = set(res.values())
            if res["python"][0] == 0:
                nb_ok += 1
            else:
                nb_err += 1
            if len(valeurs) > 1:
                desaccords.append({"entree": entree, "resultats": {k: [v[0], v[1] if isinstance(v[1], str) else list(v[1][1])] for k, v in res.items()}})
    print(f"{n} cas (graine {graine}) : {nb_ok} succès / {nb_err} erreurs selon la référence ; {len(desaccords)} désaccords")
    # Qui est minoritaire ?
    minoritaire = {"python": 0, "php": 0, "rust": 0, "tous_differents": 0}
    for d in desaccords:
        r = {k: json.dumps(v) for k, v in d["resultats"].items()}
        if r["php"] == r["rust"] != r["python"]:
            minoritaire["python"] += 1
        elif r["python"] == r["rust"] != r["php"]:
            minoritaire["php"] += 1
        elif r["python"] == r["php"] != r["rust"]:
            minoritaire["rust"] += 1
        else:
            minoritaire["tous_differents"] += 1
    print("Implémentation minoritaire :", minoritaire)
    with open(f"{ORACLE}/desaccords-{graine}.json", "w") as f:
        json.dump(desaccords, f, ensure_ascii=False, indent=1)


if __name__ == "__main__":
    main()
