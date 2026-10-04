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

# Branche courante : un push de ce workflow part toujours de la branche en cours.
# Ne pas la déduire du texte de la commande : `git push … | tail -1` donnait la « branche » -1.
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
{ [ -z "$BRANCH" ] || [ "$BRANCH" = "HEAD" ]; } && exit 0

# Runs du workflow CI pour le commit poussé : la CI ne démarre qu'à l'ouverture de la PR, puis à
# chaque push de sa branche. Quelques tentatives, le temps que GitHub enregistre le run.
SHA=$(git rev-parse HEAD 2>/dev/null || echo "")
RUN_IDS=""
for _ in 1 2 3 4; do
  sleep 10
  RUN_IDS=$(gh run list --repo "$REPO" --workflow CI --commit "$SHA" --limit 5 --json databaseId \
    -q '.[].databaseId' 2>/dev/null || echo "")
  [ -n "$RUN_IDS" ] && break
done

if [ -z "$RUN_IDS" ]; then
  echo "⚠ Aucun run CI pour le commit ${SHA:0:7} (branche: $BRANCH) : la CI démarre à l'ouverture de la PR" >&2
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
