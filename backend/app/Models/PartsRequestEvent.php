<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequestEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'parts_request_id',
        'event_type',
        'event_at',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'event_at' => 'datetime',
    ];

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper method to get event display name
    public function getEventDisplayName(): string
    {
        return match($this->event_type) {
            'created' => 'Created',
            'assigned' => 'Assigned to Runner',
            'unassigned' => 'Unassigned',
            'started' => 'Started',
            'arrived_pickup' => 'Arrived at Pickup',
            'picked_up' => 'Picked Up',
            'departed_pickup' => 'Departed Pickup',
            'arrived_dropoff' => 'Arrived at Dropoff',
            'delivered' => 'Delivered',
            'canceled' => 'Canceled',
            'problem_reported' => 'Problem Reported',
            'note_added' => 'Note Added',
            'status_changed' => 'Status Changed',
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }
}
