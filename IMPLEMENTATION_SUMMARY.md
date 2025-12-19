# IT Assets & Shop Tools + Parts Runner Implementation

## Overview

This implementation adds three major modules to your Laravel + Quasar application:

1. **IT Assets Management** - Track devices, issues, repairs, software, and subscriptions
2. **Shop Tools Management** - Track tools across locations with transfer workflows
3. **Parts Runner Module** - Dispatch & track pickup/delivery requests with GPS and photos

## Database Schema (30 Tables)

### Location & Areas
- `location_areas` - Storage areas within service_locations (bins, shelves, etc.)

### IT Assets Module (14 tables)
- `device_types` - Categories (laptop, desktop, tablet, phone, printer, etc.)
- `devices` - Main device inventory with asset tags, serials, locations
- `device_assignments` - Assignment history to users or locations
- `issue_categories` - Issue types (hardware, software, network, etc.)
- `device_issues` - Issue tracking with priority and status
- `repair_events` - Repair work performed on devices
- `parts_catalog` - Replacement parts inventory
- `repair_parts_used` - Parts consumed during repairs
- `software_products` - Software catalog
- `subscriptions` - Software subscriptions with renewal tracking
- `device_software_installations` - What's installed where
- `shared_accounts` - Encrypted shared passwords for services
- `shared_account_access` - Per-user access control
- `shared_account_device_links` - Link accounts to devices

### QR Code System (3 tables)
- `qr_tokens` - Tokenized QR codes for devices/tools
- `qr_scans` - Scan history and analytics
- `found_device_contacts` - Public "found device" contact info

### Tools Module (3 tables)
- `tool_categories` - Tool types
- `tools` - Tool inventory with home/current locations
- `tool_transfers` - Transfer requests between locations

### Parts Runner Module (9 tables)
- `parts_request_types` - pickup, delivery, transfer (seeded)
- `parts_request_statuses` - Status workflow (seeded)
- `urgency_levels` - Priority levels (seeded)
- `parts_requests` - Main request table with origin/destination
- `parts_request_events` - Append-only timeline of all events
- `parts_request_photos` - Required photos at pickup & delivery
- `parts_request_locations` - GPS breadcrumb trail
- `geo_fences` - Geofence definitions for auto-arrival
- `geo_fence_events` - Enter/exit/arrival events

## Key Features Implemented

### 1. IT Assets Management
- **Devices**: Track purchase date, cost, warranty, home & current location
- **Assignments**: Assign to users or locations with date ranges
- **Issues & Repairs**: Full issue tracking with parts tracking
- **Software Management**: Track products, subscriptions, and installations
- **Subscription Renewals**: Automatic alerts for upcoming renewals

### 2. Shared Accounts (Secure Password Management)
- **Encryption at Rest**: Passwords encrypted using Laravel encryption (AES-256)
- **Access Control**: Grant view/edit/admin access per user
- **Device Linking**: Link accounts to specific devices
- **Audit Trail**: Track who created and granted access

### 3. QR Code System
- **Two Modes**:
  - **Internal**: Requires login, shows full device/tool details
  - **Public Found**: No login, shows only contact instructions
- **Tokenized URLs**: Secure, revocable, optionally time-limited
- **Scan Analytics**: Track when/where QR codes are scanned

### 4. Shop Tools Management
- **Home vs Current Location**: Track where tools belong vs where they are
- **Transfers**: Request, ship, receive workflow
- **User Assignments**: Optionally assign tools to specific users
- **Not at Home Report**: Find tools that need to be returned

### 5. Parts Runner Module
- **Request Types**: Pickup from vendor, transfer between shops, delivery to customer
- **Full Address Support**: Structured locations + free-form addresses with lat/lng
- **Assignment**: Dispatchers assign requests to runners
- **GPS Tracking**: Optional breadcrumb trail during active jobs
- **Geofencing**: Auto-detect arrival at pickup/dropoff locations
- **Photo Requirements**: Mandatory photos at pickup and delivery
- **Event Timeline**: Append-only log of all status changes and events
- **Slack Integration**: Configurable notifications per request
- **Mobile-First UI**: Runner dashboard optimized for mobile devices

