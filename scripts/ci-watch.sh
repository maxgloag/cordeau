#!/usr/bin/env bash
# ci-watch.sh — surveille la dernière CI GitHub Actions après un git push
# Appelé en asyncRewake par le hook PostToolUse de Claude Code.
# Exit 0 = CI verte (silencieux). Exit 2 = CI échouée (réveille Claude avec les logs).

set -euo pipefail

REPO="maxgloag/cordeau"

# Lire la commande bash depuis le JSON stdin
CMD=$(jq -r '.tool_input.command // ""' 2>/dev/null || echo "")

# Vérifier que c'est bien un git push
echo "$CMD" | grep -q "git push" || exit 0

# Laisser GitHub enregistrer le run (délai réseau)
sleep 8

# Récupérer l'ID du dernier run
RUN_ID=$(gh run list --repo "$REPO" --limit 1 --json databaseId -q '.[0].databaseId' 2>/dev/null || echo "")
if [ -z "$RUN_ID" ]; then
  echo "⚠ Impossible de trouver un run CI pour $REPO" >&2
  exit 0
fi

echo "⏳ CI #$RUN_ID en cours sur $REPO..." >&2

# Attendre la fin du run (timeout implicite de gh run watch : ~10 min)
if gh run watch "$RUN_ID" --repo "$REPO" --exit-status >/dev/null 2>&1; then
  echo "✓ CI verte (run #$RUN_ID) — https://github.com/$REPO/actions/runs/$RUN_ID"
  exit 0
fi

# --- CI échouée : collecter les logs et réveiller Claude ---
LOGS=$(gh run view "$RUN_ID" --repo "$REPO" --log-failed 2>&1 \
  | grep -E "(##\[error\]|Error:|error[: ]|FAIL|Failed|exit code [^0]|Cannot find|No such)" \
  | grep -v "^$" \
  | head -80 \
  || true)

{
  echo "## CI FAILED — run #$RUN_ID"
  echo "URL : https://github.com/$REPO/actions/runs/$RUN_ID"
  echo ""
  if [ -n "$LOGS" ]; then
    echo "### Erreurs (filtrées, 80 lignes max)"
    echo "$LOGS"
  else
    echo "### Logs bruts (pas d'erreurs filtrées détectées)"
    gh run view "$RUN_ID" --repo "$REPO" --log-failed 2>&1 | tail -60 || true
  fi
}

exit 2  # asyncRewake : code 2 = réveille Claude avec le contenu stdout ci-dessus
