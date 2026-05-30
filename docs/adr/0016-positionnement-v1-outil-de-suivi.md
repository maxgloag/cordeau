# 0016 — Positionnement V1 « outil de suivi », AR et PDP différés V2

- **Status** : Accepted
- **Date** : 2026-05-24
- **Deciders** : Maxime
- **Lié à** : [ADR 0015](0015-modele-chantier-lots-taches-mesures.md), [ADR 0017](0017-differer-ia-validation-manuelle.md)

## Context

Trois éléments de la vision Notion d'avril 2026 doivent être réarbitrés à la lumière du brainstorm de mai 2026 :

1. **L'AR de mesure** était posée comme _wow feature V1_ — différenciant principal vs Tolteck/Obat. À l'examen, sa valeur réelle pour l'artisan est marginale (un mètre ruban marche déjà bien), son coût d'intégration élevé (ARKit + ARCore + edge cases LiDAR), et son report en V2 ne casse pas la promesse produit dès lors que **la fonction métré existe en saisie manuelle** (cf [ADR 0015](0015-modele-chantier-lots-taches-mesures.md) — entité `Mesure` avec `Source: MANUEL | AR`).

2. **La facturation électronique conforme PDP** devient obligatoire pour les entreprises au 1er septembre 2026 (réforme française). La vision d'avril prévoyait une Phase 8 « Facturation + conformité » avec audit expert (~2-4 k€). Le brainstorm mai propose de différer cette conformité V2 et de positionner Cordeau V1 comme **outil de suivi** produisant des **pré-documents** (devis et factures exportables non conformes PDP), l'artisan restant maître de sa conformité légale via son outil comptable habituel.

3. **L'AR comme « catalogue de visualisation »** (projection de matériaux dans la pièce filmée) restait un sujet ouvert. Décision de cohérence : tout ce qui touche à l'AR passe en V2.

Cette décision conditionne le contenu de Phase 8 (Devis + Facture brouillon), supprime les anciennes Phases 7 (AR V1) et 8 (Facturation conforme) de la roadmap, et impacte directement les sections « Vision produit » et « Anti-vision » du workspace Notion.

## Decision

### Positionnement V1

Cordeau V1 se positionne comme **outil de suivi** :

- Produit des **pré-documents** (devis, factures) exportables en PDF, **non conformes PDP**
- L'artisan reste **maître de sa conformité légale** : il importe/ressaisit dans son outil comptable habituel (ou son expert-comptable) les documents conformes à transmettre via une PDP
- Cordeau **ne se substitue pas** à un logiciel de comptabilité ou à une PDP — il les complète

### Anti-vision V1 explicite

- Pas de transmission Chorus Pro / PDP en V1
- Pas d'audit expert facturation en V1
- Pas d'AR (ni mesure, ni visualisation) en V1
- Pas de signature électronique client en V1

### Numérotation devis / factures

Même non conforme PDP, les pré-documents Cordeau doivent porter une **numérotation séquentielle ininterrompue** (article 289 du CGI, applicable dès que le document sert de base à une facture conforme). Décisions :

- Séquence par utilisateur (`owner`), pas par tenant global
- Format : `DEV-{yyyy}-{numéro séquentiel}` pour devis, `FAC-{yyyy}-{numéro séquentiel}` pour factures
- Séquence côté serveur, attribuée à la **finalisation** du brouillon (passage du statut `brouillon` à `envoye` pour devis, `emise` pour facture). Pas à la création du brouillon.
- Pas de trou autorisé : si un document est annulé après attribution du numéro, il garde son numéro et passe au statut `annule` (visible et traçable).

### Mentions légales obligatoires sur pré-documents

Phase 8 doit générer des pré-documents portant les mentions :

- Identité artisan (raison sociale ou nom commercial, SIRET, adresse, code APE si applicable)
- Mentions auto-entrepreneur le cas échéant (« TVA non applicable, art. 293 B du CGI » si franchise)
- Conditions de paiement, taux pénalités de retard, indemnité forfaitaire de recouvrement
- Mentions assurance décennale (le cas échéant, RGE, etc.)

Ces données sont collectées dans le **profil pro de l'artisan** (premières user-stories Phase 8 — onboarding/paramètres). Sans profil pro complet, l'export PDF est bloqué côté serveur (validation explicite).

### Trajectoire V2 (non engagée mais documentée)

- Étudier partenariat ou intégration **PDP** (Chorus Pro, ou PDP privée comme Pennylane / Sellsy)
- Réintroduire l'AR comme **moyen de saisie de Mesure** (un nouveau `Source: AR` sur l'entité existante, pas de refacto)
- Étudier le **catalogue produits** pour visualisation AR (partenariats fabricants)

## Implications sécurité / RGPD

