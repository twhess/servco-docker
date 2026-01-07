<?php

namespace App\Services;

use App\Models\GeoFence;
use App\Models\GeoFenceEvent;
use App\Models\PartsRequest;
use App\Models\PartsRequestEvent;
use App\Models\RouteStop;
use App\Models\RunInstance;
use App\Models\ServiceLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GeofenceService
{
    /**
     * Earth radius in meters for Haversine formula.
     */
    private const EARTH_RADIUS_M = 6371000;
    /**
     * Check if runner has entered/exited any relevant geofences
     */
    public function checkGeofences(PartsRequest $partsRequest, float $lat, float $lng): void
    {
        $runner = $partsRequest->assignedRunner;
        if (!$runner) {
            return;
        }

        // Get relevant geofences for this request
        $geofences = $this->getRelevantGeofences($partsRequest);

        foreach ($geofences as $geofence) {
            $isInside = $geofence->contains($lat, $lng);
            $cacheKey = "geofence_state_{$geofence->id}_runner_{$runner->id}_request_{$partsRequest->id}";
            $wasInside = Cache::get($cacheKey, false);

            if ($isInside && !$wasInside) {
                // Runner just entered geofence
                $this->handleGeofenceEnter($partsRequest, $geofence, $lat, $lng);
                Cache::put($cacheKey, true, now()->addHours(24));
            } elseif (!$isInside && $wasInside) {
                // Runner just exited geofence
                $this->handleGeofenceExit($partsRequest, $geofence, $lat, $lng);
                Cache::put($cacheKey, false, now()->addHours(24));
            }
        }
    }

    /**
     * Get geofences relevant to this parts request
     */
    protected function getRelevantGeofences(PartsRequest $partsRequest): \Illuminate\Support\Collection
    {
        $geofences = collect();

        // Origin location geofence
        if ($partsRequest->origin_location_id) {
            $fence = GeoFence::where('entity_type', 'service_location')
                ->where('entity_id', $partsRequest->origin_location_id)
                ->first();

            if (!$fence && $partsRequest->originLocation) {
                // Auto-create geofence if doesn't exist
                $fence = $this->createGeofenceForLocation($partsRequest->originLocation);
            }

            if ($fence) {
                $geofences->push($fence);
            }
        }

        // Receiving location geofence
        if ($partsRequest->receiving_location_id) {
            $fence = GeoFence::where('entity_type', 'service_location')
                ->where('entity_id', $partsRequest->receiving_location_id)
                ->first();

            if (!$fence && $partsRequest->receivingLocation) {
                $fence = $this->createGeofenceForLocation($partsRequest->receivingLocation);
            }

            if ($fence) {
                $geofences->push($fence);
            }
        }

        return $geofences;
    }

    /**
     * Create geofence for a service location
     */
    protected function createGeofenceForLocation($location): ?GeoFence
    {
        if (!$location->latitude || !$location->longitude) {
            return null;
        }

        return GeoFence::create([
            'name' => $location->name,
            'entity_type' => 'service_location',
            'entity_id' => $location->id,
            'center_lat' => $location->latitude,
            'center_lng' => $location->longitude,
            'radius_m' => 100, // Default 100m radius
        ]);
    }

    /**
     * Handle runner entering a geofence
     */
    protected function handleGeofenceEnter(PartsRequest $partsRequest, GeoFence $geofence, float $lat, float $lng): void
    {
        // Record geofence event
        GeoFenceEvent::create([
            'geo_fence_id' => $geofence->id,
            'parts_request_id' => $partsRequest->id,
            'runner_user_id' => $partsRequest->assigned_runner_user_id,
            'event_type' => 'entered',
            'event_at' => now(),
            'lat' => $lat,
            'lng' => $lng,
        ]);

        // Determine if this is pickup or dropoff location
        $isOrigin = $geofence->entity_id == $partsRequest->origin_location_id;
        $isDestination = $geofence->entity_id == $partsRequest->receiving_location_id;

        // Create "arrived" event
        if ($isOrigin) {
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'arrived_pickup',
                'event_at' => now(),
                'user_id' => $partsRequest->assigned_runner_user_id,
                'notes' => "Auto-detected arrival at {$geofence->name}",
            ]);

            // Record arrival geofence event
            GeoFenceEvent::create([
                'geo_fence_id' => $geofence->id,
                'parts_request_id' => $partsRequest->id,
                'runner_user_id' => $partsRequest->assigned_runner_user_id,
                'event_type' => 'arrived',
                'event_at' => now(),
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        if ($isDestination) {
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'arrived_dropoff',
                'event_at' => now(),
                'user_id' => $partsRequest->assigned_runner_user_id,
                'notes' => "Auto-detected arrival at {$geofence->name}",
            ]);

            GeoFenceEvent::create([
                'geo_fence_id' => $geofence->id,
                'parts_request_id' => $partsRequest->id,
                'runner_user_id' => $partsRequest->assigned_runner_user_id,
                'event_type' => 'arrived',
                'event_at' => now(),
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }
    }

    /**
     * Handle runner exiting a geofence
     */
    protected function handleGeofenceExit(PartsRequest $partsRequest, GeoFence $geofence, float $lat, float $lng): void
    {
        // Record geofence event
        GeoFenceEvent::create([
            'geo_fence_id' => $geofence->id,
            'parts_request_id' => $partsRequest->id,
            'runner_user_id' => $partsRequest->assigned_runner_user_id,
            'event_type' => 'exited',
            'event_at' => now(),
            'lat' => $lat,
            'lng' => $lng,
        ]);

        // If exiting origin, assume departed after pickup
        $isOrigin = $geofence->entity_id == $partsRequest->origin_location_id;
        if ($isOrigin && $partsRequest->hasPickupPhoto()) {
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'departed_pickup',
                'event_at' => now(),
                'user_id' => $partsRequest->assigned_runner_user_id,
                'notes' => "Auto-detected departure from {$geofence->name}",
            ]);
        }
    }

    // ==========================================
    // Runner-specific Geofence Methods
    // ==========================================

    /**
     * Calculate the distance between two GPS coordinates using the Haversine formula.
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in meters
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
             cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Check if a point is inside a location's geofence.
     *
     * @param float $lat Current latitude
     * @param float $lng Current longitude
     * @param ServiceLocation $location The location to check against
     * @return bool
     */
    public function isInsideLocation(float $lat, float $lng, ServiceLocation $location): bool
    {
        // Must have coordinates set
        if (!$location->latitude || !$location->longitude) {
            return false;
        }

        $distance = $this->calculateDistance(
            $lat,
            $lng,
            (float) $location->latitude,
            (float) $location->longitude
        );

        return $distance <= ($location->geofence_radius_m ?? 200);
    }

    /**
     * Find the nearest stop for a run based on current position.
     *
     * @param float $lat Current latitude
     * @param float $lng Current longitude
     * @param int $runId The run instance ID
     * @return array|null Array with 'stop', 'distance', 'inside' keys, or null if no stops
     */
    public function findNearestStop(float $lat, float $lng, int $runId): ?array
    {
        $run = RunInstance::with(['route.stops.location'])->find($runId);

        if (!$run || !$run->route) {
            return null;
        }

        $nearestStop = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($run->route->stops as $stop) {
            $location = $stop->location;

            if (!$location || !$location->latitude || !$location->longitude) {
                continue;
            }

            $distance = $this->calculateDistance(
                $lat,
                $lng,
                (float) $location->latitude,
                (float) $location->longitude
            );

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestStop = $stop;
            }
        }

        if (!$nearestStop) {
            return null;
        }

        $radius = $nearestStop->location->geofence_radius_m ?? 200;

        return [
            'stop' => $nearestStop,
            'stop_id' => $nearestStop->id,
            'location_id' => $nearestStop->location_id,
            'location_name' => $nearestStop->location->name,
            'distance_m' => round($nearestDistance),
            'inside' => $nearestDistance <= $radius,
            'radius_m' => $radius,
        ];
    }

    /**
     * Get all stops for a run with their current distance from a position.
     *
     * @param float $lat Current latitude
     * @param float $lng Current longitude
     * @param int $runId The run instance ID
     * @return Collection
     */
    public function getStopsWithDistances(float $lat, float $lng, int $runId): Collection
    {
        $run = RunInstance::with(['route.stops.location'])->find($runId);

        if (!$run || !$run->route) {
            return collect();
        }

        return $run->route->stops->map(function ($stop) use ($lat, $lng) {
            $location = $stop->location;

            if (!$location || !$location->latitude || !$location->longitude) {
                return [
                    'stop_id' => $stop->id,
                    'location_id' => $stop->location_id,
                    'location_name' => $location?->name ?? 'Unknown',
                    'stop_order' => $stop->stop_order,
                    'distance_m' => null,
                    'inside' => false,
                    'has_coordinates' => false,
                ];
            }

            $distance = $this->calculateDistance(
                $lat,
                $lng,
                (float) $location->latitude,
                (float) $location->longitude
            );

            $radius = $location->geofence_radius_m ?? 200;

            return [
                'stop_id' => $stop->id,
                'location_id' => $stop->location_id,
                'location_name' => $location->name,
                'stop_order' => $stop->stop_order,
                'distance_m' => round($distance),
                'inside' => $distance <= $radius,
                'radius_m' => $radius,
                'has_coordinates' => true,
            ];
        })->sortBy('stop_order')->values();
    }

    /**
     * Check if the runner has exited a specific stop.
     *
     * @param float $lat Current latitude
     * @param float $lng Current longitude
     * @param RouteStop $stop The stop to check
     * @return bool True if outside the stop's geofence
     */
    public function hasExitedStop(float $lat, float $lng, RouteStop $stop): bool
    {
        $location = $stop->location;

        if (!$location || !$location->latitude || !$location->longitude) {
            // If no coordinates, can't determine exit
            return false;
        }

        return !$this->isInsideLocation($lat, $lng, $location);
    }
}
