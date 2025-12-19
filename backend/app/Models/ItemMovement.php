<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'from_location_id',
        'to_location_id',
        'moved_at',
        'moved_by_user_id',
        'request_id',
        'photo_id',
        'notes',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    // Relationships

    /**
     * The item that was moved
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Location the item was moved from
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'from_location_id');
    }

    /**
     * Location the item was moved to
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'to_location_id');
    }

    /**
     * User who performed the move
     */
    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by_user_id');
    }

    // Scopes

    /**
     * Scope for movements from a specific location
     */
    public function scopeFromLocation($query, $locationId)
    {
        return $query->where('from_location_id', $locationId);
    }

    /**
     * Scope for movements to a specific location
     */
    public function scopeToLocation($query, $locationId)
    {
        return $query->where('to_location_id', $locationId);
    }

    /**
     * Scope for movements between date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('moved_at', [$startDate, $endDate]);
    }

    /**
     * Scope for movements by specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('moved_by_user_id', $userId);
    }

    // Helper Methods

    /**
     * Get formatted movement description
     */
    public function getMovementDescription(): string
    {
        $from = $this->fromLocation ? $this->fromLocation->name : 'Unknown';
        $to = $this->toLocation->name;
        return "Moved from {$from} to {$to}";
    }

    /**
     * Check if this was an initial pickup (no from_location)
     */
    public function isInitialPickup(): bool
    {
        return $this->from_location_id === null;
    }
}
