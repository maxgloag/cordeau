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

| # | Titre | Status |
|---|---|---|
| [0001](0001-stack-technique.md) | Stack technique globale | Accepted |
| [0002](0002-architecture-hexagonale.md) | Architecture hexagonale pragmatique | Accepted |
| [0003](0003-tokens-opaques-mobile.md) | Tokens opaques plutôt que JWT pour l'API mobile | Accepted |
| [0004](0004-cloudflare-r2-stockage.md) | Cloudflare R2 pour le stockage des photos | Accepted |
| [0005](0005-offline-first-sqlite-drizzle.md) | Offline-first sur mobile (SQLite + Drizzle + Outbox) | Accepted |
| [0006](0006-vitest-browser-mode.md) | Vitest Browser Mode + Playwright pour les tests UI React | Accepted |
| [0007](0007-pattern-container-view.md) | Pattern container / view pour la testabilité React | Accepted |
| [0008](0008-trajectoire-hebergement.md) | Trajectoire d'hébergement (Fly.io + Neon → Scaleway/Clever Cloud) | Accepted |
| [0009](0009-serena-mcp-outillage-semantique.md) | Adoption de Serena (MCP) pour l'outillage sémantique | Accepted |
