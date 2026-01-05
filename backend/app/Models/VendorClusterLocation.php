<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorClusterLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_stop_id',
        'vendor_id',
        'location_order',
        'is_optional',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'location_order' => 'integer',
        'is_optional' => 'boolean',
    ];

    // Relationships

    /**
     * The route stop (vendor cluster) this vendor belongs to
     */
    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    /**
     * The vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * User who created this association
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this association
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Helper Methods

    /**
     * Check if this vendor location can be optimized (location_order = 0)
     */
    public function isOptimizable(): bool
    {
        return $this->location_order === 0;
    }

    /**
     * Check if this vendor can be skipped when no pickups assigned
     */
    public function canBeSkipped(): bool
    {
        return $this->is_optional;
    }
}
