# Production Database Migrations Guide

This guide covers safe practices for managing database schema changes in production to avoid data loss and ensure smooth updates.

## Deployment Scripts

Ready-to-use scripts are available in `/scripts/`:

| Script | Purpose |
|--------|---------|
| `deploy.sh` | Full deployment (backup, pull, migrate, cache) |
| `migrate.sh` | Run migrations with preview and backup |
| `backup.sh` | Create database backup |
| `restore.sh` | Restore from backup |
| `status.sh` | Check application and database status |

See [scripts/README.md](../scripts/README.md) for detailed usage.

**Quick start on your server:**
```bash
cd /var/www/html/scripts
./status.sh      # Check current state
./deploy.sh      # Full deployment
```

---

## Overview

Laravel migrations track all database schema changes in version-controlled files. This allows you to:
- Apply changes consistently across environments
- Roll back changes if something goes wrong
- Keep your database schema in sync with your code

## Golden Rules

1. **Always backup before migrating**
2. **Never modify existing migrations that have run in production** - create new migrations instead
3. **Test migrations locally first** - use the same database backup if possible
4. **Use `--pretend` to preview changes** before running them
5. **Make migrations reversible** - always define a `down()` method

---

## Quick Reference Commands

### Local Development (Docker)

```bash
# Check migration status
docker exec myapp-backend php artisan migrate:status

# Run migrations
docker exec myapp-backend php artisan migrate

# Rollback last batch
docker exec myapp-backend php artisan migrate:rollback

# Preview what would run (dry run)
docker exec myapp-backend php artisan migrate --pretend
```

### Production (Direct SSH)

```bash
cd /var/www/html  # or your Laravel root

# Check status
php artisan migrate:status

# Run migrations (--force required in production)
php artisan migrate --force

# Preview changes first
php artisan migrate --pretend

# Rollback if needed
php artisan migrate:rollback --force
```

---

## Safe Deployment Workflow

### Step 1: Backup the Database

**Always backup before any migration.** This is your safety net.

```bash
# Local Docker
docker exec myapp-mysql mysqldump -u root -proot app > backup_$(date +%Y%m%d_%H%M%S).sql

# Production (adjust credentials)
mysqldump -u app -p app > backup_$(date +%Y%m%d_%H%M%S).sql

# Or with timestamp and compression
mysqldump -u app -p app | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Step 2: Check Current Status

See what migrations have run and what's pending:

```bash
php artisan migrate:status
```

Output shows:
- ✓ = Already run
- Pending = Will run on next migrate

### Step 3: Preview Changes

See exactly what SQL will execute without running it:

```bash
php artisan migrate --pretend
```

Review the output carefully. Look for:
- `DROP` statements (data loss risk)
- `ALTER` statements on large tables (may lock table)
- Any unexpected changes

### Step 4: Run Migrations

```bash
# Production requires --force flag
php artisan migrate --force
```

### Step 5: Verify

```bash
# Check all migrations ran
php artisan migrate:status

# Test the application
# - Check critical features
# - Verify new fields/tables exist
```

---

## Common Scenarios

### Adding a New Column

**Safe approach - nullable or with default:**

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone')->nullable()->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('phone');
    });
}
```

**With default value:**

```php
$table->boolean('is_active')->default(true)->after('status');
```

### Adding a Required Column (Non-Nullable)

Must be done in steps to avoid errors:

```php
// Migration 1: Add as nullable
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->string('tracking_number')->nullable();
    });
}

// Migration 2: Populate existing records (separate migration)
public function up(): void
{
    DB::table('orders')
        ->whereNull('tracking_number')
        ->update(['tracking_number' => 'LEGACY-' . DB::raw('id')]);
}

// Migration 3: Make non-nullable (after data is populated)
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->string('tracking_number')->nullable(false)->change();
    });
}
```

### Renaming a Column

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->renameColumn('name', 'full_name');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->renameColumn('full_name', 'name');
    });
}
```

### Creating a New Table

```php
public function up(): void
{
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->constrained()->onDelete('cascade');
        $table->string('invoice_number')->unique();
        $table->decimal('total', 10, 2);
        $table->enum('status', ['draft', 'sent', 'paid', 'cancelled'])->default('draft');
        $table->timestamps();

        // Audit fields (required per CLAUDE.md)
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->foreign('created_by')->references('id')->on('users');
        $table->foreign('updated_by')->references('id')->on('users');
    });
}

public function down(): void
{
    Schema::dropIfExists('invoices');
}
```

### Dropping a Column (Careful!)

**This causes data loss.** Ensure the column is truly unused.

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('legacy_field');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('legacy_field')->nullable();
    });
}
```

