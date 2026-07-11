#!/bin/sh
set -e

# Same background+foreground pattern as entrypoint.sh (php-fpm + nginx), for
# the same reason: a platform's "Custom Start Command" isn't guaranteed to be
# run through a shell that understands `&`, so relying on
# `schedule:work & queue:work` directly in that field can silently run only
# the first command - confirmed in production (5 jobs stuck forever, 0
# reaching Resend, despite schedule:work's own ticks showing up in the logs).
# Invoking this script explicitly via `sh` guarantees a real shell.
php artisan schedule:work &
exec php artisan queue:work --tries=3
