<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * List addresses with filtering and pagination.
     */
    public function index(Request $request)
    {
        $query = Address::with('vendors')
            ->orderBy('created_at', 'desc');

        // Search by city, state, or postal code
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('postal_code', 'like', "%{$search}%")
                    ->orWhere('line1', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Filter by state
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        // Filter by validated status
        if ($request->has('validated')) {
            $query->where('is_validated', $request->boolean('validated'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Create standalone address.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'attention' => 'nullable|string|max:255',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'postal_code' => 'required|string|max:10',
            'country' => 'nullable|string|max:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'instructions' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $validated['country'] = $validated['country'] ?? 'US';

        $address = Address::create($validated);

        return response()->json([
            'message' => 'Address created successfully',
            'data' => $address,
        ], 201);
    }

    /**
     * Get single address.
     */
    public function show($id)
    {
        $address = Address::with('vendors')->findOrFail($id);

        return response()->json([
            'data' => $address,
        ]);
    }

    /**
     * Update address.
     */
    public function update(Request $request, $id)
    {
        $address = Address::findOrFail($id);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'attention' => 'nullable|string|max:255',
            'line1' => 'sometimes|required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|max:2',
            'postal_code' => 'sometimes|required|string|max:10',
            'country' => 'nullable|string|max:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'instructions' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'is_validated' => 'boolean',
        ]);

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * Soft delete address.
     */
    public function destroy($id)
    {
        $address = Address::findOrFail($id);

        // Check if address is attached to any vendors
        if ($address->vendors()->exists()) {
            return response()->json([
                'message' => 'Cannot delete address that is still attached to vendors. Detach it first.',
            ], 422);
        }

        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully',
        ]);
    }
}
