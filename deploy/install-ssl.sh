#!/bin/bash
# Install Let's Encrypt SSL certificates for SOS-Call subdomains.
# Run ONCE on the VPS after DNS is configured.
#
# Prerequisites:
#   1. DNS A-records pointing to this VPS (check with: dig admin.sos-expat.com +short)
#   2. certbot installed: apt install -y certbot
#   3. Nginx vhosts copied to /etc/nginx/sites-available/ (use the .conf files in this directory)
#      BUT NOT YET ENABLED (we enable them after cert is obtained)
#
# Usage: sudo bash install-ssl.sh

set -euo pipefail

# Create webroot for HTTP-01 challenge
sudo mkdir -p /var/www/certbot

# Temporary HTTP-only vhost to serve the challenge
cat > /tmp/acme-challenge.conf <<'EOF'
server {
    listen 80;
    server_name admin.sos-expat.com sos-call.sos-expat.com partner-engine.sos-expat.com;
    location ^~ /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    location / { return 200 'acme-ok'; add_header Content-Type text/plain; }
}
EOF

sudo cp /tmp/acme-challenge.conf /etc/nginx/sites-available/acme-challenge.conf
sudo ln -sf /etc/nginx/sites-available/acme-challenge.conf /etc/nginx/sites-enabled/acme-challenge.conf
sudo nginx -t && sudo systemctl reload nginx

echo "→ Requesting SSL certificates via Let's Encrypt..."
sudo certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email admin@sos-expat.com \
    --agree-tos \
    --no-eff-email \
    --rsa-key-size 4096 \
    -d admin.sos-expat.com \
    -d sos-call.sos-expat.com \
    -d partner-engine.sos-expat.com

echo "→ Removing temporary challenge vhost..."
sudo rm /etc/nginx/sites-enabled/acme-challenge.conf

echo "→ Enabling production vhosts..."
sudo ln -sf /etc/nginx/sites-available/nginx-admin.sos-expat.com.conf      /etc/nginx/sites-enabled/admin.sos-expat.com.conf
sudo ln -sf /etc/nginx/sites-available/nginx-sos-call.sos-expat.com.conf   /etc/nginx/sites-enabled/sos-call.sos-expat.com.conf
sudo ln -sf /etc/nginx/sites-available/nginx-partner-engine.sos-expat.com.conf /etc/nginx/sites-enabled/partner-engine.sos-expat.com.conf

sudo nginx -t && sudo systemctl reload nginx

echo "✅ SSL installed for 3 subdomains."
echo "→ Verifying renewal config (certbot auto-renewal should already be a systemd timer):"
sudo systemctl list-timers | grep certbot || echo "WARN: certbot timer not found — check 'systemctl status certbot.timer'"
echo ""
echo "Next: smoke-test each domain"
echo "  curl -I https://admin.sos-expat.com/admin/login"
echo "  curl -I https://sos-call.sos-expat.com/"
echo "  curl -I https://partner-engine.sos-expat.com/up"
