# ADR 0029 — Un process worker Fly consomme la file Messenger en production

- **Status** : Accepted (choix du fondateur le 2026-10-04, parmi trois options)
- **Date** : 2026-10-04
- **Deciders** : Maxime
- **Contrôle** : `apps/api/tests/Unit/Infrastructure/Messenger/ConsommateurMessengerTest.php`, `scripts/smoke-image.sh`
- **Lié à** : issue #124, [ADR 0023](0023-traitement-image-imagick-heic.md) (vignettes), [ADR 0004](0004-cloudflare-r2-stockage.md) (stockage R2)

## Context

Vérification de la production le 2026-10-04 :

- `MESSENGER_TRANSPORT_DSN` est un secret Fly dont le schéma est `doctrine` : les messages sont écrits dans la table `messenger_messages` de PostgreSQL.
- `apps/api/fly.toml` ne déclare que le process `app` (FrankenPHP). **Aucun process n'exécute `messenger:consume`.**
- Deux messages sont routés vers le transport `async` : `GenerateThumbnailMessage` (émis à chaque confirmation d'upload) et `DeleteR2ObjectMessage` (émis à chaque suppression de photo).

Conséquence : les messages s'accumulent sans être traités. Une photo reste sans vignette (`thumbnailUrl` à `null`) et les objets R2 des photos supprimées ne sont jamais effacés. Aucun test ne le détectait : le transport de test est `in-memory://`, et ni le handler ni l'émission du message n'étaient testés.

Trois options ont été comparées : un process worker Fly, un transport `sync://` jusqu'à la bêta, des machines planifiées.

## Decision

**Un process group `worker` dans `fly.toml`**, qui exécute `messenger:consume async` avec des limites de durée et de mémoire. `--time-limit` fait sortir le worker avec le code 0 : une politique `[[restart]] always` ciblée sur ce process le relance (la politique par défaut, `on-failure`, le laisserait arrêté).

- `[processes]` liste explicitement `app` et `worker` (une fois la section présente, Fly la traite comme la liste complète). La commande de `app` reproduit le `CMD` de l'image FrankenPHP.
- `http_service` reste attaché au seul process `app`. Le worker n'a ni service ni health check HTTP et n'est jamais arrêté automatiquement.
- La table `messenger_messages` est créée par `messenger:setup-transports` dans la `release_command`, après les migrations, pour qu'un environnement neuf (staging) fonctionne sans dépendre de l'`auto_setup` du DSN.
- Garde en test : un test unitaire échoue si un message est routé vers `async` alors que `fly.toml` ne déclare aucun `messenger:consume async`. Un test d'intégration vérifie que la confirmation d'upload émet bien `GenerateThumbnailMessage`, et un autre que le handler renseigne la vignette.

## Consequences

- Les vignettes sont générées, hors de la requête. Les messages déjà en file en prod seront traités au premier déploiement : les photos existantes recevront leur vignette et les objets R2 orphelins seront supprimés.
- Une machine supplémentaire reste allumée (`shared-cpu-1x`, 512 Mo : le décodage d'une photo HEIC en mémoire dépasse le confortable des 256 Mo de l'API ; quelques dollars par mois).
- **Risque à surveiller : le worker interroge PostgreSQL en continu**, ce qui empêche la mise en veille du calcul Neon et peut consommer le quota. Si le coût devient gênant : allonger `--sleep`, ou passer à Redis (#170 prévoit déjà Redis).
- Le handler de vignette retourne sans rien journaliser quand la photo ou le fichier source est introuvable : hors périmètre ici, à traiter séparément si des vignettes manquent encore.
- La commande de `app` dépend du `CMD` de l'image de base : à revérifier si l'image FrankenPHP change de tag majeur.

## Implications sécurité

- **Surface ajoutée** : aucune exposition réseau (le worker n'a pas de service).
- **Secrets** : le worker reçoit les mêmes secrets Fly que l'API (base, R2, Sentry). Pas de nouveau secret.
- **Données personnelles** : le worker lit des photos de chantier (potentiellement identifiables, au titre du RGPD) depuis R2 pour générer les vignettes, comme l'API le ferait ; aucune donnée ne sort de R2 et de Neon.
- **Points de fuite** : les messages en file ne contiennent que l'identifiant de la photo et sa clé R2, pas de binaire.
