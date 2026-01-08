#!/bin/bash
#
# ServcoApp LAMP Server Setup Script
# For AWS Lightsail Ubuntu 22.04 LTS or 24.04 LTS
#
# Usage: sudo bash setup-lamp.sh [domain]
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "Please run as root (sudo bash setup-lamp.sh)"
fi

# Detect Ubuntu version
UBUNTU_VERSION=$(lsb_release -rs)
UBUNTU_CODENAME=$(lsb_release -cs)

log "Detected Ubuntu $UBUNTU_VERSION ($UBUNTU_CODENAME)"

# Validate supported Ubuntu versions
if [[ "$UBUNTU_VERSION" != "22.04" && "$UBUNTU_VERSION" != "24.04" ]]; then
    warn "This script is tested on Ubuntu 22.04 and 24.04."
    warn "Detected version: $UBUNTU_VERSION"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

APP_USER="servco"
APP_DIR="/var/www/servco"
DOMAIN="${1:-dev.truckserviceco.com}"

log "Starting ServcoApp LAMP setup..."
log "Domain: $DOMAIN"

# ==========================================
# 1. System Updates
# ==========================================
log "Updating system packages..."
apt update && apt upgrade -y

# ==========================================
# 2. Install Apache
# ==========================================
log "Installing Apache..."
apt install -y apache2
a2enmod rewrite headers ssl proxy proxy_http
systemctl enable apache2

# ==========================================
# 3. Install PHP 8.3
# ==========================================
log "Installing PHP 8.3..."
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y \
    php8.3 \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    php8.3-gd \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-opcache \
    libapache2-mod-php8.3

# Enable PHP-FPM with Apache
a2enmod proxy_fcgi setenvif
a2enconf php8.3-fpm
systemctl enable php8.3-fpm

# ==========================================
# 4. Install MySQL 8
# ==========================================
log "Installing MySQL 8..."
apt install -y mysql-server
systemctl enable mysql

# Secure MySQL (basic setup)
log "Configuring MySQL..."
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'CHANGE_THIS_ROOT_PASSWORD';"

# Create app database and user
mysql -u root -p'CHANGE_THIS_ROOT_PASSWORD' << EOF
CREATE DATABASE IF NOT EXISTS servco_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'servco'@'localhost' IDENTIFIED BY 'CHANGE_THIS_APP_PASSWORD';
GRANT ALL PRIVILEGES ON servco_prod.* TO 'servco'@'localhost';
FLUSH PRIVILEGES;
EOF

# ==========================================
# 5. Install Node.js 20 (for building frontend)
# ==========================================
log "Installing Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# ==========================================
# 6. Install Python 3 + pandas
# ==========================================
log "Installing Python 3 and pandas..."
apt install -y python3 python3-pip python3-venv

# Ubuntu 24.04+ requires --break-system-packages for pip
if [[ "$UBUNTU_VERSION" == "24.04" ]] || [[ "${UBUNTU_VERSION%%.*}" -ge 24 ]]; then
    pip3 install pandas --break-system-packages
else
    pip3 install pandas
fi

# ==========================================
# 7. Install Composer
# ==========================================
log "Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# ==========================================
# 8. Install Git
# ==========================================
log "Installing Git..."
apt install -y git

# ==========================================
# 9. Install Certbot (SSL)
# ==========================================
log "Installing Certbot for SSL..."
apt install -y certbot python3-certbot-apache

# ==========================================
# 10. Create App User and Directory
# ==========================================
log "Creating application user and directories..."
useradd -m -s /bin/bash $APP_USER || true
mkdir -p $APP_DIR
chown -R $APP_USER:$APP_USER $APP_DIR

# ==========================================
# 11. Configure PHP for Production
# ==========================================
log "Configuring PHP for production..."
PHP_INI="/etc/php/8.3/fpm/php.ini"

sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' $PHP_INI
sed -i 's/post_max_size = .*/post_max_size = 64M/' $PHP_INI
sed -i 's/memory_limit = .*/memory_limit = 256M/' $PHP_INI
sed -i 's/max_execution_time = .*/max_execution_time = 120/' $PHP_INI
sed -i 's/;opcache.enable=.*/opcache.enable=1/' $PHP_INI
sed -i 's/;opcache.memory_consumption=.*/opcache.memory_consumption=128/' $PHP_INI
sed -i 's/;opcache.validate_timestamps=.*/opcache.validate_timestamps=0/' $PHP_INI

systemctl restart php8.3-fpm

