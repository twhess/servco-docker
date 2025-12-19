<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoFence extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_type',
        'entity_id',
        'center_lat',
        'center_lng',
        'radius_m',
    ];

    protected $casts = [
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'radius_m' => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(GeoFenceEvent::class);
    }

    /**
     * Check if coordinates are within this geofence
     * Uses Haversine formula for distance calculation
     */
    public function contains(float $lat, float $lng): bool
    {
        $distance = $this->calculateDistance($lat, $lng);
        return $distance <= $this->radius_m;
    }

    /**
     * Calculate distance from geofence center to given coordinates in meters
     */
    public function calculateDistance(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // Earth's radius in meters

        $latFrom = deg2rad((float)$this->center_lat);
        $lngFrom = deg2rad((float)$this->center_lng);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
