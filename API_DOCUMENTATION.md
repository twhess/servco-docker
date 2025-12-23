# Parts Runner System - API Documentation

## Base URL

```
Production: https://api.your-domain.com/api
Development: http://localhost:8000/api
```

## Authentication

All API endpoints require authentication using Laravel Sanctum tokens.

**Headers:**

```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## Routes API

### List Routes

Get all route definitions.

```http
GET /routes
```

**Query Parameters:**
- `is_active` (optional): Filter by active status (true/false)
- `start_location_id` (optional): Filter by start location

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "North Loop",
      "code": "NORTH-01",
      "description": "Daily route covering northern vendor locations",
      "start_location_id": 1,
      "is_active": true,
      "start_location": {
        "id": 1,
        "name": "Main Shop",
        "location_type": "fixed_shop"
      },
      "stops": [...],
      "schedules": [...],
      "created_at": "2025-01-15T00:00:00.000000Z",
      "updated_at": "2025-01-15T00:00:00.000000Z"
    }
  ]
}
```

---

### Get Route

Get a single route with stops and schedules.

```http
GET /routes/{id}
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "name": "North Loop",
    "code": "NORTH-01",
    "description": "Daily route covering northern vendor locations",
    "start_location_id": 1,
    "is_active": true,
    "stops": [
      {
        "id": 1,
        "route_id": 1,
        "stop_type": "VENDOR_CLUSTER",
        "location_id": null,
        "stop_order": 1,
        "estimated_duration_minutes": 30,
        "notes": "Northern vendors",
        "vendor_locations": [...]
      }
    ],
    "schedules": [
      {
        "id": 1,
        "route_id": 1,
        "scheduled_time": "08:00:00"
      }
    ]
  }
}
```

---

### Create Route

Create a new route definition.

```http
POST /routes
```

**Request Body:**

```json
{
  "name": "North Loop",
  "code": "NORTH-01",
  "description": "Daily route covering northern vendor locations",
  "start_location_id": 1,
  "is_active": true
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `code`: required, string, max:50, unique
- `description`: optional, string
- `start_location_id`: required, exists:service_locations,id
- `is_active`: optional, boolean

**Response:** `201 Created`

```json
{
  "data": {
    "id": 1,
    "name": "North Loop",
    "code": "NORTH-01",
    ...
  }
}
```

---

### Update Route

Update an existing route.

```http
PUT /routes/{id}
```

**Request Body:**

```json
{
  "name": "Updated Route Name",
  "code": "NORTH-01",
  "description": "Updated description",
  "start_location_id": 1,
  "is_active": true
}
```

**Response:** `200 OK`

---

### Delete Route (Deactivate)

Deactivates a route (soft delete - sets is_active to false).

```http
DELETE /routes/{id}
```

**Response:** `200 OK`

```json
{
  "message": "Route deactivated successfully"
}
```

---

### Add Stop to Route

Add a new stop to a route.

```http
POST /routes/{id}/stops
```

**Request Body:**

```json
{
  "stop_type": "SHOP",
  "location_id": 2,
  "stop_order": 1,
  "estimated_duration_minutes": 30,
  "notes": "First stop"
}
```

**Stop Types:**
- `SHOP`: Fixed shop location
- `VENDOR_CLUSTER`: Group of vendor locations
- `CUSTOMER`: Customer site
- `AD_HOC`: Ad-hoc stop

**Response:** `201 Created`

---

### Reorder Stops

Reorder stops on a route.

```http
POST /routes/{id}/stops/reorder
```

**Request Body:**

```json
{
  "stops": [
    { "id": 1, "order": 2 },
    { "id": 2, "order": 1 }
  ]
}
```

**Response:** `200 OK`

---

### Add Schedule to Route

Add a scheduled time for daily run creation.

```http
POST /routes/{id}/schedules
```

**Request Body:**

```json
{
  "scheduled_time": "08:00:00"
}
```

**Response:** `201 Created`

---

### Remove Schedule

Remove a scheduled time from a route.

```http
DELETE /routes/{routeId}/schedules/{scheduleId}
```

**Response:** `200 OK`

---

### Find Path Between Locations

Find the optimal route between two locations using the graph cache.

```http
GET /routes/find-path?from={fromLocationId}&to={toLocationId}
```

**Query Parameters:**
- `from`: required, integer, origin location ID
- `to`: required, integer, destination location ID

**Response:**

```json
{
  "path": [
    {
      "location_id": 1,
      "route_id": 1,
      "stop_order": 0
    },
    {
      "location_id": 2,
      "route_id": 1,
      "stop_order": 1
    }
  ],
  "hop_count": 1
}
```

**If no path exists:**

```json
{
  "path": null,
  "message": "No route found between locations"
}
```

---

### Rebuild Route Cache

Rebuild the route graph pathfinding cache.

```http
POST /routes/rebuild-cache
```

**Response:**

```json
{
  "message": "Route graph cache rebuilt successfully"
}
```

---

## Run Instances API

### List Run Instances

Get all run instances.

```http
GET /runs
```

**Query Parameters:**
- `date` (optional): Filter by scheduled date (YYYY-MM-DD)
- `status` (optional): Filter by status (pending, in_progress, completed, canceled)
- `runner_id` (optional): Filter by runner
- `route_id` (optional): Filter by route

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "route_id": 1,
      "runner_id": 5,
      "scheduled_date": "2025-01-15",
      "scheduled_time": "08:00:00",
      "status": "pending",
      "actual_start_at": null,
      "actual_end_at": null,
      "route": {...},
      "runner": {...},
      "stop_actuals": [...],
      "created_at": "2025-01-15T00:00:00.000000Z"
    }
  ]
}
```

