#!/usr/bin/env bash
# setup-github.sh — crée labels, milestones et branch ruleset sur maxgloag/cordeau
# Idempotent : relancer ne casse rien (gh ignore les créations en double).
# Usage : ./scripts/setup-github.sh

set -euo pipefail

REPO="maxgloag/cordeau"

echo "==> Labels"

create_label() {
  local name="$1" color="$2" desc="$3"
  gh label create "$name" --color "$color" --description "$desc" --repo "$REPO" --force
}

# Type
create_label "type:feature"  "0075ca" "Story produit à implémenter"
create_label "type:bug"      "d73a4a" "Comportement anormal constaté"
create_label "type:chore"    "6a737d" "Dette technique, refactor, dépendances"
create_label "type:spike"    "7c3aed" "Investigation timeboxée"
create_label "type:docs"     "bfd4f2" "Documentation uniquement"

# App
create_label "app:api"    "0d9e6d" "apps/api (Symfony)"
create_label "app:web"    "f59e0b" "apps/web (Vite + React)"
create_label "app:mobile" "6366f1" "apps/mobile (Expo)"
create_label "app:infra"  "374151" "Infrastructure / CI / déploiement"

# Bounded context
create_label "ctx:chantier"  "dbeafe" "Bounded context Chantier"
create_label "ctx:client"    "dcfce7" "Bounded context Client"
create_label "ctx:auth"      "fef3c7" "Bounded context Auth"
create_label "ctx:photo"     "ffe4e6" "Bounded context Photo"
create_label "ctx:devis"     "f3e8ff" "Bounded context Devis"
create_label "ctx:metre-ar"  "ecfdf5" "Bounded context Mesure AR"
create_label "ctx:facture"   "fff7ed" "Bounded context Facture"

# Priorité
create_label "prio:p0" "b91c1c" "Bloque la phase courante"
create_label "prio:p1" "d97706" "À faire dans la phase"
create_label "prio:p2" "6b7280" "Nice-to-have"

# État spécial
create_label "needs-spec"      "f0fdf4" "Spec Notion manquante ou incomplète"
create_label "blocked"         "fca5a5" "Bloqué — voir commentaire"
create_label "good-first-task" "bbf7d0" "Tâche déléguable à Claude Code"

echo ""
echo "==> Milestones"

create_milestone() {
  local title="$1" due="$2"
  gh api "repos/$REPO/milestones" \
    --method POST \
    -f title="$title" \
    -f due_on="${due}T23:59:59Z" \
    -f state="open" \
    2>/dev/null || echo "  (existe déjà) $title"
}

create_milestone "Phase 0 — Fondations"          "2026-05-10"
create_milestone "Phase 1 — Verticale Chantiers" "2026-06-01"
create_milestone "Phase 2 — Verticale Clients"   "2026-06-15"
create_milestone "Phase 3 — Offline-first"       "2026-07-01"
create_milestone "Phase 4 — OAuth"               "2026-07-08"
create_milestone "Phase 5 — Photos R2"           "2026-07-15"
create_milestone "Phase 6 — Devis"               "2026-08-05"
create_milestone "Phase 7 — AR de mesure V1"     "2026-09-01"

echo ""
echo "==> Branch Ruleset (remplace Branch Protection sur plan gratuit)"

gh api "repos/$REPO/rulesets" \
  --method POST \
  -H "Accept: application/vnd.github+json" \
  --input - <<'JSON' 2>/dev/null || echo "  (existe peut-être déjà)"
{
  "name": "main-protection",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": {
      "include": ["refs/heads/main"],
      "exclude": []
    }
  },
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    { "type": "required_linear_history" },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": false,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "allowed_merge_methods": ["squash"]
      }
    }
  ]
}
JSON

echo ""
echo "==> Done!"
echo ""
echo "GitHub Projects (board Kanban) : à créer manuellement sur https://github.com/users/maxgloag/projects"
echo "Colonnes : Inbox | Backlog | Ready | In Progress (WIP 2) | In Review | Done"
