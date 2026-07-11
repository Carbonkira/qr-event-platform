#!/bin/sh
set -e

# Render/Railway inject PORT at runtime, not build time - can't bake this
# into the image. Running migrations on every boot is fine here (single
# instance, no autoscaling), but skip it by removing this line if you'd
# rather run `php artisan migrate --force` manually via the platform's shell.
php artisan migrate --force

# Only matters when FILESYSTEM_PUBLIC_DRIVER=local (S3/R2 URLs are already
# absolute and don't go through this) - without it, anything on the
# "public" disk (payment screenshots, event cover images) 404s even
# though the file is sitting right there on disk. Guarded since the
# command errors if the link already exists, which it will on every boot
# after the first if this container's filesystem ever persists across restarts.
[ -L public/storage ] || php artisan storage:link

# nginx's own $uri/$document_root/etc use the same "$" syntax as envsubst -
# scoping this to just '${PORT}' stops it from blanking those out too.
envsubst '${PORT}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

php-fpm -D

exec nginx -g "daemon off;"