Section obligatoire (conformément à [CLAUDE.md](../../CLAUDE.md#adrs)) — Cordeau collecte et stocke en V1 des données personnelles clients (nom, adresse, téléphone, email) et des données financières (devis, factures).

### Surface d'attaque

- API exposant les pré-documents (PDF stockés sur Cloudflare R2 cf [ADR 0004](0004-cloudflare-r2-stockage.md), URLs pré-signées à TTL court)
- Numérotation séquentielle : risque d'énumération si exposée publiquement (mitigation : URLs PDF par UUID, pas par numéro séquentiel)
- Export PDF côté serveur : worker Symfony Messenger, pas d'exécution de templates utilisateur (pas de SSRF / RCE via template injection)

### Données personnelles touchées

- **Clients** : nom, adresse, téléphone, email — base légale **exécution du contrat** artisan-client
- **Artisan** : SIRET, adresse pro, RIB éventuel (V1.1+), assurance décennale — base légale **exécution du contrat** Cordeau-artisan
- **Conservation** : 10 ans (durée comptable légale française), même si Cordeau ne produit pas de documents conformes — l'artisan peut s'en servir comme archive technique. Suppression de compte → suppression effective ou anonymisation après période de grâce 30 jours (à acter UX).

### Points de fuite potentiels

- PDFs sur R2 : URLs pré-signées avec TTL ≤ 5 min, pas de listing bucket public
- Export RGPD : endpoint dédié `/me/export` retournant un ZIP de toutes les données utilisateur (chantiers, clients, mesures, pointages, PDFs) — à implémenter dans Phase 8 ou Phase 9
- Suppression de compte : endpoint `/me/delete` avec confirmation, déclenche suppression hard de toutes les entités possédées (multi-tenant via `owner_id`)

### Statut non conforme PDP : implication légale

Cordeau V1 **n'est pas** une PDP. Communication à l'utilisateur :

- Onboarding Phase 8 : message explicite « Cordeau produit des documents de travail. Pour transmettre vos factures conformément à la réglementation française en vigueur (PDP), exportez-les vers votre outil comptable ou votre PDP. »
- CGV V1 (Phase 9) : clause dédiée précisant l'absence de conformité PDP et la responsabilité de l'artisan

## Consequences

### Bénéfices attendus

- **Scope V1 réduit** : pas d'audit expert (~2-4 k€ économisés), pas d'intégration Chorus Pro, pas d'AR. Lancement bêta accessible (septembre 2026 cible).
- **Positionnement clair** : Cordeau ne prétend pas remplacer un logiciel comptable. Évite le pitch ambigu et le risque légal.
- **Trajectoire ouverte** : la conformité PDP et l'AR restent intégrables en V2 sans refacto du modèle (cf [ADR 0015](0015-modele-chantier-lots-taches-mesures.md) sur `Mesure.source`).
- **Mentions et numérotation propres** : un artisan peut quand même se servir des pré-documents Cordeau comme base de facturation conforme via son outil comptable.

### Coûts assumés

- **Risque de perception « pas un vrai logiciel de facturation »** sur le marché. Mitigation : positionnement marketing explicite (le wedge n'est pas la facture, c'est la capture terrain — cf [ADR 0017](0017-differer-ia-validation-manuelle.md)).
- **Le freinage AR différée** retire un argument marketing visuel fort. Mitigation : la démo terrain (saisie rapide, métré manuel, brouillon facture pré-rempli) doit suffire à convaincre.
- **Trajectoire PDP V2 non triviale** : intégrer une vraie PDP en V2 demandera ~4-8 semaines + audit. À provisionner dans le budget V2.

### Risques résiduels

- **Évolution réglementaire** : si l'État resserre les obligations (ex : pénalités si un artisan utilise un outil produisant des documents trompeurs sur leur conformité), il faudra réagir vite. Mitigation : message d'onboarding explicite, CGV claires, veille réglementaire.
- **Confusion utilisateur** : un artisan peut envoyer le pré-document directement au client en pensant qu'il est conforme. Mitigation : mention visible « Document de travail — n'a pas valeur de facture conforme PDP » sur le PDF V1 (à confirmer UX bêta).
- **Lock-in inverse** : si Cordeau facilite trop l'export vers outils comptables externes, l'artisan peut se demander pourquoi payer Cordeau plutôt que de tout faire dans son outil comptable. La réponse est dans le wedge capture terrain (cf [ADR 0017](0017-differer-ia-validation-manuelle.md)) — à valider en bêta.

### Trade-offs assumés

- **Pas de signature électronique V1** : l'acceptation d'un devis se fait hors-Cordeau (mail, papier, oral). Statut `accepte` enregistré manuellement par l'artisan. Acceptable pour V1, à reconsidérer V1.2+ si demande forte.
- **Numérotation par utilisateur, pas par tenant global** : un artisan qui change de structure légale gardera un compteur incohérent. Mitigation : commande Symfony de réinitialisation manuelle si le cas se présente.
- **Conservation 10 ans pour des pré-documents** : on stocke long sans valeur légale stricte. Coût stockage R2 marginal, accepté.
