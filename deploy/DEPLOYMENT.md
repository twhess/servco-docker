# ServcoApp Production Deployment Guide

## AWS Lightsail LAMP Deployment

This guide covers deploying ServcoApp to an AWS Lightsail instance running Ubuntu.

## Prerequisites

- AWS Lightsail instance (recommended: 2GB RAM minimum, 4GB for comfortable operation)
- Ubuntu 22.04 LTS or **24.04 LTS** (recommended)
- Domain name pointing to your Lightsail instance IP
- SSH access to your server

> **Note:** The setup script auto-detects Ubuntu version and works with both 22.04 and 24.04.

## Recommended Instance Sizes

| Size | RAM | vCPU | Monthly Cost | Use Case |
|------|-----|------|--------------|----------|
| $5   | 1GB | 1    | $5           | Testing only |
| $10  | 2GB | 1    | $10          | Light production |
| $20  | 4GB | 2    | $20          | **Recommended** |
| $40  | 8GB | 2    | $40          | High traffic |

## Quick Start

### 1. Initial Server Setup

SSH into your Lightsail instance:

```bash
ssh -i your-key.pem ubuntu@YOUR_SERVER_IP
```

Download and run the setup script:

```bash
# Option A: If you have the repo cloned locally, upload the script
scp -i your-key.pem deploy/setup-lamp.sh ubuntu@YOUR_SERVER_IP:~/

# Option B: Create the script manually on the server
# (copy contents from deploy/setup-lamp.sh)

# Run the setup script with your domain
sudo bash setup-lamp.sh your-domain.com
```

### 2. Clone Your Repository

```bash
# Switch to the app user
sudo -u servco -i

# Clone the repository
cd /var/www
git clone https://github.com/YOUR_USERNAME/ServcoApp.git servco

# Or if using SSH
git clone git@github.com:YOUR_USERNAME/ServcoApp.git servco
```

### 3. Configure Environment

```bash
# Copy production environment file
cp /var/www/servco/deploy/.env.production /var/www/servco/backend/.env

# Edit the environment file with your production values
nano /var/www/servco/backend/.env
```

