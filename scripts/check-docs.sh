#!/usr/bin/env bash
# check-docs.sh — contrôle que quelques affirmations de la doc restent vraies (issue #126).
# Une doc fausse fait écrire du code faux à un agent : chaque règle compare une
# affirmation de la doc à ce que le code fait réellement. Bash + grep, sans dépendance.
#
# Usage : ./scripts/check-docs.sh   (code 1 + liste des écarts si une règle échoue)

set -uo pipefail
cd "$(git rev-parse --show-toplevel)" || exit 1

DOCS=(CLAUDE.md apps/*/CLAUDE.md docs/architecture.md docs/ENV-SETUP.md .serena/memories/*.md)
FAILURES=0

fail() {
  echo "❌ $1" >&2
  FAILURES=$((FAILURES + 1))
}

# 1. Auth web : token Bearer en localStorage → la doc ne doit pas parler de cookie de session.
if grep -q 'Authorization' apps/web/src/lib/api.ts && grep -q 'localStorage' apps/web/src/lib/api.ts; then
  if hits=$(grep -n -i 'cookie de session' "${DOCS[@]}" 2>/dev/null); then
    echo "$hits" | sed 's/^/   /' >&2
    fail "auth web : le code utilise un token Bearer en localStorage, la doc parle de cookie de session"
  fi
fi

# 2. Mobile : si le sync worker poll (setInterval), la doc ne doit pas dire « pas de polling ».
if grep -q 'setInterval' apps/mobile/hooks/useSyncWorker.ts \
  && grep -q -i 'pas de polling' apps/mobile/CLAUDE.md; then
  fail "mobile : useSyncWorker poll (setInterval), apps/mobile/CLAUDE.md dit « pas de polling »"
fi

# 3. Redis : sans ligne de configuration Redis active (hors commentaires) dans l'API, la doc ne doit pas le présenter comme utilisé.
if ! grep -rhiE '^[^#]*redis' apps/api/config/packages apps/api/fly.toml 2>/dev/null | grep -q . \
  && grep -q -E 'Redis 8<br/>Messenger' docs/architecture.md; then
  fail "redis : l'API n'a aucune config Redis, docs/architecture.md le montre comme transport Messenger/cache"
fi

# 4. Nombre d'ADR : « 0001 à 00NN » dans la memory = dernier ADR du dossier.
last_adr=$(ls docs/adr | grep -E '^[0-9]{4}-' | sort | tail -1 | cut -c1-4)
doc_adr=$(grep -o '0001 à [0-9]\{4\}' .serena/memories/project_overview.md | head -1 | grep -o '[0-9]\{4\}$')
if [ -n "$doc_adr" ] && [ "$doc_adr" != "$last_adr" ]; then
  fail "ADR : project_overview annonce 0001 à $doc_adr, le dossier va jusqu'à $last_adr"
fi

# 5. Version d'Expo : « Expo SDK NN » dans la doc = version majeure de apps/mobile/package.json.
expo_major=$(grep -E '^\s*"expo": ' apps/mobile/package.json | grep -oE '[0-9]+' | head -1)
for f in docs/architecture.md .serena/memories/architecture.md .serena/memories/project_overview.md; do
  for v in $(grep -oE 'Expo SDK [0-9]+' "$f" | grep -oE '[0-9]+$'); do
    [ "$v" != "$expo_major" ] && fail "Expo : $f annonce SDK $v, package.json est en SDK $expo_major"
  done
done

# 6. ADR : statut valide, index == dossier (même statut), fichiers cités par la ligne « Contrôle » existants.
ADR_ERRORS=$(python3 scripts/check-adr.py)
if [ -n "$ADR_ERRORS" ]; then
  echo "$ADR_ERRORS" | sed 's/^/   /' >&2
  fail "ADR : statut, index ou contrôle incohérent (cf docs/adr/README.md)"
fi

if [ "$FAILURES" -gt 0 ]; then
  echo "check-docs : $FAILURES écart(s) entre la doc et le code. Corriger la doc (ou le code) dans la même PR." >&2
  exit 1
fi
echo "check-docs : 6 règles OK"
