<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceLocation extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields, Auditable;

    protected $fillable = [
        'name',
        'code',
        'location_type',
        'status',
        'is_active',
        'timezone',
        'notes',
        'text_color',
        'background_color',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'vehicle_asset_id',
        'home_base_location_id',
        'assigned_user_id',
        'last_known_lat',
        'last_known_lng',
        'last_known_at',
        'is_dispatchable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'last_known_lat' => 'decimal:7',
        'last_known_lng' => 'decimal:7',
        'last_known_at' => 'datetime',
        'is_dispatchable' => 'boolean',
    ];

    // Relationships

    /**
     * Home base location for mobile units
     */
    public function homeBase(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'home_base_location_id');
    }

    /**
     * Mobile units that use this location as their home base
     */
    public function mobileUnits(): HasMany
    {
        return $this->hasMany(ServiceLocation::class, 'home_base_location_id');
    }

    /**
     * User assigned to this mobile location
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Phone numbers for this location
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ServiceLocationPhone::class);
    }

    /**
     * Primary phone number
     */
    public function primaryPhone(): HasMany
    {
        return $this->phones()->where('is_primary', true);
    }

    /**
     * Email addresses for this location
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ServiceLocationEmail::class);
    }

    /**
     * Primary email address
     */
    public function primaryEmail(): HasMany
    {
        return $this->emails()->where('is_primary', true);
    }

    /**
     * GPS position history for mobile locations
     */
    public function positions(): HasMany
    {
        return $this->hasMany(LocationPosition::class);
    }

    /**
     * Latest GPS position
     */
    public function latestPosition(): HasMany
    {
        return $this->positions()->latest('recorded_at')->limit(1);
    }

    /**
     * Items currently at this location
     */
    public function currentItems(): HasMany
    {
        return $this->hasMany(Item::class, 'current_location_id');
    }

    /**
     * Item movements from this location
     */
    public function itemMovementsFrom(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'from_location_id');
    }

    /**
     * Item movements to this location
     */
    public function itemMovementsTo(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'to_location_id');
    }

    // Scopes

    /**
     * Scope for active locations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for fixed shops
     */
    public function scopeFixedShops($query)
    {
        return $query->where('location_type', 'fixed_shop');
    }

    /**
     * Scope for mobile service trucks
     */
    public function scopeMobileTrucks($query)
    {
        return $query->where('location_type', 'mobile_service_truck');
    }

    /**
     * Scope for parts runner vehicles
     */
    public function scopePartsRunners($query)
    {
        return $query->where('location_type', 'parts_runner_vehicle');
    }

    /**
     * Scope for all mobile units (trucks + runners)
     */
    public function scopeMobileUnits($query)
    {
        return $query->whereIn('location_type', ['mobile_service_truck', 'parts_runner_vehicle']);
    }

    /**
     * Scope for dispatchable locations
     */
    public function scopeDispatchable($query)
    {
        return $query->where('is_dispatchable', true)->where('is_active', true);
    }

    /**
     * Scope for available status
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Helper Methods

    /**
     * Check if this is a fixed location
     */
    public function isFixed(): bool
    {
        return in_array($this->location_type, ['fixed_shop', 'vendor', 'customer_site']);
    }

    /**
     * Check if this is a mobile location
     */
    public function isMobile(): bool
    {
        return in_array($this->location_type, ['mobile_service_truck', 'parts_runner_vehicle']);
    }

    /**
     * Get full address string
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}
