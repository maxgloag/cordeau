# 0008 — Trajectoire d'hébergement (Fly.io + Neon → Scaleway/Clever Cloud)

- **Status** : Accepted
- **Date** : 2026-05-04
- **Deciders** : Maxime
- **Supersedes** : choix initial Oracle Cloud Free Tier + Coolify (mentionné dans le plan Phase 0, abandonné car le sign-up Oracle a échoué — voir Context)

## Context

Phase 0 demande un déploiement gratuit des 3 environnements (API, web, mobile) pour valider le critère de sortie. Trois contraintes rigides :

1. **Coût zéro** jusqu'au lancement bêta payante (cible roadmap : octobre 2026)
2. **API joignable depuis Internet en HTTPS** (sans HTTPS, l'app mobile en release build refusera l'URL — ATS iOS et NSC Android)
3. **PostgreSQL durable** (pas de DB qui s'auto-supprime au bout de N jours, sinon on perd des données utilisateur en bêta)

Plusieurs options ont été évaluées :

| Option                                                        | Verdict                                                                                                                                       |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Oracle Cloud Free Tier (VM ARM) + Coolify                     | Sign-up bloqué (rejet anti-fraude opaque, problème connu et documenté côté Oracle)                                                            |
| Hébergeurs français gratuits (Scaleway/Clever/OVH/Alwaysdata) | Pas de free tier 24/7 viable. Alwaysdata a un free plan mais 100 Mo de stockage = bloqué dès Phase 1 (`vendor/` Symfony fait déjà ~80 Mo)     |
| Render                                                        | Service free s'éteint après 15 min inactif (cold start ~1 min). DB free supprimée après 90 jours. No-go                                       |
| GCP e2-micro Always Free                                      | Région US uniquement (~150ms latence FR). VM à maintenir comme Oracle                                                                         |
| **Fly.io hobby + Neon Postgres free**                         | Fly a une région Paris (`cdg`), Neon en Frankfurt. Free tier durable, pas de cold start agressif sur Fly Machines, Neon ne supprime pas la DB |

**Sur la trajectoire long terme** : à partir du lancement bêta payante, on aura un budget infra (~10-30 €/mois). À ce moment, deux raisons motivent une migration vers un hébergeur français :

- Souveraineté FR utilisable comme **argument commercial** auprès d'artisans français (le secteur est sensible à "où sont mes données")
- Conformité GDPR plus simple à expliquer/auditer
- Support FR, facturation FR

## Decision

**Phase 0 → Phase 5 (mai 2026 → août 2026)** : déploiement gratuit sur :

- **API Symfony** : Fly.io, région primaire `cdg` (Paris), Machines auto-stop pour rester sur le free tier
- **PostgreSQL** : Neon (Frankfurt, EU central), free plan (0.5 Go) avec branches DB gratuites par PR
- **Web React** : Cloudflare Pages, sous-domaine `*.pages.dev` (pas d'achat de domaine en Phase 0)
- **Mobile Expo** : EAS preview (Android APK uniquement, iOS différé jusqu'au 1er user payant)
- **Erreurs** : Sentry Developer plan (5k events/mois × 3 projets)
- **DNS API** : sous-domaine Fly natif `cordeau-api.fly.dev` (HTTPS auto, certificat géré par Fly)

**Avant lancement bêta payante (cible : septembre 2026)** : migration de l'API et de la DB vers un hébergeur français. Deux candidats à arbitrer le moment venu :

- **Scaleway** Container Instances ou Serverless Containers (FR, ~5-10 €/mois pour un setup minimal)
- **Clever Cloud** (Nantes, FR, plan ~5-15 €/mois pour app + addon Postgres)

Le choix final dépendra des prix en vigueur à la date de migration, du volume de trafic estimé, et de la maturité du support PostgreSQL géré chez chacun.

## Consequences

**Bénéfices Phase 0** :

- 0 € sur les 5 premiers mois (octobre exclu) — l'argent reste pour le domaine `.fr` (~10 €/an), le contrat avocat (CGU/CGV), et un éventuel Apple Developer Program au moment où on a un user iOS payant
- Setup beaucoup plus rapide qu'Oracle/Coolify : `fly deploy` lit le Dockerfile, build, push, route. Pas de SSH, pas d'install Coolify, pas de Security Lists à configurer
- Logs intégrés (`fly logs`), métriques Prometheus, scaling auto sur Machines
- Region Paris : latence < 30 ms depuis France, indistinguable d'un hébergeur FR à l'œil
- Neon donne **une branche DB par PR GitHub** gratuitement → Phase 1+ pourra tester les migrations sur une DB éphémère par feature, sans risque sur la DB de prod

**Coûts / contraintes Phase 0** :

- Fly hobby tier : 256 Mo de RAM par Machine. Suffisant pour `/health` et la Phase 1 (CRUD chantiers). Risque d'être tight en Phase 6 (génération PDF de devis avec wkhtmltopdf ou équivalent) — on gère ce problème quand on l'a, pas avant
- Neon free : 0.5 Go de stockage. Largement OK avant 1000+ chantiers et sans photos en DB (les photos sont sur R2, ADR 0004)
- Pas de souveraineté FR pendant 5 mois. Acceptable car aucun user en prod sur cette période (juste démos perso)

**Migration future — coût et risque** :

- L'app étant **conteneurisée** (Dockerfile dans `apps/api/`), la migration Fly → Scaleway/Clever Cloud est un changement de plateforme cible, pas un refactor. Le code ne bouge pas
- Pour la DB : `pg_dump` depuis Neon → restore sur l'instance Postgres FR. Downtime estimé : 5-15 min sur une DB de Phase 5 (quelques milliers d'enregistrements max). Acceptable hors heures ouvrées
- À planifier comme un Lot dédié dans la roadmap : "Migration FR" avant lancement bêta

**Trade-off explicitement assumé** : on ne paie pas tant qu'on n'a pas d'user. C'est cohérent avec la stratégie produit (validation par verticales avant monétisation). Si la roadmap glisse de 2-3 mois et qu'on dépasse les limites du free tier, le coût marginal de bascule (5-15 €/mois) est acceptable et n'invalide pas la décision.

## Notes opérationnelles

- Région primaire Fly : `cdg` (Paris). Si on veut tester un fail-over plus tard, ajouter `cdg2` ou `ams` (Amsterdam) comme secondaire
- Neon : créer le project en région **EU Central (Frankfurt)** à l'inscription. Non modifiable a posteriori (sinon migration de DB)
- Anti-suspension Fly : les Machines auto-stop quand pas de trafic. Le web qui consomme `/health` toutes les 10 s suffit largement à les garder vivantes en démo. En prod, c'est aussi OK (les vrais users génèrent du trafic)
- Une `auto_stop_machines = true` dans `fly.toml` est explicitement souhaitée pour rester en free tier — accepter une **première requête plus lente** (~1-2 s de cold start) après période d'inactivité
