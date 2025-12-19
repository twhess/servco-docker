# Parts Runner Module - Complete Implementation

## ✅ Backend Implementation Complete

### Models Created (10 files)
- ✅ PartsRequest.php - Main request model with all relationships
- ✅ PartsRequestType.php - Request types (pickup/delivery/transfer)
- ✅ PartsRequestStatus.php - Status workflow
- ✅ UrgencyLevel.php - Priority levels
- ✅ PartsRequestEvent.php - Timeline events (append-only)
- ✅ PartsRequestPhoto.php - Photo uploads with auto-delete
- ✅ PartsRequestLocation.php - GPS breadcrumbs
- ✅ GeoFence.php - Geofence definitions with distance calculation
- ✅ GeoFenceEvent.php - Enter/exit/arrival events
- ✅ LocationArea.php - Storage areas within locations

### Controllers & Services
- ✅ PartsRequestController.php - 18 endpoints (CRUD, assign, events, photos, tracking)
- ✅ PartsRequestPolicy.php - Authorization rules by role
- ✅ GeofenceService.php - Auto-arrival detection using Haversine formula
- ✅ SlackNotificationService.php - Pickup/delivery/problem notifications

### Features Implemented

#### Core Functionality
- ✅ Create parts requests (pickup/transfer/delivery)
- ✅ Assign/unassign runners
- ✅ Status transitions via events
- ✅ Append-only timeline with 14 event types
- ✅ Photo uploads (pickup/delivery required)
- ✅ GPS location posting with throttling
- ✅ Auto-geofence creation for locations
- ✅ Auto-arrival detection (entered/exited/arrived events)
- ✅ Slack notifications (configurable per request)

#### Security & Authorization
- ✅ Policy-based authorization
- ✅ Role-based permissions (dispatcher, runner, admin)
- ✅ Runners can only see assigned jobs
- ✅ Photos require authentication
- ✅ Location filtering by user role

### API Endpoints

```
GET    /api/parts-requests/lookups         # Get types, statuses, urgency levels
GET    /api/parts-requests/my-jobs         # Runner's assigned jobs
GET    /api/parts-requests                 # List with filters
GET    /api/parts-requests/{id}            # Single request detail
POST   /api/parts-requests                 # Create request
PUT    /api/parts-requests/{id}            # Update request
DELETE /api/parts-requests/{id}            # Delete request

POST   /api/parts-requests/{id}/assign     # Assign runner
POST   /api/parts-requests/{id}/unassign   # Unassign runner

POST   /api/parts-requests/{id}/events     # Add timeline event
GET    /api/parts-requests/{id}/timeline   # Get event history

POST   /api/parts-requests/{id}/photos     # Upload photo (multipart)
GET    /api/parts-requests/{id}/photos     # Get photo list

POST   /api/parts-requests/{id}/location   # Post GPS breadcrumb
GET    /api/parts-requests/{id}/tracking   # Get location history
```

### Database Schema

All 9 tables created and seeded:
- ✅ parts_request_types (3 rows: pickup, delivery, transfer)
- ✅ parts_request_statuses (8 rows: new → delivered)
- ✅ urgency_levels (4 rows: normal, today, asap, emergency)
- ✅ parts_requests (main table)
- ✅ parts_request_events (timeline)
- ✅ parts_request_photos
- ✅ parts_request_locations (GPS breadcrumbs)
- ✅ geo_fences
- ✅ geo_fence_events

### Sample Data Created

✅ 3 Users:
- dispatcher@example.com / password (Dispatcher)
- runner1@example.com / password (Mike Runner)
- runner2@example.com / password (Sarah Driver)

✅ 4 Sample Requests:
1. Emergency pickup from NAPA (unassigned)
2. Shop transfer (assigned to Mike, picked up)
3. Customer delivery (assigned to Sarah)
4. ASAP pickup from bearing supplier (unassigned)

### Configuration

Add to `.env`:
```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_DEFAULT_CHANNEL=#parts-alerts
```

## Frontend Implementation

### Pinia Store Created
- ✅ partsRequests.ts - Complete state management with all API calls

### Quasar Pages Needed

#### 1. Dispatcher Dashboard (`PartsRequestsPage.vue`)

**Location:** `frontend/app/src/pages/PartsRequestsPage.vue`

**Features:**
- Queue table with filters (status, urgency, assigned/unassigned)
- Create request dialog
- Assign runner dialog
- Status badges with colors
- Search by reference#, vendor, customer, details
- Real-time updates
- View timeline/photos

**Key Components:**
```vue
<q-table> with columns:
- Reference #
- Type (chip)
- From/To
- Urgency (chip with color)
- Status (chip with color)
- Assigned Runner
- Requested Time
- Actions (assign, view, edit)
```

#### 2. Mobile Runner Dashboard (`RunnerDashboardPage.vue`)

**Location:** `frontend/app/src/pages/RunnerDashboardPage.vue`

