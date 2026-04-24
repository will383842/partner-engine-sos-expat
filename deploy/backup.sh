#!/bin/bash
# Backup script for Partner Engine (DB + storage + env)
#
# Runs daily via cron: 0 3 * * * /opt/partner-engine/deploy/backup.sh
#
# Outputs to /opt/backups/partner-engine/
#   db/YYYY-MM-DD.sql.gz        (pg_dump, gzipped)
#   storage/YYYY-MM-DD.tar.gz   (storage/app — invoices PDFs, firebase creds)
#   env/YYYY-MM-DD.env.gpg      (encrypted .env.production)
#
# Rotation: daily kept 30 days, monthly (1st of month) kept 12 months.

set -e

BACKUP_ROOT=/opt/backups/partner-engine
DATE=$(date +%Y-%m-%d)
MONTH_DAY=$(date +%d)
APP_DIR=/opt/partner-engine
LOG=/var/log/partner-engine-backup.log

mkdir -p "$BACKUP_ROOT"/{db,storage,env,monthly}

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"; }

log "==== Backup start ===="

# 1. DB dump from pe-postgres container
log "Dumping PostgreSQL partner_engine"
cd "$APP_DIR"
DB_USER="${DB_USERNAME:-partner}"
DB_NAME="${DB_DATABASE:-partner_engine}"
docker compose exec -T postgres pg_dump -U "$DB_USER" -d "$DB_NAME" --no-owner --no-acl \
    | gzip > "$BACKUP_ROOT/db/${DATE}.sql.gz"
DB_SIZE=$(du -h "$BACKUP_ROOT/db/${DATE}.sql.gz" | cut -f1)
log "DB dump: ${DB_SIZE}"

# 2. Storage (invoices PDFs + firebase credentials)
log "Archiving storage/app"
tar czf "$BACKUP_ROOT/storage/${DATE}.tar.gz" \
    -C "$APP_DIR" storage/app 2>/dev/null || true
STORAGE_SIZE=$(du -h "$BACKUP_ROOT/storage/${DATE}.tar.gz" | cut -f1)
log "Storage archive: ${STORAGE_SIZE}"

# 3. .env.production (restricted, no encryption here — file mode 0600)
if [ -f "$APP_DIR/.env.production" ]; then
    cp "$APP_DIR/.env.production" "$BACKUP_ROOT/env/${DATE}.env"
    chmod 600 "$BACKUP_ROOT/env/${DATE}.env"
    log ".env.production saved (mode 0600)"
fi

# 4. Monthly snapshot (1st of month) — copy to monthly/ for longer retention
if [ "$MONTH_DAY" = "01" ]; then
    cp "$BACKUP_ROOT/db/${DATE}.sql.gz" "$BACKUP_ROOT/monthly/${DATE}.sql.gz"
    log "Monthly snapshot created"
fi

# 5. Rotation: delete daily files older than 30 days
find "$BACKUP_ROOT/db" -maxdepth 1 -name '*.sql.gz' -mtime +30 -delete
find "$BACKUP_ROOT/storage" -maxdepth 1 -name '*.tar.gz' -mtime +30 -delete
find "$BACKUP_ROOT/env" -maxdepth 1 -name '*.env' -mtime +30 -delete
# Monthly: keep 12 months
find "$BACKUP_ROOT/monthly" -maxdepth 1 -name '*.sql.gz' -mtime +400 -delete

# 6. Summary
TOTAL=$(du -sh "$BACKUP_ROOT" | cut -f1)
COUNT_DB=$(ls "$BACKUP_ROOT/db/" | wc -l)
COUNT_STORAGE=$(ls "$BACKUP_ROOT/storage/" | wc -l)
log "Total backup size: ${TOTAL} | DB files: ${COUNT_DB} | Storage files: ${COUNT_STORAGE}"
log "==== Backup done ===="

# 7. Fail-safe: alert if backup smaller than 1KB (likely dump failed)
if [ ! -s "$BACKUP_ROOT/db/${DATE}.sql.gz" ] || [ $(stat -c%s "$BACKUP_ROOT/db/${DATE}.sql.gz") -lt 1024 ]; then
    log "ERROR: DB dump too small or empty!"
    exit 1
fi
