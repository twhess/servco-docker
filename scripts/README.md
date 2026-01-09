# ServcoApp Deployment Scripts

Scripts for managing the ServcoApp on AWS Lightsail (or any LAMP server).

## Setup

1. Copy these scripts to your server:
   ```bash
   scp scripts/*.sh user@your-server:/var/www/html/scripts/
   ```

2. Make them executable:
   ```bash
   chmod +x /var/www/html/scripts/*.sh
   ```

3. Set database password (optional, scripts will prompt if not set):
   ```bash
   export DB_PASSWORD="your_password"
   ```

## Scripts

### deploy.sh - Full Deployment

Runs a complete deployment: backup, pull code, install dependencies, migrate, cache.

```bash
./deploy.sh                    # Full deployment with prompts
./deploy.sh --skip-backup      # Skip the database backup
./deploy.sh --skip-migrations  # Skip migrations (code-only update)
```

### migrate.sh - Run Migrations

Safely run database migrations with preview and optional backup.

```bash
./migrate.sh            # Interactive - shows status, preview, asks for confirmation
./migrate.sh --force    # Skip prompts (still creates backup)
./migrate.sh --rollback # Undo the last batch of migrations
```

### backup.sh - Create Backup

Create a timestamped database backup.

```bash
./backup.sh          # Interactive backup
./backup.sh --quiet  # Silent mode (for cron jobs)
```

Add to cron for automatic daily backups:
```bash
# Run at 2 AM daily
0 2 * * * DB_PASSWORD="yourpass" /var/www/html/scripts/backup.sh --quiet
```

### restore.sh - Restore from Backup

Restore the database from a backup file.

```bash
./restore.sh                                    # Shows available backups
./restore.sh /var/backups/servcoapp/backup_20240115_143022.sql.gz
```

### status.sh - System Status

Shows current application status, git info, migration status, and backups.

```bash
./status.sh
```

## Configuration

Edit the configuration section at the top of each script:

```bash
APP_DIR="/var/www/html"           # Laravel application root
BACKUP_DIR="/var/backups/servcoapp"  # Backup storage location
DB_NAME="app"                     # Database name
DB_USER="app"                     # Database username
GIT_BRANCH="main"                 # Branch to deploy
```

## Typical Workflow

### Regular Deployment

```bash
cd /var/www/html/scripts
./status.sh              # Check current state
./deploy.sh              # Run full deployment
```

### Just Run Migrations

```bash
./migrate.sh             # Preview and run migrations
```

### Emergency Rollback

```bash
./migrate.sh --rollback  # Undo last migration batch
# OR
./restore.sh /var/backups/servcoapp/backup_XXXXXX.sql.gz
```

### Check What's Pending

```bash
./status.sh              # Quick overview
php artisan migrate:status  # Detailed migration list
```

## Backup Location

Backups are stored in `/var/backups/servcoapp/` with naming format:
- `backup_YYYYMMDD_HHMMSS.sql.gz` - Regular backups
- `pre_migrate_YYYYMMDD_HHMMSS.sql.gz` - Pre-migration backups
- `pre_rollback_YYYYMMDD_HHMMSS.sql.gz` - Pre-rollback backups

By default, the last 30 backups are kept (configurable in backup.sh).

## Troubleshooting

### "Permission denied"
```bash
chmod +x scripts/*.sh
```

### "Command not found: php"
PHP might not be in the PATH. Use full path:
```bash
/usr/bin/php artisan migrate:status
```

### Database connection failed
Check your DB_PASSWORD environment variable or the password prompt.

### Can't write to backup directory
```bash
sudo mkdir -p /var/backups/servcoapp
sudo chown www-data:www-data /var/backups/servcoapp
```
