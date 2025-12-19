<?php

namespace App\Services;

use App\Models\GeoFence;
use App\Models\GeoFenceEvent;
use App\Models\PartsRequest;
use App\Models\PartsRequestEvent;
use Illuminate\Support\Facades\Cache;

class GeofenceService
{
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
}
