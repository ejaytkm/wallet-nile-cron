#!/bin/bash

# AWS SSM Git Deployment Script for wallet-nile-cron
# Usage: /opt/deployment/git-deploy.sh <GITHUB_REPO> <COMMIT_SHA>

set -e  # Exit on any error

GITHUB_REPO="$1"
COMMIT_SHA="$2"
LOG_FILE="/var/log/deployment.log"
APP_DIR="/var/www/html"
WEB_DIR="/var/www/html/server"

# Logging function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log "Starting git deployment of $GITHUB_REPO at commit $COMMIT_SHA"

# Stop services
log "Stopping services..."
sudo systemctl stop wallet-nile-cron || true
sudo systemctl stop nginx || true
sudo systemctl stop php8.3-fpm || true

# Handle git repository
if [ -d "$APP_DIR/.git" ]; then
    log "Updating existing repository..."
    cd $APP_DIR
    # Fix git ownership issue
    sudo git config --global --add safe.directory $APP_DIR
    sudo git fetch origin
    sudo git reset --hard $COMMIT_SHA
    sudo git clean -fd
else
    log "Cloning repository..."
    # Backup existing files if any
    if [ -d "$APP_DIR" ] && [ "$(ls -A $APP_DIR)" ]; then
        BACKUP_DIR="/var/www/html.backup.$(date +%s)"
        log "Backing up existing files to $BACKUP_DIR"
        sudo mv $APP_DIR $BACKUP_DIR
    fi
    
    # Clone repository
    sudo git clone https://github.com/${GITHUB_REPO}.git $APP_DIR
    cd $APP_DIR
    sudo git config --global --add safe.directory $APP_DIR
    sudo git reset --hard $COMMIT_SHA
fi

# Set proper permissions before composer
log "Setting permissions..."
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR

# Install Composer dependencies
log "Installing Composer dependencies..."
cd $APP_DIR
sudo -u www-data composer install --no-dev --optimize-autoloader

# Create required directories
sudo mkdir -p $APP_DIR/storage/logs
sudo mkdir -p $APP_DIR/storage/cache
sudo chown -R www-data:www-data $APP_DIR/storage

# Configure nginx
log "Configuring nginx..."
sudo tee /etc/nginx/sites-available/wallet-nile-cron > /dev/null <<EOF
server {
    listen 80;
    server_name _;
    root $WEB_DIR;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable nginx site
sudo ln -sf /etc/nginx/sites-available/wallet-nile-cron /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test nginx configuration
log "Testing nginx configuration..."
sudo nginx -t

# Install/update systemd service
log "Installing systemd service..."
sudo cp $APP_DIR/systemd/cron.service /etc/systemd/system/wallet-nile-cron.service
sudo systemctl daemon-reload
sudo systemctl enable wallet-nile-cron
sudo systemctl enable nginx
sudo systemctl enable php8.3-fpm

# Start services
log "Starting services..."
sudo systemctl start php8.3-fpm
sudo systemctl start nginx
sudo systemctl start wallet-nile-cron

# Check service status
log "Checking service status..."
sudo systemctl is-active php8.3-fpm || { log "ERROR: php8.3-fpm failed to start"; exit 1; }
sudo systemctl is-active nginx || { log "ERROR: nginx failed to start"; exit 1; }
sudo systemctl is-active wallet-nile-cron || { log "ERROR: wallet-nile-cron failed to start"; exit 1; }

log "✅ Deployment completed successfully!"
log "Deployed commit: $COMMIT_SHA"