# 0017 — Différer l'IA : validation manuelle V1, magie V1.2+

- **Status** : Accepted
- **Date** : 2026-05-24
- **Deciders** : Maxime
- **Lié à** : [ADR 0015](0015-modele-chantier-lots-taches-mesures.md), [ADR 0016](0016-positionnement-v1-outil-de-suivi.md)

## Context

Le brainstorm vision produit (mai 2026) proposait une **V0.1 « Le secrétaire vocal »** dès les premiers mois post-Phase 5 :

- Saisie vocale structurée par LLM (effet wow immédiat)
- Récap auto de journée
- Descriptifs facture générés depuis les notes
- Détection de chantiers à risque depuis l'historique
- Coût estimé : 5-10 €/mois/artisan en appels API LLM

À la relecture, ce positionnement présente plusieurs problèmes structurels :

1. **Confusion sur le wedge réel** : si Cordeau lance avec la magie LLM activée, on ne saura jamais si le gain de temps perçu par les testeurs vient du modèle Chantier/Lots/Tâches (cf [ADR 0015](0015-modele-chantier-lots-taches-mesures.md)) + UX mobile bien faite, ou du fait que l'IA mâche le travail. En cas de retour positif on aura un produit fragile (le jour où le LLM se trompe, déception forte). En cas de retour négatif on ne saura pas si le concept est mauvais ou si l'IA est mal calibrée.

2. **Coût économique non amorti** : 5-10 €/mois/utilisateur de coûts API LLM sur un abonnement cible 20-30 €/mois HT, soit 25-50 % du revenu brut consommé en coûts variables, avant même validation que les utilisateurs paient. Investir cette dépense récurrente sans signal de demande validé est prématuré.

3. **Tension avec l'offline-first** ([ADR 0005](0005-offline-first-sqlite-drizzle.md)) : la structuration LLM nécessite un réseau. Le brainstorm acte « capture toujours offline, structuration différée » — mais l'UX d'un délai entre capture vocale et brouillon structuré reste à valider, et complique le mental model des testeurs (« pourquoi mon récap n'est pas encore prêt ? »).

4. **Promesse marketing « facture automatique »** mal calibrée : trancher entre « zéro saisie » (full magic, fragile) et « brouillon intelligent » (assisted) avait été tranché en faveur du second lors du brainstorming d'intégration (mai 2026). Le brouillon intelligent ne nécessite pas de LLM en V1 — il nécessite que **les données amont soient propres**, ce qui est un problème de capture (UX manuelle), pas un problème de magie en aval.

Trois alternatives ont été évaluées :

| Option | Verdict |
|---|---|
| **IA dès V1** comme proposé dans le brainstorm original | Coût économique non amorti, wedge réel obscurci, risque de déception fort. Rejeté |
| **IA optionnelle dès V1** (toggle) | Complexifie le code (deux chemins UX), double les tests, n'élimine pas le coût économique. Rejeté |
| **Pas de LLM en V1, ré-évaluation post-bêta** sur critère explicite | Simplifie V1, force la validation du concept manuel, garde la magie en option claire pour V1.2+. **Retenue** |

## Decision

### Aucune dépendance LLM en V1

V1 (Phases 5-9 de la ROADMAP révisée) ne consomme aucune API LLM. Cela exclut :

- Structuration de dictée vocale en entrées de domaine (matériaux, imprévus, pointages)
- Récap auto de journée
- Génération de descriptifs pour devis ou facture
- Détection de chantiers à risque depuis l'historique
- Suggestions d'estimation depuis l'historique
- Reconnaissance de matériaux sur photo

### Wedge V1 explicite

Le différenciant V1 est la **combinaison** :

1. Modèle Chantier → Lots → Tâches → Mesures/Matériaux/Pointages (cf [ADR 0015](0015-modele-chantier-lots-taches-mesures.md)) — alignement métier
2. UX mobile *vraiment* optimisée saisie rapide : gros boutons, actions en 1-2 taps, formulaires courts, photo en un geste, catégories larges pour matériaux, imprévu en note rapide
3. Offline-first solide (déjà acquis Phase 3)
4. Chrono manuel start/stop sans friction sur le lot
5. Métré manuel assisté (calcul auto depuis dimensions, cf [ADR 0015](0015-modele-chantier-lots-taches-mesures.md))
6. Devis et facture en édition brouillon (mobile + web), exportables (cf [ADR 0016](0016-positionnement-v1-outil-de-suivi.md))

C'est la **somme** de ces éléments qui doit produire le gain de temps. Aucun n'est censé être impressionnant seul ; ensemble ils forment le « carnet de chantier moderne ».

### Critère de validation explicite (porte d'entrée V1.2)

À la fin de Phase 9 (Bêta payante V1), les conditions cumulatives pour autoriser le démarrage des phases V1.2+ (magie LLM) sont :

- **3 testeurs sur 5** déclarent (en interview verbatim) que Cordeau leur fait gagner du temps **en saisie manuelle** (sans aucune magie)
- **2 testeurs sur 5** acceptent un abonnement payant symbolique (10-15 €/mois minimum)
- **Aucun feedback récurrent** sur un point de friction qui aurait été masqué par une magie LLM (ex : « la dictée vocale aurait sauvé ce truc »)