---

### Get My Runs

Get runs assigned to authenticated runner.

```http
GET /runs/my-runs?date={date}
```

**Query Parameters:**
- `date`: required, YYYY-MM-DD format

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "route": {...},
      "status": "pending",
      "scheduled_time": "08:00:00",
      "stop_actuals": [...]
    }
  ]
}
```

---

### Create Run Instance

Manually create a run instance.

```http
POST /runs
```

**Request Body:**

```json
{
  "route_id": 1,
  "runner_id": 5,
  "scheduled_date": "2025-01-15",
  "scheduled_time": "08:00:00"
}
```

**Response:** `201 Created`

---

### Start Run

Mark a run as started.

```http
POST /runs/{id}/start
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "status": "in_progress",
    "actual_start_at": "2025-01-15T08:05:32.000000Z"
  }
}
```

---

### Complete Run

Mark a run as completed.

```http
POST /runs/{id}/complete
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "status": "completed",
    "actual_end_at": "2025-01-15T12:34:56.000000Z"
  }
}
```

---

### Arrive at Stop

Record arrival at a stop.

```http
POST /runs/{runId}/stops/{stopId}/arrive
```

**Response:**

```json
{
  "data": {
    "run_instance_id": 1,
    "route_stop_id": 1,
    "arrived_at": "2025-01-15T08:15:00.000000Z",
    "departed_at": null,
    "tasks_completed": 0,
    "tasks_total": 3
  }
}
```

---

### Depart from Stop

Record departure from a stop.

```http
POST /runs/{runId}/stops/{stopId}/depart
```

**Request Body (optional):**

```json
{
  "force": false
}
```

**If tasks incomplete and force=false:**

```json
{
  "warning": true,
  "message": "You have 2 incomplete tasks. Continue anyway?",
  "incomplete_tasks": 2
}
```

**Response (success):**

```json
{
  "data": {
    "departed_at": "2025-01-15T08:45:00.000000Z",
    "tasks_completed": 3,
    "tasks_total": 3
  }
}
```

---

### Add Run Note

Add a note to a run instance.

```http
POST /runs/{id}/notes
```

**Request Body:**

```json
{
  "note": "Traffic delay on Main Street"
}
```

**Response:** `201 Created`

---

### Reassign Run

Reassign a run to a different runner.

```http
POST /runs/{id}/reassign
```

**Request Body:**

```json
{
  "runner_id": 6
}
```

**Response:** `200 OK`

---

## Parts Requests API (Enhanced)

### Assign Request to Run

Manually assign a request to a specific run.

```http
POST /parts-requests/{id}/assign-to-run
```

**Request Body:**

```json
{
  "run_instance_id": 1,
  "pickup_stop_id": 1,
  "dropoff_stop_id": 2
}
```

**Response:** `200 OK`

---

### Override Run Assignment

Override automatic run assignment.

```http
POST /parts-requests/{id}/override-run
```

**Request Body:**

```json
{
  "override_run_instance_id": 5,
  "override_reason": "Customer requested specific time"
}
```

**Response:** `200 OK`

---

### Execute Action

Execute a workflow action on a request.

```http
POST /parts-requests/{id}/execute-action
```

**Request Body (multipart/form-data):**

```
action_name: "picked_up"
note: "Picked up from vendor"
photo: [File]
lat: 40.7128
lng: -74.0060
```

**Response:**

```json
{
  "message": "Action executed successfully",
  "new_status": "picked_up"
}
```

---

### Fetch Feed

Get dispatcher dashboard feed of requests.

```http
GET /parts-requests/feed
```

**Query Parameters:**
- `status` (optional): Filter by status
- `urgency` (optional): Filter by urgency
- `type` (optional): Filter by request type
- `scheduled_after` (optional): Date filter

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "reference_number": "PR-2025-001",
      "status": {...},
      "urgency": {...},
      "run_instance": {...},
      "pickup_stop": {...},
      "dropoff_stop": {...}
    }
  ]
}
```