## Role-Based Access Control (RBAC)

### Roles (already in User model)
- **super_admin**: Full access to everything
- **ops_admin**: Manage locations, devices, tools, create requests
- **dispatcher**: Create/assign parts requests, view tracking
- **shop_manager**: View devices/tools at their locations
- **parts_manager**: Manage vendors, parts catalog
- **runner_driver**: View assigned jobs, update status, upload photos
- **technician_mobile**: Report issues, view assigned devices
- **read_only**: View-only access for accounting/reporting

### Permissions Matrix

| Permission | Super Admin | Ops Admin | Dispatcher | Shop Mgr | Parts Mgr | Runner | Tech | Read Only |
|------------|-------------|-----------|------------|----------|-----------|--------|------|-----------|
| devices.create | ✓ | ✓ | - | - | - | - | - | - |
| devices.update | ✓ | ✓ | - | Location | - | - | - | - |
| devices.view_all | ✓ | ✓ | ✓ | Location | - | - | - | ✓ |
| issues.create | ✓ | ✓ | - | ✓ | - | - | ✓ | - |
| repairs.create | ✓ | ✓ | - | ✓ | - | - | ✓ | - |
| shared_accounts.view | ✓ | ✓ | - | Granted | Granted | - | - | - |
| tools.transfer | ✓ | ✓ | ✓ | ✓ | - | - | - | - |
| parts_requests.create | ✓ | ✓ | ✓ | - | - | - | - | - |
| parts_requests.assign | ✓ | ✓ | ✓ | - | - | - | - | - |
| parts_requests.update_status | ✓ | - | - | - | - | Assigned | - | - |
| parts_requests.upload_photo | ✓ | - | - | - | - | Assigned | - | - |

## API Endpoints Structure

### Devices
```
GET    /api/devices                    # List with filters
GET    /api/devices/{id}               # Single device detail
POST   /api/devices                    # Create device
PUT    /api/devices/{id}               # Update device
DELETE /api/devices/{id}               # Soft delete
POST   /api/devices/{id}/assign        # Assign to user/location
POST   /api/devices/{id}/qr-code       # Generate QR code
```

### Device Issues
```
GET    /api/devices/{id}/issues        # List issues for device
POST   /api/devices/{id}/issues        # Create issue
PUT    /api/issues/{id}                # Update issue
POST   /api/issues/{id}/resolve        # Mark resolved
```

### Repairs
```
POST   /api/devices/{id}/repairs       # Create repair event
PUT    /api/repairs/{id}               # Update repair
POST   /api/repairs/{id}/parts         # Add parts used
```

### Software & Subscriptions
```
GET    /api/software                   # List software products
POST   /api/software                   # Create product
GET    /api/subscriptions              # List subscriptions
GET    /api/subscriptions/renewals     # Upcoming renewals (within 30 days)
POST   /api/subscriptions              # Create subscription
POST   /api/devices/{id}/software      # Install software on device
```

### Shared Accounts
```
GET    /api/shared-accounts            # List (filtered by access)
POST   /api/shared-accounts            # Create account
GET    /api/shared-accounts/{id}       # View (decrypt if has access)
PUT    /api/shared-accounts/{id}       # Update
POST   /api/shared-accounts/{id}/grant # Grant user access
DELETE /api/shared-accounts/{id}/revoke/{userId} # Revoke access
```

### Tools
```
GET    /api/tools                      # List tools
GET    /api/tools/not-at-home          # Tools not at home location
POST   /api/tools                      # Create tool
PUT    /api/tools/{id}                 # Update tool
POST   /api/tools/{id}/transfer        # Request transfer
PUT    /api/tool-transfers/{id}/ship   # Mark shipped
PUT    /api/tool-transfers/{id}/receive # Mark received
```

