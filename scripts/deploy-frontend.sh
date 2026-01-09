#!/bin/bash
#
# ServcoApp Frontend Deployment Script
# Pulls latest code from GitHub and rebuilds the frontend (if needed)
#
# Usage: ./deploy-frontend.sh [--skip-pull] [--force-build]
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
FORCE_BUILD=false
for arg in "$@"; do
    case $arg in
        --skip-pull)
            SKIP_PULL=true
            shift
            ;;
        --force-build)
            FORCE_BUILD=true
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

# Get current commit before pull
BEFORE_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "none")

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

# Get commit after pull
AFTER_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "none")

# Step 2: Check if frontend files changed
FRONTEND_CHANGED=false

if [ "$FORCE_BUILD" = true ]; then
    echo -e "${YELLOW}Step 2: Force build requested${NC}"
    FRONTEND_CHANGED=true
elif [ "$BEFORE_COMMIT" = "$AFTER_COMMIT" ]; then
    echo -e "${YELLOW}Step 2: No new commits pulled${NC}"
    # Check if dist folder exists - if not, we need to build
    if [ ! -d "$FRONTEND_DIR/dist/spa" ]; then
        echo -e "${YELLOW}  Build output missing, will rebuild${NC}"
        FRONTEND_CHANGED=true
    fi
elif [ "$BEFORE_COMMIT" != "none" ] && [ "$AFTER_COMMIT" != "none" ]; then
    # Check if any frontend files changed between commits
    CHANGED_FILES=$(git diff --name-only "$BEFORE_COMMIT" "$AFTER_COMMIT" -- frontend/ 2>/dev/null || echo "")
    if [ -n "$CHANGED_FILES" ]; then
        echo -e "${YELLOW}Step 2: Frontend files changed:${NC}"
        echo "$CHANGED_FILES" | head -10
        CHANGED_COUNT=$(echo "$CHANGED_FILES" | wc -l)
        if [ "$CHANGED_COUNT" -gt 10 ]; then
            echo "  ... and $((CHANGED_COUNT - 10)) more files"
        fi
        FRONTEND_CHANGED=true
    else
        echo -e "${YELLOW}Step 2: No frontend files changed${NC}"
    fi
else
    # Can't determine, assume rebuild needed
    echo -e "${YELLOW}Step 2: Unable to determine changes, will rebuild${NC}"
    FRONTEND_CHANGED=true
fi
echo ""

# Step 3: Install dependencies and build (if needed)
if [ "$FRONTEND_CHANGED" = true ]; then
    echo -e "${YELLOW}Step 3: Installing npm dependencies...${NC}"
    cd "$FRONTEND_DIR"
    npm ci
    echo -e "${GREEN}✓ Dependencies installed${NC}"
    echo ""

    echo -e "${YELLOW}Step 4: Building frontend for production...${NC}"
    npm run build
    echo -e "${GREEN}✓ Frontend built${NC}"
    echo ""

    # Check build output
    if [ -d "$FRONTEND_DIR/dist/spa" ]; then
        BUILD_SIZE=$(du -sh "$FRONTEND_DIR/dist/spa" | cut -f1)
        echo -e "${GREEN}✓ Build output: $FRONTEND_DIR/dist/spa ($BUILD_SIZE)${NC}"
    else
        echo -e "${RED}Warning: Expected build output not found at $FRONTEND_DIR/dist/spa${NC}"
    fi
else
    echo -e "${GREEN}Step 3: Skipping build - no frontend changes detected${NC}"
    echo ""
    echo -e "${GREEN}✓ Frontend is already up to date${NC}"
fi
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Frontend Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Build location: $FRONTEND_DIR/dist/spa"
echo "Time: $(date)"
echo ""

# Show usage hint if build was skipped
if [ "$FRONTEND_CHANGED" = false ]; then
    echo -e "${YELLOW}Tip:${NC} Use --force-build to rebuild even without changes"
    echo ""
fi
