<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequestAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_type_id',
        'from_status_id',
        'action_name',
        'to_status_id',
        'actor_role',
        'requires_note',
        'requires_photo',
        'display_label',
        'display_color',
        'display_icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_note' => 'boolean',
        'requires_photo' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships

    /**
     * The request type this action applies to
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(PartsRequestType::class, 'request_type_id');
    }

    /**
     * The status this action transitions from
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(PartsRequestStatus::class, 'from_status_id');
    }

    /**
     * The status this action transitions to
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(PartsRequestStatus::class, 'to_status_id');
    }

    // Scopes

    /**
     * Scope to active actions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to actions for a specific request type
     */
    public function scopeForRequestType($query, int $requestTypeId)
    {
        return $query->where('request_type_id', $requestTypeId);
    }

    /**
     * Scope to actions from a specific status
     */
    public function scopeFromStatus($query, int $statusId)
    {
        return $query->where('from_status_id', $statusId);
    }

    /**
     * Scope to actions for a specific actor role
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where('actor_role', $role);
    }

    /**
     * Scope ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Helper Methods

    /**
     * Check if note is required for this action
     */
    public function requiresNote(): bool
    {
        return $this->requires_note;
    }

    /**
     * Check if photo is required for this action
     */
    public function requiresPhoto(): bool
    {
        return $this->requires_photo;
    }
}
