# One image, both halves.
#
# The SPA is built in the first stage and its hashed assets are copied into the second, where
# Caddy — with PHP compiled in, via FrankenPHP — serves them straight off disk while
# everything it cannot find falls through to Laravel.
#
# That is not tidiness. The session is a cookie, and one origin means it works with no CORS
# negotiation at all, no second service to keep awake, and no chance of the two halves being
# deployed at different versions.

# ---------------------------------------------------------------------------
# 1. The SPA
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /build

# Dependencies before source, so a change to a component does not reinstall the world.
COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.ts tsconfig.json ./
COPY resources ./resources

RUN npm run build

# ---------------------------------------------------------------------------
# 2. The application
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3 AS runtime

RUN install-php-extensions pdo_pgsql intl zip opcache pcntl

# FrankenPHP ships with cap_net_bind_service so it can take port 80 unprivileged. Nothing
# here binds below 1024 — the platform hands us a port in the ten-thousands — and some hosts
# refuse to exec a binary carrying file capabilities at all. Stripped, and the build fails if
# any survive, because the failure otherwise shows up as a container that will not start and
# says nothing about why.
RUN setcap -r /usr/local/bin/frankenphp \
    && test -z "$(getcap /usr/local/bin/frankenphp)"

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Again dependencies first. `--no-scripts --no-autoloader` because the scripts want the
# application, which is not here yet.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
COPY --from=frontend /build/public/build ./public/build

# This also runs post-autoload-dump, which is what discovers the packages.
RUN composer dump-autoload --no-dev --no-interaction --classmap-authoritative

# Routes and views are compiled at build time so the first request after a cold start is not
# the one that pays for it.
#
# Config is deliberately *not* cached. A cached config freezes whatever the environment held
# when it was built, and at build time that is a throwaway key and a database that does not
# exist. Baking those in is how an application comes up talking to nothing.
#
# The throwaway values below exist only because booting the framework insists on them; they
# are set for this layer and go no further.
RUN APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
    DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=build DB_USERNAME=build DB_PASSWORD=build \
    sh -c 'php artisan route:cache && php artisan view:cache'

RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/touchline-entrypoint
RUN chmod +x /usr/local/bin/touchline-entrypoint

EXPOSE 8080

ENTRYPOINT ["touchline-entrypoint"]
