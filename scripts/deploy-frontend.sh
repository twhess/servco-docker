#!/bin/bash
#
# ServcoApp Frontend Deployment Script
# Pulls latest code from GitHub and rebuilds the frontend
#
# Usage: ./deploy-frontend.sh [--skip-pull]
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration - Update these for your environment
APP_DIR="/var/www/servco"
FRONTEND_DIR="$APP_DIR/frontend/app"
GIT_BRANCH="master"

# Parse arguments
SKIP_PULL=false
for arg in "$@"; do
    case $arg in
        --skip-pull)
            SKIP_PULL=true
            shift
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Frontend Deployment${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check directories exist
if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}Error: APP_DIR not found: $APP_DIR${NC}"
    exit 1
fi

if [ ! -d "$FRONTEND_DIR" ]; then
    echo -e "${RED}Error: Frontend directory not found: $FRONTEND_DIR${NC}"
    exit 1
fi

cd "$APP_DIR"

# Step 1: Pull latest code
if [ "$SKIP_PULL" = false ]; then
    echo -e "${YELLOW}Step 1: Pulling latest code from $GIT_BRANCH...${NC}"
    git fetch origin
    git reset --hard "origin/$GIT_BRANCH"
    echo -e "${GREEN}✓ Code updated${NC}"
else
    echo -e "${YELLOW}Step 1: Skipping git pull (--skip-pull flag)${NC}"
fi
echo ""

# Step 2: Install npm dependencies
echo -e "${YELLOW}Step 2: Installing npm dependencies...${NC}"
cd "$FRONTEND_DIR"
npm ci
echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""

# Step 3: Build frontend
echo -e "${YELLOW}Step 3: Building frontend for production...${NC}"
npm run build
echo -e "${GREEN}✓ Frontend built${NC}"
echo ""

# Step 4: Check build output
if [ -d "$FRONTEND_DIR/dist/spa" ]; then
    BUILD_SIZE=$(du -sh "$FRONTEND_DIR/dist/spa" | cut -f1)
    echo -e "${GREEN}✓ Build output: $FRONTEND_DIR/dist/spa ($BUILD_SIZE)${NC}"
else
    echo -e "${RED}Warning: Expected build output not found at $FRONTEND_DIR/dist/spa${NC}"
fi
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Frontend Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Build location: $FRONTEND_DIR/dist/spa"
echo "Time: $(date)"
echo ""

# Reminder about web server config
echo -e "${YELLOW}Note:${NC} Make sure your web server (Apache/Nginx) is configured"
echo "to serve files from: $FRONTEND_DIR/dist/spa"
echo ""
