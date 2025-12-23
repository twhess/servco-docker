# Parts Runner Routing and Scheduling System

## Overview

The Parts Runner Routing and Scheduling System is a comprehensive solution for managing multi-location parts pickup, transfer, and delivery operations for truck repair businesses. The system provides intelligent routing, automated scheduling, and real-time tracking capabilities.

## Key Features

### 🗺️ Intelligent Routing
- **Graph-based pathfinding** for multi-hop routing between locations
- **Vendor cluster support** - group vendors at any point in the route
- **Automatic route optimization** with cached pathfinding
- **Multi-leg request handling** - automatically splits requests requiring multiple routes

### 📅 Automated Scheduling
- **Daily midnight run creation** from route schedules
- **Forward scheduling** - schedule requests for future dates
- **Auto-assignment** to optimal runs based on location and time
- **Manual override** capability for dispatchers

### 🚚 Run Execution
- **Mobile-first runner interface** for executing daily routes
- **Stop-by-stop progress tracking** with arrival/departure times
- **Photo capture** for pickup/delivery proof
- **GPS location tracking** for audit trails
- **Task completion tracking** per stop

### 🔄 Workflow Management
- **Action-driven workflow** system for request status transitions
- **Role-based actions** (shop staff, runner, dispatcher)
- **Photo and note requirements** per action type
- **Problem reporting** at any stage

### 📊 Dashboard & Reporting
- **Dispatcher feed dashboard** for request overview
- **Runner dashboard** showing assigned runs
- **Shop staging interface** for transfer preparation
- **Historical ETA calculation** from past run data

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend (Vue 3)                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Routes     │  │    Runs      │  │   Requests   │     │
│  │  Management  │  │  Dashboard   │  │    Feed      │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ▼ REST API
┌─────────────────────────────────────────────────────────────┐
│                     Backend (Laravel)                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Controllers │  │   Services   │  │    Models    │     │
│  │              │  │              │  │              │     │
│  │ • Routes     │  │ • RouteGraph │  │ • Route      │     │
│  │ • Runs       │  │ • Scheduler  │  │ • RunInstance│     │
│  │ • Requests   │  │ • Workflow   │  │ • Request    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Background Jobs & Scheduler              │  │
│  │  • ProcessScheduledRequestsJob (Daily @ Midnight)    │  │
│  │  • RebuildRouteGraphCacheJob (Weekly @ 2 AM)        │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database (MySQL)                        │
│  • Routes & Stops                                           │
│  • Run Instances & Actuals                                  │
│  • Parts Requests (Enhanced)                                │
│  • Route Graph Cache                                        │
└─────────────────────────────────────────────────────────────┘
```

## Technology Stack

### Backend
- **PHP 8.1+** with Laravel 11
- **MySQL 8.0+** for data persistence
- **Laravel Sanctum** for API authentication
- **Laravel Scheduler** for automated jobs
- **Queue Workers** for background processing

### Frontend
- **Vue 3** with Composition API
- **TypeScript** for type safety
- **Quasar Framework v2** for mobile-first UI
- **Pinia** for state management
- **Axios** for HTTP requests
- **Vue Router** for navigation

### Infrastructure
- **Docker** for containerization
- **Supervisord** for process management
- **Cron** for scheduler execution

## Installation

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed deployment instructions.

### Quick Start (Development)

```bash
# Backend setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RoutingPermissionsSeeder

# Frontend setup
cd ../frontend/app
npm install
npm run dev

# Start backend server
cd ../../backend
php artisan serve
```

## Database Schema

### Core Routing Tables

**routes**
- Route definitions with start location
- Soft delete via `is_active` flag

**route_stops**
- Ordered stops on each route
- Stop types: SHOP, VENDOR_CLUSTER, CUSTOMER, AD_HOC
- Estimated duration per stop

**route_schedules**
- Scheduled times for daily run creation
- Multiple times per route supported

**vendor_cluster_locations**
- Vendors assigned to cluster stops
- Order: 0 = optimizable, >0 = fixed sequence

### Run Execution Tables

**run_instances**
- Daily execution of route templates
- Status: pending, in_progress, completed, canceled
- Assigned runner and actual start/end times

**run_stop_actuals**
- Actual arrival/departure times per stop
- Task completion tracking
- Used for historical ETA calculation

**run_notes**
- Runner and dispatcher notes per run
- Timestamped with user attribution

### Enhanced Parts Requests

New fields added to existing `parts_requests` table:

- `run_instance_id` - Assigned run
- `pickup_stop_id` - Where to pick up
- `dropoff_stop_id` - Where to deliver
- `parent_request_id` - For multi-leg segments
- `segment_order` - Leg number in multi-hop
- `scheduled_for_date` - Future scheduling
- `override_run_instance_id` - Manual override

### Cache & Workflow

**route_graph_cache**
- Pre-computed paths between all location pairs
- BFS pathfinding results cached
- Hop count and route IDs stored

**parts_request_actions**
- Available actions per request type/status/role
- Photo/note requirements defined
- Display labels, colors, and icons

## API Endpoints

See [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for complete API reference.

### Routes
- `GET /api/routes` - List routes
- `POST /api/routes` - Create route
- `POST /api/routes/{id}/stops` - Add stop
- `POST /api/routes/{id}/stops/reorder` - Reorder stops
- `GET /api/routes/find-path` - Find path between locations

### Runs
- `GET /api/runs` - List runs
- `GET /api/runs/my-runs` - Get runner's runs
- `POST /api/runs/{id}/start` - Start run
- `POST /api/runs/{id}/complete` - Complete run
- `POST /api/runs/{id}/stops/{stopId}/arrive` - Arrive at stop
- `POST /api/runs/{id}/stops/{stopId}/depart` - Depart from stop

### Requests
- `POST /api/parts-requests/{id}/assign-to-run` - Assign to run
- `POST /api/parts-requests/{id}/execute-action` - Execute workflow action
- `POST /api/parts-requests/{id}/schedule` - Schedule for future
- `GET /api/parts-requests/feed` - Dispatcher dashboard

## Console Commands

```bash
# Create run instances for a date
php artisan runs:create {date?}

