<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'scheduled_date',
        'scheduled_time',
        'route_schedule_id',
        'is_on_demand',
        'assigned_runner_user_id',
        'assigned_vehicle_location_id',
        'status',
        'actual_start_at',
        'actual_end_at',
        'current_stop_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'is_on_demand' => 'boolean',
    ];

    // Relationships

    /**
     * The route this run is executing
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * The schedule this run was created from
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RouteSchedule::class, 'route_schedule_id');
    }

    /**
     * Get the display name (Route Name - Schedule Name)
     */
    public function getDisplayNameAttribute(): string
    {
        $routeName = $this->route?->name ?? 'Unknown Route';
        $scheduleName = $this->schedule?->name;

        return $scheduleName ? "{$routeName} - {$scheduleName}" : $routeName;
    }

    /**
     * The assigned runner
     */
    public function assignedRunner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_runner_user_id');
    }

    /**
     * The assigned vehicle
     */
    public function assignedVehicle(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'assigned_vehicle_location_id');
    }

    /**
     * The current stop (for tracking)
     */
    public function currentStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'current_stop_id');
    }

    /**
     * Parts requests assigned to this run
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'run_instance_id');
    }

    /**
     * Run notes
     */
    public function notes(): HasMany
    {
        return $this->hasMany(RunNote::class);
    }

    /**
     * Actual stop times (historical tracking)
     */
    public function stopActuals(): HasMany
    {
        return $this->hasMany(RunStopActual::class);
    }

    /**
     * User who created this run
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this run
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    /**
     * Scope to runs for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', Carbon::today());
    }

    /**
     * Scope to runs for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scheduled_date', $date);
    }

    /**
     * Scope to upcoming runs (future dates)
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_date', '>=', Carbon::today());
    }

    /**
     * Scope to runs assigned to a specific runner
     */
    public function scopeForRunner($query, int $userId)
    {
        return $query->where('assigned_runner_user_id', $userId);
    }

    /**
     * Scope to runs with a specific status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to only show runs for today or past (hide future scheduled runs from runners)
     */
    public function scopeVisible($query)
    {
        return $query->where('scheduled_date', '<=', Carbon::today());
    }

    // Helper Methods

    /**
     * Mark run as started
     */
    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'actual_start_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark run as completed
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'actual_end_at' => Carbon::now(),
        ]);
    }

    /**
     * Get estimated current stop based on time and progress
     */
    public function getCurrentStopEstimate(): ?RouteStop
    {
        if (!$this->actual_start_at) {
            return $this->route->stops()->orderBy('stop_order')->first();
        }

        $elapsed = Carbon::now()->diffInMinutes($this->actual_start_at);
        $cumulativeTime = 0;

        foreach ($this->route->stops as $stop) {
            $cumulativeTime += $stop->estimated_duration_minutes;
            if ($cumulativeTime > $elapsed) {
                return $stop;
            }
        }

        return $this->route->stops()->orderBy('stop_order', 'desc')->first();
    }

    /**
     * Get requests for a specific stop (only those scheduled for this run's date)
     */
    public function getRequestsForStop(int $stopId)
    {
        return $this->requests()
            ->where(function ($query) use ($stopId) {
                $query->where('pickup_stop_id', $stopId)
                      ->orWhere('dropoff_stop_id', $stopId);
            })
            ->where(function ($query) {
                $query->whereNull('scheduled_for_date')
                      ->orWhereDate('scheduled_for_date', '<=', $this->scheduled_date);
            })
            ->get();
    }

    /**
     * Check if this run can be modified by the user
     */
    public function canBeModifiedBy(User $user): bool
    {
        // Dispatchers can always modify
        if ($user->hasPermission('runs.assign_runner')) {
            return true;
        }

        // Runners can only modify their own assigned runs
        if ($this->assigned_runner_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if this run is visible to runners (today or past)
     */
    public function isVisibleToRunner(): bool
    {
        return $this->scheduled_date->lte(Carbon::today());
    }

    /**
     * Check if this run has already passed a specific stop
     * Used to determine if a request can still be picked up on this run
     *
     * @param int $stopId The route_stop ID to check
     * @return bool True if the run has passed this stop
     */
    public function hasPassedStop(int $stopId): bool
    {
        // If run is completed, it has passed all stops
        if ($this->status === 'completed') {
            return true;
        }

        // If run hasn't started yet, it hasn't passed any stops
        if ($this->status === 'pending' || !$this->actual_start_at) {
            return false;
        }

        // Get the stop we're checking
        $targetStop = RouteStop::find($stopId);
        if (!$targetStop || $targetStop->route_id !== $this->route_id) {
            return true; // Stop doesn't exist or isn't on this route
        }

        // If we have a current_stop_id, use it for accurate tracking
        if ($this->current_stop_id) {
            $currentStop = RouteStop::find($this->current_stop_id);
            if ($currentStop) {
                // If current stop order is greater than target, we've passed it
                return $currentStop->stop_order > $targetStop->stop_order;
            }
        }

        // Check stop actuals for explicit departure record
        $departedFromStop = $this->stopActuals()
            ->where('route_stop_id', $stopId)
            ->whereNotNull('departed_at')
            ->exists();

        if ($departedFromStop) {
            return true;
        }

        // Fallback: estimate based on elapsed time
        return $this->hasPassedStopByTimeEstimate($targetStop);
    }

    /**
     * Estimate if run has passed a stop based on elapsed time
     */
    private function hasPassedStopByTimeEstimate(RouteStop $targetStop): bool
    {
        if (!$this->actual_start_at) {
            return false;
        }

        $elapsedMinutes = Carbon::now()->diffInMinutes($this->actual_start_at);

        // Get cumulative time to reach this stop
        $previousStops = $this->route->stops()
            ->where('stop_order', '<=', $targetStop->stop_order)
            ->get();

        $cumulativeMinutes = $previousStops->sum('estimated_duration_minutes');

        // Add a buffer - consider passed if we're past the estimated arrival by 5 min
        return $elapsedMinutes > ($cumulativeMinutes + 5);
    }

    /**
     * Check if this run has passed a specific location (by location_id)
     * Convenience method that finds the stop for a location
     *
     * @param int $locationId The service_location ID to check
     * @return bool True if the run has passed all stops at this location
     */
    public function hasPassedLocation(int $locationId): bool
    {
        // Find the stop for this location on this route
        $stop = $this->route->stops()
            ->where('location_id', $locationId)
            ->first();

        if (!$stop) {
            return true; // Location isn't on this route
        }

        return $this->hasPassedStop($stop->id);
    }

    /**
     * Check if this run is still available for new assignments
     * A run is available if it's pending OR in_progress but hasn't started moving yet
     */
    public function isAvailableForAssignment(): bool
    {
        // Completed or cancelled runs aren't available
        if (in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }

        // Pending runs are always available
        if ($this->status === 'pending') {
            return true;
        }

        // In-progress runs that haven't actually departed first stop are still available
        if ($this->status === 'in_progress') {
            $firstStop = $this->route->stops()->orderBy('stop_order')->first();
            if ($firstStop && !$this->hasPassedStop($firstStop->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the scheduled time for this run has passed
     */
    public function isScheduledTimePassed(): bool
    {
        if (!$this->scheduled_date || !$this->scheduled_time) {
            return false;
        }

        $scheduledDateTime = Carbon::parse(
            $this->scheduled_date->format('Y-m-d') . ' ' .
            ($this->scheduled_time instanceof \DateTime
                ? $this->scheduled_time->format('H:i')
                : $this->scheduled_time)
        );

        return Carbon::now()->greaterThan($scheduledDateTime);
    }
}
