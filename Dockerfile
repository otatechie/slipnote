# syntax=docker/dockerfile:1.7
# Cache-bust: 2026-05-21-3 (trust Cloudflare proxy — fix Livewire upload 401)

# ---- Stage 1: Build front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources

# Vite bakes APP_URL into the build manifest (preload Link headers,
# absolute asset URLs). Must be the public HTTPS origin or you get
# mixed-content errors on production. Supply via --build-arg APP_URL=...
# in Dokploy → Build → Build Arguments.
ARG APP_URL=https://slipnote.co
ENV APP_URL=${APP_URL}

RUN npm run build

# ---- Stage 2: Install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-scripts \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# ---- Stage 3: Runtime image (nginx + php-fpm) ----
FROM serversideup/php:8.4-fpm-nginx AS runtime

# Dokploy notes (set as env vars on the service):
#   APP_ENV=production, APP_DEBUG=false, APP_KEY=base64:...
#   DB_CONNECTION=sqlite, DB_DATABASE=/var/www/html/database/database.sqlite
#   FILESYSTEM_DISK=public  (uploads live on a persistent volume)
#   SESSION_DRIVER=database, CACHE_STORE=database, QUEUE_CONNECTION=database
# Mount persistent volumes:
#   /var/www/html/database               -> SQLite file (NEVER let this disappear)
#   /var/www/html/storage/app/public     -> user uploads (course materials)
#   /var/www/html/storage/framework      -> sessions/views/cache
#   /var/www/html/storage/logs           -> optional, helpful
ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    # Disable build-time route/config/view caching: caches get baked before
    # the runtime .env is in place, so requests 404 with stale routes. With
    # opcache enabled the perf cost is negligible for an app this size.
    AUTORUN_LARAVEL_CONFIG_CACHE=false \
    AUTORUN_LARAVEL_ROUTE_CACHE=false \
    AUTORUN_LARAVEL_VIEW_CACHE=false \
    AUTORUN_LARAVEL_EVENT_CACHE=false

USER root
RUN install-php-extensions gd exif intl pdo_sqlite bcmath \
 && install -d -o www-data -g www-data \
        /var/www/html/database \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/logs
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# storage:link is best-effort; safe to fail if already linked on a redeploy
RUN php artisan storage:link || true