---

### Schedule Request

Schedule a request for a future date.

```http
POST /parts-requests/{id}/schedule
```

**Request Body:**

```json
{
  "scheduled_for_date": "2025-01-20"
}
```

**Response:** `200 OK`

---

### Bulk Schedule

Schedule multiple requests at once.

```http
POST /parts-requests/bulk-schedule
```

**Request Body:**

```json
{
  "request_ids": [1, 2, 3, 4],
  "scheduled_for_date": "2025-01-20"
}
```

**Response:**

```json
{
  "message": "4 requests scheduled successfully"
}
```

---

## Inventory Items API

### Scan QR Code

Scan an item QR code and retrieve item details.

```http
POST /items/scan
```

**Request Body:**

```json
{
  "qr_code": "ITEM-2025-001"
}
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "qr_code": "ITEM-2025-001",
    "description": "Brake Pads - Front",
    "current_location_id": 1,
    "current_location": {...}
  }
}
```

---

### Link Item to Request

Link an inventory item to a parts request.

```http
POST /parts-requests/{id}/link-item
```

**Request Body:**

```json
{
  "item_id": 1
}
```

**Response:** `200 OK`

---

### Fetch Movement History

Get movement history for an item.

```http
GET /items/{id}/movements
```

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "item_id": 1,
      "from_location_id": 1,
      "to_location_id": 2,
      "moved_at": "2025-01-15T08:30:00.000000Z",
      "parts_request_id": 5,
      "moved_by": {...}
    }
  ]
}
```

---

## Error Responses

All endpoints return consistent error responses:

**Validation Error (422):**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "code": ["The code has already been taken."]
  }
}
```

**Not Found (404):**

```json
{
  "message": "Route not found"
}
```

**Unauthorized (401):**

```json
{
  "message": "Unauthenticated."
}
```

**Forbidden (403):**

```json
{
  "message": "This action is unauthorized."
}
```

**Server Error (500):**

```json
{
  "message": "Server Error",
  "error": "Detailed error message"
}
```

---

## Rate Limiting

API requests are rate-limited to prevent abuse:

- **Authenticated requests**: 60 requests per minute
- **Unauthenticated requests**: 10 requests per minute

**Rate Limit Headers:**

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1642234567
```

---

## Pagination

List endpoints support pagination:

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

**Response:**

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 73
  },
  "links": {
    "first": "http://api.example.com/routes?page=1",
    "last": "http://api.example.com/routes?page=5",
    "prev": null,
    "next": "http://api.example.com/routes?page=2"
  }
}
```

---

## Webhooks (Future)

Webhook support for real-time updates is planned for future releases.

---

## Support

For API support, contact the development team or submit an issue at:
https://github.com/your-org/servcoapp/issues
