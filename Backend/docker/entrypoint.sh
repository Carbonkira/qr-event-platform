#!/bin/sh
set -e

# Render injects PORT at runtime, not build time - can't bake this into
# the image. Running migrations on every boot is fine here (single
# instance, no autoscaling), but skip it by removing this line if you'd
# rather run `php artisan migrate --force` manually via Render's shell.
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
