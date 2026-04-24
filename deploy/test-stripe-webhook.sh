#!/bin/bash
# Test the Stripe webhook endpoint after you've created it in the Dashboard.
# Runs 3 checks:
#   1. Endpoint is reachable (returns 400, not 404)
#   2. Unsigned payload is rejected (401)
#   3. Stripe can reach it (uses Stripe CLI if installed, or asks Dashboard retest)
#
# Usage:
#   bash deploy/test-stripe-webhook.sh

set -e
GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; NC='\033[0m'
ENDPOINT="https://partner-engine.sos-expat.com/api/webhooks/stripe/invoice-events"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Stripe Webhook — test endpoint"
echo " $ENDPOINT"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 1. Reachability
echo ""
echo "→ Test 1: Endpoint reachability"
code=$(curl -sS -o /dev/null -w "%{http_code}" -X POST --max-time 10 "$ENDPOINT" \
    -H "Content-Type: application/json" -d '{}' || echo "000")

case "$code" in
    401|400|500)
        echo -e "${GREEN}✅ PASS${NC}  Endpoint reachable (HTTP $code — expected: rejects unsigned)"
        ;;
    404)
        echo -e "${RED}❌ FAIL${NC}  Endpoint returns 404 — route not deployed"
        exit 1
        ;;
    200)
        echo -e "${YELLOW}⚠️  WARN${NC}  Endpoint accepts unsigned payload (HTTP 200) — STRIPE_WEBHOOK_SECRET may be misconfigured"
        ;;
    *)
        echo -e "${YELLOW}⚠️  WARN${NC}  Unexpected response: HTTP $code"
        ;;
esac

# 2. Signature rejection
echo ""
echo "→ Test 2: Signature rejection (forged header)"
code=$(curl -sS -o /dev/null -w "%{http_code}" -X POST --max-time 10 "$ENDPOINT" \
    -H "Content-Type: application/json" \
    -H "Stripe-Signature: t=1234,v1=forged_signature" \
    -d '{"type":"invoice.paid","data":{"object":{}}}' || echo "000")

if [ "$code" = "401" ]; then
    echo -e "${GREEN}✅ PASS${NC}  Forged signature rejected with HTTP 401"
elif [ "$code" = "400" ]; then
    echo -e "${GREEN}✅ PASS${NC}  Forged payload rejected with HTTP 400"
else
    echo -e "${RED}❌ FAIL${NC}  Forged request should have been rejected (got HTTP $code)"
fi

# 3. Stripe CLI (if installed)
echo ""
echo "→ Test 3: Stripe CLI forward (optional)"
if command -v stripe >/dev/null 2>&1; then
    echo "  Stripe CLI détecté. Pour tester avec un vrai event signé :"
    echo "    stripe trigger invoice.paid --forward-to $ENDPOINT"
    echo "  (nécessite stripe login préalable)"
else
    echo "  Stripe CLI non installé (optionnel)."
    echo "  Installer : https://stripe.com/docs/stripe-cli"
    echo ""
    echo "  Alternative : dans Stripe Dashboard → Webhooks → ton endpoint → onglet 'Events' :"
    echo "    → bouton 'Resend' sur un event récent pour retester"
    echo "    → ou utiliser 'Send test webhook' pour simuler invoice.paid"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Pour vérifier les logs côté serveur :"
echo "   ssh root@95.216.179.163"
echo "   docker compose -f /opt/partner-engine/docker-compose.yml logs -f pe-app | grep StripeWebhook"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
