#!/bin/sh
# Migrations run here instead of AUTORUN_LARAVEL_MIGRATION so the database is
# copied first. No `exit`: the image may source this script rather than run it.
if cd /var/www/html; then
    if php artisan db:snapshot --no-interaction; then
        php artisan migrate --force --no-interaction \
            || echo "SLIPNOTE: migration FAILED. The pre-migration copy is in database/backups." >&2
    else
        echo "SLIPNOTE: database snapshot FAILED, so migrations were SKIPPED. Free disk space and redeploy." >&2
    fi
fi
