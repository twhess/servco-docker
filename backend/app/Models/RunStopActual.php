<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunStopActual extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_instance_id',
        'route_stop_id',
        'arrived_at',
        'departed_at',
        'tasks_completed',
        'tasks_total',
        'notes',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'departed_at' => 'datetime',
        'tasks_completed' => 'integer',
        'tasks_total' => 'integer',
    ];

    // Relationships

    /**
     * The run instance this actual belongs to
     */
    public function runInstance(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class);
    }

    /**
     * The route stop
     */
    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    // Helper Methods

    /**
     * Calculate duration at this stop in minutes
     */
    public function getDurationMinutes(): ?int
    {
        if (!$this->arrived_at || !$this->departed_at) {
            return null;
        }

        return $this->departed_at->diffInMinutes($this->arrived_at);
    }

    /**
     * Check if all tasks were completed at this stop
     */
    public function allTasksCompleted(): bool
    {
        return $this->tasks_completed >= $this->tasks_total;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): float
    {
        if ($this->tasks_total === 0) {
            return 100.0;
        }

        return round(($this->tasks_completed / $this->tasks_total) * 100, 2);
    }
}
