#!/bin/bash
# Disk-usage alert.
#
# Cron: every hour. When / exceeds THRESHOLD_PCT, push a Telegram message
# via the existing SOS-Expat Telegram Engine webhook (no new bot needed).
#
# Idempotence: we only send an alert when we cross from "below" to "above"
# the threshold, by keeping a state file. We send a recovery message when
# we cross back down. That way we avoid spamming hourly while at 90%.
#
# Deps: curl, awk, df. No external packages.

set -euo pipefail

THRESHOLD_PCT=85
RECOVER_MARGIN=5   # must drop below (THRESHOLD - margin) to re-arm
STATE_FILE=/var/run/disk-alert.state
TELEGRAM_ENGINE_URL="${TELEGRAM_ENGINE_URL:-https://engine-telegram-sos-expat.life-expat.com}"
ENGINE_SECRET_FILE="${ENGINE_SECRET_FILE:-/opt/partner-engine/.secrets/telegram-engine-secret}"
HOSTNAME="$(hostname)"

usage_pct=$(df --output=pcent / | tail -1 | tr -d ' %')
used_human=$(df -h --output=used / | tail -1 | tr -d ' ')
avail_human=$(df -h --output=avail / | tail -1 | tr -d ' ')

state="ok"
if [ -f "$STATE_FILE" ]; then
    state=$(cat "$STATE_FILE" 2>/dev/null || echo ok)
fi

send_telegram() {
    local event="$1"
    local title="$2"
    local body="$3"

    local secret=""
    if [ -f "$ENGINE_SECRET_FILE" ]; then
        secret=$(cat "$ENGINE_SECRET_FILE")
    fi

    curl -sS --max-time 10 \
        -X POST "$TELEGRAM_ENGINE_URL/api/events/$event" \
        -H "Content-Type: application/json" \
        -H "X-Engine-Secret: $secret" \
        -d "{\"title\":\"$title\",\"body\":\"$body\",\"host\":\"$HOSTNAME\"}" \
        > /dev/null || true
}

if [ "$usage_pct" -ge "$THRESHOLD_PCT" ] && [ "$state" != "alerting" ]; then
    send_telegram "security_alert" \
        "[${HOSTNAME}] Disk ${usage_pct}%" \
        "Root partition at ${usage_pct}% used (${used_human} used, ${avail_human} free). Threshold ${THRESHOLD_PCT}%. Run: docker image prune -f && docker builder prune -f && journalctl --vacuum-time=7d"
    echo alerting > "$STATE_FILE"
    echo "$(date -Is) ALERT: disk at ${usage_pct}%"
elif [ "$usage_pct" -lt "$((THRESHOLD_PCT - RECOVER_MARGIN))" ] && [ "$state" = "alerting" ]; then
    send_telegram "security_alert" \
        "[${HOSTNAME}] Disk recovered (${usage_pct}%)" \
        "Root partition back to ${usage_pct}% used (${used_human} used, ${avail_human} free). No action needed."
    echo ok > "$STATE_FILE"
    echo "$(date -Is) RECOVERED: disk at ${usage_pct}%"
else
    # No state transition
    :
fi
