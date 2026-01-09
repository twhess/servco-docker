#!/bin/bash
#
# ServcoApp Migration Script
# Safely runs database migrations with backup and preview
#
# Usage: ./migrate.sh [--force] [--rollback]
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
APP_DIR="/var/www/html"
BACKUP_DIR="/var/backups/servcoapp"
DB_NAME="app"
DB_USER="app"
DB_PASS="${DB_PASSWORD:-}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Parse arguments
FORCE=false
ROLLBACK=false

for arg in "$@"; do
    case $arg in
        --force)
            FORCE=true
            shift
            ;;
        --rollback)
            ROLLBACK=true
            shift
            ;;
    esac
done

cd "$APP_DIR"

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  ServcoApp Migration Tool${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# Show current status
echo -e "${YELLOW}Current Migration Status:${NC}"
echo ""
php artisan migrate:status
echo ""

if [ "$ROLLBACK" = true ]; then
    echo -e "${RED}ROLLBACK MODE${NC}"
    echo ""
    echo "This will undo the last batch of migrations."
    echo ""

    read -p "Continue with rollback? (y/n): " CONFIRM
    if [ "$CONFIRM" = "y" ] || [ "$CONFIRM" = "Y" ]; then
        # Backup first
        if [ -z "$DB_PASS" ]; then
            read -sp "Enter database password for backup: " DB_PASS
            echo ""
        fi

        mkdir -p "$BACKUP_DIR"
        BACKUP_FILE="$BACKUP_DIR/pre_rollback_$TIMESTAMP.sql.gz"
        echo -e "${YELLOW}Creating backup before rollback...${NC}"
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"
        echo -e "${GREEN}✓ Backup: $BACKUP_FILE${NC}"
        echo ""

        php artisan migrate:rollback --force
        echo ""
        echo -e "${GREEN}✓ Rollback complete${NC}"
    else
        echo "Aborted."
    fi
    exit 0
fi

# Check for pending migrations
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)

if [ "$PENDING" -eq 0 ]; then
    echo -e "${GREEN}✓ No pending migrations. Database is up to date.${NC}"
    exit 0
fi

echo -e "${YELLOW}Found $PENDING pending migration(s)${NC}"
echo ""

# Preview changes
echo -e "${YELLOW}Preview of changes (--pretend):${NC}"
echo ""
php artisan migrate --pretend
echo ""

if [ "$FORCE" = false ]; then
    echo -e "${YELLOW}Do you want to proceed?${NC}"
    echo "  1) Run migrations"
    echo "  2) Run with backup first (recommended)"
    echo "  3) Cancel"
    echo ""
    read -p "Choice [2]: " CHOICE
    CHOICE=${CHOICE:-2}
else
    CHOICE=2
fi

case $CHOICE in
    1)
        echo ""
        php artisan migrate --force
        echo ""
        echo -e "${GREEN}✓ Migrations complete${NC}"
        ;;
    2)
        # Prompt for database password if not set
        if [ -z "$DB_PASS" ]; then
            read -sp "Enter database password for backup: " DB_PASS
            echo ""
        fi

        # Create backup
        mkdir -p "$BACKUP_DIR"
        BACKUP_FILE="$BACKUP_DIR/pre_migrate_$TIMESTAMP.sql.gz"
        echo ""
        echo -e "${YELLOW}Creating backup...${NC}"
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"
        echo -e "${GREEN}✓ Backup: $BACKUP_FILE${NC}"
        echo ""

        # Run migrations
        echo -e "${YELLOW}Running migrations...${NC}"
        php artisan migrate --force
        echo ""
        echo -e "${GREEN}✓ Migrations complete${NC}"
        echo ""
        echo "If something went wrong, restore with:"
        echo "  ./restore.sh $BACKUP_FILE"
        ;;
    *)
        echo "Aborted."
        exit 0
        ;;
esac

echo ""
echo -e "${YELLOW}Updated Migration Status:${NC}"
php artisan migrate:status
