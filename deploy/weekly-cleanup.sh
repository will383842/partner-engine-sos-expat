#!/bin/bash
# Weekly safe cleanup — runs every Sunday 4am.
#
# Three operations, all strictly safe (nothing running in Docker is touched,
# no other project's volumes or images are removed):
#   - docker image prune -f  (only <none> tagged dangling images)
#   - docker builder prune -f (build cache only)
#   - journalctl --vacuum-time=14d (systemd journal older than 14 days)
#
# Logs its before/after disk usage to /var/log/weekly-cleanup.log.

set -e

LOG=/var/log/weekly-cleanup.log
{
    echo "==== $(date -Is) Weekly cleanup start ===="
    echo "BEFORE:"
    df -h / | tail -1
    echo ""

    echo "-- docker image prune -f --"
    docker image prune -f 2>&1 | tail -3 || true
    echo ""

    echo "-- docker builder prune -f --"
    docker builder prune -f 2>&1 | tail -3 || true
    echo ""

    echo "-- journalctl --vacuum-time=14d --"
    journalctl --vacuum-time=14d 2>&1 | tail -3 || true
    echo ""

    echo "AFTER:"
    df -h / | tail -1
    echo "==== Done ===="
    echo ""
} >> "$LOG" 2>&1