# Process scheduled requests
php artisan requests:process-scheduled {date?}

# Rebuild route graph cache
php artisan routes:rebuild-cache {--async}
```

## Scheduled Jobs

**Daily at Midnight:**
- `ProcessScheduledRequestsJob`
- Makes scheduled requests visible
- Auto-assigns to appropriate runs

**Weekly on Sunday at 2 AM:**
- `RebuildRouteGraphCacheJob`
- Rebuilds pathfinding cache
- Ensures optimal routing

## User Roles & Permissions

### Super Admin / Ops Admin
- Full access to all routing features
- Can create/edit/delete routes
- Can manage schedules and stops
- Can rebuild cache

### Dispatcher
- Create and manage routes
- Assign requests to runs
- Override automatic assignments
- View all runs and requests
- Cannot delete routes or rebuild cache

### Runner (runner_driver)
- View assigned runs
- Execute run workflow
- Mark arrivals/departures
- Add run notes
- View routes (read-only)

### Shop Manager / Shop Staff
- View routes (read-only)
- Stage transfer requests
- Mark parts ready/not available

## Workflow Actions

### Vendor Pickup
1. **New** → Ready for Pickup (Shop Staff)
2. **Ready for Pickup** → Picked Up (Runner + Photo)
3. **Picked Up** → Delivered (Runner + Photo)

### Shop Transfer
1. **New** → Ready to Transfer (Shop Staff)
2. **Ready to Transfer** → Picked Up (Runner + Photo)
3. **Picked Up** → Delivered (Runner + Photo)

### Customer Delivery
1. **New** → Ready to Deliver (Shop Staff)
2. **Ready to Deliver** → Picked Up (Runner + Photo)
3. **Picked Up** → Delivered to Customer (Runner + Photo)

### Universal
- **Report Problem** (Any status → Problem, requires note)

## Testing

### Run Tests

```bash
# Backend tests
cd backend
php artisan test

# Specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Frontend tests
cd frontend/app
npm run test:unit
```

### Test Coverage

- ✅ Route API endpoints (RouteApiTest)
- ✅ Run Instance API endpoints (RunInstanceApiTest)
- ✅ RouteGraphService pathfinding (RouteGraphServiceTest)

## Performance Considerations

### Route Graph Cache
- Pre-computes all paths at build time
- Weekly rebuild keeps cache fresh
- Prevents expensive BFS on every request

### Database Indexing
- Composite indexes on (route_id, stop_order)
- Indexes on foreign keys
- Indexes on frequently queried fields

### Queue Workers
- Background processing for slow operations
- Prevents blocking API requests
- Retry logic for failed jobs

## Troubleshooting

### Routes not appearing
- Check `is_active = true` in database
- Verify permissions seeded
- Rebuild route cache

### Scheduled jobs not running
- Verify cron configured correctly
- Check `php artisan schedule:list`
- Review scheduler logs

### Requests not auto-assigning
- Ensure routes exist with appropriate stops
- Check route graph cache has entries
- Verify request locations match route stops

## Future Enhancements

- [ ] Real-time GPS tracking on map
- [ ] Route optimization based on traffic
- [ ] Driver mobile app (native)
- [ ] Customer delivery notifications
- [ ] Analytics dashboard
- [ ] Webhook support for integrations

## Contributing

1. Create feature branch from `dev`
2. Follow coding standards (PSR-12 for PHP, Vue style guide)
3. Write tests for new features
4. Submit pull request with description

## Support

- **Issues**: Submit via GitHub Issues
- **Documentation**: See `/docs` folder
- **API Docs**: [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
- **Deployment**: [DEPLOYMENT.md](DEPLOYMENT.md)

## License

Proprietary - All rights reserved

---

**Version**: 1.0.0
**Last Updated**: January 2025
**Maintained By**: Development Team
