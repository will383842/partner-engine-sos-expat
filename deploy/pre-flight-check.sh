#!/bin/bash
# Pre-flight check before deploying SOS-Call on a SHARED VPS.
# This script READS ONLY — it makes NO changes to your server.
#
# Run it on the VPS BEFORE pushing anything:
#   ssh root@95.216.179.163
#   curl -sSL https://raw.githubusercontent.com/.../pre-flight-check.sh | bash
#   OR: bash /opt/partner-engine/deploy/pre-flight-check.sh (after git pull)

set +e  # Don't fail on single check

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

section() { echo -e "\n${BLUE}── $1 ──${NC}"; }
ok() { echo -e "${GREEN}✅${NC} $1"; }
warn() { echo -e "${YELLOW}⚠️${NC}  $1"; }
err() { echo -e "${RED}❌${NC} $1"; }
info() { echo -e "   $1"; }

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " VPS pre-flight check — safe, read-only"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Système
section "1. Ressources système"
echo "  Hostname : $(hostname)"
echo "  Uptime   : $(uptime -p 2>/dev/null || uptime)"
free -h | head -2 | sed 's/^/  /'
df -h / /opt 2>/dev/null | sed 's/^/  /'
echo ""
RAM_FREE=$(free -m | awk 'NR==2{print $7}')
if [ "$RAM_FREE" -lt 500 ]; then
    warn "RAM libre <500 MB — risque pendant le composer install Filament (peut être lourd)"
    info "Solution : arrêter un service non critique temporairement, ou swap activé"
else
    ok "RAM libre : ${RAM_FREE} MB"
fi

DISK_FREE_GB=$(df -BG / | awk 'NR==2{print $4}' | tr -d 'G')
if [ "$DISK_FREE_GB" -lt 5 ]; then
    err "Disque libre <5 GB — certbot + composer pourraient échouer"
else
    ok "Disque libre : ${DISK_FREE_GB} GB"
fi

# Services détectés
section "2. Services en écoute"
echo "  Ports écoutés :"
ss -tlnp 2>/dev/null | awk 'NR>1 {print "    "$1" "$4" → "$6}' | sort -u | head -20

section "3. Conteneurs Docker en cours"
if command -v docker >/dev/null 2>&1; then
    if docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>/dev/null | head -20; then
        :
    else
        err "docker ps échoue (droits ?)"
    fi
else
    warn "Docker non installé ?"
fi

# Nginx sites
section "4. Vhosts Nginx existants"
if [ -d /etc/nginx/sites-enabled ]; then
    echo "  Vhosts actifs :"
    ls -l /etc/nginx/sites-enabled/ 2>/dev/null | sed 's/^/    /' | head -10

    # Check for collisions with our new domains
    for d in admin.sos-expat.com sos-call.sos-expat.com partner-engine.sos-expat.com; do
        if grep -rE "server_name.*\b${d//./\\.}" /etc/nginx/sites-available/ 2>/dev/null | grep -v ":0" | head -1 | grep -q .; then
            warn "Domaine $d déjà présent dans un vhost existant — vérifier avant d'ajouter le nouveau"
        else
            ok "Domaine $d libre"
        fi
    done
else
    warn "/etc/nginx/sites-enabled introuvable — Nginx non installé ?"
fi

# Partner Engine existant
section "5. Partner Engine (état actuel avant déploiement)"
if [ -d /opt/partner-engine ]; then
    ok "Partner Engine installé"
    cd /opt/partner-engine
    echo "  Branche git : $(git branch --show-current 2>/dev/null || echo '?')"
    echo "  Dernier commit : $(git log --oneline -1 2>/dev/null || echo '?')"

    # Compter les partenaires existants avec sos_call_active — IMPORTANT
    if docker compose ps pe-postgres 2>/dev/null | grep -q Up; then
        ok "PostgreSQL container Up"

        # Test si la table agreements a déjà la colonne sos_call_active
        COL_EXISTS=$(docker compose exec -T pe-postgres psql -U partner -d partner_engine -tAc "SELECT 1 FROM information_schema.columns WHERE table_name='agreements' AND column_name='sos_call_active'" 2>/dev/null)
        if [ "$COL_EXISTS" = "1" ]; then
            warn "La colonne agreements.sos_call_active existe DÉJÀ — migration 000001 va sauter"
            COUNT_ACTIVE=$(docker compose exec -T pe-postgres psql -U partner -d partner_engine -tAc "SELECT COUNT(*) FROM agreements WHERE sos_call_active=true" 2>/dev/null)
            info "Partenaires avec sos_call_active=true actuellement : $COUNT_ACTIVE"
            info "(devrait être 0 au premier déploiement — si >0 c'est un test antérieur à vérifier)"
        else
            ok "Colonne sos_call_active n'existe pas encore — migration va tourner proprement"
        fi

        # Taille des tables principales (pour estimer durée migration)
        SUBS_COUNT=$(docker compose exec -T pe-postgres psql -U partner -d partner_engine -tAc "SELECT COUNT(*) FROM subscribers" 2>/dev/null || echo "?")
        AGR_COUNT=$(docker compose exec -T pe-postgres psql -U partner -d partner_engine -tAc "SELECT COUNT(*) FROM agreements" 2>/dev/null || echo "?")
        info "Subscribers actuels : $SUBS_COUNT"
        info "Agreements actuels  : $AGR_COUNT"
        if [ "$SUBS_COUNT" -gt 100000 ] 2>/dev/null; then
            warn "Table subscribers >100k rows — migration 000002 (5 ADD COLUMN + 2 INDEX) peut prendre plusieurs minutes"
        fi
    else
        warn "Container pe-postgres pas Up"
    fi

    # Vérifier que SOS_CALL secrets sont dans .env (ou pas)
    for key in STRIPE_SECRET STRIPE_WEBHOOK_SECRET FIREBASE_PARTNER_BRIDGE_API_KEY ENGINE_API_KEY; do
        if grep -qE "^${key}=.+" .env 2>/dev/null; then
            val=$(grep "^${key}=" .env | head -1 | sed 's/^[^=]*=//')
            if [ -z "$val" ] || [ "$val" = "" ] || echo "$val" | grep -qE "REPLACE_ME|XXX|CHANGE_ME"; then
                err "$key présent mais vide ou placeholder"
            else
                ok "$key défini ($(echo "$val" | head -c 10)...)"
            fi
        else
            warn "$key manquant dans .env"
        fi
    done
