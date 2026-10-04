#!/usr/bin/env bash
# stop-verify.sh — hook Stop de Claude Code (issue #137).
# Vérifie de façon déterministe, en fin de tour, ce que le tour a touché.
# Code 2 + stderr = la fin de tour est bloquée et le rapport est renvoyé à Claude.
# Code 0 = rien à signaler (ou rien à vérifier, ou outil absent : on n'invente pas d'échec).
#
# Portée : fichiers de la branche par rapport à l'ancêtre commun avec origin/main
# (commités ou non) plus les fichiers non suivis. Un tour qui commite avant de
# s'arrêter reste donc contrôlé. Sans changement, le coût est de quelques commandes git.
#
# Anti-boucle : après un blocage, la correction est revérifiée. Au bout de
# MAX_BLOCKS blocages consécutifs dans la même session, on laisse conclure.
# Désactivation ponctuelle : CORDEAU_SKIP_STOP_VERIFY=1.
#
# Test manuel : echo '{}' | ./scripts/stop-verify.sh

set -uo pipefail

[ "${CORDEAU_SKIP_STOP_VERIFY:-}" = "1" ] && exit 0

INPUT=$(cat)
SESSION=$(printf '%s' "$INPUT" | jq -r '.session_id // "nosession"' 2>/dev/null || echo nosession)
COUNTER="${TMPDIR:-/tmp}/cordeau-stop-verify-$SESSION"
MAX_BLOCKS=3

ROOT=${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)} || exit 0
cd "$ROOT" || exit 0
export PATH="/opt/homebrew/bin:$PATH"

# Base de comparaison : ancêtre commun avec origin/main, à défaut HEAD.
BASE_REF=origin/main
BASE=$(git merge-base HEAD "$BASE_REF" 2>/dev/null) || { BASE=HEAD; BASE_REF=HEAD; }

CHANGED=$( { git diff --name-only --diff-filter=d "$BASE"; git ls-files --others --exclude-standard; } 2>/dev/null | sort -u)
if [ -z "$CHANGED" ]; then
  rm -f "$COUNTER"
  exit 0
fi

REPORT=""
# check <libellé> <commande…> : exécute, et consigne les 40 dernières lignes si elle échoue
# (les erreurs sont en fin de sortie).
check() {
  local label=$1 out
  shift
  out=$("$@" 2>&1) || REPORT="$REPORT
### ❌ $label
$(printf '%s' "$out" | tail -n 40)
"
}

# --- API : PHPStan ciblé + PHPUnit filtré ---------------------------------
API_PHP=$(printf '%s\n' "$CHANGED" | grep -E '^apps/api/(src|tests)/.*\.php$' | sed 's|^apps/api/||')
if [ -n "$API_PHP" ] && command -v php >/dev/null 2>&1 && [ -x apps/api/vendor/bin/phpstan ]; then
  cd apps/api || exit 0
  # shellcheck disable=SC2086
  check "PHPStan (fichiers modifiés)" ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M $API_PHP
  # PHPUnit : tests unitaires (sans DB, < 1 s) + tests d'intégration modifiés.
  # Les autres tests d'intégration restent à la CI : ils exigent Postgres.
  check "PHPUnit tests/Unit" php bin/phpunit tests/Unit
  for T in $(printf '%s\n' "$API_PHP" | grep -E '^tests/Integration/.*Test\.php$'); do
    check "PHPUnit $T" php bin/phpunit "$T"
  done
  cd "$ROOT" || exit 0
fi

# --- TypeScript : tsc + tests liés au paquet touché -----------------------
ts_pkg() { # $1 = dossier, $2 = filtre pnpm, $3 = type-check, $4 = tests
  printf '%s\n' "$CHANGED" | grep -qE "^$1/.*\.(ts|tsx|js|jsx|json)$" || return 0
  [ -d "$1/node_modules" ] || return 0
  # shellcheck disable=SC2086
  check "tsc ($2)" pnpm --filter "$2" exec $3
  # shellcheck disable=SC2086
  check "Tests ($2)" pnpm --filter "$2" exec $4
}
ts_pkg apps/web @cordeau/web "tsc -b --noEmit" "vitest run --changed $BASE_REF --passWithNoTests --config vitest.config.ts"
ts_pkg apps/mobile @cordeau/mobile "tsc --noEmit" "jest --changedSince=$BASE_REF --passWithNoTests"

if [ -z "$REPORT" ]; then
  rm -f "$COUNTER"
  exit 0
fi

BLOCKS=$(cat "$COUNTER" 2>/dev/null || echo 0)
if [ "$BLOCKS" -ge "$MAX_BLOCKS" ]; then
  rm -f "$COUNTER"
  exit 0
fi
echo $((BLOCKS + 1)) >"$COUNTER"

{
  echo "Vérification de fin de tour (scripts/stop-verify.sh) : ÉCHEC. Corrige avant de conclure."
  printf '%s\n' "$REPORT"
} >&2
exit 2
