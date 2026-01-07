<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\Auditable;
use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasAuditFields, Auditable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar',
        'employee_id',
        'first_name',
        'last_name',
        'preferred_name',
        'phone_number',
        'pin_code',
        'home_shop',
        'personal_email',
        'slack_id',
        'dext_email',
        'address',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip',
        'paytype',
        'active',
        'role',
        'home_location_id',
        'allowed_location_ids',
        // Runner PIN authentication fields
        'pin_hash',
        'pin_enabled',
        'pin_failed_attempts',
        'pin_locked_until',
        // Runner alert preferences
        'alert_on_leave_with_open',
        'alert_email_enabled',
        'alert_slack_enabled',
        'alert_popup_enabled',
        'alert_sms_enabled',
        'phone_e164',
        'slack_member_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'allowed_location_ids' => 'array',
            // Runner fields
            'pin_enabled' => 'boolean',
            'pin_failed_attempts' => 'integer',
            'pin_locked_until' => 'datetime',
            'alert_on_leave_with_open' => 'boolean',
            'alert_email_enabled' => 'boolean',
            'alert_slack_enabled' => 'boolean',
            'alert_popup_enabled' => 'boolean',
            'alert_sms_enabled' => 'boolean',
        ];
    }

    // Accessors

    /**
     * Get the user's full name.
     * Uses preferred_name in place of first_name if set, combined with last_name.
     * Falls back to username if no name fields are set.
     */
    public function getNameAttribute(): string
    {
        $firstName = $this->preferred_name ?: $this->first_name;

        if ($firstName || $this->last_name) {
            return trim("{$firstName} {$this->last_name}");
        }

        return $this->username ?? '';
    }

    // Relationships

    /**
     * User's home location
     */
    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'home_location_id');
    }

    // Permission helper methods

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is ops admin
     */
    public function isOpsAdmin(): bool
    {
        return $this->role === 'ops_admin';
    }

    /**
     * Check if user is dispatcher
     */
    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    /**
     * Check if user has admin-level access
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['super_admin', 'ops_admin']);
    }

    /**
     * Check if user has dispatch-level access
     */
    public function hasDispatchAccess(): bool
    {
        return in_array($this->role, ['super_admin', 'ops_admin', 'dispatcher']);
    }

    /**
     * Check if user can access a specific location
     */
    public function canAccessLocation($locationId): bool
    {
        // Super admin and ops admin can access all locations
        if ($this->hasAdminAccess()) {
            return true;
        }

        // Dispatcher can access all locations
        if ($this->isDispatcher()) {
            return true;
        }

        // Check home location
        if ($this->home_location_id == $locationId) {
            return true;
        }

        // Check allowed locations
        if ($this->allowed_location_ids && in_array($locationId, $this->allowed_location_ids)) {
            return true;
        }

        return false;
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * Sync roles to this user.
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
    }

    /**
     * Get all permission names from all user's roles.
     */
    public function getAllPermissions(): array
    {
        $permissions = [];

        foreach ($this->roles as $role) {
            $permissions = array_merge($permissions, $role->getPermissionNames());
        }

        return array_unique($permissions);
    }

    /**
     * Get user's abilities/permissions for frontend
     * This method now checks both the legacy 'role' field and the new roles relationship
     */
    public function getAbilities(): array
    {
        $abilities = [
            'service_locations.create' => false,
            'service_locations.update_details' => false,
            'service_locations.update_contacts' => false,
            'service_locations.assign_user' => false,
            'service_locations.update_status' => false,
            'service_locations.record_position' => false,
            'service_locations.delete' => false,
            'service_locations.view_all' => false,
            'parts_requests.create' => true,  // All users can create
            'parts_requests.view_all' => true,  // All users can view all
            'parts_requests.assign' => false,
            'parts_requests.update_status' => false,
            'parts_requests.upload_photo' => false,
            'users.create' => false,
            'users.view_all' => false,
            'users.update' => false,
            'users.delete' => false,
            'users.assign_roles' => false,
            'roles.create' => false,
            'roles.view_all' => false,
            'roles.update' => false,
            'roles.delete' => false,
        ];

        // If user has new roles system, use that
        if ($this->roles()->count() > 0) {
            $permissions = $this->getAllPermissions();
            foreach ($permissions as $permission) {
                $abilities[$permission] = true;
            }
            return $abilities;
        }

        // Otherwise fall back to legacy role field
        switch ($this->role) {
            case 'super_admin':
                // Can do everything
                return array_fill_keys(array_keys($abilities), true);

            case 'ops_admin':
                $abilities['service_locations.create'] = true;
                $abilities['service_locations.update_details'] = true;
                $abilities['service_locations.update_contacts'] = true;
                $abilities['service_locations.assign_user'] = true;
                $abilities['service_locations.update_status'] = true;
                $abilities['service_locations.delete'] = true;
                $abilities['service_locations.view_all'] = true;
                $abilities['parts_requests.assign'] = true;
                $abilities['parts_requests.update_status'] = true;
                $abilities['users.view_all'] = true;
                $abilities['users.create'] = true;
                $abilities['users.update'] = true;
                $abilities['users.assign_roles'] = true;
                break;

            case 'dispatcher':
                $abilities['service_locations.assign_user'] = true;
                $abilities['service_locations.update_status'] = true;
                $abilities['service_locations.record_position'] = true;
                $abilities['service_locations.view_all'] = true;
                $abilities['parts_requests.assign'] = true;
                $abilities['users.view_all'] = true;
                break;

            case 'shop_manager':
                $abilities['service_locations.update_details'] = true;
                $abilities['service_locations.update_contacts'] = true;
                break;

            case 'parts_manager':
                $abilities['service_locations.update_contacts'] = true;
                $abilities['service_locations.update_status'] = true;
                break;

            case 'runner_driver':
                $abilities['service_locations.update_status'] = true;
                $abilities['service_locations.record_position'] = true;
                $abilities['parts_requests.update_status'] = true;
                $abilities['parts_requests.upload_photo'] = true;
                break;

            case 'technician_mobile':
                $abilities['service_locations.update_status'] = true;
                $abilities['service_locations.record_position'] = true;
                break;
        }

        return $abilities;
    }

    // ==========================================
    // PIN Authentication Methods
    // ==========================================

    /**
     * Set the PIN for this user.
     * Uses the existing pin_code field.
     */
    public function setPin(string $pin): void
    {
        $this->pin_code = $pin;
        $this->pin_enabled = true;
        $this->pin_failed_attempts = 0;
        $this->pin_locked_until = null;
        $this->save();
    }

    /**
     * Verify a PIN against the stored pin_code.
     */
    public function verifyPin(string $pin): bool
    {
        if (!$this->pin_code || !$this->pin_enabled) {
            return false;
        }

        return $this->pin_code === $pin;
    }

    /**
     * Check if the user's PIN is currently locked.
     */
    public function isPinLocked(): bool
    {
        if (!$this->pin_locked_until) {
            return false;
        }

        return $this->pin_locked_until->isFuture();
    }

    /**
     * Get remaining lockout time in seconds.
     */
    public function getPinLockoutSecondsRemaining(): int
    {
        if (!$this->isPinLocked()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->pin_locked_until, false);
    }

    /**
     * Increment failed PIN attempts and lock if threshold exceeded.
     */
    public function incrementFailedPinAttempts(): void
    {
        $this->pin_failed_attempts++;

        // Lock after 5 failed attempts
        if ($this->pin_failed_attempts >= 5) {
            $this->pin_locked_until = now()->addMinutes(10);
        }

        $this->save();
    }

    /**
     * Reset PIN failed attempts (called after successful login).
     */
    public function resetPinAttempts(): void
    {
        $this->pin_failed_attempts = 0;
        $this->pin_locked_until = null;
        $this->save();
    }

    /**
     * Disable PIN authentication for this user.
     */
    public function disablePin(): void
    {
        $this->pin_code = null;
        $this->pin_enabled = false;
        $this->pin_failed_attempts = 0;
        $this->pin_locked_until = null;
        $this->save();
    }

    /**
     * Check if user can use PIN authentication.
     */
    public function canUsePinAuth(): bool
    {
        return $this->pin_enabled && $this->pin_code && !$this->isPinLocked();
    }

    /**
     * Find a user by their PIN code.
     * Direct lookup since pin_code is stored in plaintext.
     */
    public static function findByPin(string $pin): ?self
    {
        return static::where('pin_enabled', true)
            ->where('pin_code', $pin)
            ->first();
    }

    /**
     * Check if user is a runner (has runner_driver role).
     */
    public function isRunner(): bool
    {
        return $this->role === 'runner_driver' || $this->hasRole('runner_driver');
    }

    /**
     * Get runner locations (GPS breadcrumbs).
     */
    public function runnerLocations()
    {
        return $this->hasMany(RunnerLocation::class);
    }

    /**
     * Get assigned run instances.
     */
    public function assignedRuns()
    {
        return $this->hasMany(RunInstance::class, 'assigned_runner_user_id');
    }
}
