<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Support\Collection;

class CustomerService
{
    /**
     * Parse company name into formatted_name and detail.
     * Everything before first "(" is formatted_name.
     * Everything after first "(" (trimmed, without trailing ")") is detail.
     */
    public function parseCompanyName(string $companyName): array
    {
        return Customer::parseCompanyName($companyName);
    }

    /**
     * Normalize a customer name for duplicate detection.
     */
    public function normalizeName(string $name): string
    {
        return Customer::normalizeCustomerName($name);
    }

    /**
     * Normalize a phone number to digits only.
     */
    public function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Generate a deduplication key for an address.
     * Returns normalized: "123mainst-45801-lima-physical"
     */
    public function dedupeAddressKey(array $address, string $type = 'physical'): string
    {
        $line1 = strtolower(preg_replace('/[^\w]/', '', $address['line1'] ?? ''));
        $zip = preg_replace('/[^0-9]/', '', $address['postal_code'] ?? '');
        $city = strtolower(preg_replace('/[^\w]/', '', $address['city'] ?? ''));

        return "{$line1}-{$zip}-{$city}-{$type}";
    }

    /**
     * Find potential duplicate customers using multi-factor scoring.
     *
     * Scoring:
     * - Exact normalized name match: 40 points
     * - DOT number exact match: 30 points
     * - Phone match (normalized): 15 points
     * - Address match (line1 + zip): 15 points
     * - Fuzzy name match (levenshtein): 0-40 points scaled
     *
     * Returns candidates with score >= threshold (default 50)
     */
    public function findDuplicates(
        string $formattedName,
        ?string $dotNumber = null,
        ?string $phone = null,
        ?array $address = null,
        int $limit = 5,
        float $threshold = 50
    ): array {
        $normalizedName = $this->normalizeName($formattedName);
        $normalizedPhone = $this->normalizePhone($phone);
        $candidates = [];

        // Get all active customers for comparison
        $customers = Customer::active()
            ->select(['id', 'company_name', 'formatted_name', 'normalized_name', 'dot_number', 'phone', 'phone_secondary'])
            ->with(['addresses' => function ($query) {
                $query->select(['addresses.id', 'line1', 'postal_code', 'city']);
            }])
            ->get();

        foreach ($customers as $customer) {
            $score = 0;
            $reasons = [
                'name' => false,
                'name_fuzzy' => false,
                'dot' => false,
                'phone' => false,
                'address' => false,
            ];

            // Name matching
            if ($customer->normalized_name === $normalizedName) {
                $score += 40;
                $reasons['name'] = true;
            } else {
                // Fuzzy name match using Levenshtein
                $similarity = $this->calculateSimilarity($normalizedName, $customer->normalized_name);
                if ($similarity >= 0.6) {
                    $fuzzyScore = $similarity * 40;
                    $score += $fuzzyScore;
                    $reasons['name_fuzzy'] = true;
                }
            }

            // DOT number matching (exact match only)
            if (!empty($dotNumber) && !empty($customer->dot_number)) {
                $incomingDot = preg_replace('/[^0-9]/', '', $dotNumber);
                $existingDot = preg_replace('/[^0-9]/', '', $customer->dot_number);

                if ($incomingDot === $existingDot) {
                    $score += 30;
                    $reasons['dot'] = true;
                }
            }

            // Phone matching
            if (!empty($normalizedPhone)) {
                $customerPhone = $this->normalizePhone($customer->phone);
                $customerPhoneSecondary = $this->normalizePhone($customer->phone_secondary);

                if (
                    (!empty($customerPhone) && $customerPhone === $normalizedPhone) ||
                    (!empty($customerPhoneSecondary) && $customerPhoneSecondary === $normalizedPhone)
                ) {
                    $score += 15;
                    $reasons['phone'] = true;
                }
            }

            // Address matching
            if (!empty($address) && !empty($address['line1']) && !empty($address['postal_code'])) {
                $incomingKey = $this->dedupeAddressKey($address, 'any');

                foreach ($customer->addresses as $existingAddress) {
                    $existingKey = $this->dedupeAddressKey([
                        'line1' => $existingAddress->line1,
                        'postal_code' => $existingAddress->postal_code,
                        'city' => $existingAddress->city,
                    ], 'any');

                    if ($incomingKey === $existingKey) {
                        $score += 15;
                        $reasons['address'] = true;
                        break;
                    }
                }
            }

            // Only include if score meets threshold
            if ($score >= $threshold) {
                $candidates[] = [
                    'id' => $customer->id,
                    'company_name' => $customer->company_name,
                    'formatted_name' => $customer->formatted_name,
                    'dot_number' => $customer->dot_number,
                    'phone' => $customer->phone,
                    'score' => round($score, 2),
                    'reasons' => $reasons,
                ];
            }
        }

        // Sort by score descending
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     * Returns a value between 0 and 1 (1 = identical).
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        if ($str1 === $str2) {
            return 1.0;
        }

        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Calculate match score between incoming data and an existing customer.
     */
    public function calculateMatchScore(array $incomingData, Customer $existing): array
    {
        $physicalAddress = $incomingData['physical_address'] ?? null;

        $candidates = $this->findDuplicates(
            $incomingData['formatted_name'] ?? '',
            $incomingData['dot_number'] ?? null,
            $incomingData['phone'] ?? null,
            $physicalAddress,
            1,
            0 // Get score even if below threshold
        );

        // Find the specific customer in results
        foreach ($candidates as $candidate) {
            if ($candidate['id'] === $existing->id) {
                return [
                    'score' => $candidate['score'],
                    'reasons' => $candidate['reasons'],
                ];
            }
        }

        // If not found in results, calculate directly
        return [
            'score' => 0,
            'reasons' => [
                'name' => false,
                'name_fuzzy' => false,
                'dot' => false,
                'phone' => false,
                'address' => false,
            ],
        ];
    }

    /**
     * Create a customer with duplicate check.
     * If duplicates found and force_create !== true, returns candidates.
     */
    public function createCustomer(array $data, bool $forceCreate = false): array
    {
        // Parse company name if not already parsed
        if (empty($data['formatted_name']) && !empty($data['company_name'])) {
            $parsed = $this->parseCompanyName($data['company_name']);
            $data['formatted_name'] = $parsed['formatted_name'];
            $data['detail'] = $parsed['detail'];
        }

        $formattedName = $data['formatted_name'] ?? $data['company_name'] ?? '';

        // Check for duplicates unless forced
        if (!$forceCreate) {
            $duplicates = $this->findDuplicates(
                $formattedName,
                $data['dot_number'] ?? null,
                $data['phone'] ?? null,
                $data['physical_address'] ?? null
            );

            if (count($duplicates) > 0) {
                return [
                    'status' => 'duplicates_found',
                    'message' => 'Possible duplicate customers found. Set force_create=true to proceed.',
                    'candidates' => $duplicates,
                ];
            }
        }

        // Create the customer
        $customerData = $this->prepareCustomerData($data);
        $customer = Customer::create($customerData);

        return [
            'status' => 'created',
            'message' => 'Customer created successfully',
            'data' => $customer->fresh(),
        ];
    }

    /**
     * Update a customer.
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        // Parse company name if changed
        if (isset($data['company_name']) && $data['company_name'] !== $customer->company_name) {
            $parsed = $this->parseCompanyName($data['company_name']);
            $data['formatted_name'] = $parsed['formatted_name'];
            $data['detail'] = $parsed['detail'];
        }

        $customerData = $this->prepareCustomerData($data);
        $customer->update($customerData);

        return $customer->fresh();
    }

    /**
     * Search customers by name/DOT (for autocomplete).
     * Optimized for speed - only loads essential fields.
     */
    public function search(string $term, int $limit = 20): Collection
    {
        return Customer::active()
            ->search($term)
            ->select(['id', 'company_name', 'formatted_name', 'detail', 'dot_number', 'phone', 'is_active'])
            ->with(['addresses' => function ($query) {
                $query->wherePivot('is_primary', true)
                    ->select(['addresses.id', 'line1', 'city', 'state', 'postal_code']);
            }])
            ->limit($limit)
            ->get();
    }

    /**
     * Get customer with all addresses.
     */
    public function getCustomerWithAddresses(int $customerId): ?Customer
    {
        return Customer::with('addresses')
            ->find($customerId);
    }

    /**
     * Attach an address to a customer.
     */
    public function attachAddress(
        Customer $customer,
        array $addressData,
        string $addressType = 'physical',
        bool $isPrimary = false
    ): array {
        // If setting as primary, unset any existing primary addresses of this type
        if ($isPrimary) {
            $existingPrimaryIds = $customer->addresses()
                ->wherePivot('address_type', $addressType)
                ->wherePivot('is_primary', true)
                ->pluck('addresses.id')
                ->toArray();

            if (!empty($existingPrimaryIds)) {
                $customer->addresses()->updateExistingPivot(
                    $existingPrimaryIds,
                    ['is_primary' => false]
                );
            }
        }

        // Create the address
        $address = Address::create($addressData);

        // Get the next sort order
        $maxSortOrder = $customer->addresses()
            ->wherePivot('address_type', $addressType)
            ->max('addressables.sort_order') ?? 0;

        // Attach to customer
        $customer->addresses()->attach($address->id, [
            'address_type' => $addressType,
            'is_primary' => $isPrimary,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return [
            'message' => 'Address attached successfully',
            'data' => $customer->fresh('addresses'),
        ];
    }

    /**
     * Update address pivot data.
     */
    public function updateAddressPivot(
        Customer $customer,
        int $addressId,
        array $pivotData
    ): array {
        // If setting as primary, unset other primary addresses of this type
        if (($pivotData['is_primary'] ?? false) === true) {
            $addressType = $pivotData['address_type'] ?? $customer->addresses()
                ->where('addresses.id', $addressId)
                ->first()
                ?->pivot
                ?->address_type;

            if ($addressType) {
                $existingPrimaryIds = $customer->addresses()
                    ->wherePivot('address_type', $addressType)
                    ->wherePivot('is_primary', true)
                    ->pluck('addresses.id')
                    ->toArray();

                if (!empty($existingPrimaryIds)) {
                    $customer->addresses()->updateExistingPivot(
                        $existingPrimaryIds,
                        ['is_primary' => false]
                    );
                }
            }
        }

        $customer->addresses()->updateExistingPivot($addressId, $pivotData);

        return [
            'message' => 'Address updated successfully',
            'data' => $customer->fresh('addresses'),
        ];
    }

    /**
     * Detach an address from a customer.
     */
    public function detachAddress(Customer $customer, int $addressId): array
    {
        $customer->addresses()->detach($addressId);

        // Optionally delete the address if it's not attached to anything else
        $address = Address::find($addressId);
        if ($address && !$address->vendors()->exists() && !$address->customers()->exists()) {
            $address->delete();
        }

        return [
            'message' => 'Address detached successfully',
            'data' => $customer->fresh('addresses'),
        ];
    }

    /**
     * Prepare customer data for create/update.
     * Removes nested address data and ensures proper field mapping.
     */
    private function prepareCustomerData(array $data): array
    {
        // Remove address arrays - they should be handled separately
        unset($data['physical_address'], $data['billing_address']);

        // Ensure company_name is set
        if (empty($data['company_name']) && !empty($data['formatted_name'])) {
            $data['company_name'] = $data['formatted_name'];
            if (!empty($data['detail'])) {
                $data['company_name'] .= ' (' . $data['detail'] . ')';
            }
        }

        // Filter to only fillable fields
        $fillable = (new Customer())->getFillable();
        return array_intersect_key($data, array_flip($fillable));
    }
}