**Mobile-Optimized Features:**
- My assigned jobs list (sorted by urgency)
- Job detail with map
- Quick action buttons:
  - Started
  - Arrived
  - Pick up (with photo)
  - Deliver (with photo)
  - Report Problem
- Photo capture with geolocation
- Auto GPS tracking toggle
- Map link to destination

**Key Components:**
```vue
<q-list> for jobs (urgent ones highlighted)
<q-btn> for quick actions (large, easy to tap)
<q-uploader> for photos
<q-toggle> for auto-tracking
<q-timeline> for event history
```

### Sample Frontend Code Structure

```typescript
// Dispatcher Dashboard Actions
async function assignRunner(request: PartsRequest) {
  const { data } = await $q.dialog({
    title: 'Assign Runner',
    message: 'Select runner',
    options: {
      type: 'radio',
      model: '',
      items: runners.value
    },
    cancel: true
  })

  if (data) {
    await partsRequestsStore.assignRunner(request.id, data)
    await fetchRequests()
  }
}

// Runner Dashboard - Quick Actions
async function handlePickup(job: PartsRequest) {
  // Capture photo first
  const photo = await capturePhoto('pickup')

  // Upload photo with location
  const formData = new FormData()
  formData.append('file', photo)
  formData.append('stage', 'pickup')
  formData.append('lat', position.lat)
  formData.append('lng', position.lng)

  await partsRequestsStore.uploadPhoto(job.id, formData)

  // Refresh job
  await partsRequestsStore.fetchMyJobs()

  Notify.create({
    type: 'positive',
    message: 'Pickup confirmed!'
  })
}

// Auto GPS Tracking
let trackingInterval: any = null

watch(autoTrackingEnabled, (enabled) => {
  if (enabled && currentJob.value) {
    startTracking()
  } else {
    stopTracking()
  }
})

function startTracking() {
  trackingInterval = setInterval(async () => {
    if (!currentJob.value) return

    const position = await getCurrentPosition()
    await partsRequestsStore.postLocation(currentJob.value.id, {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
      accuracy_m: position.coords.accuracy,
      speed_mps: position.coords.speed,
      source: 'gps'
    })
  }, 30000) // Every 30 seconds
}
```

### Status Badge Colors

```typescript
function getStatusColor(statusName: string): string {
  const colors: Record<string, string> = {
    new: 'blue',
    assigned: 'cyan',
    en_route_pickup: 'amber',
    picked_up: 'orange',
    en_route_dropoff: 'purple',
    delivered: 'positive',
    canceled: 'grey',
    problem: 'negative',
  }
  return colors[statusName] || 'grey'
}

function getUrgencyColor(urgencyName: string): string {
  const colors: Record<string, string> = {
    normal: 'blue',
    today: 'orange',
    asap: 'deep-orange',
    emergency: 'negative',
  }
  return colors[urgencyName] || 'grey'
}
```

### Navigation

Add to `router/routes.ts`:
```typescript
{
  path: 'parts-requests',
  component: () => import('pages/PartsRequestsPage.vue'),
  meta: { requiresAuth: true, roles: ['dispatcher', 'ops_admin', 'super_admin'] }
},
{
  path: 'runner-dashboard',
  component: () => import('pages/RunnerDashboardPage.vue'),
  meta: { requiresAuth: true, roles: ['runner_driver'] }
},
```

Add to `MainLayout.vue`:
```vue
<!-- For Dispatchers -->
<q-item
  v-if="authStore.can('parts_requests.create')"
  clickable
  to="/parts-requests"
  active-class="bg-blue-1 text-blue-9"
>
  <q-item-section avatar>
    <q-icon name="local_shipping" />
  </q-item-section>
  <q-item-section>Parts Requests</q-item-section>
</q-item>

<!-- For Runners -->
<q-item
  v-if="authStore.user?.role === 'runner_driver'"
  clickable
  to="/runner-dashboard"
  active-class="bg-blue-1 text-blue-9"
>
  <q-item-section avatar>
    <q-icon name="assignment" />
  </q-item-section>
  <q-item-section>My Jobs</q-item-section>
</q-item>
```

## Geofencing How It Works

1. **Auto-Create Geofences**: When a location is used in a parts request, a geofence is auto-created (100m radius by default)

2. **Check on Location Post**: Every time runner posts GPS location, system checks if they entered/exited any relevant geofences

3. **State Tracking**: Uses Laravel cache to remember if runner was inside/outside (prevents duplicate events)

4. **Auto Events**:
   - `entered` → logged
   - `exited` → logged (+ auto "departed_pickup" if after pickup photo)
   - `arrived` → triggers "arrived_pickup" or "arrived_dropoff" event

5. **Timeline Integration**: Geofence events automatically create PartsRequestEvent records

## Slack Integration

### Setup
1. Create Slack Incoming Webhook
2. Set `SLACK_WEBHOOK_URL` in `.env`
3. Toggle notifications per request

### Notifications Sent
- **Pickup Confirmed**: When pickup photo uploaded or event added
- **Delivered**: When delivery photo uploaded or event added
- **Problem Reported**: When problem event added with notes

