#!/bin/bash

# Quantix Inventory Management System - Deployment Configuration Template
# 
# INSTRUCTIONS:
# 1. Copy this file to 'deploy-config.sh' in the same directory
# 2. Update the configuration variables below with your actual settings
# 3. Make it executable: chmod +x deploy-config.sh
# 4. Never commit the actual deploy-config.sh file to version control

# =============================================================================
# SERVER CONFIGURATION
# =============================================================================

# Production server details
PROD_SERVER="your-server.com"
PROD_USER="your-username"
PROD_PATH="/var/www/html/quantix"
PROD_SSH_KEY="~/.ssh/your-key.pem"

# Staging server details (optional)
STAGING_SERVER="staging.your-server.com"
STAGING_USER="your-username"
STAGING_PATH="/var/www/html/quantix-staging"
STAGING_SSH_KEY="~/.ssh/your-staging-key.pem"

# =============================================================================
# DATABASE CONFIGURATION
# =============================================================================

# Production database
PROD_DB_HOST="localhost"
PROD_DB_NAME="quantix_production"
PROD_DB_USER="quantix_user"
PROD_DB_PASS="your-secure-production-password"

# Staging database
STAGING_DB_HOST="localhost"
STAGING_DB_NAME="quantix_staging"
STAGING_DB_USER="quantix_staging_user"
STAGING_DB_PASS="your-staging-password"

# =============================================================================
# BACKUP CONFIGURATION
# =============================================================================

# Backup settings
BACKUP_DIR="/backups/quantix"
BACKUP_RETENTION_DAYS=30
BACKUP_REMOTE_HOST="backup-server.com"
BACKUP_REMOTE_USER="backup-user"
BACKUP_REMOTE_PATH="/backups/quantix"

# =============================================================================
# NOTIFICATION SETTINGS
# =============================================================================

# Email notifications for deployment
NOTIFICATION_EMAIL="admin@yourdomain.com"
SLACK_WEBHOOK_URL="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"

# =============================================================================
# SECURITY SETTINGS
# =============================================================================

# SSL certificate paths (if managing SSL)
SSL_CERT_PATH="/etc/ssl/certs/yourdomain.crt"
SSL_KEY_PATH="/etc/ssl/private/yourdomain.key"

# =============================================================================
# APPLICATION SETTINGS
# =============================================================================

# Git repository
GIT_REPO="https://github.com/yourusername/quantix.git"
GIT_BRANCH="main"

# PHP settings
PHP_VERSION="8.1"
PHP_FPM_SERVICE="php8.1-fpm"

# Web server
WEB_SERVER="nginx"  # or "apache"
WEB_SERVER_SERVICE="nginx"

# =============================================================================
# DEPLOYMENT FUNCTIONS
# =============================================================================

# Function to deploy to production
deploy_production() {
    echo "🚀 Deploying to production..."
    
    # Add your deployment commands here
    # rsync -avz --exclude-from='.gitignore' ./ $PROD_USER@$PROD_SERVER:$PROD_PATH/
    # ssh $PROD_USER@$PROD_SERVER "cd $PROD_PATH && php artisan migrate --force"
    
    echo "✅ Production deployment completed"
}

# Function to deploy to staging
deploy_staging() {
    echo "🚀 Deploying to staging..."
    
    # Add your staging deployment commands here
    # rsync -avz --exclude-from='.gitignore' ./ $STAGING_USER@$STAGING_SERVER:$STAGING_PATH/
    
    echo "✅ Staging deployment completed"
}

# Function to create database backup
backup_database() {
    echo "💾 Creating database backup..."
    
    # Add your backup commands here
    # mysqldump -h $PROD_DB_HOST -u $PROD_DB_USER -p$PROD_DB_PASS $PROD_DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql
    
    echo "✅ Database backup completed"
}

# Function to rollback deployment
rollback_deployment() {
    echo "⏪ Rolling back deployment..."
    
    # Add your rollback commands here
    
    echo "✅ Rollback completed"
}

# =============================================================================
# USAGE EXAMPLES
# =============================================================================

# Uncomment and modify these lines to use the functions
# deploy_staging
# deploy_production
# backup_database
