#!/bin/bash
# Fix 405 "Method Not Allowed" sur Filament admin login
#
# Cause probable : route:cache a été exécuté AVANT que Livewire enregistre
# sa route POST /livewire/update, donc le cache ne la contient pas.
#
# Solution : purger tous les caches, puis seulement re-cacher config (pas routes).
#
# Usage sur VPS : bash /opt/partner-engine/deploy/fix-405-livewire.sh

set -e
cd /opt/partner-engine

echo "==> 1. Clearing route cache (Livewire routes were missing)"
docker compose exec -T app php artisan route:clear

echo "==> 2. Clearing config cache"
docker compose exec -T app php artisan config:clear

echo "==> 3. Clearing view cache"
docker compose exec -T app php artisan view:clear

echo "==> 4. Clearing app cache"
docker compose exec -T app php artisan cache:clear

echo "==> 5. Re-caching config only (safe)"
docker compose exec -T app php artisan config:cache

echo "==> 6. Listing Livewire routes (must show POST /livewire/update)"
docker compose exec -T app php artisan route:list --path=livewire

echo "==> 7. Verifying login page is reachable"
curl -sI https://admin.sos-expat.com/admin/login | head -3

echo ""
echo "Fix applied. Please:"
echo "1. Open https://admin.sos-expat.com/admin/login in INCOGNITO mode"
echo "2. Enter credentials and click Connexion"
echo "3. The 405 should be gone"
