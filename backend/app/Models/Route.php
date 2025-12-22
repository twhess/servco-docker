<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'start_location_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships

    /**
     * Starting location for this route
     */
    public function startLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'start_location_id');
    }

    /**
     * All stops on this route (ordered)
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }

    /**
     * Scheduled times for this route
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(RouteSchedule::class);
    }

    /**
     * Run instances (daily executions)
     */
    public function runInstances(): HasMany
    {
        return $this->hasMany(RunInstance::class);
    }

    /**
     * User who created this route
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this route
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    /**
     * Only active routes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Routes that serve a specific location
     */
    public function scopeHasLocation($query, int $locationId)
    {
        return $query->whereHas('stops', function ($q) use ($locationId) {
            $q->where('location_id', $locationId)
              ->orWhereHas('vendorClusterLocations', function ($vq) use ($locationId) {
                  $vq->where('vendor_location_id', $locationId);
              });
        });
    }

    /**
     * Routes with vendor cluster capability
     */
    public function scopeWithVendorCluster($query)
    {
        return $query->whereHas('stops', function ($q) {
            $q->where('stop_type', 'VENDOR_CLUSTER');
        });
    }

    // Helper Methods

    /**
     * Get stops in proper sequence order
     */
    public function getOrderedStops()
    {
        return $this->stops()->orderBy('stop_order')->get();
    }

    /**
     * Check if route includes a specific location
     */
    public function includesLocation(int $locationId): bool
    {
        return $this->stops()
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                  ->orWhereHas('vendorClusterLocations', function ($vq) use ($locationId) {
                      $vq->where('vendor_location_id', $locationId);
                  });
            })
            ->exists();
    }

    /**
     * Get next scheduled time after a given time
     */
    public function getNextScheduledTime(\Carbon\Carbon $after, ?\Carbon\Carbon $forDate = null): ?string
    {
        $query = $this->schedules()->where('is_active', true);

        if ($forDate) {
            // For specific date, return any active schedule
            return $query->orderBy('scheduled_time')->first()?->scheduled_time;
        }

        // For today, return next schedule after current time
        return $query->where('scheduled_time', '>', $after->format('H:i:s'))
                     ->orderBy('scheduled_time')
                     ->first()?->scheduled_time;
    }

    /**
     * Factory method to create a run instance
     */
    public function createRunInstance(\Carbon\Carbon $date, string $time): RunInstance
    {
        return $this->runInstances()->create([
            'scheduled_date' => $date->toDateString(),
            'scheduled_time' => $time,
            'status' => 'pending',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
