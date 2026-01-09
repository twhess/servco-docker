#!/bin/bash
#
# ServcoApp Status Script
# Shows current application and database status
#
# Usage: ./status.sh
#

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

APP_DIR="/var/www/servco"
BACKUP_DIR="/var/backups/servcoapp"

cd "$APP_DIR" 2>/dev/null || {
    echo -e "${RED}Error: Cannot access $APP_DIR${NC}"
    exit 1
}

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}  ServcoApp Status${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# Application Status
echo -e "${YELLOW}Application:${NC}"
if [ -f "storage/framework/down" ]; then
    echo -e "  Status: ${RED}MAINTENANCE MODE${NC}"
else
    echo -e "  Status: ${GREEN}ONLINE${NC}"
fi
echo "  Directory: $APP_DIR"
echo "  Laravel: $(php artisan --version 2>/dev/null | head -1)"
echo ""

# Git Status
echo -e "${YELLOW}Git:${NC}"
echo "  Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null)"
echo "  Commit: $(git rev-parse --short HEAD 2>/dev/null)"
echo "  Date: $(git log -1 --format=%ci 2>/dev/null)"

LOCAL=$(git rev-parse HEAD 2>/dev/null)
REMOTE=$(git rev-parse @{u} 2>/dev/null || echo "")
if [ -n "$REMOTE" ]; then
    if [ "$LOCAL" = "$REMOTE" ]; then
        echo -e "  Remote: ${GREEN}Up to date${NC}"
    else
        BEHIND=$(git rev-list --count HEAD..@{u} 2>/dev/null || echo "0")
        echo -e "  Remote: ${YELLOW}$BEHIND commit(s) behind${NC}"
    fi
fi
echo ""

# Migration Status
echo -e "${YELLOW}Migrations:${NC}"
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
TOTAL=$(php artisan migrate:status 2>/dev/null | grep -c "migration" || true)
if [ "$PENDING" -eq 0 ]; then
    echo -e "  Status: ${GREEN}All migrations applied${NC}"
else
    echo -e "  Status: ${YELLOW}$PENDING pending migration(s)${NC}"
fi
echo "  Total: $TOTAL migrations"
echo ""

# Backups
echo -e "${YELLOW}Backups:${NC}"
if [ -d "$BACKUP_DIR" ]; then
    BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/*.sql.gz 2>/dev/null | wc -l)
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | head -1)
    echo "  Location: $BACKUP_DIR"
    echo "  Count: $BACKUP_COUNT backup(s)"
    if [ -n "$LATEST_BACKUP" ]; then
        LATEST_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
        LATEST_DATE=$(stat -c %y "$LATEST_BACKUP" 2>/dev/null | cut -d. -f1)
        echo "  Latest: $(basename "$LATEST_BACKUP") ($LATEST_SIZE)"
        echo "  Date: $LATEST_DATE"
    fi
else
    echo -e "  ${YELLOW}No backups found${NC}"
fi
echo ""

# Disk Space
echo -e "${YELLOW}Disk Space:${NC}"
df -h "$APP_DIR" | tail -1 | awk '{print "  Used: " $3 " / " $2 " (" $5 ")"}'
echo ""

# Cache Status
echo -e "${YELLOW}Cache Status:${NC}"
if [ -f "bootstrap/cache/config.php" ]; then
    echo -e "  Config: ${GREEN}Cached${NC}"
else
    echo -e "  Config: ${YELLOW}Not cached${NC}"
fi
if [ -f "bootstrap/cache/routes-v7.php" ]; then
    echo -e "  Routes: ${GREEN}Cached${NC}"
else
    echo -e "  Routes: ${YELLOW}Not cached${NC}"
fi
echo ""

# Quick Commands Reference
echo -e "${CYAN}Quick Commands:${NC}"
echo "  ./deploy.sh      - Full deployment"
echo "  ./migrate.sh     - Run migrations only"
echo "  ./backup.sh      - Create backup"
echo "  ./restore.sh     - Restore from backup"
echo ""