---

## Rollback Procedures

### Rollback Last Batch

Undoes the most recent `migrate` command:

```bash
php artisan migrate:rollback --force
```

### Rollback Specific Number of Batches

```bash
# Rollback last 3 batches
php artisan migrate:rollback --step=3 --force
```

### Rollback to Specific Migration

```bash
# See current status first
php artisan migrate:status

# Reset to a specific migration (rolls back everything after it)
php artisan migrate:reset --force
php artisan migrate --force  # Re-run all migrations
```

### Emergency: Restore from Backup

If rollback doesn't work or causes more issues:

```bash
# Drop and recreate database (DESTRUCTIVE)
mysql -u root -p -e "DROP DATABASE app; CREATE DATABASE app;"

# Restore from backup
mysql -u root -p app < backup_20240115_143022.sql

# Verify
php artisan migrate:status
```

---

## Deployment Script Example

Save this as `deploy.sh` in your project root:

```bash
#!/bin/bash
set -e  # Exit on any error

echo "=== Starting Deployment ==="

# Configuration
BACKUP_DIR="/var/backups/mysql"
DB_NAME="app"
DB_USER="app"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Step 1: Backup
echo "Creating database backup..."
mkdir -p $BACKUP_DIR
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > "$BACKUP_DIR/backup_$TIMESTAMP.sql.gz"
echo "Backup saved: $BACKUP_DIR/backup_$TIMESTAMP.sql.gz"

# Step 2: Pull latest code
echo "Pulling latest code..."
git pull origin main

# Step 3: Install dependencies
echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Step 4: Check pending migrations
echo "Checking migrations..."
php artisan migrate:status

# Step 5: Run migrations
echo "Running migrations..."
php artisan migrate --force

# Step 6: Clear caches
echo "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 7: Restart queue workers (if using)
# echo "Restarting queue workers..."
# php artisan queue:restart

echo "=== Deployment Complete ==="
```

---

## Zero-Downtime Considerations

For high-traffic applications, consider these strategies:

### 1. Add Before Remove

When renaming columns or changing structure:
1. Add new column
2. Deploy code that writes to both columns
3. Migrate existing data
4. Deploy code that reads from new column
5. Remove old column (in a later release)

### 2. Use Database Transactions

For complex migrations:

```php
public function up(): void
{
    DB::transaction(function () {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('new_field')->nullable();
        });

        DB::table('orders')->update(['new_field' => DB::raw('old_field')]);
    });
}
```

### 3. Maintenance Mode

For major changes that can't be done live:

```bash
# Enable maintenance mode
php artisan down --message="Upgrading database. Back in 5 minutes."

# Run migrations
php artisan migrate --force

# Disable maintenance mode
php artisan up
```

---

## Troubleshooting

### "Nothing to migrate"

Your migrations have already run. Check status:
```bash
php artisan migrate:status
```

### "Table already exists"

A previous migration partially ran. Options:
1. Manually drop the table and re-run migration
2. Add `if (!Schema::hasTable('tablename'))` check

### "Cannot add foreign key constraint"

Common causes:
- Referenced table doesn't exist yet (check migration order)
- Column types don't match (both must be same type)
- Referenced column isn't indexed

### "Class not found"

Run composer autoload:
```bash
composer dump-autoload
```

### Migration Stuck / Timeout

For large tables, migrations may timeout. Options:
1. Increase PHP timeout temporarily
2. Run raw SQL in smaller batches
3. Use `--no-interaction` flag

---

## Best Practices Checklist

Before deploying migrations:

- [ ] Tested locally with production data backup
- [ ] Migration has a working `down()` method
- [ ] No `DROP` statements on tables with important data
- [ ] New columns are nullable OR have defaults
- [ ] Foreign key references exist
- [ ] Database backup created
- [ ] Ran `--pretend` to preview changes
- [ ] Team notified of deployment

---

## Files Reference

- **Migrations Location**: `backend/database/migrations/`
- **Migration Naming**: `YYYY_MM_DD_HHMMSS_description.php`
- **Create New Migration**: `php artisan make:migration create_tablename_table`
- **Add Column Migration**: `php artisan make:migration add_column_to_tablename_table`

---

## Emergency Contacts

If you encounter issues during production migration:

1. **Don't panic** - you have a backup
2. Check Laravel logs: `storage/logs/laravel.log`
3. Check MySQL logs for database-specific errors
4. Rollback if possible, restore from backup if not
5. Document what happened for future reference
