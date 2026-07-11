# Neither Railway nor Render have a native PHP runtime (unlike Node/Python/
# Ruby) - Laravel apps deploy to both via Docker, and this same image works
# on either. Kept deliberately simple (php artisan serve, not nginx+php-fpm)
# since this app's traffic doesn't need more than that yet - revisit if that
# changes.
FROM php:8.2-cli

# install-php-extensions (mlocati) fetches prebuilt binaries instead of
# compiling from source - docker-php-ext-install compiling gd in particular
# was taking 30+ minutes on Railway's builder (vs ~10s locally with cached
# layers), to the point of looking hung. This is the standard fix.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql pgsql zip gd bcmath

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
