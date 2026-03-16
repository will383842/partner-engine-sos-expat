#!/bin/sh
set -e

# Copy public files to shared volume for Nginx
if [ -d /var/www/html/public-shared ]; then
    cp -r /var/www/html/public/* /var/www/html/public-shared/ 2>/dev/null || true
fi

# Route cache only (config cache breaks firebase credentials path resolution)
php artisan route:cache 2>/dev/null || true

exec "$@"