else
    err "/opt/partner-engine introuvable"
fi

# Telegram Engine (shared VPS)
section "6. Autres services critiques sur le VPS"
if [ -d /opt/engine-telegram ]; then
    ok "Telegram Engine détecté (/opt/engine-telegram)"
    if command -v docker >/dev/null; then
        TG_CONTAINERS=$(docker ps --format '{{.Names}}' | grep -E '^tg-' | wc -l)
        if [ "$TG_CONTAINERS" -gt 0 ]; then
            ok "$TG_CONTAINERS container(s) Telegram Engine en cours"
            info "→ ATTENTION : ne pas toucher au docker-compose de /opt/engine-telegram"
        fi
    fi
fi

# Memory usage per container
section "7. Empreinte mémoire actuelle (avant déploiement)"
if command -v docker >/dev/null; then
    docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}" 2>/dev/null | head -10 | sed 's/^/  /'
    TOTAL_MEM_PCT=$(free | awk 'NR==2{printf "%.0f", $3/$2*100}')
    if [ "$TOTAL_MEM_PCT" -gt 75 ]; then
        err "Mémoire utilisée à ${TOTAL_MEM_PCT}% — l'ajout de Filament + workers peut saturer"
        info "Solution : ajouter du swap ou attendre la migration vers CPX22"
    else
        ok "Mémoire utilisée à ${TOTAL_MEM_PCT}% — marge suffisante"
    fi
fi

# PostgreSQL shared ?
section "8. PostgreSQL : isolation"
if docker ps --format '{{.Names}}' | grep -q pe-postgres; then
    # List all DBs
    DBS=$(docker compose -f /opt/partner-engine/docker-compose.yml exec -T pe-postgres psql -U partner -lqt 2>/dev/null | awk -F'|' '{print $1}' | grep -v template | grep -v postgres | grep -v '^ *$' | head -5)
    info "Bases PostgreSQL sur pe-postgres : $(echo $DBS | tr '\n' ' ')"
    if [ "$(echo "$DBS" | wc -l)" -gt 1 ]; then
        warn "pe-postgres héberge plusieurs bases — les migrations ne toucheront que 'partner_engine' ✓"
    fi
fi

# DNS pour nos domaines (doit être fait avant certbot)
section "9. DNS des nouveaux domaines"
for d in admin.sos-expat.com sos-call.sos-expat.com partner-engine.sos-expat.com; do
    ip=$(dig +short "$d" 2>/dev/null | head -1)
    if [ -n "$ip" ]; then
        if [ "$ip" = "$(curl -s ifconfig.me 2>/dev/null)" ]; then
            ok "DNS $d → $ip (= ce VPS)"
        else
            warn "DNS $d → $ip (≠ IP ce VPS — vérifier Cloudflare)"
        fi
    else
        info "DNS $d non résolu (pas encore configuré — faire Phase 1 du runbook)"
    fi
done

# Certbot
section "10. certbot disponible ?"
if command -v certbot >/dev/null 2>&1; then
    ok "certbot installé ($(certbot --version 2>&1 | head -1))"
else
    err "certbot non installé — installe-le : apt install -y certbot"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Pre-flight check terminé. Rien n'a été modifié."
echo " Relis les ⚠️  et ❌ avant de lancer install-ssl.sh"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
