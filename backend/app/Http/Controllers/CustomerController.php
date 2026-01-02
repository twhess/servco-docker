<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * List customers with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with(['addresses', 'creator'])
            ->orderBy('formatted_name', 'asc');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->search($request->search);
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Source filter
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        // Customer group filter
        if ($request->has('customer_group')) {
            $query->where('customer_group', $request->customer_group);
        }

        // Assigned shop filter
        if ($request->has('assigned_shop')) {
            $query->where('assigned_shop', $request->assigned_shop);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Search customers for autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'term' => 'required|string|min:1',
            'limit' => 'integer|min:1|max:50',
        ]);

        $results = $this->customerService->search(
            $request->term,
            $request->get('limit', 20)
        );

        return response()->json(['data' => $results]);
    }

    /**
     * Check for duplicate customers.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1',
            'dot_number' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|array',
        ]);

        $parsed = $this->customerService->parseCompanyName($request->name);

        $candidates = $this->customerService->findDuplicates(
            $parsed['formatted_name'],
            $request->dot_number,
            $request->phone,
            $request->address
        );

        return response()->json([
            'has_duplicates' => count($candidates) > 0,
            'candidates' => $candidates,
        ]);
    }

    /**
     * Create a new customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'formatted_name' => 'nullable|string|max:255',
            'detail' => 'nullable|string|max:255',
            'fb_id' => 'nullable|string|max:255|unique:customers,fb_id',
            'external_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'phone_secondary' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'dot_number' => 'nullable|string|max:50',
            'customer_group' => 'nullable|string|max:255',
            'assigned_shop' => 'nullable|string|max:255',
            'associated_shops' => 'nullable|array',
            'sales_rep' => 'nullable|string|max:255',
            'credit_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_location' => 'nullable|string|max:255',
            'price_level' => 'nullable|string|max:100',
            'is_taxable' => 'boolean',
            'tax_exempt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'force_create' => 'boolean',
            'physical_address' => 'nullable|array',
            'billing_address' => 'nullable|array',
        ]);

        $forceCreate = $request->boolean('force_create', false);
        $result = $this->customerService->createCustomer($validated, $forceCreate);

        if ($result['status'] === 'duplicates_found') {
            return response()->json($result, 409);
        }

        // Attach addresses if provided
        if ($result['status'] === 'created') {
            $customer = $result['data'];

            if (!empty($validated['physical_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $validated['physical_address'],
                    'physical',
                    true
                );
            }

            if (!empty($validated['billing_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $validated['billing_address'],
                    'billing',
                    true
                );
            }

            $result['data'] = $customer->fresh('addresses');
        }

        return response()->json($result, 201);
    }

    /**
     * Get a single customer with addresses.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['addresses', 'creator', 'updater'])
            ->findOrFail($id);

        return response()->json(['data' => $customer]);
    }

    /**
     * Update a customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'formatted_name' => 'nullable|string|max:255',
            'detail' => 'nullable|string|max:255',
            'fb_id' => 'nullable|string|max:255|unique:customers,fb_id,' . $id,
            'external_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'phone_secondary' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'dot_number' => 'nullable|string|max:50',
            'customer_group' => 'nullable|string|max:255',
            'assigned_shop' => 'nullable|string|max:255',
            'associated_shops' => 'nullable|array',
            'sales_rep' => 'nullable|string|max:255',
            'credit_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_location' => 'nullable|string|max:255',
            'price_level' => 'nullable|string|max:100',
            'is_taxable' => 'boolean',
            'tax_exempt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = $this->customerService->updateCustomer($customer, $validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer->load('addresses'),
        ]);
    }

    /**
     * Soft delete a customer.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Attach an address to a customer.
     */
    public function attachAddress(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'address' => 'required|array',
            'address.label' => 'nullable|string|max:255',
            'address.company_name' => 'nullable|string|max:255',
            'address.attention' => 'nullable|string|max:255',
            'address.line1' => 'required|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|size:2',
            'address.postal_code' => 'required|string|max:10',
            'address.country' => 'nullable|string|size:2',
            'address.phone' => 'nullable|string|max:50',
            'address.email' => 'nullable|email|max:255',
            'address.instructions' => 'nullable|string',
            'address_type' => 'required|in:physical,billing,shipping,other',
            'is_primary' => 'boolean',
        ]);

        $result = $this->customerService->attachAddress(
            $customer,
            $validated['address'],
            $validated['address_type'],
            $request->boolean('is_primary', false)
        );

        return response()->json($result, 201);
    }

    /**
     * Update address pivot data.
     */
    public function updateAddressPivot(Request $request, int $id, int $addressId): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        // Verify the address belongs to this customer
        if (!$customer->addresses()->where('addresses.id', $addressId)->exists()) {
            return response()->json(['message' => 'Address not found for this customer'], 404);
        }

        $validated = $request->validate([
            'address_type' => 'sometimes|in:physical,billing,shipping,other',
            'is_primary' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $result = $this->customerService->updateAddressPivot($customer, $addressId, $validated);

        return response()->json($result);
    }

    /**
     * Detach an address from a customer.
     */
    public function detachAddress(int $id, int $addressId): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        // Verify the address belongs to this customer
        if (!$customer->addresses()->where('addresses.id', $addressId)->exists()) {
            return response()->json(['message' => 'Address not found for this customer'], 404);
        }

        $result = $this->customerService->detachAddress($customer, $addressId);

        return response()->json($result);
    }
}
