<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteGraphCache extends Model
{
    use HasFactory;

    protected $table = 'route_graph_cache';

    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'path_json',
        'hop_count',
        'estimated_duration_minutes',
        'requires_manual_routing',
        'cached_at',
    ];

    protected $casts = [
        'path_json' => 'array',
        'hop_count' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'requires_manual_routing' => 'boolean',
        'cached_at' => 'datetime',
    ];

    // Relationships

    /**
     * The origin location
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'from_location_id');
    }

    /**
     * The destination location
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'to_location_id');
    }

    // Helper Methods

    /**
     * Check if cache entry is stale (older than 1 hour)
     */
    public function isStale(): bool
    {
        return $this->cached_at->lt(Carbon::now()->subHour());
    }

    /**
     * Check if this is a direct route (single hop)
     */
    public function isDirect(): bool
    {
        return $this->hop_count === 1;
    }

    /**
     * Get the path as a human-readable string
     */
    public function getPathDescription(): string
    {
        if ($this->requires_manual_routing) {
            return 'Manual routing required';
        }

        $locations = [];
        foreach ($this->path_json as $hop) {
            $locations[] = $hop['location_name'] ?? "Location #{$hop['location_id']}";
        }

        return implode(' → ', $locations);
    }

    /**
     * Refresh the cached_at timestamp
     */
    public function refreshCache(): void
    {
        $this->update(['cached_at' => Carbon::now()]);
    }
}