# ==========================================
# 12. Create Apache Virtual Host
# ==========================================
log "Creating Apache virtual host..."
cat > /etc/apache2/sites-available/servco.conf << 'APACHE_CONF'
<VirtualHost *:80>
    ServerName DOMAIN_PLACEHOLDER
    ServerAlias www.DOMAIN_PLACEHOLDER

    # Redirect all HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName DOMAIN_PLACEHOLDER
    ServerAlias www.DOMAIN_PLACEHOLDER

    DocumentRoot /var/www/servco/frontend/app/dist/spa

    # SSL Configuration (will be configured by certbot)
    # SSLEngine on
    # SSLCertificateFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem
    # SSLCertificateKeyFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Frontend SPA - serve static files
    <Directory /var/www/servco/frontend/app/dist/spa>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted

        # SPA fallback - all routes go to index.html
        FallbackResource /index.html
    </Directory>

    # API Routes -> Laravel Backend
    Alias /api /var/www/servco/backend/public
    <Directory /var/www/servco/backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        <FilesMatch \.php$>
            SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>

    # Sanctum/CSRF routes
    Alias /sanctum /var/www/servco/backend/public

    # Storage symlink for public files
    Alias /storage /var/www/servco/backend/storage/app/public
    <Directory /var/www/servco/backend/storage/app/public>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/servco-error.log
    CustomLog ${APACHE_LOG_DIR}/servco-access.log combined
</VirtualHost>
APACHE_CONF

# Replace domain placeholder
sed -i "s/DOMAIN_PLACEHOLDER/$DOMAIN/g" /etc/apache2/sites-available/servco.conf

# Enable site
a2dissite 000-default.conf || true
a2ensite servco.conf
systemctl reload apache2

# ==========================================
# 13. Create Deploy Script
# ==========================================
log "Creating deploy script..."
cat > /usr/local/bin/deploy-servco << 'DEPLOY_SCRIPT'
#!/bin/bash
set -e

APP_DIR="/var/www/servco"
APP_USER="servco"

echo "=== ServcoApp Deployment ==="

cd $APP_DIR

# Pull latest code
echo "Pulling latest code..."
sudo -u $APP_USER git pull origin main

# Backend
echo "Installing backend dependencies..."
cd $APP_DIR/backend
sudo -u $APP_USER composer install --no-dev --optimize-autoloader --no-interaction

echo "Running migrations..."
sudo -u $APP_USER php artisan migrate --force

echo "Clearing and caching config..."
sudo -u $APP_USER php artisan config:cache
sudo -u $APP_USER php artisan route:cache
sudo -u $APP_USER php artisan view:cache

# Fix permissions
chown -R $APP_USER:www-data $APP_DIR/backend/storage
chmod -R 775 $APP_DIR/backend/storage
chmod -R 775 $APP_DIR/backend/bootstrap/cache

# Frontend
echo "Building frontend..."
cd $APP_DIR/frontend/app
sudo -u $APP_USER npm ci
sudo -u $APP_USER npm run build

# Restart services
echo "Restarting services..."
systemctl restart php8.3-fpm
systemctl reload apache2

echo "=== Deployment Complete ==="
DEPLOY_SCRIPT

chmod +x /usr/local/bin/deploy-servco

# ==========================================
# 14. Create Backup Script
# ==========================================
log "Creating backup script..."
cat > /usr/local/bin/backup-servco << 'BACKUP_SCRIPT'
#!/bin/bash
BACKUP_DIR="/var/backups/servco"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u servco -p'CHANGE_THIS_APP_PASSWORD' servco_prod | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/db_$DATE.sql.gz"
BACKUP_SCRIPT

chmod +x /usr/local/bin/backup-servco

# Add to crontab (daily at 2am)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup-servco") | crontab -

# ==========================================
# 15. Configure Firewall
# ==========================================
log "Configuring firewall..."
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw --force enable

# ==========================================
# Done
# ==========================================
log "=========================================="
log "LAMP Setup Complete!"
log "=========================================="
log ""
log "IMPORTANT: Update these passwords:"
log "  1. MySQL root: CHANGE_THIS_ROOT_PASSWORD"
log "  2. MySQL app:  CHANGE_THIS_APP_PASSWORD"
log "  3. Update /usr/local/bin/backup-servco with correct password"
log ""
log "Next steps:"
log "  1. Clone your repo: sudo -u servco git clone <repo> $APP_DIR"
log "  2. Copy .env.production to $APP_DIR/backend/.env"
log "  3. Run: php artisan key:generate"
log "  4. Run: deploy-servco"
log "  5. Get SSL: certbot --apache -d $DOMAIN -d www.$DOMAIN"
log ""
log "Commands:"
log "  Deploy updates: deploy-servco"
log "  Manual backup:  backup-servco"
log "  View logs:      tail -f /var/log/apache2/servco-*.log"
log "=========================================="
