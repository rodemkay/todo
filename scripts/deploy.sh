#!/bin/bash
# Deploy script for TODO plugin

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
SOURCE_DIR="/home/rodemkay/www/react/todo/plugin"
STAGING_HOST="rodemkay@100.67.210.46"  # Tailscale IP für bessere Performance
STAGING_PATH="/var/www/forexsignale/staging/wp-content/plugins/todo"
OLD_PLUGIN_PATH="/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos"

# Function to deploy to staging
deploy_staging() {
    echo -e "${YELLOW}Deploying to staging server...${NC}"
    
    # Create backup of old plugin
    echo -e "${YELLOW}Creating backup of old plugin...${NC}"
    ssh $STAGING_HOST "if [ -d $OLD_PLUGIN_PATH ]; then cp -r $OLD_PLUGIN_PATH ${OLD_PLUGIN_PATH}.backup-$(date +%Y%m%d-%H%M%S); fi"
    
    # Create new plugin directory if not exists
    echo -e "${YELLOW}Creating new plugin directory...${NC}"
    ssh $STAGING_HOST "mkdir -p $STAGING_PATH"
    
    # Sync files
    echo -e "${YELLOW}Syncing files...${NC}"
    rsync -avz --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='*.log' \
        --exclude='*.tmp' \
        $SOURCE_DIR/ $STAGING_HOST:$STAGING_PATH/
    
    # Update plugin name in main file
    echo -e "${YELLOW}Updating plugin name...${NC}"
    ssh $STAGING_HOST "sed -i 's/Plugin Name: WP Project Todos/Plugin Name: TODO/g' $STAGING_PATH/wp-project-todos.php"
    ssh $STAGING_HOST "sed -i 's/wp-project-todos/todo/g' $STAGING_PATH/wp-project-todos.php"
    
    # Rename main plugin file
    ssh $STAGING_HOST "if [ -f $STAGING_PATH/wp-project-todos.php ]; then mv $STAGING_PATH/wp-project-todos.php $STAGING_PATH/todo.php; fi"
    
    # Update database if needed
    echo -e "${YELLOW}Checking database...${NC}"
    ssh $STAGING_HOST "cd /var/www/forexsignale/staging && wp option update active_plugins --format=json \$(wp option get active_plugins --format=json | sed 's/wp-project-todos/todo/g')"
    
    echo -e "${GREEN}✓ Deployment to staging complete!${NC}"
    echo -e "${GREEN}Test at: https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos${NC}"
}

# Function to deploy to production
deploy_production() {
    echo -e "${RED}Production deployment not yet configured.${NC}"
    echo "Please deploy to staging first and test thoroughly."
    exit 1
}

# Main script
case "$1" in
    "staging")
        deploy_staging
        ;;
    "production")
        deploy_production
        ;;
    *)
        echo "Usage: $0 {staging|production}"
        exit 1
        ;;
esac