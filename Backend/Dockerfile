# Neither Railway nor Render have a native PHP runtime (unlike Node/Python/
# Ruby) - Laravel apps deploy to both via Docker, and this same image works
# on either.
#
# This used to run `php artisan serve` directly - Laravel's own docs say not
# to use that in production, and it turns out to be more than a style
# preference: it handles exactly one request at a time, so a single slow
# request (a DB write, an external API call, anything) blocks the *entire*
# service for *every* user until it resolves - confirmed in production, not
# theoretical. php-fpm + nginx actually serves requests concurrently.
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y nginx gettext-base \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

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

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

# nginx's $uri/$document_root/etc use the same "$" syntax as shell/envsubst -
# substituting the whole file would blank all of those out. entrypoint.sh
# runs envsubst scoped to just '${PORT}' to avoid that.
COPY docker/nginx.conf.template /etc/nginx/conf.d/default.conf.template
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/worker-start.sh /worker-start.sh
RUN chmod +x /entrypoint.sh /worker-start.sh

# CMD (not ENTRYPOINT) deliberately - a platform's "custom start command"
# setting replaces CMD, not ENTRYPOINT, so the queue-worker service can
# override this with `php artisan queue:work --tries=3` directly instead
# of going through entrypoint.sh (which also runs migrations and starts
# nginx - the worker service needs neither).
CMD ["/entrypoint.sh"]
