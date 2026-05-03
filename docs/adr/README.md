# Architecture Decision Records

> Une trace écrite et datée de chaque décision technique structurante. Chaque ADR explique le **contexte**, la **décision** et ses **conséquences** (positives et négatives).

## Format

Chaque ADR est un fichier markdown nommé `NNNN-titre-court.md`. Structure type :

```markdown
# NNNN — Titre

- **Status** : Proposed | Accepted | Deprecated | Superseded by ADR-XXXX
- **Date** : YYYY-MM-DD
- **Deciders** : Maxime

## Context
Pourquoi on se pose la question, quelles contraintes, quelles options.

## Decision
La décision retenue, énoncée clairement.

## Consequences
Bénéfices attendus, coûts, trade-offs assumés, risques résiduels.
```

## Quand écrire un ADR

- Choix de framework, lib structurante, ou pattern d'architecture
- Migration de data store, changement de modèle de données critique
- Décision de sécurité (auth, gestion de clés, RBAC)
- Trade-off non évident qui mérite d'être expliqué à un futur soi

**Avant** d'introduire la décision dans le code, pas après. L'ADR force la clarté.

## Index

(Rédigés en Lot 3 de Phase 0)

- ADR 0001 — Stack technique globale
- ADR 0002 — Architecture hexagonale pragmatique
- ADR 0003 — Tokens opaques plutôt que JWT pour l'API mobile
- ADR 0004 — Cloudflare R2 pour le stockage des photos
- ADR 0005 — Offline-first sur mobile (SQLite + Drizzle)
