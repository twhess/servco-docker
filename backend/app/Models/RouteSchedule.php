<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RouteSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'scheduled_time',
        'name',
        'is_active',
        'days_of_week',
        'schedule_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'days_of_week' => 'array',
    ];

    // Relationships

    /**
     * The route this schedule belongs to
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Run instances created from this schedule
     */
    public function runInstances(): HasMany
    {
        return $this->hasMany(RunInstance::class, 'route_schedule_id');
    }

    /**
     * Get the display name (combines route name and schedule name)
     */
    public function getDisplayNameAttribute(): string
    {
        $routeName = $this->route?->name ?? 'Unknown Route';
        return $this->name ? "{$routeName} - {$this->name}" : $routeName;
    }

    /**
     * User who created this schedule
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this schedule
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    /**
     * Scope to only active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to schedules for a specific time
     */
    public function scopeForTime($query, string $time)
    {
        return $query->where('scheduled_time', $time);
    }

    /**
     * Scope to only fixed schedules
     */
    public function scopeFixed($query)
    {
        return $query->where('schedule_type', self::TYPE_FIXED);
    }

    /**
     * Scope to only on-demand schedules
     */
    public function scopeOnDemand($query)
    {
        return $query->where('schedule_type', self::TYPE_ON_DEMAND);
    }

    // Schedule Type Constants

    public const TYPE_FIXED = 'fixed';
    public const TYPE_ON_DEMAND = 'on_demand';

    /**
     * Check if this is a fixed schedule (runs on specific days/times)
     */
    public function isFixed(): bool
    {
        return $this->schedule_type === self::TYPE_FIXED;
    }

    /**
     * Check if this is an on-demand schedule (created manually as needed)
     */
    public function isOnDemand(): bool
    {
        return $this->schedule_type === self::TYPE_ON_DEMAND;
    }

    // Day of Week Helpers

    /**
     * Day of week constants for clarity
     */
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    /**
     * Check if this schedule runs on a specific date
     */
    public function runsOnDate(Carbon $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $dayOfWeek = (int) $date->dayOfWeek; // 0=Sunday through 6=Saturday
        return in_array($dayOfWeek, $this->days_of_week ?? [1, 2, 3, 4, 5]);
    }

    /**
     * Check if this schedule runs on a specific day of week
     */
    public function runsOnDay(int $dayOfWeek): bool
    {
        return in_array($dayOfWeek, $this->days_of_week ?? [1, 2, 3, 4, 5]);
    }

    /**
     * Get human-readable days description
     */
    public function getDaysDescriptionAttribute(): string
    {
        $days = $this->days_of_week ?? [1, 2, 3, 4, 5];
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        // Check for common patterns
        if ($days == [1, 2, 3, 4, 5]) {
            return 'Weekdays';
        }
        if ($days == [0, 6]) {
            return 'Weekends';
        }
        if ($days == [0, 1, 2, 3, 4, 5, 6]) {
            return 'Every day';
        }

        // Otherwise list the days
        $names = array_map(fn($d) => $dayNames[$d], $days);
        return implode(', ', $names);
    }
}
