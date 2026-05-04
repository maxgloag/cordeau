# FrankenPHP : serveur PHP moderne (Caddy intégré, HTTP/2, HTTP/3, workers natifs)
# Image officielle, alpine-based, supporte ARM (Fly.io shared-cpu-1x est ARM)
FROM dunglas/frankenphp:1-php8.5-alpine AS base

# Extensions PHP nécessaires : pdo_pgsql (Doctrine PostgreSQL), redis (cache/messenger),
# intl (Symfony i18n + validation), opcache (perf prod), apcu (cache local)
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    opcache \
    apcu

# Composer (copié depuis l'image officielle, plus léger que d'installer via package manager)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# --- Étape de build ---
# On copie d'abord uniquement les manifests pour profiter du cache Docker :
# tant que composer.json/lock ne changent pas, les deps sont en cache
COPY composer.json composer.lock symfony.lock ./

# Install des deps prod uniquement, sans scripts (qui nécessiteraient la codebase complète)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Maintenant on copie tout le reste
COPY . .

# Dump de l'autoloader optimisé (classmap authoritatif = lookup direct, pas de filesystem scan)
# On lance les scripts post-install (cache:clear, assets:install) maintenant que tout est là
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script post-install-cmd --no-dev

# Permissions : FrankenPHP tourne en non-root, www-data doit pouvoir écrire dans var/
RUN chown -R www-data:www-data /app/var

# FrankenPHP écoute sur :80 par défaut, on l'expose
ENV SERVER_NAME=:80
EXPOSE 80

# Healthcheck Docker — Fly s'en sert aussi via [http_service.checks] dans fly.toml
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost/health || exit 1
