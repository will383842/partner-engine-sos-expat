#!/bin/sh
set -e

# Publish Filament assets (CSS/JS for admin panel) — idempotent, safe to re-run
# Without this, admin panel UI has no styling.
php artisan filament:assets 2>/dev/null || true

# Copy public files to shared volume for Nginx
if [ -d /var/www/html/public-shared ]; then
    cp -r /var/www/html/public/* /var/www/html/public-shared/ 2>/dev/null || true
fi

# View cache cleared (Filament views need fresh cache after upgrade)
php artisan view:clear 2>/dev/null || true

# Route cache only (config cache breaks firebase credentials path resolution)
php artisan route:clear 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

exec "$@"
