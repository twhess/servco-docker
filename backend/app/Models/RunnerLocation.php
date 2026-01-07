<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunnerLocation extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'user_id',
        'run_id',
        'lat',
        'lng',
        'accuracy_m',
        'recorded_at',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy_m' => 'integer',
        'recorded_at' => 'datetime',
    ];

    /**
     * The runner who reported this location.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The run this location is associated with (if any).
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class, 'run_id');
    }

    /**
     * Scope to get locations for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get locations for a specific run.
     */
    public function scopeForRun($query, int $runId)
    {
        return $query->where('run_id', $runId);
    }

    /**
     * Scope to get locations within a time range.
     */
    public function scopeRecordedBetween($query, $start, $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Get the most recent location for a user.
     */
    public static function latestForUser(int $userId): ?self
    {
        return static::forUser($userId)
            ->orderByDesc('recorded_at')
            ->first();
    }
}
