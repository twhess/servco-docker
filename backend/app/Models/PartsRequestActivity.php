<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartsRequestActivity extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'parts_request_id',
        'from_status_id',
        'to_status_id',
        'actor_user_id',
        'notes',
        'stop_id',
        'run_id',
    ];

    protected $casts = [
        'parts_request_id' => 'integer',
        'from_status_id' => 'integer',
        'to_status_id' => 'integer',
        'actor_user_id' => 'integer',
        'stop_id' => 'integer',
        'run_id' => 'integer',
    ];

    /**
     * The parts request this activity belongs to.
     */
    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    /**
     * The previous status (before this activity).
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(PartsRequestStatus::class, 'from_status_id');
    }

    /**
     * The new status (after this activity).
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(PartsRequestStatus::class, 'to_status_id');
    }

    /**
     * The user who performed this activity.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * The stop where this activity occurred (if any).
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'stop_id');
    }

    /**
     * The run during which this activity occurred (if any).
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class, 'run_id');
    }

    /**
     * Photos associated with this activity.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PartsRequestPhoto::class, 'activity_id');
    }

    /**
     * Scope to get activities for a specific parts request.
     */
    public function scopeForRequest($query, int $partsRequestId)
    {
        return $query->where('parts_request_id', $partsRequestId);
    }

    /**
     * Scope to get activities by a specific actor.
     */
    public function scopeByActor($query, int $userId)
    {
        return $query->where('actor_user_id', $userId);
    }

    /**
     * Get a human-readable description of the status change.
     */
    public function getDescriptionAttribute(): string
    {
        $from = $this->fromStatus?->name ?? 'none';
        $to = $this->toStatus?->name ?? 'unknown';

        return "Status changed from {$from} to {$to}";
    }
}