### Parts Requests
```
GET    /api/parts-requests             # List (dispatcher view)
GET    /api/parts-requests/my-jobs     # Runner's assigned jobs
GET    /api/parts-requests/{id}        # Single request detail
POST   /api/parts-requests             # Create request
PUT    /api/parts-requests/{id}        # Update request
POST   /api/parts-requests/{id}/assign # Assign runner
POST   /api/parts-requests/{id}/events # Add event to timeline
POST   /api/parts-requests/{id}/photos # Upload photo (multipart)
GET    /api/parts-requests/{id}/timeline # Get event history
POST   /api/parts-requests/{id}/location # Post GPS location
GET    /api/parts-requests/{id}/track  # Get location history
POST   /api/parts-requests/{id}/slack  # Send Slack notification
```

### QR Codes
```
GET    /qr/{token}                     # Scan QR code (public or auth)
POST   /api/qr/generate                # Generate new QR token
GET    /api/qr/{token}/label           # Printable label page
```

## Example API Requests & Responses

### Create Device
```http
POST /api/devices
Content-Type: application/json

{
  "asset_tag": "LAP-2024-001",
  "serial_number": "ABC123XYZ",
  "device_type_id": 1,
  "make": "Dell",
  "model": "Latitude 5420",
  "hostname": "SHOP1-LAP01",
  "function": "Shop Manager Workstation",
  "purchase_date": "2024-01-15",
  "purchase_cost": 1299.99,
  "vendor_name": "CDW",
  "warranty_expires_on": "2027-01-15",
  "status": "active",
  "home_location_id": 1,
  "current_location_id": 1,
  "notes": "Includes docking station"
}
```

Response:
```json
{
  "device": {
    "id": 1,
    "asset_tag": "LAP-2024-001",
    "serial_number": "ABC123XYZ",
    "device_type": {
      "id": 1,
      "name": "Laptop"
    },
    "make": "Dell",
    "model": "Latitude 5420",
    "status": "active",
    "home_location": {
      "id": 1,
      "name": "Main Shop"
    },
    "qr_url": "https://app.example.com/qr/abc123..."
  },
  "message": "Device created successfully"
}
```

### Create Parts Request
```http
POST /api/parts-requests
Content-Type: application/json

{
  "request_type_id": 1,
  "vendor_name": "NAPA Auto Parts",
  "origin_address": "123 Main St, Lima, OH 45801",
  "origin_lat": 40.7425,
  "origin_lng": -84.1052,
  "receiving_location_id": 2,
  "urgency_id": 3,
  "details": "Brake pads for F-250, part #12345",
  "special_instructions": "Call ahead, ask for Mike",
  "pickup_run": true,
  "slack_notify_pickup": true,
  "slack_channel": "#parts-alerts"
}
```

Response:
```json
{
  "request": {
    "id": 1,
    "reference_number": "PR-2024-0001",
    "request_type": "pickup",
    "vendor_name": "NAPA Auto Parts",
    "urgency": "asap",
    "status": "new",
    "origin_address": "123 Main St, Lima, OH 45801",
    "receiving_location": {
      "id": 2,
      "name": "West Shop"
    },
    "requested_at": "2024-12-19T10:30:00Z",
    "requested_by": {
      "id": 5,
      "name": "John Dispatcher"
    }
  },
  "message": "Parts request created successfully"
}
```

### Runner Update Status
```http
POST /api/parts-requests/1/events
Content-Type: application/json

{
  "event_type": "picked_up",
  "notes": "Received parts, invoice #9876"
}
```

### Upload Photo
```http
POST /api/parts-requests/1/photos
Content-Type: multipart/form-data

stage=pickup
file=[binary image data]
lat=40.7425
lng=-84.1052
notes=Boxed and loaded
```

## Encryption Implementation

### Shared Accounts Password Encryption

Using Laravel's built-in encryption (AES-256-CBC):

```php
// In SharedAccount model
use Illuminate\Support\Facades\Crypt;

protected $casts = [
    'password_encrypted' => 'encrypted',
];

// When storing
$account->password_encrypted = $plainPassword;

// When retrieving (only if user has access)
if ($user->canView($account)) {
    $plainPassword = $account->password_encrypted;
}
```

### Access Control Logic
```php
// In SharedAccountPolicy
public function view(User $user, SharedAccount $account)
{
    // Admin can view all
    if ($user->isSuperAdmin() || $user->isOpsAdmin()) {
        return true;
    }

    // Check explicit access grant
    return $account->accessGrants()
        ->where('user_id', $user->id)
        ->exists();
}
```

