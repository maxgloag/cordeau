# Politique de sécurité

Cordeau est un projet en pré-bêta, porté par une personne. Il n'a pas encore d'utilisateurs en production.

## Signaler une vulnérabilité

**Ne publiez pas de faille dans une issue, une PR ou un commentaire.** Utilisez le signalement privé de GitHub :
onglet **Security** → **Report a vulnerability** du dépôt.

Précisez, autant que possible :

- le composant concerné (`apps/api`, `apps/web`, `apps/mobile`, CI) et la version ou le commit ;
- les étapes pour reproduire, et l'impact que vous constatez ;
- si la faille touche des données d'un autre utilisateur.

Je réponds dans la mesure du possible, sans délai garanti. Il n'y a pas de programme de récompense.

## Périmètre

Dans le périmètre : le code de ce dépôt (API Symfony, web React, mobile Expo), ses workflows GitHub Actions et sa configuration de déploiement.

Hors périmètre : le déni de service volumétrique, l'ingénierie sociale, les services tiers (Fly.io, Neon, Cloudflare, Sentry, Google) et les tests contre une instance qui n'est pas la vôtre.

## Ce que le dépôt public contient

Le dépôt est public pour bénéficier des protections de GitHub (analyse du code, détection de secrets, alertes de dépendances). Les secrets d'exécution vivent hors du dépôt (`fly secrets`) ; les fichiers `.env` committés ne contiennent que des valeurs par défaut. Si vous trouvez un secret réel dans l'historique, signalez-le par le même canal privé.
