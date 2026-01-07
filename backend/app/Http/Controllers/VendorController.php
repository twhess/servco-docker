<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        protected VendorService $vendorService
    ) {}

    /**
     * List vendors with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = Vendor::with(['addresses' => function ($q) {
            $q->wherePivot('is_primary', true);
        }])
            ->orderBy('name');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to active only
            $query->active();
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Quick search for autocomplete.
     */
    public function search(Request $request)
    {
        $request->validate([
            'term' => 'required|string|min:1',
            'limit' => 'integer|min:1|max:50',
        ]);

        $vendors = $this->vendorService->search(
            $request->term,
            $request->get('limit', 20)
        );

        return response()->json([
            'data' => $vendors,
        ]);
    }

    /**
     * Check for duplicate vendors without creating.
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $duplicates = $this->vendorService->findDuplicates($request->name);

        return response()->json([
            'has_duplicates' => count($duplicates) > 0,
            'candidates' => $duplicates,
        ]);
    }

    /**
     * Detect if a name is likely an acronym.
     * Returns detection result with suggested formatting.
     */
    public function detectAcronym(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = $this->vendorService->detectAcronym($request->name);

        return response()->json($result);
    }

    /**
     * Create new vendor with duplicate check and acronym handling.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'is_acronym' => 'nullable|boolean',
            'force_create' => 'boolean',
        ]);

        $result = $this->vendorService->createVendor(
            $validated,
            $request->boolean('force_create', false)
        );

        if ($result['status'] === 'duplicates_found') {
            return response()->json($result, 409); // Conflict
        }

        return response()->json($result, 201);
    }

    /**
     * Get single vendor with all addresses.
     */
    public function show($id)
    {
        $vendor = Vendor::with('addresses')->findOrFail($id);

        return response()->json([
            'data' => $vendor,
        ]);
    }

    /**
     * Update vendor.
     */
    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'is_acronym' => 'nullable|boolean',
        ]);

        // If is_acronym is being set to true, format the name as uppercase
        if (isset($validated['is_acronym']) && $validated['is_acronym'] && isset($validated['name'])) {
            $acronymDetector = app(\App\Services\AcronymDetector::class);
            $validated['name'] = $acronymDetector->formatName($validated['name'], true);
        }

        $vendor->update($validated);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data' => $vendor->fresh('addresses'),
        ]);
    }

    /**
     * Soft delete vendor.
     */
    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json([
            'message' => 'Vendor deleted successfully',
        ]);
    }

    /**
     * Attach address to vendor.
     */
    public function attachAddress(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'address.label' => 'nullable|string|max:255',
            'address.company_name' => 'nullable|string|max:255',
            'address.attention' => 'nullable|string|max:255',
            'address.line1' => 'required|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|max:2',
            'address.postal_code' => 'required|string|max:10',
            'address.country' => 'nullable|string|max:2',
            'address.phone' => 'nullable|string|max:20',
            'address.email' => 'nullable|email|max:255',
            'address.instructions' => 'nullable|string',
            'address.lat' => 'nullable|numeric',
            'address.lng' => 'nullable|numeric',
            'address_type' => 'nullable|in:pickup,billing,shipping,other',
            'is_primary' => 'boolean',
        ]);

        $addressData = $validated['address'];
        $addressData['country'] = $addressData['country'] ?? 'US';

        $result = $this->vendorService->attachAddress(
            $vendor,
            $addressData,
            $validated['address_type'] ?? 'pickup',
            $request->boolean('is_primary', false)
        );

        return response()->json($result, 201);
    }

    /**
     * Update address pivot data (is_primary, address_type).
     */
    public function updateAddressPivot(Request $request, $id, $addressId)
    {
        $vendor = Vendor::findOrFail($id);

        // Verify address is attached to this vendor
        if (!$vendor->addresses()->where('addresses.id', $addressId)->exists()) {
            return response()->json([
                'message' => 'Address not found for this vendor',
            ], 404);
        }

        $validated = $request->validate([
            'address_type' => 'nullable|in:pickup,billing,shipping,other',
            'is_primary' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $result = $this->vendorService->updateAddressPivot(
            $vendor,
            $addressId,
            $validated
        );

        return response()->json($result);
    }

    /**
     * Detach address from vendor.
     */
    public function detachAddress($id, $addressId)
    {
        $vendor = Vendor::findOrFail($id);

        // Verify address is attached to this vendor
        if (!$vendor->addresses()->where('addresses.id', $addressId)->exists()) {
            return response()->json([
                'message' => 'Address not found for this vendor',
            ], 404);
        }

        $result = $this->vendorService->detachAddress($vendor, $addressId);

        return response()->json($result);
    }
}
