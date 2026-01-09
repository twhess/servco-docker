#!/bin/bash
#
# ServcoApp Database Backup Script
# Creates a timestamped backup of the database
#
# Usage: ./backup.sh [--quiet]
#
# Can be added to cron for automatic backups:
#   0 2 * * * /var/www/html/scripts/backup.sh --quiet
#

set -e

# Load environment file if it exists
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/.env" ]; then
    set -a  # Export all variables
    source "$SCRIPT_DIR/.env"
    set +a
fi

# Configuration
APP_DIR="/var/www/servco/backend"
BACKUP_DIR="/var/backups/servcoapp"
DB_NAME="app"
DB_USER="app"
DB_PASS="${DB_PASSWORD:-}"
KEEP_BACKUPS=30  # Number of backups to keep
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

QUIET=false
if [ "$1" = "--quiet" ]; then
    QUIET=true
fi

log() {
    if [ "$QUIET" = false ]; then
        echo "$1"
    fi
}

# Prompt for database password if not set and not quiet mode
if [ -z "$DB_PASS" ]; then
    if [ "$QUIET" = true ]; then
        echo "Error: DB_PASSWORD environment variable not set"
        exit 1
    fi
    read -sp "Enter database password for $DB_USER: " DB_PASS
    echo ""
fi

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Create backup
BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.sql.gz"

log "Creating backup: $BACKUP_FILE"

mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"

if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log "✓ Backup created successfully ($BACKUP_SIZE)"
else
    echo "Error: Backup failed!"
    exit 1
fi

# Cleanup old backups
DELETED=$(ls -t "$BACKUP_DIR"/backup_*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | wc -l)
ls -t "$BACKUP_DIR"/backup_*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm

if [ "$DELETED" -gt 0 ]; then
    log "  Cleaned up $DELETED old backup(s)"
fi

log ""
log "Backup location: $BACKUP_FILE"
