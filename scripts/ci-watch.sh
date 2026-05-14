#!/usr/bin/env bash
# ci-watch.sh — outil CLI manuel pour surveiller les runs CI GitHub Actions.
# Usage : echo '{"tool_input":{"command":"git push origin BRANCH"}}' | ./scripts/ci-watch.sh
#         (ou plus simplement : gh run watch <RUN_ID> --exit-status)
#
# Note : le hook PostToolUse asyncRewake configure dans .claude/settings.json
# ne se declenche pas dans la version Claude Code actuelle. Pour la
# notification automatique de fin de CI cote Claude, voir le pattern
# feedback_ci_watch_pattern (gh run watch en run_in_background).

set -euo pipefail

REPO="maxgloag/cordeau"

# Lire la commande bash depuis le JSON stdin
CMD=$(jq -r '.tool_input.command // ""' 2>/dev/null || echo "")

# Ne s'exécuter que sur un git push
echo "$CMD" | grep -q "git push" || exit 0

# Détecter la branche pushée depuis la commande
BRANCH=$(echo "$CMD" | grep -oE '[a-zA-Z0-9_/.-]+$' | tail -1 || git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if [ -z "$BRANCH" ]; then
  BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
fi
# Normaliser : supprimer le remote éventuel (origin/feat/...) → garder feat/...
BRANCH=$(echo "$BRANCH" | sed 's|^[^/]*/||')

# Laisser GitHub enregistrer les runs (délai réseau + trigger)
sleep 10

# Récupérer les IDs de tous les runs déclenchés sur cette branche
RUN_IDS=$(gh run list --repo "$REPO" --branch "$BRANCH" --limit 5 --json databaseId,status \
  -q '[.[] | select(.status != "completed")] | .[].databaseId' 2>/dev/null || echo "")

# Si tous déjà completés, prendre les 5 plus récents de la branche
if [ -z "$RUN_IDS" ]; then
  RUN_IDS=$(gh run list --repo "$REPO" --branch "$BRANCH" --limit 5 --json databaseId \
    -q '.[].databaseId' 2>/dev/null || echo "")
fi

if [ -z "$RUN_IDS" ]; then
  # Fallback : dernier run global
  RUN_IDS=$(gh run list --repo "$REPO" --limit 1 --json databaseId -q '.[0].databaseId' 2>/dev/null || echo "")
fi

if [ -z "$RUN_IDS" ]; then
  echo "⚠ Aucun run CI trouvé pour $REPO (branche: $BRANCH)" >&2
  exit 0
fi

echo "⏳ CI en cours sur $REPO (branche: $BRANCH) — runs: $(echo "$RUN_IDS" | tr '\n' ' ')" >&2

FAILED_RUNS=""
ALL_LOGS=""

for RUN_ID in $RUN_IDS; do
  # Attendre la fin de ce run
  if ! gh run watch "$RUN_ID" --repo "$REPO" --exit-status >/dev/null 2>&1; then
    FAILED_RUNS="$FAILED_RUNS $RUN_ID"
    LOGS=$(gh run view "$RUN_ID" --repo "$REPO" --log-failed 2>&1 \
      | grep -E "(##\[error\]|Error:|error[: ]|FAIL|Failed|exit code [^0]|Cannot find|No such)" \
      | grep -v "^$" \
      | head -60 \
      || true)
    RUN_NAME=$(gh run view "$RUN_ID" --repo "$REPO" --json name -q '.name' 2>/dev/null || echo "run #$RUN_ID")
    ALL_LOGS="$ALL_LOGS
### ❌ $RUN_NAME (run #$RUN_ID)
URL : https://github.com/$REPO/actions/runs/$RUN_ID
"
    if [ -n "$LOGS" ]; then
      ALL_LOGS="$ALL_LOGS$LOGS"
    else
      ALL_LOGS="$ALL_LOGS$(gh run view "$RUN_ID" --repo "$REPO" --log-failed 2>&1 | tail -40 || true)"
    fi
  fi
done

if [ -z "$FAILED_RUNS" ]; then
  echo "## CI OK — branche $BRANCH"
  echo "Tous les runs verts. La PR peut être revue/mergée."
  exit 0
fi

{
  echo "## CI FAILED — branche $BRANCH"
  echo "Runs échoués :$FAILED_RUNS"
  echo ""
  echo "$ALL_LOGS"
}

exit 2  # asyncRewake : code 2 = réveille Claude avec le contenu stdout ci-dessus
