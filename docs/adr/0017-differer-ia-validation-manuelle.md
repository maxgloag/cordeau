# 0017 — Différer l'IA : validation manuelle V1, magie V1.2+

- **Status** : Accepted (amendé le 2026-10-04)
- **Date** : 2026-05-24
- **Deciders** : Maxime
- **Contrôle** : non contrôlé
- **Lié à** : [ADR 0015](0015-modele-chantier-lots-taches-mesures.md), [ADR 0016](0016-positionnement-v1-outil-de-suivi.md)

## Context

Le brainstorm vision produit (mai 2026) proposait des fonctions pilotées par un LLM dès les premiers mois : saisie vocale structurée, récap automatique de journée, descriptifs de facture générés depuis les notes, détection de chantiers à risque depuis l'historique.

À la relecture, ce choix pose plusieurs problèmes structurels :

1. **Wedge illisible** : si la magie LLM est active dès le lancement, on ne saura pas si le gain perçu vient du modèle Chantier → Lots → Tâches ([ADR 0015](0015-modele-chantier-lots-taches-mesures.md)) et de l'UX mobile, ou de l'IA. Un retour positif donnerait un produit fragile ; un retour négatif ne dirait pas si le concept ou l'IA est en cause.
2. **Coût variable récurrent non amorti** : des appels LLM par utilisateur avant d'avoir validé la demande.
3. **Tension avec l'offline-first** ([ADR 0005](0005-offline-first-sqlite-drizzle.md)) : la structuration par LLM nécessite un réseau, ce qui complique le modèle mental (délai entre capture et brouillon structuré).
4. **Brouillon intelligent sans LLM** : un brouillon utile en V1 exige des données amont propres, ce qui est un problème de capture (UX manuelle), pas de magie en aval.

Trois alternatives ont été évaluées :

| Option                                                                 | Verdict                                                                                           |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| **IA dès V1**                                                          | Coût non amorti, wedge obscurci. Rejeté                                                           |
| **IA optionnelle dès V1** (toggle)                                     | Deux chemins UX, tests doublés, coût non éliminé. Rejeté                                          |
| **Pas de LLM en V1, réévaluation après la bêta** sur critère explicite | Simplifie V1, force la validation du concept manuel, garde la magie en option claire. **Retenue** |

## Decision

### Aucune dépendance LLM en V1

V1 (Phases 5-9 de la ROADMAP) ne consomme aucune API LLM. Cela exclut :

- structuration de dictée vocale en entrées de domaine (matériaux, imprévus, pointages) ;
- récap automatique de journée ;
- génération de descriptifs pour devis ou facture ;
- détection de chantiers à risque depuis l'historique ;
- suggestions d'estimation depuis l'historique ;
- reconnaissance de matériaux sur photo.

### Critère de validation (porte d'entrée de V1.2)

À la fin de la Phase 9, le démarrage des phases V1.2+ (magie LLM) est conditionné à un critère de validation défini hors du dépôt (Notion) : la valeur de Cordeau doit être démontrée **en saisie manuelle, sans aucune magie**.

- Critère levé : V1.2 priorisée, avec un ADR dédié sur la stack LLM (transcription locale ou API, choix du modèle, schémas de structuration, gestion hors ligne).
- Critère non levé : rétro avant V1.1. La magie LLM ne sauvera rien si le socle est mauvais.

### Whisper local sans structuration (V1.1 possible)

La **transcription vocale brute** (Whisper local sur l'appareil, sans appel à un LLM distant) reste envisageable en V1.1 comme aide à la saisie de notes libres, sans rouvrir cet ADR. Conditions :

- 100 % hors ligne (aucun audio transmis à un service distant) ;
- transcription brute insérée dans un champ texte que l'artisan valide ou corrige avant enregistrement ;
- aucune structuration sémantique (le LLM reste hors V1.x tant que cet ADR n'est pas levé).

## Consequences

### Bénéfices

- **Validation honnête du concept** : si le produit est utile en pur manuel, le socle est sain ; sinon on n'a pas engagé de coût variable pour rien.
- **Périmètre V1 réduit** : pas d'ADR sur la stack LLM, pas de pipeline de structuration vocale, pas de gestion de repli hors ligne ou en ligne sur la transcription, pas de suivi du coût LLM.
- **Trajectoire ouverte** : la magie reste une option claire pour V1.2 et V1.3.

### Coûts assumés

- **Effort UX renforcé** : sans magie pour masquer une UX médiocre, chaque écran de saisie doit être réellement fluide. La Phase 7 (capture terrain, métré manuel) en est le test.
- **Pas de mesure de la demande pour la magie en V1**, tant qu'elle n'est pas livrée.
- **Pas d'ADR sur la stack LLM maintenant** : le choix est différé au démarrage de V1.2, pour profiter de l'état de l'art à ce moment.

### Risques résiduels

- **Déclaratif biaisé** : les retours déclaratifs des testeurs peuvent flatter (politesse) ou sous-estimer. À croiser avec l'observation terrain.
- **Pression pour introduire l'IA plus tôt** si la bêta tarde à converger. Cet ADR sert de garde-fou ; la lever nécessite un nouvel ADR.
- **Exception Whisper local en V1.1** sans nouvel ADR : limitée à la transcription brute hors ligne ; si elle dérive vers la structuration, un nouvel ADR est obligatoire.

## Amendements

- 2026-10-04 — Expurgé : critères chiffrés de validation, hypothèses de prix et de coût, positionnement marketing, détails de recrutement et mentions de testeurs retirés du dépôt public ; ils vivent sur Notion. L'historique Git conserve l'ancien texte.
