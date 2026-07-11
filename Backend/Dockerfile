# Neither Railway nor Render have a native PHP runtime (unlike Node/Python/
# Ruby) - Laravel apps deploy to both via Docker, and this same image works
# on either. Kept deliberately simple (php artisan serve, not nginx+php-fpm)
# since this app's traffic doesn't need more than that yet - revisit if that
# changes.
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

# CMD (not ENTRYPOINT) deliberately - a platform's "custom start command"
# setting replaces CMD, not ENTRYPOINT, so the queue-worker service can
# override this with `php artisan queue:work --tries=3` directly instead
# of going through entrypoint.sh (which also runs migrations - only the
# web service should do that, not every worker instance on every boot).
CMD ["/entrypoint.sh"]
