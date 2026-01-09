#!/bin/bash
#
# ServcoApp Database Restore Script
# Restores database from a backup file
#
# Usage: ./restore.sh <backup_file>
#

set -e

# Load environment file if it exists
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/.env" ]; then
    set -a  # Export all variables
    source "$SCRIPT_DIR/.env"
    set +a
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
APP_DIR="/var/www/servco"
DB_NAME="app"
DB_USER="app"
DB_PASS="${DB_PASSWORD:-}"

# Check arguments
if [ -z "$1" ]; then
    echo -e "${RED}Usage: ./restore.sh <backup_file>${NC}"
    echo ""
    echo "Available backups:"
    ls -lht /var/backups/servcoapp/*.sql.gz 2>/dev/null | head -10 || echo "  No backups found"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}  Database Restore${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""
echo "Backup file: $BACKUP_FILE"
echo "Database: $DB_NAME"
echo ""
echo -e "${RED}WARNING: This will OVERWRITE all current data!${NC}"
echo ""

read -p "Are you sure you want to continue? (type 'yes' to confirm): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

# Prompt for database password if not set
if [ -z "$DB_PASS" ]; then
    read -sp "Enter database password for $DB_USER: " DB_PASS
    echo ""
fi

# Enable maintenance mode
echo ""
echo -e "${YELLOW}Enabling maintenance mode...${NC}"
cd "$APP_DIR"
php artisan down --message="Database restore in progress." 2>/dev/null || true

# Restore database
echo -e "${YELLOW}Restoring database...${NC}"

if [[ "$BACKUP_FILE" == *.gz ]]; then
    # Compressed backup
    gunzip -c "$BACKUP_FILE" | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
else
    # Uncompressed backup
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"
fi

echo -e "${GREEN}✓ Database restored${NC}"

# Clear caches
echo -e "${YELLOW}Clearing caches...${NC}"
php artisan cache:clear
php artisan config:clear

# Disable maintenance mode
echo -e "${YELLOW}Disabling maintenance mode...${NC}"
php artisan up

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Restore Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Database restored from: $BACKUP_FILE"
echo "Time: $(date)"
echo ""