Si ces conditions sont remplies → V1.2 priorisée, ADR dédié sur la stack LLM (Whisper local vs API, Claude vs OpenAI, schémas de structuration, gestion offline).

Si elles ne sont pas remplies → **rétro** avant V1.1. Le concept manuel n'a pas validé son utilité ; la magie LLM ne sauvera rien si le socle est mauvais.

### Positionnement marketing V1

- **Pas** de mention « IA », « intelligent », « secrétaire vocal », « assistant », « générée par IA » dans la communication V1
- Vocabulaire V1 : « carnet de chantier », « capture rapide », « ton chantier dans ta poche », « brouillon prêt »
- Le tableau de discours du brainstorm Notion (« à éviter / Cordeau dit ») reste valable mais est **différé V1.2+** — il n'apparaît dans la communication qu'au moment où la magie est effectivement livrée

### Whisper local sans structuration (V1.1 possible)

Cas limite : la **transcription vocale brute** (Whisper local sur device, sans appel à un LLM distant pour structurer) reste envisageable en V1.1 comme aide à la saisie de notes libres (imprévus, descriptions). Pas en V1 (priorité à valider le manuel d'abord), mais sans rouvrir l'ADR si le besoin émerge fort en bêta.

Conditions pour Whisper local V1.1 :

- 100 % offline (pas de transmission audio à un service distant)
- Transcription brute insérée dans un champ texte que l'artisan valide / corrige avant enregistrement
- Pas de structuration sémantique (le LLM reste hors V1.x tant que ADR 0016 n'est pas levé)

## Consequences

### Bénéfices attendus

- **Validation honnête du concept** : si l'artisan trouve Cordeau utile en pur manuel, on a un produit. Sinon, on n'a pas brûlé 5-10 €/mois/utilisateur de coûts variables pour rien.
- **Scope V1 réduit** : pas d'ADR stack LLM, pas de pipeline de structuration vocale, pas de gestion de fallbacks offline/online sur transcription, pas de monitoring coût LLM. ~3-4 semaines de Phase évitées en V1.
- **Pricing V1 simple** : 20-30 €/mois HT brut, marge claire car pas de coûts variables LLM. Permet un freemium léger sans craindre l'abus (cf vision Notion avril).
- **Trajectoire ouverte** : la magie reste un upside fort pour V1.2 → V1.3, motivant les testeurs (« on travaille déjà sur la dictée vocale qui arrive plus tard »).

### Coûts assumés

- **Risque marketing à contre-courant** : en 2026 le marché SaaS communique massivement sur l'IA. Ne pas en parler peut sembler « en retard ». Mitigation : le pitch terrain (gain de temps observé en saisie manuelle) suffit auprès d'artisans pragmatiques (cible Cordeau). Le marketing « IA invisible » émerge plus tard quand la magie est livrée.
- **Effort UX renforcé** : sans magie pour masquer une UX médiocre, chaque écran doit être *vraiment* fluide. Phase 7 (Capture terrain + Métré manuel) est le test ultime. Si l'UX manuelle est trop friction-prone, le concept échouera la bêta — risque assumé, c'est l'objectif du critère de validation.
- **Frustration potentielle des testeurs** qui s'attendraient à de la magie. Mitigation : onboarding bêta explicite (« V1 manuel, magie en cours de validation post-bêta »).

### Risques résiduels

- **Faux négatif bêta** : 5 testeurs trouvent ça bien mais n'osent pas payer (par radinerie d'artisan, par habitude du gratuit). Mitigation : interview qualitative en plus du test de paiement, ne pas trancher uniquement sur le critère « 2 sur 5 payent ».
- **Faux positif bêta** : 3 testeurs disent « ça me fait gagner du temps » par politesse (Maxime est ami avec eux). Mitigation : recruter au moins 2 testeurs hors réseau bigouden direct, observation terrain en plus du déclaratif.
- **Pression interne à introduire l'IA plus tôt** si la bêta tarde à converger : tentation d'ajouter de la magie pour relancer l'intérêt. Mitigation : cet ADR sert de garde-fou explicite. Levée nécessite un nouvel ADR.

### Trade-offs assumés

- **Pas de mesure de la demande pour la magie en V1** : on ne saura pas combien d'utilisateurs auraient payé pour la dictée vocale tant qu'on ne l'a pas livrée. Acceptable — on tranche en faveur de la solidité du socle.
- **Pas d'ADR stack LLM dès maintenant** : la décision (Whisper local vs API, Claude vs OpenAI, schémas JSON Structured Output, etc.) est différée au moment où V1.2 démarre. Permet de profiter de l'état de l'art au moment où on s'y attaque (LLM en 2027 ≠ LLM en 2026).
- **Whisper local possible V1.1 sans nouvel ADR** : exception ciblée et limitée à la transcription brute offline. Si elle dérive vers de la structuration, nouvel ADR obligatoire.
