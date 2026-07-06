# LaraJob — Laravel + PostgreSQL/pgvector on Railway.
# Full Dockerfile bypasses Railpack's build-time `artisan config:cache`
# (which runs with no env/DB and crashes). Config is read fresh at runtime.

# ---- Stage 1: build Vite assets ----
FROM node:20-slim AS assets
WORKDIR /app
COPY . .
RUN npm ci && npm run build

# ---- Stage 2: PHP runtime ----
FROM php:8.4-cli
WORKDIR /app

# PHP extensions (pdo_pgsql is critical for Postgres/pgvector)
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql mbstring dom xml bcmath zip gd intl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# PHP deps — no build-time artisan (--no-scripts); packages discovered at runtime
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# App source (vendor/node_modules excluded via .dockerignore)
COPY . .

# Built front-end assets from stage 1
COPY --from=assets /app/public/build ./public/build

# Writable dirs
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
 && chmod -R ug+rw storage bootstrap/cache

EXPOSE 8080
# Runtime (env + DB networking available): migrate, link storage, serve.
# NO seed — site launches clean. Add real data manually later if you want.
CMD sh -c "php artisan migrate --force && (php artisan storage:link || true); php artisan serve --host 0.0.0.0 --port ${PORT:-8080}"