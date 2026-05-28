# ADR 0018 — Documentation d'architecture as-code (C4 + Mermaid) et enforcement

- **Status** : Accepted
- **Date** : 2026-05-28
- **Deciders** : Maxime

## Context

Le projet grandit (3 apps, plusieurs bounded contexts, offline-first, OAuth, R2 à venir). Pour mieux **construire** et mieux **comprendre** l'application sur la durée, il manque une représentation explicite de l'architecture : aujourd'hui elle vit éclatée entre `CLAUDE.md`, les 17 ADRs et le code lui-même. Un nouvel arrivant (ou un soi futur) n'a pas de vue d'ensemble.

Plusieurs familles d'outils répondent à ce besoin :

- **Diagrammes as-code** versionnés (Mermaid, Structurizr DSL) — documentation durable, dans le repo, diffable en PR.
- **Tableaux blancs interactifs** (FigJam, Excalidraw) — excellents pour la découverte collaborative, mais **hors git** : artefacts de réflexion jetables, pas une source de vérité durable.
- **Enforcement architectural** (Deptrac côté PHP, dependency-cruiser côté TS) — vérifient en CI que les dépendances réelles respectent les règles (ex. `Domain/` n'importe pas Doctrine).

Contraintes : tout ce qui est « source de vérité technique » doit être **dans le repo** (règle d'or `CLAUDE.md`). Le rendu doit marcher là où on lit déjà (GitHub, Notion) sans outil supplémentaire à installer.

## Decision

**1. Documentation d'architecture as-code, modèle C4, en Mermaid.**

- Vue d'ensemble vivante dans [`docs/architecture.md`](../architecture.md) : niveaux C4 Context → Container → Component, plus les flux clés (offline-first).
- Format **Mermaid `flowchart`/`sequenceDiagram`** stylé C4 (classDef), **pas** la syntaxe `C4Context` native de Mermaid — cette dernière est encore expérimentale et son rendu GitHub est instable. Le `flowchart` se rend de façon fiable dans GitHub et Notion.
- Frontière ADR ↔ `architecture.md` : un **ADR** est une décision datée et immuable (le *pourquoi*) ; `architecture.md` est un document **vivant** qui décrit l'*état courant* (le *quoi*) et se met à jour en fin de phase. `architecture.md` reflète, il ne décide pas.

**2. FigJam reste un outil de découverte, hors repo.**

EventStorming et context maps en FigJam sont autorisés pour la phase de réflexion (notamment avant un bounded context complexe : Lots/Mesures/Devis), mais leur sortie durable doit être transcrite en ADR + diagramme Mermaid. Le board FigJam est jetable.

**3. Enforcement architectural — Deptrac activé dès maintenant côté API.**

- **Deptrac** (PHP, `deptrac/deptrac` en `require-dev`) est **actif en CI** (job `ci / api`, après PHPStan, formatter `github-actions`). Config dans [`apps/api/deptrac.yaml`](../../apps/api/deptrac.yaml). Choix d'activer **tôt** plutôt qu'en Phase 6 : poser le filet pendant que le coeur hexagonal est propre (0 violation au départ) encode la règle quand elle est simple, au lieu de la rétro-appliquer sur un code potentiellement déjà dévié.
- **Périmètre volontairement restreint** au coeur hexagonal rigoureux : couches `Domain`, `Application`, `Shared`. Elles ne peuvent dépendre que d'elles-mêmes (vers l'intérieur). Toute fuite vers Doctrine, API Platform ou le framework Symfony = violation bloquante. `symfony/uid` et `symfony/clock` sont tolérés (utilitaires de value objects, pas de couplage framework). Les bounded contexts **CRUD léger** (Auth, Client) qui mélangent Doctrine dans leurs entités ([ADR 0010](0010-crud-leger-pattern-reference.md)) sont **hors périmètre** — c'est cohérent avec « rigueur sur le complexe, légèreté sur le simple » ([ADR 0002](0002-architecture-hexagonale.md)). Les futurs contextes complexes (Lot, Mesure, Devis) tomberont automatiquement sous `src/Domain/` et `src/Application/`, donc couverts sans config supplémentaire.
- **dependency-cruiser** (TS) pour web/mobile : pas d'activation planifiée tant que la taille des apps (~37 fichiers chacune) ne le justifie pas. À reconsidérer si une frontière de couche (ex. `pages/` n'importe pas directement `lib/api`) devient à protéger. Fera l'objet d'un ADR de suivi le moment venu.

## Consequences

**Bénéfices** :
- Vue d'ensemble unique, versionnée, diffable en PR — l'archi devient relisible et reviewable.
- Onboarding (humain ou IA) accéléré : `architecture.md` complète `CLAUDE.md` et les memories Serena.
- Le passage par Mermaid force à expliciter les frontières et les protocoles.
- Trajectoire d'enforcement claire : la règle hexagonale (aujourd'hui une discipline) devient vérifiable mécaniquement quand le coût de l'erreur monte.

**Trade-offs** :
- Un document vivant **peut diverger** du code s'il n'est pas tenu. Mitigation : mise à jour en fin de phase intégrée au protocole (`CLAUDE.md` § Fin de phase), datée en en-tête.
- Mermaid `flowchart` stylé C4 est une approximation du C4 « pur » (pas de tooling C4 dédié type Structurizr). Choix assumé pour le ROII : zéro dépendance, rendu natif. Migration vers Structurizr DSL possible plus tard si le besoin de navigation interactive multi-niveaux apparaît.
- FigJam hors git = perte des sessions de découverte si non transcrites. Assumé : c'est le rôle de la transcription en ADR.

**Signaux pour reconsidérer** :
- Si `architecture.md` est régulièrement obsolète en revue → automatiser (génération partielle depuis le code, ex. `deptrac analyse --formatter=mermaidjs`) ou réduire son périmètre.
- Si une violation hexagonale apparaît dans un CRUD léger hors périmètre Deptrac et qu'elle pose problème → étendre le périmètre Deptrac à ce contexte (le promouvoir en couche interne).
