#!/usr/bin/env python3
"""Contrôle de cohérence des ADR (appelé par check-docs.sh, règle 6).

Pour chaque docs/adr/NNNN-*.md : statut valide, présence dans l'index avec le même statut,
et existence des fichiers cités entre accents graves sur la ligne « Contrôle ».
Affiche une erreur par ligne ; aucune sortie = tout est cohérent.
"""

import pathlib
import re

adr_dir = pathlib.Path("docs/adr")
valid = ("Proposed", "Accepted", "Superseded by", "Informational", "Rejected")
index = (adr_dir / "README.md").read_text()
rows = {
    m.group(1): m.group(2).strip()
    for m in re.finditer(r"^\| \[(\d{4})\]\([^)]*\)\s*\|[^|]*\|\s*(.+?)\s*\|\s*$", index, re.M)
}
errors = []
seen = set()
for path in sorted(adr_dir.glob("[0-9][0-9][0-9][0-9]-*.md")):
    n = path.name[:4]
    seen.add(n)
    text = path.read_text()
    m = re.search(r"^- \*\*Status\*\* : (.+)$", text, re.M)
    if not m:
        errors.append(f"ADR {n} : ligne Status absente")
        continue
    word = next((v for v in valid if m.group(1).startswith(v)), None)
    if word is None:
        errors.append(f"ADR {n} : statut inconnu « {m.group(1)[:40]} »")
        continue
    if n not in rows:
        errors.append(f"ADR {n} absent de l'index")
    elif rows[n].split()[0] != word.split()[0]:
        errors.append(f"ADR {n} : index « {rows[n]} », fichier « {word} »")
    control = re.search(r"^- \*\*Contrôle\*\* : (.+)$", text, re.M)
    if control and not control.group(1).strip().lower().startswith("non contrôlé"):
        for token in re.findall(r"`([^`]+)`", control.group(1)):
            if not pathlib.Path(token.split("::")[0]).exists():
                errors.append(f"ADR {n} : le contrôle cité `{token}` n'existe pas")
for n in sorted(set(rows) - seen):
    errors.append(f"index : ADR {n} sans fichier")
print("\n".join(errors))