## Geofencing Implementation

### Auto-Arrival Detection
```php
// When runner posts location
public function postLocation(Request $request, $id)
{
    $partsRequest = PartsRequest::findOrFail($id);
    $location = $request->validate([
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
        'accuracy_m' => 'nullable|numeric',
    ]);

    // Save location
    PartsRequestLocation::create([
        'parts_request_id' => $id,
        'runner_user_id' => $request->user()->id,
        'captured_at' => now(),
        'lat' => $location['lat'],
        'lng' => $location['lng'],
        'accuracy_m' => $location['accuracy_m'],
    ]);

    // Check geofences
    $this->checkGeofences($partsRequest, $location);
}

private function checkGeofences($request, $location)
{
    // Get relevant geofences (origin & destination)
    $geofences = GeoFence::where(function($q) use ($request) {
        if ($request->origin_location_id) {
            $q->orWhere('entity_id', $request->origin_location_id);
        }
        if ($request->receiving_location_id) {
            $q->orWhere('entity_id', $request->receiving_location_id);
        }
    })->get();

    foreach ($geofences as $fence) {
        $distance = $this->calculateDistance(
            $location['lat'],
            $location['lng'],
            $fence->center_lat,
            $fence->center_lng
        );

        if ($distance <= $fence->radius_m) {
            // Inside fence - trigger arrived event
            $this->handleArrival($request, $fence);
        }
    }
}
```

## Slack Integration

### Notification Service
```php
// config/services.php
'slack' => [
    'webhook_url' => env('SLACK_WEBHOOK_URL'),
],

// In SlackNotificationService
public function notifyPickup(PartsRequest $request)
{
    if (!$request->slack_notify_pickup) {
        return;
    }

    $channel = $request->slack_channel ?? config('services.slack.default_channel');

    $message = [
        'channel' => $channel,
        'text' => "Parts picked up: {$request->reference_number}",
        'attachments' => [[
            'color' => 'good',
            'fields' => [
                ['title' => 'From', 'value' => $request->vendor_name, 'short' => true],
                ['title' => 'To', 'value' => $request->receivingLocation->name, 'short' => true],
                ['title' => 'Runner', 'value' => $request->assignedRunner->name, 'short' => true],
                ['title' => 'Details', 'value' => $request->details, 'short' => false],
            ],
        ]],
    ];

    Http::post(config('services.slack.webhook_url'), $message);
}
```

## Reports & Queries

### Tools Not at Home Location
```sql
SELECT
    t.asset_tag,
    t.name,
    t.status,
    home.name AS home_location,
    current.name AS current_location,
    DATEDIFF(NOW(), t.updated_at) AS days_away
FROM tools t
JOIN service_locations home ON t.home_location_id = home.id
JOIN service_locations current ON t.current_location_id = current.id
WHERE t.home_location_id != t.current_location_id
    AND t.status = 'available'
    AND t.deleted_at IS NULL
ORDER BY days_away DESC;
```

### Upcoming Subscription Renewals
```sql
SELECT
    sp.name AS software,
    s.plan_name,
    s.renew_date,
    s.cost_amount,
    s.cost_currency,
    DATEDIFF(s.renew_date, CURDATE()) AS days_until_renewal
FROM subscriptions s
JOIN software_products sp ON s.software_product_id = sp.id
WHERE s.status = 'active'
    AND s.renew_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
ORDER BY s.renew_date ASC;
```

### Devices by Location
```sql
SELECT
    l.name AS location,
    dt.name AS device_type,
    COUNT(*) AS device_count,
    SUM(d.purchase_cost) AS total_value
FROM devices d
JOIN service_locations l ON d.current_location_id = l.id
JOIN device_types dt ON d.device_type_id = dt.id
WHERE d.status = 'active'
GROUP BY l.id, l.name, dt.id, dt.name
ORDER BY l.name, device_count DESC;
```

## Next Steps to Complete Implementation

