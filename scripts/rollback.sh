#!/bin/bash
#
# ServcoApp Git Rollback Script
# Reverts to a previous commit if something breaks after deployment
#
# Usage: ./rollback.sh [commit_hash]
#        ./rollback.sh           # Shows recent commits and prompts
#        ./rollback.sh abc1234   # Rolls back to specific commit
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
REPO_DIR="/var/www/servco"
APP_DIR="/var/www/servco/backend"

cd "$REPO_DIR"

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  ServcoApp Git Rollback${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# Show current state
CURRENT_COMMIT=$(git rev-parse --short HEAD)
CURRENT_MSG=$(git log -1 --format=%s)
echo -e "${YELLOW}Current state:${NC}"
echo "  Commit: $CURRENT_COMMIT"
echo "  Message: $CURRENT_MSG"
echo ""

# Show recent commits
echo -e "${YELLOW}Recent commits:${NC}"
echo ""
git log --oneline -10
echo ""

# Determine target commit
if [ -n "$1" ]; then
    TARGET_COMMIT="$1"
else
    echo -e "${YELLOW}Enter the commit hash to rollback to (or 'HEAD~1' for previous):${NC}"
    read -p "Commit: " TARGET_COMMIT

    if [ -z "$TARGET_COMMIT" ]; then
        echo "No commit specified. Aborted."
        exit 0
    fi
fi

# Validate commit exists
if ! git cat-file -t "$TARGET_COMMIT" >/dev/null 2>&1; then
    echo -e "${RED}Error: Invalid commit '$TARGET_COMMIT'${NC}"
    exit 1
fi

# Show what we're rolling back to
TARGET_SHORT=$(git rev-parse --short "$TARGET_COMMIT")
TARGET_MSG=$(git log -1 --format=%s "$TARGET_COMMIT")
echo -e "${YELLOW}Rolling back to:${NC}"
echo "  Commit: $TARGET_SHORT"
echo "  Message: $TARGET_MSG"
echo ""

# Show files that will change
echo -e "${YELLOW}Files that will change:${NC}"
CHANGED=$(git diff --name-only HEAD "$TARGET_COMMIT" 2>/dev/null | head -20)
if [ -n "$CHANGED" ]; then
    echo "$CHANGED"
    CHANGED_COUNT=$(git diff --name-only HEAD "$TARGET_COMMIT" | wc -l)
    if [ "$CHANGED_COUNT" -gt 20 ]; then
        echo "  ... and $((CHANGED_COUNT - 20)) more files"
    fi
else
    echo "  (no changes - already at this commit)"
    exit 0
fi
echo ""

# Confirm
echo -e "${RED}WARNING: This will reset local changes and cannot be undone easily!${NC}"
read -p "Continue with rollback? (type 'yes' to confirm): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

echo ""

# Enable maintenance mode
echo -e "${YELLOW}Enabling maintenance mode...${NC}"
cd "$APP_DIR"
php artisan down --message="Rolling back. Back shortly." 2>/dev/null || true
echo -e "${GREEN}✓ Maintenance mode enabled${NC}"

# Perform rollback
echo -e "${YELLOW}Rolling back...${NC}"
cd "$REPO_DIR"
git reset --hard "$TARGET_COMMIT"
echo -e "${GREEN}✓ Rolled back to $TARGET_SHORT${NC}"

# Clear Laravel caches
echo -e "${YELLOW}Clearing caches...${NC}"
cd "$APP_DIR"
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
echo -e "${GREEN}✓ Caches cleared${NC}"

# Disable maintenance mode
echo -e "${YELLOW}Disabling maintenance mode...${NC}"
php artisan up
echo -e "${GREEN}✓ Application is live${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Rollback Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Rolled back from: $CURRENT_COMMIT"
echo "            to: $TARGET_SHORT"
echo ""
echo -e "${YELLOW}Note:${NC} If you need to rebuild the frontend, run:"
echo "  ./deploy-frontend.sh --force-build"
echo ""
echo -e "${YELLOW}Note:${NC} If you need to reinstall composer dependencies, run:"
echo "  cd $APP_DIR && composer install --no-dev --optimize-autoloader"
echo ""
