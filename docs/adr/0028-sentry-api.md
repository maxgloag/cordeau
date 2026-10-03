# ADR 0028 — Remontée des erreurs de l'API vers Sentry, avec filtrage des données

- **Status** : Accepted (feu vert du fondateur le 2026-10-03 ; les actions manuelles ci-dessous restent à faire)
- **Date** : 2026-10-03
- **Deciders** : Maxime
- **Lié à** : issue #175, [ADR 0019](0019-durcissement-ci-cd.md) (dépendances), [ADR 0027](0027-rate-limiting-login.md)

## Context

Vérification de la production le 2026-10-03 : les trois projets Sentry (`cordeau-api`, `cordeau-web`, `cordeau-mobile`) n'ont **jamais reçu un événement**.

- **Web** : le SDK est actif et le DSN est embarqué dans le bundle de production. Zéro événement = aucune erreur non gérée à ce jour.
- **API** : le SDK **n'est pas installé** (ni `sentry/sentry-symfony` dans `composer.json`, ni configuration ; seulement des restes de recette dans `.env`). Le secret Fly `SENTRY_DSN` est inutilisé. Une erreur 500 en production n'alerte personne et n'est lisible que dans `flyctl logs`, sans historique.
- **Mobile** : la dépendance est déclarée mais jamais initialisée (hors périmètre ici, cf plus bas).

La bêta payante approche : la première panne ne doit pas être découverte par un testeur.

## Decision

Installer **`sentry/sentry-symfony` 5.13** (SDK `sentry/sentry` 4.32), activé uniquement quand `SENTRY_DSN` est défini (donc en production : dev et test restent inertes), avec une configuration **minimale et filtrée** :

- **Erreurs seulement** : pas de traces de performance (`traces_sample_rate: 0`, `tracing.enabled: false`), pas de fils d'Ariane (`max_breadcrumbs: 0`).
- **Aucune donnée personnelle par défaut** : `send_default_pii: false`, `max_request_body_size: none`.
- **Filtre `before_send`** (`App\Infrastructure\Observability\SentryBeforeSend`, testé) appliqué à chaque événement :
  1. **écarte les erreurs client (4xx)**, y compris les exceptions du domaine que API Platform transforme en 404/409/422 : ce sont des réponses normales, pas des pannes ;
  2. **requête : liste blanche** — on ne garde que la méthode, l'URL **sans query string** (le callback OAuth y porte `code` et `state`) et les en-têtes `host`, `user-agent`, `content-type`, `accept`, `x-client-type`. Tout le reste est supprimé : `Authorization`, `Cookie`, `X-Forwarded-For`, corps, cookies, variables serveur (dont l'IP cliente) ;
  3. **supprime l'utilisateur** de l'événement ;
  4. **masque les emails et les numéros de téléphone français** dans les messages d'erreur ;
  5. **retire les arguments des fonctions** des traces : sans `zend.exception_ignore_args` (non défini dans l'image), une trace passant par `isPasswordValid($user, $motDePasse)` contiendrait le mot de passe en clair.
- Un test d'intégration vérifie que ces options sont **réellement appliquées par le bundle** (une clé mal orthographiée serait sinon ignorée sans bruit).

### Hors périmètre

- **Mobile** : à traiter à part (initialiser avec le même niveau de filtrage, ou retirer la dépendance). Le mobile envoie davantage de contexte d'appareil ; décision distincte.
- **Web** : déjà branché, non revu ici ; à auditer avec la même grille.

### Choix écartés

- **`send_default_pii: true`** : l'IP et l'utilisateur aideraient au diagnostic mais exposent des données personnelles à un tiers pour un gain faible en bêta privée.
- **Retirer les secrets uniquement par liste noire d'en-têtes** : un nouvel en-tête sensible passerait. La liste blanche échoue du côté sûr.
- **Ne rien envoyer et se contenter des logs Fly** : pas d'alerte, pas d'historique.

## Implications sécurité

- **Surface d'attaque ajoutée** : une dépendance externe (SDK Sentry et deux utilitaires : `jean85/pretty-package-versions`, `symfony/psr-http-message-bridge` 7.4), une connexion sortante de l'API vers `*.ingest.de.sentry.io`. Le SDK s'exécute dans le processus de l'API : une compromission de la chaîne d'approvisionnement serait équivalente à celle de n'importe quelle dépendance (`composer audit` bloquant en CI, SHA des actions épinglés).
- **Secrets manipulés** : le DSN. Il n'autorise que l'envoi d'événements (pas de lecture) ; il est déjà présent comme secret Fly. Le jeton de lecture `sentry-cli` du fondateur reste local.
- **Données personnelles envoyées à un tiers** (Functional Software, Inc. — Sentry) : nom de classe et message de l'exception (emails et téléphones masqués par motif), chemin d'appel (fichiers, fonctions, lignes), méthode et chemin de la requête (qui peut contenir l'UUID d'un chantier, identifiant opaque), en-têtes de la liste blanche, nom de la machine et version. **Ni IP, ni utilisateur, ni corps, ni cookie, ni jeton, ni arguments de fonctions.**
- **Limites connues** : (1) le masquage de texte libre repose sur des motifs : un nom ou une adresse postale dans un message d'exception ne serait pas masqué ; (2) un message d'exception peut citer une valeur saisie par l'utilisateur. Atténuation : les exceptions du domaine portent des identifiants et non des valeurs de champs (à garder comme règle d'écriture), et les validations (422) sont écartées.
- **Base légale RGPD** : intérêt légitime (sécurité et maintenance du service), avec minimisation ci-dessus. Données hébergées en **Union européenne** (le DSN pointe sur `ingest.de.sentry.io`, région de stockage Allemagne). Une mention de ce sous-traitant doit figurer dans la politique de confidentialité avant la bêta payante.
- **Points de fuite potentiels** : un futur code qui place une valeur personnelle dans un message d'exception ; une montée de version du SDK qui ajouterait un nouveau champ envoyé par défaut (le test de configuration et le test du filtre servent de filet ; relire le changelog du SDK à chaque montée majeure).

## Consequences

**Positives** : les erreurs 5xx de l'API deviennent visibles, avec historique, et peuvent déclencher une alerte ; le trou d'observabilité avant la bêta est fermé ; le filtrage est testé et échoue du côté sûr.

**Négatives / à surveiller** : un sous-traitant de plus à déclarer ; un nouveau code à maintenir (le filtre) ; volume d'événements à surveiller pour rester dans le quota du plan.

### Actions manuelles (hors dépôt)

1. **Alerte** : dans Sentry (projet `cordeau-api`), créer une règle d'alerte par email sur « nouvelle issue » (et « issue régressée »).
2. **Rétention** : vérifier dans Sentry (Settings → Data Retention) la durée de conservation du plan, et la renseigner dans la politique de confidentialité.
3. **Vérification après déploiement** : avec `sentry-cli issues list --org maximegloaguen --project cordeau-api`, après une erreur contrôlée sur un environnement de test, jamais en production.
