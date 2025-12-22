<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'scheduled_time',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime:H:i',
        'is_active' => 'boolean',
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
        return $this->hasMany(RunInstance::class, 'scheduled_time', 'scheduled_time')
                    ->where('route_id', $this->route_id);
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
}
