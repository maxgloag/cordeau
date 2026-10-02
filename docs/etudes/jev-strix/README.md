# Évaluation — Jev (TypeSafe AI) et Strix pour les pratiques de développement de Cordeau

- **Date** : 2026-10-02
- **Contexte** : cycle d'amélioration des pratiques ouvert par l'[étude Rust agentique](../rust-agentique/README.md) (jalon « Socle solide », issues #123–#140).
- **Méthode** :
  - Lecture des sources primaires : sites et docs officiels, dépôts GitHub via `gh api`, PyPI.
  - Recherche via les MCP Exa et DeepWiki.
  - **Aucun des deux outils n'a été exécuté** : Strix exige Docker, inutilisable sur ce poste (socket OrbStack rattaché à un autre compte), et une clé d'API LLM ; Jev exige une clé TypeSafe.
- **Interprétation des noms.** « JEV » = **Jev**, le modèle de décision « System One » de TypeSafe AI, et son écosystème d'outils pour agents de code. « STRIX » = **Strix**, l'agent de pentest IA open source `usestrix/strix`.

## En bref

|                                    | Strix                                                                                                                                                             | Jev                                                                                                                                                                              |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Ce que c'est**                   | Agents IA de test d'intrusion qui exécutent l'application, tentent des exploits et livrent des preuves (PoC)                                                      | Modèle fermé qui rend des **décisions typées avec probabilités** (pas de texte), appelé depuis des hooks d'agent                                                                 |
| **Maturité**                       | 66 k ★, 7,2 k forks, Apache-2.0, v1.6.2 (5 sept. 2026), actif quotidiennement, créé en août 2025                                                                  | Lancé le **15 sept. 2026** (17 jours) ; site en « Version 0.01 » ; outils communautaires créés depuis une à deux semaines (jev-lint 6 ★, jev-kit 55 ★)                           |
| **Trou qu'il comble chez Cordeau** | **Réel** : aucun test de sécurité dynamique, CodeQL ne couvre pas PHP (#136), auth maison, isolation entre artisans, données personnelles, bêta payante imminente | **Partiel** : vérifier des « règles floues » qu'aucun linter ne sait tester (« un test doit pouvoir échouer », « ne jamais faire confiance à un `userId` envoyé par le client ») |
| **Verdict**                        | **Adopter, par un spike avant la bêta**, puis en scans périodiques                                                                                                | **Pas maintenant.** Les gardes déterministes (#137) d'abord ; spike en mode observation plus tard, côté TS uniquement                                                            |

---

## 1. Strix

### Ce qu'il fait

[github.com/usestrix/strix](https://github.com/usestrix/strix) · [docs.strix.ai](https://docs.strix.ai/llms.txt)

- **Fonctionnement.** Agents multiples (reconnaissance, exploitation, validation) dans un **bac à sable Docker**, outillés comme un pentesteur : proxy HTTP (Caido), navigateur Playwright, terminal, runtime Python pour écrire des PoC, semgrep et recherche AST en mode boîte blanche.
- **Cibles.** Un dépôt de code (boîte blanche), une URL (boîte noire), ou les deux combinés.
- **Classes visées** : OWASP Top 10 et au-delà, dont **contrôle d'accès cassé et IDOR**, mass assignment, SSRF, injection, failles de logique métier, sessions et authentification, API.
- **Modes de scan** :

  | Mode       | Durée            | Usage recommandé                    |
  | ---------- | ---------------- | ----------------------------------- |
  | `quick`    | quelques minutes | chaque PR                           |
  | `standard` | 30-60 min        | scans hebdomadaires                 |
  | `deep`     | 1 à 4 h          | avant une release (mode par défaut) |

- **Modèle d'IA** : au choix de l'utilisateur. Anthropic est pris en charge (`anthropic/claude-sonnet-4-6`, `claude-opus-4-6`), comme OpenRouter, Bedrock ou des modèles locaux.
- **Intégrations** :
  - une GitHub Action qui ne scanne que les fichiers modifiés de la PR et échoue (code 2) si une faille est trouvée ;
  - 9 skills pour Claude Code : lancer un pentest, corriger les findings, configurer la CI ;
  - une plateforme hébergée payante (app.strix.ai) en option.
- **Crédibilité.** En mai 2026, le Google Threat Intelligence Group l'a cité comme framework de pentest multi-agents utilisé par des attaquants. C'est un indice de capacité réelle, pas une recommandation.

### Pourquoi c'est pertinent pour Cordeau

- **Aucun test de sécurité dynamique aujourd'hui.** `/security-review` est statique et se limite au diff ; CodeQL ne couvre que JS/TS (#136) ; il n'y a pas de rate limiting (#63).
- **Surface sensible :**
  - auth maison : tokens opaques Bearer, OAuth Google avec rattachement automatique par email (ADR 0013) ;
  - **isolation des données entre artisans** : un artisan ne doit jamais voir les chantiers d'un autre. C'est la classe IDOR, que Strix cible en priorité et qu'aucun outil actuel ne teste ;
  - URL pré-signées R2, endpoints API Platform (mass assignment) ;
  - et deux advisories api-platform/core déjà ouvertes (fuite d'attributs entre utilisateurs, IRI de relation non typées, #123).
- **Bêta payante avec données personnelles (RGPD).** Un pentest avant d'ouvrir l'accès relève de la diligence raisonnable.

### Limites et risques

| Point                             | Détail                                                                                   | Parade                                                                                                             |
| --------------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Docker obligatoire                | Cassé sur ce poste                                                                       | Lancer en CI (runner GitHub) ou réparer OrbStack (prérequis de #143)                                               |
| Cible                             | **Jamais la prod** : le pentest agit réellement (écritures, injections)                  | Environnement de staging : app Fly de preview + branche Neon, données factices, 2 comptes de test (artisan A et B) |
| Coût LLM                          | Non publié ; un scan `deep` de 1 à 4 h avec Opus peut coûter des dizaines de dollars     | Mesurer sur le premier scan ; `standard` avec Sonnet par défaut                                                    |
| Données envoyées                  | Code source et trafic de staging transmis au fournisseur LLM                             | Anthropic est déjà sous-traitant ; staging sans données réelles ; pas de secrets de prod dans le repo              |
| Chaîne d'approvisionnement        | La doc installe par `curl … \| bash`, ce qui contredit l'épinglage par SHA de l'ADR 0019 | `pipx install strix-agent==1.6.2` (version épinglée), image Docker épinglée                                        |
| Non-déterminisme et faux positifs | Malgré les PoC, chaque finding reste à trier                                             | Non bloquant au début ; triage manuel ; issue `fix:` avec protocole double-fix pour chaque vrai positif            |
| Cadre légal                       | Ne tester que ses propres actifs                                                         | Fly et R2 de Cordeau uniquement ; vérifier les conditions de Fly.io et Cloudflare sur les tests d'intrusion        |

### Recommandation

1. **Spike avant la bêta** (issue créée) : scan `standard` en boîte blanche (repo) et boîte noire (staging) avec deux comptes. On mesure le coût, la durée, le nombre de findings et la part de vrais positifs.
2. **Si le signal est bon** :
   - un ADR, puisque c'est une dépendance externe avec section sécurité obligatoire ;
   - un scan `standard` hebdomadaire planifié en CI ;
   - un scan `quick` **non bloquant** sur les PR qui touchent `ctx:auth`, la sécurité ou les endpoints ;
   - un scan `deep` avant chaque release majeure.
3. **Ne pas** en faire un gate de merge tant que le taux de faux positifs n'est pas mesuré.

---

## 2. Jev (TypeSafe AI)

### Ce que c'est

[typesafe.ai](https://typesafe.ai) · [autojev.ai/jev-ai](https://autojev.ai/jev-ai)

- **Un « System One model »**, lancé le 15 septembre 2026. On lui envoie un état (texte ou JSON) et des questions bornées ; il renvoie des choix, des scores et des probabilités calibrées. Il ne génère ni texte ni code.
- **Coûts et délais annoncés** : environ 0,1 à 0,3 s par décision, 42 $ par milliard de tokens d'entrée, sorties gratuites.
- **API hébergée et fermée.** Le site affiche « Version 0.01 » et promet « zero hallucinations », une affirmation marketing non vérifiable.
- **Écosystème pour agents de code, communautaire et très jeune** :
  - **[jev-lint](https://jevlint.dev/)** (MIT, créé le 26 sept., 6 ★) : un hook PostToolUse qui envoie le code ajouté à Jev et pose une question oui/non par règle. 170 règles en 5 packs (hygiène, pratiques, sécurité, tests, performance), qui laissent passer l'édition en cas d'erreur.
    - Mesures **de l'auteur** sur 96 runs Claude Code : 2,38 → 1,21 violations de règles par tâche, −41 % de commentaires de revue couverts par une règle, environ 0,3 s par vérification, environ 2 $ pour 10 000 éditions.
    - Langages : TS/React, Swift, Kotlin, Rust, Python, Ruby. **Pas de PHP.**
  - **[jev-kit](https://jevforagents.com/builds/jev-kit)** (55 ★) : une garde PreToolUse pour Claude Code. L'auteur reconnaît qu'elle **n'a rien bloqué en 30 sessions**.
  - **jev-harness**, **AutoJev** (routage de modèles), et des intégrations LangChain, Pydantic AI et SuperQode. Vercel aurait adopté Jev pour son mode auto, d'après une source secondaire non vérifiée.

### Ce qui est intéressant

- **L'idée de jev-lint correspond à un vrai angle mort de l'étude Rust.** Les échecs typiques des agents ne relèvent ni d'un linter ni d'un type-checker :
  - des tests tautologiques ;
  - un appel `fetch` dans un `useEffect` sans annulation ;
  - un `userId` envoyé par le client et pris pour argent comptant.
  - Les vérifier en 0,3 s pendant l'édition, plutôt qu'en revue, raccourcit la boucle.
- Le modèle « décision typée et seuils dans le code » est **sain** : l'autorité reste au code déterministe, Jev ne fait que conseiller.

### Pourquoi pas maintenant

1. **Maturité.** Le produit a 17 jours et les outils une à deux semaines. Toutes les mesures sont produites par les auteurs, et aucune n'a été reproduite de façon indépendante.
2. **Ordre des priorités.**
   - L'étude recommande d'abord des **gardes déterministes** : hook Stop, lints, interdiction des échappatoires (#137, #132, #134). Elles sont gratuites et sans dépendance externe.
   - Un outil qui laisse passer les éditions en cas d'erreur ne garantit rien : c'est un filet en plus, pas un socle.
3. **Redondance partielle.** Claude Code dispose déjà de son propre classifieur en mode auto pour juger les commandes ; c'est lui qui a refusé le push de la branche d'étude. Une garde Jev en PreToolUse ferait doublon.
4. **API couverte à moitié.** Pas de pack PHP : il faudrait écrire et valider des règles maison pour `apps/api`.
5. **Dépendance externe et données.**
   - Des extraits de code partent vers une startup américaine dont les conditions et la conservation des données restent à lire.
   - Risque de fuite de secrets si un extrait en contient.
   - Il faut un ADR avec section sécurité (CLAUDE.md).
6. **Routage de modèles (AutoJev) sans intérêt** pour un dev solo sous abonnement Claude.

### Recommandation

- **Veille** : ajouter Jev et jev-lint aux signaux de la routine de veille (#145).
- **Spike en mode observation**, après #137, d'environ 2 h (issue créée) :
  - jev-lint sur `apps/web` et `apps/mobile` uniquement ;
  - 5 à 10 règles Cordeau : isolation entre artisans côté client, jamais de `float` pour un montant, un test doit pouvoir échouer, `useEffect` sans annulation ;
  - mesurer la précision sur les 20 dernières PR rejouées.
  - **Critère d'adoption** : précision ≥ 90 %, signalements à tort sur du code propre ≤ 5 %, et au moins un vrai défaut trouvé que les lints et la revue avaient laissé passer.

---

## Sources

- Strix : [dépôt](https://github.com/usestrix/strix) (66 091 ★, push du 2026-10-02, via `gh api`), [PyPI strix-agent 1.6.2](https://pypi.org/project/strix-agent/), [docs : quick start, scan modes, GitHub Actions, Anthropic](https://docs.strix.ai/llms.txt), [page open source](https://www.strix.ai/open-source-pentesting), [Wikipédia](<https://en.wikipedia.org/wiki/Strix_(security)>).
- Jev : [typesafe.ai](https://typesafe.ai), [autojev.ai/jev-ai](https://autojev.ai/jev-ai), [jevlint.dev](https://jevlint.dev/) et [ckorhonen/jev-lint](https://github.com/ckorhonen/jev-lint) (6 ★, créé le 2026-09-26), [jev-kit](https://jevforagents.com/builds/jev-kit) ([dépôt](https://github.com/jonathanavis96/jev-kit), 55 ★), [jev-harness (PyPI)](https://pypi.org/project/jev-harness/), [SuperQode × Jev](https://super-agentic.ai/resources/super-posts/jev-in-superqode-harness-with-jev-agent).
