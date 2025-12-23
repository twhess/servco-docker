# Parts Runner System - Deployment Guide

## Overview

This guide covers deploying the Parts Runner Routing and Scheduling System to production environments.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Database Migration](#database-migration)
4. [Seeding Data](#seeding-data)
5. [Scheduler Configuration](#scheduler-configuration)
6. [Queue Workers](#queue-workers)
7. [Verification](#verification)
8. [Rollback Plan](#rollback-plan)

---

## Prerequisites

### Required Software

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Node.js 18+ and npm
- Composer 2.x
- Docker & Docker Compose (for containerized deployment)

### Required PHP Extensions

- PDO
- MySQL
- mbstring
- openssl
- tokenizer
- xml
- ctype
- json
- BCMath

---

## Environment Setup

### 1. Clone Repository

```bash
git clone <repository-url>
cd ServcoApp
```

### 2. Backend Configuration

```bash
cd backend
cp .env.example .env
```

Edit `.env` and configure:

```env
APP_NAME="ServcoApp Parts Runner"
APP_ENV=production
APP_KEY=  # Generate with: php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=servcoapp
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

QUEUE_CONNECTION=database  # or redis for better performance
```

### 3. Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Frontend Configuration

```bash
cd ../frontend/app
cp .env.example .env
```

Edit `.env`:

```env
VITE_API_BASE_URL=https://api.your-domain.com
```

Build for production:

```bash
npm install
npm run build
```

---

## Database Migration

### 1. Run Migrations

```bash
cd backend

# Review migrations first
php artisan migrate:status

# Run migrations
php artisan migrate --force

# Verify all tables created
php artisan migrate:status
```

### Expected Tables

The following tables should be created:

**Core Tables:**
- users
- roles
- permissions
- role_permission
- user_role
- service_locations
- parts_requests
- parts_request_types
- parts_request_statuses

**Routing Tables (NEW):**
- routes
- route_stops
- route_schedules
- vendor_cluster_locations
- run_instances
- run_stop_actuals
- run_notes
- route_graph_cache
- parts_request_actions

---

## Seeding Data

### 1. Seed Permissions (Required)

```bash
php artisan db:seed --class=RoutingPermissionsSeeder
```

This creates:
- 14 routing module permissions
- 7 enhanced parts request permissions
- Assigns permissions to roles

### 2. Seed Sample Routes (Optional - Development/Staging Only)

```bash
# Seed sample routes
php artisan db:seed --class=RouteSeeder

# Seed workflow actions
php artisan db:seed --class=RequestActionsSeeder
```

**Production Note:** In production, routes should be created through the UI by administrators, not via seeders.

### 3. Verify Permissions

```bash
php artisan tinker

# Check permission count
DB::table('permissions')->whereIn('module', ['routes', 'runs'])->count();
# Should return: 14

# Check super_admin has all permissions
$admin = DB::table('roles')->where('name', 'super_admin')->first();
DB::table('role_permission')->where('role_id', $admin->id)->count();
# Should be > 20
```

---

## Scheduler Configuration

The system uses Laravel's task scheduler for automated jobs.

### 1. Configure Cron (Linux/Production)

Add to crontab:

```bash
crontab -e
```

Add this line:

```cron
* * * * * cd /path/to/ServcoApp/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Verify Scheduled Jobs

```bash
php artisan schedule:list
```

Expected output:

```
0 0 * * *  process-scheduled-requests ........... Next Due: [time]
0 2 * * 0  rebuild-route-graph-cache ............. Next Due: [time]
```

### 3. Scheduled Jobs Overview

**Daily at Midnight (00:00):**
- `ProcessScheduledRequestsJob`
- Processes requests scheduled for today
- Makes them visible to runners
- Auto-assigns to appropriate runs

**Weekly on Sunday at 2 AM:**
- `RebuildRouteGraphCacheJob`
- Rebuilds route pathfinding cache
- Ensures optimal routing performance

### 4. Docker/Supervisord Configuration

For Docker deployments, use supervisord:

```ini
[program:scheduler]
process_name=%(program_name)s
command=/usr/local/bin/php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/scheduler.log
```

---

## Queue Workers

### 1. Configure Queue Workers

The system uses queued jobs for background processing.

**Supervisord Configuration:**

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/local/bin/php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
```

### 2. Start Queue Workers

```bash
# Using artisan directly (development)
php artisan queue:work --sleep=3 --tries=3

# Using supervisord (production)
supervisorctl start queue-worker:*
```

### 3. Monitor Queue

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Verification

### 1. Test Routes System

```bash
# Rebuild route cache
php artisan routes:rebuild-cache

# Check cache entries
php artisan tinker
DB::table('route_graph_cache')->count();
# Should return > 0 if routes exist
```

### 2. Test Scheduled Jobs Manually

```bash
# Test scheduled requests processing
php artisan requests:process-scheduled

# Test run creation
php artisan runs:create
```

### 3. API Health Checks

```bash
# Test route endpoints
curl https://api.your-domain.com/api/routes

# Test run endpoints
curl https://api.your-domain.com/api/runs

# Test health endpoint
curl https://api.your-domain.com/up
```

### 4. Frontend Verification

1. Navigate to `https://your-domain.com`
2. Login as admin
3. Navigate to `/routes` - should see routes list
4. Navigate to `/runner-dashboard` - should see runner interface
5. Navigate to `/shop/staging` - should see staging interface

---

## Post-Deployment Tasks

### 1. Create First Route

1. Login as `super_admin` or `ops_admin`
2. Navigate to Routes page
3. Create a route with:
   - Name: "Main Route"
   - Code: "MAIN-01"
   - Start location: Your main shop
   - Add stops (vendors/shops)
   - Add scheduled times

### 2. Rebuild Route Cache

```bash
php artisan routes:rebuild-cache
```

### 3. Assign Permissions

Verify users have appropriate roles:
- **Dispatchers**: Route and run management
- **Runners**: View and execute runs
- **Shop Staff**: Stage transfer requests

---

## Rollback Plan

### If Deployment Fails

1. **Rollback Database:**

```bash
# Rollback all routing migrations
php artisan migrate:rollback --step=10
```

2. **Restore Previous Code:**

```bash
git checkout <previous-commit-hash>
composer install
php artisan config:clear
php artisan cache:clear
```

3. **Disable New Features:**

Remove scheduled jobs from `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule): void {
    // Comment out new scheduled jobs
})
```

---

## Monitoring & Logs

### Log Files

- **Scheduler**: `storage/logs/scheduler.log`
- **Queue Workers**: `storage/logs/queue-worker.log`
- **Laravel**: `storage/logs/laravel.log`

### Monitor Commands

```bash
# Tail Laravel logs
tail -f storage/logs/laravel.log

# Watch queue status
watch -n 5 'php artisan queue:monitor'

# Check scheduled jobs
php artisan schedule:list
```

---

## Performance Optimization

### 1. Enable OPcache

Add to `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

### 2. Database Indexing

All critical indexes are created by migrations. Verify with:

```sql
SHOW INDEX FROM routes;
SHOW INDEX FROM route_stops;
SHOW INDEX FROM run_instances;
```

### 3. Queue Optimization

For high volume, use Redis instead of database:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
```

---

## Support & Troubleshooting

### Common Issues

**Issue: Scheduled jobs not running**
- Verify cron is configured
- Check `php artisan schedule:list`
- Review scheduler logs

**Issue: Route cache empty**
- Run `php artisan routes:rebuild-cache`
- Verify routes exist in database
- Check for migration errors

**Issue: Permissions denied**
- Re-run `php artisan db:seed --class=RoutingPermissionsSeeder`
- Verify user roles are assigned
- Check role_permission table

---

## Production Checklist

- [ ] All migrations run successfully
- [ ] Permissions seeded
- [ ] Cron configured for scheduler
- [ ] Queue workers running
- [ ] Route cache built
- [ ] API endpoints tested
- [ ] Frontend deployed and accessible
- [ ] SSL certificates configured
- [ ] Database backups configured
- [ ] Monitoring/alerts configured
- [ ] Log rotation configured
- [ ] Error tracking enabled (Sentry/Bugsnag)
