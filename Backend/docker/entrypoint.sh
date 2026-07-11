#!/bin/sh
set -e

# Render injects PORT at runtime, not build time - can't bake this into
# the image. Running migrations on every boot is fine here (single
# instance, no autoscaling), but skip it by removing this line if you'd
# rather run `php artisan migrate --force` manually via Render's shell.
php artisan migrate --force

# Only matters when FILESYSTEM_PUBLIC_DRIVER=local (S3/R2 URLs are already
# absolute and don't go through this) - without it, anything on the
# "public" disk (payment screenshots, event cover images) 404s even
# though the file is sitting right there on disk. Guarded since the
# command errors if the link already exists, which it will on every boot
# after the first if this container's filesystem ever persists across restarts.
[ -L public/storage ] || php artisan storage:link

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
