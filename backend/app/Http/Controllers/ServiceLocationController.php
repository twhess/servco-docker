<?php

namespace App\Http\Controllers;

use App\Models\ServiceLocation;
use App\Models\ServiceLocationPhone;
use App\Models\ServiceLocationEmail;
use App\Models\LocationPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceLocationController extends Controller
{
    /**
     * Get list of locations with filtering
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ServiceLocation::class);

        $query = ServiceLocation::with(['homeBase', 'assignedUser', 'phones', 'emails'])
            ->orderBy('name');

        // Filter by type
        if ($request->has('type')) {
            $types = is_array($request->type) ? $request->type : [$request->type];
            $query->whereIn('location_type', $types);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by active
        if ($request->has('active')) {
            $query->where('is_active', $request->active === 'true');
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter by user's allowed locations (unless admin/dispatcher)
        $user = $request->user();
        if (!$user->hasDispatchAccess() && $request->get('my_locations_only', 'false') === 'true') {
            $allowedIds = $user->allowed_location_ids ?? [];
            if ($user->home_location_id) {
                $allowedIds[] = $user->home_location_id;
            }
            $query->whereIn('id', $allowedIds);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Get single location with all details
     */
    public function show(Request $request, $id)
    {
        $location = ServiceLocation::with([
            'homeBase',
            'assignedUser',
            'phones',
            'emails',
            'latestPosition',
        ])->findOrFail($id);

        Gate::authorize('view', $location);

        return response()->json($location);
    }

    /**
     * Create new location
     */
    public function store(Request $request)
    {
        Gate::authorize('create', ServiceLocation::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:service_locations,code',
            'location_type' => 'required|in:fixed_shop,mobile_service_truck,parts_runner_vehicle,vendor,customer_site',
            'status' => 'nullable|in:available,on_job,on_run,offline,maintenance',
            'is_active' => 'boolean',
            'timezone' => 'nullable|string',
            'notes' => 'nullable|string',
            'address_line1' => 'nullable|string',
            'address_line2' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'vehicle_asset_id' => 'nullable|integer',
            'home_base_location_id' => 'nullable|exists:service_locations,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'is_dispatchable' => 'boolean',
        ]);

        $location = ServiceLocation::create($validated);

        return response()->json([
            'location' => $location->load(['homeBase', 'assignedUser']),
            'message' => 'Location created successfully',
        ], 201);
    }

    /**
     * Update location details
     */
    public function update(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateDetails', $location);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string|max:50|unique:service_locations,code,' . $id,
            'location_type' => 'sometimes|required|in:fixed_shop,mobile_service_truck,parts_runner_vehicle,vendor,customer_site',
            'status' => 'nullable|in:available,on_job,on_run,offline,maintenance',
            'is_active' => 'boolean',
            'timezone' => 'nullable|string',
            'notes' => 'nullable|string',
            'address_line1' => 'nullable|string',
            'address_line2' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'vehicle_asset_id' => 'nullable|integer',
            'home_base_location_id' => 'nullable|exists:service_locations,id',
            'is_dispatchable' => 'boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'location' => $location->load(['homeBase', 'assignedUser']),
            'message' => 'Location updated successfully',
        ]);
    }

    /**
     * Assign user to mobile location
     */
    public function assignUser(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('assignUser', $location);

        $validated = $request->validate([
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $location->update($validated);

        return response()->json([
            'location' => $location->load(['assignedUser']),
            'message' => 'User assigned successfully',
        ]);
    }

    /**
     * Update location status
     */
    public function updateStatus(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateStatus', $location);

        $validated = $request->validate([
            'status' => 'required|in:available,on_job,on_run,offline,maintenance',
        ]);

        $location->update($validated);

        return response()->json([
            'location' => $location,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * Record GPS position for mobile location
     */
    public function recordPosition(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('recordPosition', $location);

        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy_meters' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
            'heading' => 'nullable|numeric',
            'source' => 'nullable|in:gps,manual,geofence',
        ]);

        $validated['service_location_id'] = $id;
        $validated['recorded_at'] = now();

        $position = LocationPosition::create($validated);

        // Update last known position on location
        $location->update([
            'last_known_lat' => $validated['lat'],
            'last_known_lng' => $validated['lng'],
            'last_known_at' => now(),
        ]);

        return response()->json([
            'position' => $position,
            'message' => 'Position recorded successfully',
        ]);
    }

    /**
     * Get position history for mobile location
     */
    public function positionHistory(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('view', $location);

        $limit = $request->get('limit', 50);
        $positions = $location->positions()
            ->orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($positions);
    }

    /**
     * Add phone number to location
     */
    public function addPhone(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20',
            'extension' => 'nullable|string|max:10',
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
        ]);

        // If setting as primary, unset other primary phones
        if ($validated['is_primary'] ?? false) {
            $location->phones()->update(['is_primary' => false]);
        }

        $validated['service_location_id'] = $id;
        $phone = ServiceLocationPhone::create($validated);

        return response()->json([
            'phone' => $phone,
            'message' => 'Phone number added successfully',
        ], 201);
    }

    /**
     * Update phone number
     */
    public function updatePhone(Request $request, $id, $phoneId)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $phone = ServiceLocationPhone::where('service_location_id', $id)
            ->findOrFail($phoneId);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:50',
            'phone_number' => 'sometimes|required|string|max:20',
            'extension' => 'nullable|string|max:10',
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
        ]);

        // If setting as primary, unset other primary phones
        if (($validated['is_primary'] ?? false) && !$phone->is_primary) {
            $location->phones()->where('id', '!=', $phoneId)->update(['is_primary' => false]);
        }

        $phone->update($validated);

        return response()->json([
            'phone' => $phone,
            'message' => 'Phone number updated successfully',
        ]);
    }

    /**
     * Delete phone number
     */
    public function deletePhone(Request $request, $id, $phoneId)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $phone = ServiceLocationPhone::where('service_location_id', $id)
            ->findOrFail($phoneId);

        $phone->delete();

        return response()->json([
            'message' => 'Phone number deleted successfully',
        ]);
    }

    /**
     * Add email to location
     */
    public function addEmail(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
        ]);

        // If setting as primary, unset other primary emails
        if ($validated['is_primary'] ?? false) {
            $location->emails()->update(['is_primary' => false]);
        }

        $validated['service_location_id'] = $id;
        $email = ServiceLocationEmail::create($validated);

        return response()->json([
            'email' => $email,
            'message' => 'Email added successfully',
        ], 201);
    }

    /**
     * Update email
     */
    public function updateEmail(Request $request, $id, $emailId)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $email = ServiceLocationEmail::where('service_location_id', $id)
            ->findOrFail($emailId);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:50',
            'email' => 'sometimes|required|email|max:255',
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
        ]);

        // If setting as primary, unset other primary emails
        if (($validated['is_primary'] ?? false) && !$email->is_primary) {
            $location->emails()->where('id', '!=', $emailId)->update(['is_primary' => false]);
        }

        $email->update($validated);

        return response()->json([
            'email' => $email,
            'message' => 'Email updated successfully',
        ]);
    }

    /**
     * Delete email
     */
    public function deleteEmail(Request $request, $id, $emailId)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('updateContacts', $location);

        $email = ServiceLocationEmail::where('service_location_id', $id)
            ->findOrFail($emailId);

        $email->delete();

        return response()->json([
            'message' => 'Email deleted successfully',
        ]);
    }

    /**
     * Soft delete location
     */
    public function destroy(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);
        Gate::authorize('delete', $location);

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
        ]);
    }

    /**
     * Restore soft deleted location
     */
    public function restore(Request $request, $id)
    {
        $location = ServiceLocation::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $location);

        $location->restore();

        return response()->json([
            'location' => $location,
            'message' => 'Location restored successfully',
        ]);
    }
}
