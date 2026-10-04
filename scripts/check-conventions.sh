#!/usr/bin/env bash
# check-conventions.sh — deux conventions du dépôt qui tenaient par discipline (revue ADR 2026-10-04).
#   1. ADR 0014 : aucun identifiant accentué dans apps/api/src (classes, fonctions, variables, fichiers).
#   2. ADR 0019 : toute action GitHub (`uses:`) est épinglée par un SHA de commit complet.
# Python 3 + bash, sans dépendance.
#
# Usage : ./scripts/check-conventions.sh   (code 1 + liste des écarts si une règle échoue)

set -uo pipefail
cd "$(git rev-parse --show-toplevel)" || exit 1

ERRORS=$(python3 - <<'PYEOF'
import pathlib, re

errors = []

# 1. Identifiants accentués (ADR 0014, règle 2).
decl = re.compile(r"\b(?:class|interface|trait|enum|function|const)\s+([A-Za-z0-9_]*[^\x00-\x7f]\w*)")
var = re.compile(r"\$([A-Za-z0-9_]*[^\x00-\x7f]\w*)")
for path in sorted(pathlib.Path("apps/api/src").rglob("*")):
    if not path.is_file():
        continue
    if any(ord(ch) > 127 for ch in path.name):
        errors.append(f"{path} : nom de fichier accentué")
    if path.suffix != ".php":
        continue
    for number, line in enumerate(path.read_text().splitlines(), 1):
        for pattern in (decl, var):
            m = pattern.search(line)
            if m:
                errors.append(f"{path}:{number} : identifiant accentué « {m.group(1)} »")

# 2. Actions épinglées par SHA (ADR 0019).
uses = re.compile(r"^\s*-?\s*uses:\s*([^\s#]+)")
pinned = re.compile(r"@[0-9a-f]{40}$")
for path in sorted(list(pathlib.Path(".github").rglob("*.yml")) + list(pathlib.Path(".github").rglob("*.yaml"))):
    for number, line in enumerate(path.read_text().splitlines(), 1):
        m = uses.match(line)
        if not m:
            continue
        ref = m.group(1)
        if ref.startswith(("./", "docker://")):
            continue
        if not pinned.search(ref):
            errors.append(f"{path}:{number} : action non épinglée par SHA « {ref} »")

print("\n".join(errors))
PYEOF
)

if [ -n "$ERRORS" ]; then
  echo "$ERRORS" | sed 's/^/❌ /' >&2
  echo "check-conventions : écart(s) à corriger (ADR 0014 et 0019)." >&2
  exit 1
fi
echo "check-conventions : 2 règles OK"