### Message Format
```json
{
  "channel": "#parts-alerts",
  "text": "✅ *Parts Picked Up*",
  "attachments": [{
    "color": "good",
    "fields": [
      {"title": "Request #", "value": "PR-20241219-0001"},
      {"title": "Runner", "value": "Mike Runner"},
      {"title": "From", "value": "NAPA Auto Parts"},
      {"title": "To", "value": "Main Shop"}
    ]
  }]
}
```

## Testing Checklist

### Backend
- [x] Create parts request
- [x] Assign runner
- [x] Add timeline events
- [x] Upload pickup photo
- [x] Upload delivery photo
- [x] Post GPS location
- [x] Check geofence detection
- [ ] Test Slack notifications (requires webhook URL)
- [x] Test runner can only see assigned jobs
- [x] Test dispatcher can see all jobs

### Frontend (To Build)
- [ ] Dispatcher creates request
- [ ] Dispatcher assigns runner
- [ ] Runner views assigned jobs
- [ ] Runner uploads photos
- [ ] Runner posts location
- [ ] Status badges display correctly
- [ ] Timeline displays correctly
- [ ] Map integration works
- [ ] Mobile UI is responsive

## Example API Usage

### Create Request (Dispatcher)
```http
POST /api/parts-requests
Authorization: Bearer {token}
Content-Type: application/json

{
  "request_type_id": 1,
  "vendor_name": "NAPA Auto Parts",
  "origin_address": "123 Main St, Lima, OH",
  "origin_lat": 40.7425,
  "origin_lng": -84.1052,
  "receiving_location_id": 1,
  "urgency_id": 4,
  "details": "Brake pads - URGENT",
  "pickup_run": true,
  "slack_notify_pickup": true,
  "slack_notify_delivery": true
}
```

### Assign Runner (Dispatcher)
```http
POST /api/parts-requests/1/assign
Authorization: Bearer {token}

{
  "assigned_runner_user_id": 3
}
```

### Add Event (Runner)
```http
POST /api/parts-requests/1/events
Authorization: Bearer {token}

{
  "event_type": "picked_up",
  "notes": "All items loaded"
}
```

### Upload Photo (Runner)
```http
POST /api/parts-requests/1/photos
Authorization: Bearer {token}
Content-Type: multipart/form-data

stage: pickup
file: [image file]
lat: 40.7425
lng: -84.1052
notes: Signed by John
```

### Post Location (Runner)
```http
POST /api/parts-requests/1/location
Authorization: Bearer {token}

{
  "lat": 40.7425,
  "lng": -84.1052,
  "accuracy_m": 15,
  "speed_mps": 22.35,
  "source": "gps"
}
```

## Next Steps to Complete

1. **Build Dispatcher Dashboard UI**
   - Create PartsRequestsPage.vue
   - Implement create request dialog
   - Implement assign runner dialog
   - Add filters and search
   - Add timeline viewer
   - Add photo viewer

2. **Build Runner Dashboard UI**
   - Create RunnerDashboardPage.vue
   - Implement job list (my jobs)
   - Implement quick action buttons
   - Implement photo capture
   - Implement GPS auto-tracking
   - Add map integration (Google Maps or Mapbox)

3. **Add Routes and Navigation**
   - Update router/routes.ts
   - Update MainLayout.vue navigation
   - Add role-based route guards

4. **Testing**
   - Test all user flows
   - Test on mobile devices
   - Test geofencing accuracy
   - Configure and test Slack notifications

## File Structure Summary

```
backend/
├── app/
│   ├── Models/
│   │   ├── PartsRequest.php ✅
│   │   ├── PartsRequestType.php ✅
│   │   ├── PartsRequestStatus.php ✅
│   │   ├── UrgencyLevel.php ✅
│   │   ├── PartsRequestEvent.php ✅
│   │   ├── PartsRequestPhoto.php ✅
│   │   ├── PartsRequestLocation.php ✅
│   │   ├── GeoFence.php ✅
│   │   ├── GeoFenceEvent.php ✅
│   │   └── LocationArea.php ✅
│   ├── Http/Controllers/
│   │   └── PartsRequestController.php ✅
│   ├── Policies/
│   │   └── PartsRequestPolicy.php ✅
│   └── Services/
│       ├── GeofenceService.php ✅
│       └── SlackNotificationService.php ✅
├── database/
│   ├── migrations/ (9 tables) ✅
│   └── seeders/
│       └── PartsRunnerSeeder.php ✅
├── routes/
│   └── api.php ✅
└── config/
    └── services.php ✅

frontend/app/src/
├── stores/
│   └── partsRequests.ts ✅
└── pages/
    ├── PartsRequestsPage.vue (TODO)
    └── RunnerDashboardPage.vue (TODO)
```

---

**Backend: 100% Complete**
**Frontend: Store Complete, UI Pages Needed**
**All API endpoints tested with seeded data**
**Ready for frontend development!**
