import subprocess, json
IMPLS = {
 "python": "python3 reference.py",
 "php": "/Users/maxime/cordeau/.claude/worktrees/agent-ab6ecd713ce85860f/experience/run.sh",
 "rust": "/Users/maxime/cordeau/.claude/worktrees/agent-a10cbcf5100a20564/experience/run.sh",
}
L = lambda d, **kw: dict({"designation": d, "quantite": "1", "unite": "U", "prix_unitaire_ht": "10", "taux_tva": "20"}, **kw)
def req(lignes, **kw): return json.dumps(dict({"regime_tva": "NORMAL", "lignes": lignes}, **kw), ensure_ascii=False)
SONDES = {
 "trim NBSP": req([L(" ")]),
 "trim tab": req([L("\t")]),
 "trim \\x1c": req([L("\x1c")]),
 "trim ideographic space": req([L("　")]),
 "remise MONTANT part négative": req([L("a", prix_unitaire_ht="0.01", taux_tva="5.5"), L("b", prix_unitaire_ht="0.01", taux_tva="10"), L("c", prix_unitaire_ht="0.01", taux_tva="20")], remise_globale={"type":"MONTANT","valeur":"0.02"}),
 "remise POURCENTAGE valeur null": req([L("a")], remise_globale={"type":"POURCENTAGE","valeur":None}),
 "remise_globale null": req([L("a")], remise_globale=None),
 "BOM": "﻿" + req([L("a")]),
 "surrogate isolé": '{"regime_tva":"NORMAL","lignes":[{"designation":"\\ud800","quantite":"1","unite":"U","prix_unitaire_ht":"10","taux_tva":"20"}]}',
 "clé \\u0000": '{"\\u0000x":1,"regime_tva":"NORMAL","lignes":[{"designation":"a","quantite":"1","unite":"U","prix_unitaire_ht":"10","taux_tva":"20"}]}',
 "clé dupliquée": '{"regime_tva":"FRANCHISE_EN_BASE","regime_tva":"NORMAL","lignes":[{"designation":"a","quantite":"1","unite":"U","prix_unitaire_ht":"10","taux_tva":"20"}]}',
 "nombre JSON énorme": '{"regime_tva":"NORMAL","lignes":[{"designation":"a","quantite":1e400,"unite":"U","prix_unitaire_ht":"10","taux_tva":"20"}]}',
 "entrée vide": "",
 "racine tableau": "[]",
 "5000 lignes": req([L("x", quantite="999.999", prix_unitaire_ht="9999.99", remise_pct="33.33") for _ in range(5000)], remise_globale={"type":"MONTANT","valeur":"12345.67"}, acompte_pct="33.33"),
}
for nom, entree in SONDES.items():
    out = {}
    for k, cmd in IMPLS.items():
        r = subprocess.run(cmd, shell=True, input=entree.encode("utf-8", "surrogatepass"), capture_output=True, timeout=60)
        s = r.stdout.decode(errors="replace").strip()
        try:
            o = json.loads(s); s = json.dumps(o.get("erreurs") or {k2: o["ok"][k2] for k2 in ("total_ht_net","total_ttc","ventilation_tva")}, ensure_ascii=False)
        except Exception: s = "NON_JSON " + s[:80] + " | stderr: " + r.stderr.decode(errors="replace")[:120]
        out[k] = f"[{r.returncode}] {s[:230]}"
    accord = "OK " if len(set(out.values())) == 1 else "DIFF"
    print(f"{accord} {nom}")
    if accord == "DIFF":
        for k, v in out.items(): print(f"     {k:6} {v}")