**Important values to update:**
- `APP_KEY` - Generate with `php artisan key:generate`
- `APP_URL` - Your domain (https://your-domain.com)
- `DB_PASSWORD` - The MySQL password you set during setup
- `SESSION_DOMAIN` - Your domain (.your-domain.com)
- `SANCTUM_STATEFUL_DOMAINS` - Your domain
- Google API credentials (if using)
- Gemini API key (if using)

### 4. Install Dependencies & Build

```bash
cd /var/www/servco/backend

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink
php artisan storage:link
```

### 5. Build Frontend

```bash
cd /var/www/servco/frontend/app

# Install Node dependencies
npm ci

# Build for production
npm run build
```

### 6. Set Permissions

```bash
# As root/sudo
sudo chown -R servco:www-data /var/www/servco/backend/storage
sudo chmod -R 775 /var/www/servco/backend/storage
sudo chmod -R 775 /var/www/servco/backend/bootstrap/cache
```

### 7. Get SSL Certificate

```bash
sudo certbot --apache -d your-domain.com -d www.your-domain.com
```

### 8. Restart Services

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart apache2
```

## File Structure on Server

```
/var/www/servco/
├── backend/
│   ├── .env                    # Production environment
│   ├── public/                 # Laravel public (API entry)
│   ├── storage/
│   │   ├── app/public/         # Public file storage
│   │   └── credentials/        # Google service account JSON
│   └── ...
├── frontend/
│   └── app/
│       └── dist/
│           └── spa/            # Built Quasar SPA (Apache serves this)
└── deploy/
    └── ...
```

## Ongoing Deployments

After initial setup, use the deploy script for updates:

```bash
sudo deploy-servco
```

This script:
1. Pulls latest code from git
2. Installs/updates Composer dependencies
3. Runs database migrations
4. Clears and rebuilds caches
5. Rebuilds frontend
6. Restarts PHP-FPM and reloads Apache

## Manual Deployment Steps

If you prefer manual control:

```bash
cd /var/www/servco

# Pull latest code
sudo -u servco git pull origin main

# Backend
cd backend
sudo -u servco composer install --no-dev --optimize-autoloader
sudo -u servco php artisan migrate --force
sudo -u servco php artisan config:cache
sudo -u servco php artisan route:cache
sudo -u servco php artisan view:cache

# Frontend
cd ../frontend/app
sudo -u servco npm ci
sudo -u servco npm run build

# Restart services
sudo systemctl restart php8.3-fpm
sudo systemctl reload apache2
```

## Backups

Automatic daily backups are configured via cron (2 AM daily):

```bash
# View backup cron
crontab -l

# Manual backup
sudo backup-servco

# Backup location
ls -la /var/backups/servco/
```

Backups include:
- MySQL database dump (gzipped)
- Retention: 7 days

**Recommended**: Also backup to S3 or external storage for disaster recovery.

## Monitoring & Logs

### Application Logs

```bash
# Laravel logs
tail -f /var/www/servco/backend/storage/logs/laravel.log

# Apache access logs
tail -f /var/log/apache2/servco-access.log

# Apache error logs
tail -f /var/log/apache2/servco-error.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log
```

### Service Status

```bash
sudo systemctl status apache2
sudo systemctl status php8.3-fpm
sudo systemctl status mysql
```

## Troubleshooting

### 500 Internal Server Error

1. Check Laravel logs: `tail -f /var/www/servco/backend/storage/logs/laravel.log`
2. Check Apache error logs: `tail -f /var/log/apache2/servco-error.log`
3. Verify permissions: `ls -la /var/www/servco/backend/storage`
4. Clear caches: `php artisan cache:clear && php artisan config:clear`

### API Not Working

1. Verify Apache config: `sudo apache2ctl configtest`
2. Check PHP-FPM is running: `sudo systemctl status php8.3-fpm`
3. Test PHP directly: `php -v`
4. Check route caching: `php artisan route:list`

### Frontend Not Loading

1. Verify build exists: `ls /var/www/servco/frontend/app/dist/spa/`
2. Check Apache DocumentRoot in config
3. Verify permissions on dist folder

### Database Connection Failed

1. Check MySQL is running: `sudo systemctl status mysql`
2. Verify credentials in `.env`
3. Test connection: `mysql -u servco -p servco_prod`

### SSL Certificate Issues

```bash
# Check certificate status
sudo certbot certificates

# Renew certificate manually
sudo certbot renew

# Test auto-renewal
sudo certbot renew --dry-run
```

## Security Checklist

- [ ] Change default MySQL passwords in setup script before running
- [ ] Update backup script with correct MySQL password
- [ ] Enable UFW firewall (done by setup script)
- [ ] Configure fail2ban for SSH protection
- [ ] Set up regular security updates
- [ ] Review and restrict file permissions
- [ ] Enable SSL/HTTPS (Certbot)
- [ ] Configure proper CORS headers
- [ ] Remove/disable debug mode in production

## Performance Tuning

### PHP OPcache (already configured)

The setup script enables OPcache. For high-traffic sites, consider:

```ini
; /etc/php/8.3/fpm/php.ini
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

### MySQL Tuning

For 4GB+ instances, consider:

```ini
; /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
```

### Apache MPM

For high traffic, consider switching to event MPM:

```bash
sudo a2dismod mpm_prefork
sudo a2enmod mpm_event
sudo systemctl restart apache2
```

## Rolling Back

If a deployment causes issues:

```bash
cd /var/www/servco

# View recent commits
git log --oneline -10

# Roll back to previous commit
sudo -u servco git checkout <commit-hash>

# Re-run deployment steps
sudo -u servco composer install --no-dev --optimize-autoloader
sudo -u servco php artisan migrate --force
cd frontend/app && sudo -u servco npm ci && sudo -u servco npm run build
sudo systemctl restart php8.3-fpm
```

## Support

For issues specific to this deployment:
1. Check this guide's troubleshooting section
2. Review Laravel and Quasar documentation
3. Check AWS Lightsail documentation for infrastructure issues
