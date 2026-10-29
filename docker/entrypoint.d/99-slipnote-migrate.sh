#!/bin/sh
# Migrations run here instead of AUTORUN_LARAVEL_MIGRATION so the database is
# copied first. The image sources this in a subshell under `set -e`: a non-zero
# exit aborts the start, and the previous container keeps serving. Only a
# deploy that brings migrations copies anything, so plain restarts never
# rotate an older copy away.
cd /var/www/html || exit 1

if php artisan migrate:status --pending=1 --no-interaction >/dev/null 2>&1; then
    echo "SLIPNOTE: no pending migrations."
    exit 0
fi

if ! php artisan db:snapshot --no-interaction; then
    echo "SLIPNOTE: database snapshot FAILED, so the start was aborted before migrating. Free disk space and redeploy." >&2
    exit 1
fi

if ! php artisan migrate --force --no-interaction; then
    echo "SLIPNOTE: migration FAILED; the start was aborted. The pre-migration copy is in the backups folder beside the database." >&2
    exit 1
fi
