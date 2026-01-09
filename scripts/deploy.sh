#!/bin/bash
#
# ServcoApp Deployment Script
# Run this script on your AWS Lightsail server to deploy updates
#
# Usage: ./deploy.sh [--skip-backup] [--skip-migrations]
#

set -e  # Exit immediately on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration - Update these for your environment
APP_DIR="/var/www/html"
BACKUP_DIR="/var/backups/servcoapp"
DB_NAME="app"
DB_USER="app"
DB_PASS="${DB_PASSWORD:-}"  # Set via environment variable or prompt
GIT_BRANCH="main"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Parse arguments
SKIP_BACKUP=false
SKIP_MIGRATIONS=false

for arg in "$@"; do
    case $arg in
        --skip-backup)
            SKIP_BACKUP=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  ServcoApp Deployment - $TIMESTAMP${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if we're in the right directory
if [ ! -f "$APP_DIR/artisan" ]; then
    echo -e "${RED}Error: Laravel artisan not found in $APP_DIR${NC}"
    echo "Please update APP_DIR in this script."
    exit 1
fi

cd "$APP_DIR"

# Prompt for database password if not set
if [ -z "$DB_PASS" ]; then
    read -sp "Enter database password for $DB_USER: " DB_PASS
    echo ""
fi

# Step 1: Create backup
if [ "$SKIP_BACKUP" = false ]; then
    echo -e "${YELLOW}Step 1: Creating database backup...${NC}"
    mkdir -p "$BACKUP_DIR"

    BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.sql.gz"
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE"

    if [ -f "$BACKUP_FILE" ]; then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        echo -e "${GREEN}✓ Backup created: $BACKUP_FILE ($BACKUP_SIZE)${NC}"
    else
        echo -e "${RED}✗ Backup failed!${NC}"
        exit 1
    fi

    # Keep only last 10 backups
    ls -t "$BACKUP_DIR"/backup_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm
    echo "  (Keeping last 10 backups)"
else
    echo -e "${YELLOW}Step 1: Skipping backup (--skip-backup flag)${NC}"
fi

echo ""

# Step 2: Enable maintenance mode
echo -e "${YELLOW}Step 2: Enabling maintenance mode...${NC}"
php artisan down --message="Deploying updates. Back shortly." --retry=60 2>/dev/null || true
echo -e "${GREEN}✓ Maintenance mode enabled${NC}"
echo ""

# Step 3: Pull latest code
echo -e "${YELLOW}Step 3: Pulling latest code from $GIT_BRANCH...${NC}"
git fetch origin
git reset --hard "origin/$GIT_BRANCH"
echo -e "${GREEN}✓ Code updated${NC}"
echo ""

# Step 4: Install Composer dependencies
echo -e "${YELLOW}Step 4: Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction
echo -e "${GREEN}✓ Composer dependencies installed${NC}"
echo ""

# Step 5: Run migrations
if [ "$SKIP_MIGRATIONS" = false ]; then
    echo -e "${YELLOW}Step 5: Checking migrations...${NC}"

    # Show pending migrations
    PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)

    if [ "$PENDING" -gt 0 ]; then
        echo "  Found $PENDING pending migration(s)"
        echo ""
        echo "  Preview of changes:"
        php artisan migrate --pretend 2>/dev/null || true
        echo ""

        read -p "  Run these migrations? (y/n): " CONFIRM
        if [ "$CONFIRM" = "y" ] || [ "$CONFIRM" = "Y" ]; then
            php artisan migrate --force
            echo -e "${GREEN}✓ Migrations completed${NC}"
        else
            echo -e "${YELLOW}⚠ Migrations skipped by user${NC}"
        fi
    else
        echo -e "${GREEN}✓ No pending migrations${NC}"
    fi
else
    echo -e "${YELLOW}Step 5: Skipping migrations (--skip-migrations flag)${NC}"
fi

echo ""

# Step 6: Clear and rebuild caches
echo -e "${YELLOW}Step 6: Rebuilding caches...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN}✓ Caches rebuilt${NC}"
echo ""

# Step 7: Set permissions
echo -e "${YELLOW}Step 7: Setting file permissions...${NC}"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 8: Restart queue workers (if using)
if [ -f "/etc/supervisor/conf.d/laravel-worker.conf" ]; then
    echo -e "${YELLOW}Step 8: Restarting queue workers...${NC}"
    php artisan queue:restart
    supervisorctl reread
    supervisorctl update
    supervisorctl restart laravel-worker:*
    echo -e "${GREEN}✓ Queue workers restarted${NC}"
else
    echo -e "${YELLOW}Step 8: No queue workers configured (skipping)${NC}"
fi

echo ""

# Step 9: Disable maintenance mode
echo -e "${YELLOW}Step 9: Disabling maintenance mode...${NC}"
php artisan up
echo -e "${GREEN}✓ Application is live${NC}"
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Summary:"
echo "  - Backup: $BACKUP_FILE"
echo "  - Branch: $GIT_BRANCH"
echo "  - Time: $(date)"
echo ""
echo "If something went wrong, restore with:"
echo "  ./restore.sh $BACKUP_FILE"
echo ""
