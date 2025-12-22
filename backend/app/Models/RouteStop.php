<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'stop_type',
        'location_id',
        'stop_order',
        'estimated_duration_minutes',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'estimated_duration_minutes' => 'integer',
    ];

    // Relationships

    /**
     * The route this stop belongs to
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * The service location (nullable for VENDOR_CLUSTER types)
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'location_id');
    }

    /**
     * Vendor locations in this cluster (only for VENDOR_CLUSTER type)
     */
    public function vendorClusterLocations(): HasMany
    {
        return $this->hasMany(VendorClusterLocation::class)->orderBy('location_order');
    }

    /**
     * Parts requests assigned to pick up at this stop
     */
    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'pickup_stop_id');
    }

    /**
     * Parts requests assigned to drop off at this stop
     */
    public function dropoffRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'dropoff_stop_id');
    }

    /**
     * Run stop actuals (historical tracking)
     */
    public function runStopActuals(): HasMany
    {
        return $this->hasMany(RunStopActual::class);
    }

    /**
     * User who created this stop
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this stop
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Helper Methods

    /**
     * Check if this is a vendor cluster stop
     */
    public function isVendorCluster(): bool
    {
        return $this->stop_type === 'VENDOR_CLUSTER';
    }

    /**
     * Get estimated arrival time for a run starting at given time
     */
    public function getEstimatedArrivalTime(\Carbon\Carbon $runStart): \Carbon\Carbon
    {
        // Get all previous stops in sequence
        $previousStops = $this->route->stops()
            ->where('stop_order', '<', $this->stop_order)
            ->get();

        // Sum up all estimated durations
        $totalMinutes = $previousStops->sum('estimated_duration_minutes');

        return $runStart->copy()->addMinutes($totalMinutes);
    }

    /**
     * Get display name for this stop
     */
    public function getDisplayName(): string
    {
        if ($this->isVendorCluster()) {
            $vendorCount = $this->vendorClusterLocations()->count();
            return "Vendor Cluster ({$vendorCount} vendors)";
        }

        return $this->location?->name ?? 'Unknown Location';
    }
}
