# Render has no native PHP runtime (unlike Node/Python/Ruby) - Laravel apps
# deploy there via Docker. Kept deliberately simple (php artisan serve, not
# nginx+php-fpm) since this app's traffic doesn't need more than that yet -
# revisit if that changes.
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
        libpq-dev libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip gd bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
