<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null; // Only created_at, no updates

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human-readable event name.
     */
    public function getEventNameAttribute(): string
    {
        return match($this->event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'soft_deleted' => 'Soft Deleted',
            'restored' => 'Restored',
            default => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }

    /**
     * Get a summary of changes.
     */
    public function getChangesSummaryAttribute(): string
    {
        if ($this->event === 'created') {
            return 'Record created';
        }

        if ($this->event === 'deleted' || $this->event === 'soft_deleted') {
            return 'Record deleted';
        }

        if ($this->event === 'restored') {
            return 'Record restored';
        }

        if (empty($this->changed_fields)) {
            return 'No changes';
        }

        $count = count($this->changed_fields);
        if ($count === 1) {
            return "Updated: {$this->changed_fields[0]}";
        }

        $fields = implode(', ', array_slice($this->changed_fields, 0, 3));
        if ($count > 3) {
            $remaining = $count - 3;
            return "Updated: {$fields} and {$remaining} more";
        }

        return "Updated: {$fields}";
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeForModel($query, string $modelClass, int $modelId)
    {
        return $query->where('auditable_type', $modelClass)
                    ->where('auditable_id', $modelId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeOfEvent($query, string $event)
    {
        return $query->where('event', $event);
    }
}
