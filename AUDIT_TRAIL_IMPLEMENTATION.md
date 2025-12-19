# Audit Trail Implementation

## Overview

All tables in the system now automatically track which user created and last updated each record via `created_by` and `updated_by` fields.

## What Was Implemented

### 1. HasAuditFields Trait

**Location**: `backend/app/Models/Traits/HasAuditFields.php`

Automatically populates audit fields on model create and update:

```php
use App\Models\Traits\HasAuditFields;

class YourModel extends Model
{
    use HasAuditFields;
}
```

**Features**:
- Auto-sets `created_by` and `updated_by` on create
- Auto-updates `updated_by` on every update
- Provides `creator()` and `updater()` relationships to access User models
- Works automatically with authenticated users via `auth()->id()`

### 2. Database Migration

**Migration**: `2025_12_19_180000_add_audit_fields_to_tables.php`

Added `created_by` and `updated_by` columns to all existing tables:
- Users
- Service Locations
- Parts Requests
- Roles
- Permissions
- All other entity tables

**Excluded**: Pivot tables (role_permission, user_role, etc.)

### 3. Models Updated

The following models now use the `HasAuditFields` trait:
- ✅ User
- ✅ ServiceLocation
- ✅ PartsRequest
- ✅ Role
- ✅ Permission

## Database Schema

Each table now has:

```sql
created_by BIGINT UNSIGNED NULL,
updated_by BIGINT UNSIGNED NULL,

FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
```

## Usage Examples

### Automatic Population

```php
// Creating a record (as authenticated user)
$role = Role::create([
    'name' => 'new_role',
    'display_name' => 'New Role',
]);

// created_by and updated_by are automatically set to auth()->id()
echo $role->created_by; // e.g., 1
echo $role->updated_by; // e.g., 1
```

### Updating Records

```php
// Updating a record
$role->update(['display_name' => 'Updated Role']);

// created_by stays the same
// updated_by is updated to current auth()->id()
echo $role->created_by; // e.g., 1 (unchanged)
echo $role->updated_by; // e.g., 2 (current user)
```

### Accessing Creator/Updater

```php
// Get the User who created this record
$creator = $role->creator; // Returns User model
echo $creator->username;

// Get the User who last updated this record
$updater = $role->updater; // Returns User model
echo $updater->username;
```

### In API Responses

You can include creator/updater information in API responses:

```php
// In Controller
$role = Role::with(['creator', 'updater'])->find($id);

return response()->json([
    'id' => $role->id,
    'name' => $role->name,
    'created_by' => $role->creator ? [
        'id' => $role->creator->id,
        'username' => $role->creator->username,
    ] : null,
    'updated_by' => $role->updater ? [
        'id' => $role->updater->id,
        'username' => $role->updater->username,
    ] : null,
]);
```

## Adding to New Models

When creating new models, always:

1. **Add the trait**:
```php
use App\Models\Traits\HasAuditFields;

class NewModel extends Model
{
    use HasAuditFields;
}
```

2. **Include fields in migration**:
```php
public function up()
{
    Schema::create('new_table', function (Blueprint $table) {
        $table->id();

        // Your fields here

        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();

        $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
    });
}
```

## Important Notes

### Nullable Fields
Both fields are **nullable** because:
- System-created records (seeders) may not have an authenticated user
- User deletion sets these to NULL (on delete cascade)
- Backwards compatibility with existing records

### Pivot Tables
Pivot tables (many-to-many) **do not** have audit fields as per the claude.md standard.

### Testing/Seeders
When creating records in tests or seeders without authentication:

```php
// Records will have NULL for created_by/updated_by
$role = Role::create([...]);

// Or manually set them
$role = Role::create([
    'name' => 'test',
    'created_by' => 1,
    'updated_by' => 1,
]);
```

### Frontend Display

To show who created/updated a record in the frontend:

```vue
<template>
  <div>
    <div>Created by: {{ request.creator?.username }}</div>
    <div>Last updated by: {{ request.updater?.username }}</div>
  </div>
</template>
```

## Verification

Test the implementation:

```bash
docker exec myapp-backend php artisan tinker

# In tinker:
$user = User::first();
auth()->login($user);

$role = Role::create([
    'name' => 'test',
    'display_name' => 'Test',
    'is_system' => false,
    'is_active' => true,
]);

// Check audit fields
echo $role->created_by; // Should show user ID
echo $role->creator->username; // Should show username

$role->update(['display_name' => 'Updated']);
echo $role->updated_by; // Should show user ID
```

## Benefits

1. **Full Audit Trail**: Track who created and modified every record
2. **Automatic**: No manual intervention needed
3. **Consistent**: All models follow same pattern
4. **Secure**: Cannot be bypassed (set via model events)
5. **Queryable**: Can filter/sort by creator or updater
6. **User-Friendly**: Easy to display in UI via relationships

## Future Considerations

Consider adding:
- `deleted_by` for soft deletes (track who deleted)
- Audit log table for complete change history
- Separate audit service for complex tracking needs
