#!/usr/bin/env bash
# stop-verify.sh — hook Stop de Claude Code (issue #137).
# Vérifie de façon déterministe, en fin de tour, ce que le tour a touché.
# Code 2 + stderr = la fin de tour est bloquée et le rapport est renvoyé à Claude.
# Code 0 = rien à signaler (ou rien à vérifier, ou outil absent : on n'invente pas d'échec).
#
# Portée : fichiers modifiés ou non suivis par rapport à HEAD. Un tour sans
# modification de code ne coûte que deux commandes git.
# Désactivation ponctuelle : CORDEAU_SKIP_STOP_VERIFY=1.
#
# Test manuel : echo '{}' | ./scripts/stop-verify.sh

set -uo pipefail

[ "${CORDEAU_SKIP_STOP_VERIFY:-}" = "1" ] && exit 0

# Anti-boucle : si Claude vient déjà de corriger suite à un blocage, on le laisse conclure.
INPUT=$(cat)
[ "$(printf '%s' "$INPUT" | jq -r '.stop_hook_active // false' 2>/dev/null)" = "true" ] && exit 0

ROOT=${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)} || exit 0
cd "$ROOT" || exit 0
export PATH="/opt/homebrew/bin:$PATH"

CHANGED=$( { git diff --name-only --diff-filter=d HEAD; git ls-files --others --exclude-standard; } 2>/dev/null | sort -u)
[ -z "$CHANGED" ] && exit 0

REPORT=""
fail() { REPORT="$REPORT
### ❌ $1
$2
"; }

# Dernières lignes utiles d'une sortie (les erreurs sont en fin de rapport).
tail_lines() { tail -n 40; }

# --- API : PHPStan ciblé + PHPUnit filtré ---------------------------------
API_PHP=$(printf '%s\n' "$CHANGED" | grep -E '^apps/api/(src|tests)/.*\.php$' | sed 's|^apps/api/||')
if [ -n "$API_PHP" ] && command -v php >/dev/null 2>&1 && [ -x apps/api/vendor/bin/phpstan ]; then
  # shellcheck disable=SC2086
  OUT=$(cd apps/api && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M $API_PHP 2>&1) \
    || fail "PHPStan (fichiers modifiés)" "$(printf '%s' "$OUT" | tail_lines)"

  # PHPUnit : tests modifiés + tests unitaires (sans DB, < 1 s). Les tests
  # d'intégration restent à la CI : ils exigent Postgres.
  TESTS=$(printf '%s\n' "$API_PHP" | grep -E '^tests/(Unit|Integration)/.*Test\.php$' || true)
  OUT=$(cd apps/api && php bin/phpunit tests/Unit 2>&1) \
    || fail "PHPUnit tests/Unit" "$(printf '%s' "$OUT" | tail_lines)"
  for T in $(printf '%s\n' "$TESTS" | grep '^tests/Integration/' || true); do
    OUT=$(cd apps/api && php bin/phpunit "$T" 2>&1) \
      || fail "PHPUnit $T" "$(printf '%s' "$OUT" | tail_lines)"
  done
fi

# --- TypeScript : tsc + tests liés au paquet touché -----------------------
# shellcheck disable=SC2016
ts_pkg() { # $1 = dossier, $2 = filtre pnpm, $3 = type-check, $4 = tests
  printf '%s\n' "$CHANGED" | grep -qE "^$1/.*\.(ts|tsx|js|jsx|json)$" || return 0
  [ -d "$1/node_modules" ] || return 0
  OUT=$(pnpm --filter "$2" exec $3 2>&1) || fail "tsc ($2)" "$(printf '%s' "$OUT" | tail_lines)"
  if [ -n "$4" ]; then
    OUT=$(pnpm --filter "$2" exec $4 2>&1) || fail "Tests ($2)" "$(printf '%s' "$OUT" | tail_lines)"
  fi
}
ts_pkg apps/web @cordeau/web "tsc -b --noEmit" "vitest run --changed --passWithNoTests --config vitest.config.ts"
ts_pkg apps/mobile @cordeau/mobile "tsc --noEmit" "jest --onlyChanged --passWithNoTests"

[ -z "$REPORT" ] && exit 0

{
  echo "Vérification de fin de tour (scripts/stop-verify.sh) : ÉCHEC. Corrige avant de conclure."
  printf '%s\n' "$REPORT"
} >&2
exit 2
