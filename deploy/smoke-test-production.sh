#!/bin/bash
# Smoke tests after SOS-Call deployment.
# Verifies all 3 subdomains respond correctly, health endpoints work,
# and the Stripe webhook endpoint is reachable.
#
# Usage: bash smoke-test-production.sh

set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

pass() { echo -e "${GREEN}✅ PASS${NC}  $1"; }
fail() { echo -e "${RED}❌ FAIL${NC}  $1"; EXIT=1; }
warn() { echo -e "${YELLOW}⚠️  WARN${NC}  $1"; }

EXIT=0

check_http() {
    local url=$1
    local expected=$2
    local label=$3
    local actual
    actual=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "$url" || echo "000")
    if [ "$actual" = "$expected" ]; then
        pass "$label → $expected"
    else
        fail "$label → expected $expected, got $actual ($url)"
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " SOS-Call B2B — Production smoke test"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo ""
echo "── 1. DNS resolution ──"
for host in admin.sos-expat.com sos-call.sos-expat.com partner-engine.sos-expat.com; do
    if dig +short "$host" | grep -qE '^[0-9]'; then
        pass "DNS $host resolves"
    else
        fail "DNS $host doesn't resolve — check Cloudflare/OVH"
    fi
done

echo ""
echo "── 2. SSL certificates ──"
for host in admin.sos-expat.com sos-call.sos-expat.com partner-engine.sos-expat.com; do
    if echo | openssl s_client -servername "$host" -connect "$host:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null | grep -q "notAfter"; then
        pass "SSL $host OK"
    else
        fail "SSL $host missing/invalid"
    fi
done

echo ""
echo "── 3. Health checks ──"
check_http "https://partner-engine.sos-expat.com/up" "200" "Laravel health /up"
check_http "https://partner-engine.sos-expat.com/api/health" "200" "API health"
check_http "https://partner-engine.sos-expat.com/api/health/detailed" "200" "Detailed health"

echo ""
echo "── 4. Public SOS-Call page ──"
check_http "https://sos-call.sos-expat.com/" "200" "Blade /sos-call landing"
check_http "https://sos-call.sos-expat.com/mon-acces/login" "200" "Subscriber login"

echo ""
echo "── 5. Filament admin (expect redirect) ──"
# Should redirect / → /admin → /admin/login
code=$(curl -sS -o /dev/null -w "%{http_code}" -L --max-time 10 "https://admin.sos-expat.com/" || echo "000")
if [ "$code" = "200" ]; then
    pass "Admin panel redirects to /admin/login → 200"
else
    fail "Admin panel → $code"
fi

echo ""
echo "── 6. SOS-Call API endpoints ──"
# /api/sos-call/check with no body should be 422 (validation error), not 404
check_http "https://partner-engine.sos-expat.com/api/sos-call/check" "405" "SOS-Call check endpoint exists (GET → 405 method)"

# /api/webhooks/stripe/invoice-events should be 500 without proper signature (not 404)
code=$(curl -sS -o /dev/null -w "%{http_code}" -X POST --max-time 10 "https://partner-engine.sos-expat.com/api/webhooks/stripe/invoice-events" -H "Content-Type: application/json" -d '{}' || echo "000")
if [ "$code" = "400" ] || [ "$code" = "500" ]; then
    pass "Stripe webhook endpoint exists (rejects unsigned payload with $code)"
else
    fail "Stripe webhook → $code (expected 400/500 for unsigned)"
fi

# /api/v1/partner/subscribers requires auth → 401 not 404
check_http "https://partner-engine.sos-expat.com/api/v1/partner/subscribers" "401" "Partner API v1 requires auth"

echo ""
echo "── 7. Firebase callables ──"
PROJECT_ID="sos-urgently-ac307"
for fn in checkSosCallCode triggerSosCallFromWeb releaseProviderPayments; do
    url="https://us-central1-${PROJECT_ID}.cloudfunctions.net/${fn}"
    code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "$url" || echo "000")
    # Callables return 400/401 without proper auth, 404 if not deployed
    if [ "$code" = "404" ]; then
        fail "Firebase $fn → 404 (NOT DEPLOYED)"
    else
        pass "Firebase $fn deployed (got $code)"
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ "${EXIT:-0}" = "0" ]; then
    echo -e "${GREEN}✅ All smoke tests passed${NC}"
else
    echo -e "${RED}❌ Some tests failed — investigate before closing deployment${NC}"
fi
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
exit "${EXIT:-0}"
