<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;

class VendorService
{
    public function __construct(
        protected AcronymDetector $acronymDetector
    ) {}

    /**
     * Detect if a name is likely an acronym.
     *
     * @return array{isLikely: bool, reason: string, suggestedName: string}
     */
    public function detectAcronym(string $name): array
    {
        return $this->acronymDetector->detect($name);
    }

    /**
     * Normalize vendor name for duplicate detection.
     * - Lowercase
     * - Remove punctuation
     * - Collapse whitespace
     * - Remove common suffixes (inc, llc, corp, company, co, ltd)
     */
    public function normalizeName(string $name): string
    {
        return Vendor::normalizeVendorName($name);
    }

    /**
     * Find potential duplicate vendors.
     * Returns array of matches with similarity scores.
     */
    public function findDuplicates(string $name, int $limit = 5): array
    {
        $normalizedName = $this->normalizeName($name);
        $candidates = [];

        // First, look for exact normalized name matches
        $exactMatches = Vendor::where('normalized_name', $normalizedName)
            ->active()
            ->limit($limit)
            ->get();

        foreach ($exactMatches as $vendor) {
            $candidates[] = [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'normalized_name' => $vendor->normalized_name,
                'similarity' => 1.0,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
            ];
        }

        // If we have exact matches, return them
        if (count($candidates) > 0) {
            return $candidates;
        }

        // Otherwise, use Levenshtein distance for fuzzy matching
        $allVendors = Vendor::active()
            ->select(['id', 'name', 'normalized_name', 'phone', 'email'])
            ->get();

        foreach ($allVendors as $vendor) {
            $similarity = $this->calculateSimilarity($normalizedName, $vendor->normalized_name);

            // Only include if similarity is above threshold (0.6 = 60%)
            if ($similarity >= 0.6) {
                $candidates[] = [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'normalized_name' => $vendor->normalized_name,
                    'similarity' => round($similarity, 2),
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                ];
            }
        }

        // Sort by similarity descending
        usort($candidates, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

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
     * Create vendor with duplicate check and acronym handling.
     * If duplicates found and force_create !== true, returns candidates.
     *
     * @param array $data Vendor data including 'name', 'is_acronym' (optional), etc.
     * @param bool $forceCreate Skip duplicate check
     * @return array
     */
    public function createVendor(array $data, bool $forceCreate = false): array
    {
        $name = $data['name'] ?? '';

        // Check for duplicates unless forced
        if (!$forceCreate) {
            $duplicates = $this->findDuplicates($name);

            if (count($duplicates) > 0) {
                return [
                    'status' => 'duplicates_found',
                    'message' => 'Possible duplicate vendors found. Set force_create=true to proceed.',
                    'candidates' => $duplicates,
                ];
            }
        }

        // Handle acronym detection and name formatting
        $isAcronym = $data['is_acronym'] ?? null;
        $finalName = $name;

        // If is_acronym not explicitly set, auto-detect
        if ($isAcronym === null) {
            $detection = $this->acronymDetector->detect($name);
            $isAcronym = $detection['isLikely'];
            if ($isAcronym) {
                $finalName = $detection['suggestedName'];
            }
        } elseif ($isAcronym === true) {
            // User confirmed it's an acronym - format accordingly
            $finalName = $this->acronymDetector->formatName($name, true);
        }

        // Create the vendor
        $vendor = Vendor::create([
            'name' => $finalName,
            'legal_name' => $data['legal_name'] ?? null,
            'is_acronym' => (bool) $isAcronym,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return [
            'status' => 'created',
            'message' => 'Vendor created successfully',
            'data' => $vendor->fresh(),
        ];
    }

    /**
     * Search vendors by name (for autocomplete).
     * Optimized for speed - only loads essential fields and primary address.
     */
    public function search(string $term, int $limit = 20): Collection
    {
        return Vendor::active()
            ->search($term)
            ->select(['id', 'name', 'phone', 'email', 'status'])
            ->with(['addresses' => function ($query) {
                $query->wherePivot('is_primary', true)
                    ->select(['addresses.id', 'line1', 'city', 'state', 'postal_code']);
            }])
            ->limit($limit)
            ->get();
    }

    /**
     * Get vendor with all addresses.
     */
    public function getVendorWithAddresses(int $vendorId): ?Vendor
    {
        return Vendor::with('addresses')
            ->find($vendorId);
    }

    /**
     * Attach an address to a vendor.
     */
    public function attachAddress(
        Vendor $vendor,
        array $addressData,
        string $addressType = 'pickup',
        bool $isPrimary = false
    ): array {
        // If setting as primary, unset any existing primary addresses
        if ($isPrimary) {
            $vendor->addresses()->updateExistingPivot(
                $vendor->addresses()->pluck('addresses.id')->toArray(),
                ['is_primary' => false]
            );
        }

        // Create the address
        $address = \App\Models\Address::create($addressData);

        // Get the next sort order
        $maxSortOrder = $vendor->addresses()
            ->max('addressables.sort_order') ?? 0;

        // Attach to vendor
        $vendor->addresses()->attach($address->id, [
            'address_type' => $addressType,
            'is_primary' => $isPrimary,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return [
            'message' => 'Address attached successfully',
            'data' => $vendor->fresh('addresses'),
        ];
    }

    /**
     * Update address pivot data.
     */
    public function updateAddressPivot(
        Vendor $vendor,
        int $addressId,
        array $pivotData
    ): array {
        // If setting as primary, unset other primary addresses
        if (($pivotData['is_primary'] ?? false) === true) {
            $vendor->addresses()->updateExistingPivot(
                $vendor->addresses()->pluck('addresses.id')->toArray(),
                ['is_primary' => false]
            );
        }

        $vendor->addresses()->updateExistingPivot($addressId, $pivotData);

        return [
            'message' => 'Address updated successfully',
            'data' => $vendor->fresh('addresses'),
        ];
    }

    /**
     * Detach an address from a vendor.
     */
    public function detachAddress(Vendor $vendor, int $addressId): array
    {
        $vendor->addresses()->detach($addressId);

        // Optionally delete the address if it's not attached to anything else
        $address = \App\Models\Address::find($addressId);
        if ($address && !$address->vendors()->exists()) {
            $address->delete();
        }

        return [
            'message' => 'Address detached successfully',
            'data' => $vendor->fresh('addresses'),
        ];
    }
}
