<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'lat',
        'lng',
        'accuracy_meters',
        'speed',
        'heading',
        'recorded_at',
        'source',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy_meters' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /**
     * The location this position belongs to
     */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    /**
     * Get formatted coordinates
     */
    public function getCoordinates(): string
    {
        return "{$this->lat}, {$this->lng}";
    }

    /**
     * Check if position has accuracy data
     */
    public function hasAccuracy(): bool
    {
        return $this->accuracy_meters !== null;
    }

    /**
     * Get compass direction from heading
     */
    public function getCompassDirection(): ?string
    {
        if ($this->heading === null) {
            return null;
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = round($this->heading / 45) % 8;
        return $directions[$index];
    }
}
