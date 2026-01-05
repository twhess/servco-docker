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
     * Routes that serve a specific location (shops, customer sites)
     */
    public function scopeHasLocation($query, int $locationId)
    {
        return $query->whereHas('stops', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        });
    }

    /**
     * Routes that serve a specific vendor
     */
    public function scopeHasVendor($query, int $vendorId)
    {
        return $query->whereHas('stops', function ($q) use ($vendorId) {
            $q->whereHas('vendorClusterLocations', function ($vq) use ($vendorId) {
                $vq->where('vendor_id', $vendorId);
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
            ->where('location_id', $locationId)
            ->exists();
    }

    /**
     * Check if route includes a specific vendor
     */
    public function includesVendor(int $vendorId): bool
    {
        return $this->stops()
            ->whereHas('vendorClusterLocations', function ($vq) use ($vendorId) {
                $vq->where('vendor_id', $vendorId);
            })
            ->exists();
    }

    /**
     * Get next scheduled time after a given time
     * Returns the time in H:i format, or null if no schedule available
     */
    public function getNextScheduledTime(\Carbon\Carbon $after, ?\Carbon\Carbon $forDate = null): ?string
    {
        $schedules = $this->schedules()->where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $targetDate = $forDate ?? $after->copy()->startOfDay();
        $currentTime = $after->format('H:i');

        // If we have a specific date, check if schedules run on that day
        // Otherwise use today first, then check next available day
        $maxDaysToCheck = 7;

        for ($dayOffset = 0; $dayOffset < $maxDaysToCheck; $dayOffset++) {
            $checkDate = $targetDate->copy()->addDays($dayOffset);

            foreach ($schedules->sortBy(fn($s) => $s->scheduled_time?->format('H:i') ?? '00:00') as $schedule) {
                // Check if schedule runs on this day
                if (!$schedule->runsOnDate($checkDate)) {
                    continue;
                }

                // Get the time from the schedule
                $scheduleTime = $schedule->scheduled_time?->format('H:i');
                if (!$scheduleTime) {
                    continue;
                }

                // For today (dayOffset = 0), only consider times after current time
                // For future days, any time works
                if ($dayOffset === 0 && !$forDate) {
                    if ($scheduleTime <= $currentTime) {
                        continue;
                    }
                }

                // Found a valid schedule
                return $scheduleTime;
            }
        }

        return null;
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
            'created_by' => auth()->id() ?? 1,
            'updated_by' => auth()->id() ?? 1,
        ]);
    }
}