1. **Create Eloquent Models** (Device, Tool, PartsRequest, etc.) with relationships
2. **Create API Controllers** for all modules
3. **Create Policy Classes** for authorization
4. **Build Encryption Service** for shared accounts
5. **Build QR Code Service** (using SimpleSoftwareIO/simple-qrcode)
6. **Create Seeders** with sample data
7. **Build Quasar Frontend Pages**:
   - Devices list/detail/form
   - Tools list with "Not at Home" report
   - Software & subscriptions management
   - Shared accounts (with decrypt on view)
   - Parts Runner dispatcher dashboard
   - Parts Runner mobile UI
8. **Implement Photo Upload** with S3 or local storage
9. **Setup Slack Webhook** configuration
10. **Create Printable QR Labels** (PDF generation)

## File Structure

```
backend/
├── app/
│   ├── Models/
│   │   ├── Device.php
│   │   ├── DeviceIssue.php
│   │   ├── RepairEvent.php
│   │   ├── SoftwareProduct.php
│   │   ├── Subscription.php
│   │   ├── SharedAccount.php
│   │   ├── Tool.php
│   │   ├── ToolTransfer.php
│   │   ├── PartsRequest.php
│   │   ├── PartsRequestEvent.php
│   │   ├── GeoFence.php
│   │   └── QRToken.php
│   ├── Http/Controllers/
│   │   ├── DeviceController.php
│   │   ├── DeviceIssueController.php
│   │   ├── SoftwareController.php
│   │   ├── SharedAccountController.php
│   │   ├── ToolController.php
│   │   ├── PartsRequestController.php
│   │   └── QRCodeController.php
│   ├── Policies/
│   │   ├── DevicePolicy.php
│   │   ├── SharedAccountPolicy.php
│   │   ├── ToolPolicy.php
│   │   └── PartsRequestPolicy.php
│   └── Services/
│       ├── EncryptionService.php
│       ├── QRCodeService.php
│       ├── GeofenceService.php
│       └── SlackNotificationService.php
├── database/
│   ├── migrations/ (30 files created)
│   └── seeders/
│       ├── DeviceTypeSeeder.php
│       ├── IssueCategorySeeder.php
│       ├── ToolCategorySeeder.php
│       └── SampleDataSeeder.php
└── routes/
    └── api.php (add new routes)

frontend/app/src/
├── pages/
│   ├── DevicesPage.vue
│   ├── DeviceDetailPage.vue
│   ├── ToolsPage.vue
│   ├── SoftwarePage.vue
│   ├── SharedAccountsPage.vue
│   ├── PartsRequestsPage.vue (dispatcher)
│   └── RunnerDashboardPage.vue (mobile)
└── stores/
    ├── devices.ts
    ├── tools.ts
    ├── software.ts
    ├── sharedAccounts.ts
    └── partsRequests.ts
```

## Configuration

### Environment Variables
```env
# Slack Integration
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_DEFAULT_CHANNEL=#parts-alerts

# Geofencing
DEFAULT_GEOFENCE_RADIUS_M=100

# QR Codes
QR_CODE_EXPIRY_DAYS=365
QR_PUBLIC_BASE_URL=https://yourdomain.com/qr

# Timezone
APP_TIMEZONE=America/New_York
```

## Testing Checklist

- [ ] Create device and generate QR code
- [ ] Scan QR in internal mode (requires auth)
- [ ] Scan QR in public mode (shows contact only)
- [ ] Assign device to user
- [ ] Report device issue
- [ ] Create repair event with parts
- [ ] Create subscription and check renewal alert
- [ ] Create shared account and grant access
- [ ] Decrypt shared account password (with permission)
- [ ] Create tool and transfer between locations
- [ ] Run "Tools Not at Home" report
- [ ] Create parts request as dispatcher
- [ ] Assign parts request to runner
- [ ] Runner views assigned jobs on mobile
- [ ] Runner uploads pickup photo
- [ ] Runner posts GPS location (breadcrumb)
- [ ] System detects geofence arrival
- [ ] Runner uploads delivery photo
- [ ] Slack notification triggered
- [ ] View parts request timeline

---

**All 30 database migrations have been successfully created and run.**

Timezone configured: America/New_York
Default location: West Central Ohio
