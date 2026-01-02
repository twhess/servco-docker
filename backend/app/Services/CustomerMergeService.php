<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerImportRow;
use App\Models\CustomerMergeCandidate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerMergeService
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Get merge candidates with optional filters.
     */
    public function getMergeCandidates(
        ?int $importId = null,
        string $status = 'pending',
        int $limit = 50
    ): Collection {
        $query = CustomerMergeCandidate::with([
            'matchedCustomer',
            'importRow',
            'import',
            'resolver',
        ])->orderBy('match_score', 'desc');

        if ($importId) {
            $query->where('customer_import_id', $importId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get a single merge candidate with comparison data.
     */
    public function getCandidateWithComparison(int $candidateId): array
    {
        $candidate = CustomerMergeCandidate::with([
            'matchedCustomer.addresses',
            'importRow',
            'import.uploader',
        ])->findOrFail($candidateId);

        return [
            'candidate' => $candidate,
            'comparison' => $this->getComparisonData($candidate),
        ];
    }

    /**
     * Resolve a merge candidate.
     *
     * @param CustomerMergeCandidate $candidate
     * @param string $action 'merge' | 'create_new' | 'skip'
     * @param array $fieldSelections For merge: which fields to take from incoming
     * @param User $user
     * @return array
     */
    public function resolveMerge(
        CustomerMergeCandidate $candidate,
        string $action,
        array $fieldSelections = [],
        User $user
    ): array {
        if (!$candidate->isPending()) {
            return [
                'success' => false,
                'message' => 'This merge candidate has already been resolved.',
            ];
        }

        return match ($action) {
            'merge' => $this->performMerge($candidate, $fieldSelections, $user),
            'create_new' => $this->createAsNew($candidate, $user),
            'skip' => $this->skipCandidate($candidate, $user),
            default => [
                'success' => false,
                'message' => 'Invalid action. Must be: merge, create_new, or skip',
            ],
        };
    }

    /**
     * Perform a merge - update existing customer with selected fields from incoming.
     */
    protected function performMerge(
        CustomerMergeCandidate $candidate,
        array $fieldSelections,
        User $user
    ): array {
        $customer = $candidate->matchedCustomer;
        $incomingData = $candidate->incoming_data;

        DB::transaction(function () use ($candidate, $customer, $incomingData, $fieldSelections, $user) {
            $updateData = [];

            // Apply field selections
            foreach ($fieldSelections as $field => $source) {
                if ($source === 'incoming' && isset($incomingData[$field])) {
                    $updateData[$field] = $incomingData[$field];
                }
                // 'existing' means keep current value - no update needed
            }

            // Always set fb_id from incoming if the existing customer doesn't have one
            if (empty($customer->fb_id) && !empty($incomingData['fb_id'])) {
                $updateData['fb_id'] = $incomingData['fb_id'];
            }

            // Keep source as 'manual' for merged records
            // (intentionally not setting source to preserve manual designation)

            if (!empty($updateData)) {
                $this->customerService->updateCustomer($customer, $updateData);
            }

            // Handle addresses if selected
            if (($fieldSelections['physical_address'] ?? '') === 'incoming' && !empty($incomingData['physical_address'])) {
                $this->mergeAddressIfNotExists($customer, $incomingData['physical_address'], 'physical');
            }

            if (($fieldSelections['billing_address'] ?? '') === 'incoming' && !empty($incomingData['billing_address'])) {
                $this->mergeAddressIfNotExists($customer, $incomingData['billing_address'], 'billing');
            }

            // Mark as merged
            $candidate->markMerged($user->id, [
                'field_selections' => $fieldSelections,
                'updated_fields' => array_keys($updateData),
            ]);

            // Update the import row
            $candidate->importRow->update([
                'action' => 'updated',
                'customer_id' => $customer->id,
                'message' => 'Merged with existing customer after review',
            ]);
        });

        return [
            'success' => true,
            'message' => 'Successfully merged with existing customer.',
            'customer' => $customer->fresh('addresses'),
        ];
    }

    /**
     * Create incoming data as a new customer.
     */
    protected function createAsNew(
        CustomerMergeCandidate $candidate,
        User $user
    ): array {
        $incomingData = $candidate->incoming_data;
        $customer = null;

        DB::transaction(function () use ($candidate, $incomingData, $user, &$customer) {
            // Create the customer
            $result = $this->customerService->createCustomer($incomingData, true);

            if ($result['status'] !== 'created') {
                throw new \Exception('Failed to create customer');
            }

            $customer = $result['data'];

            // Attach addresses
            if (!empty($incomingData['physical_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $incomingData['physical_address'],
                    'physical',
                    true
                );
            }

            if (!empty($incomingData['billing_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $incomingData['billing_address'],
                    'billing',
                    true
                );
            }

            // Mark as created new
            $candidate->markCreatedNew($user->id, $customer->id);
        });

        return [
            'success' => true,
            'message' => 'Created as new customer.',
            'customer' => $customer->fresh('addresses'),
        ];
    }

    /**
     * Skip this merge candidate.
     */
    protected function skipCandidate(
        CustomerMergeCandidate $candidate,
        User $user,
        ?string $reason = null
    ): array {
        $candidate->markSkipped($user->id, $reason ?? 'Skipped by user during merge review');

        return [
            'success' => true,
            'message' => 'Merge candidate skipped.',
        ];
    }

    /**
     * Get side-by-side comparison data for UI.
     */
    public function getComparisonData(CustomerMergeCandidate $candidate): array
    {
        $existing = $candidate->matchedCustomer;
        $incoming = $candidate->incoming_data;

        $existingData = [
            'id' => $existing->id,
            'fb_id' => $existing->fb_id,
            'company_name' => $existing->company_name,
            'formatted_name' => $existing->formatted_name,
            'detail' => $existing->detail,
            'phone' => $existing->phone,
            'phone_secondary' => $existing->phone_secondary,
            'fax' => $existing->fax,
            'email' => $existing->email,
            'dot_number' => $existing->dot_number,
            'customer_group' => $existing->customer_group,
            'assigned_shop' => $existing->assigned_shop,
            'sales_rep' => $existing->sales_rep,
            'credit_terms' => $existing->credit_terms,
            'credit_limit' => $existing->credit_limit,
            'tax_location' => $existing->tax_location,
            'price_level' => $existing->price_level,
            'is_taxable' => $existing->is_taxable,
            'is_active' => $existing->is_active,
            'source' => $existing->source,
            'physical_address' => $this->formatAddressForComparison(
                $existing->addresses->firstWhere('pivot.address_type', 'physical')
            ),
            'billing_address' => $this->formatAddressForComparison(
                $existing->addresses->firstWhere('pivot.address_type', 'billing')
            ),
        ];

        $incomingData = [
            'fb_id' => $incoming['fb_id'] ?? null,
            'company_name' => $incoming['company_name'] ?? null,
            'formatted_name' => $incoming['formatted_name'] ?? null,
            'detail' => $incoming['detail'] ?? null,
            'phone' => $incoming['phone'] ?? null,
            'phone_secondary' => $incoming['phone_secondary'] ?? null,
            'fax' => $incoming['fax'] ?? null,
            'email' => $incoming['email'] ?? null,
            'dot_number' => $incoming['dot_number'] ?? null,
            'customer_group' => $incoming['customer_group'] ?? null,
            'assigned_shop' => $incoming['assigned_shop'] ?? null,
            'sales_rep' => $incoming['sales_rep'] ?? null,
            'credit_terms' => $incoming['credit_terms'] ?? null,
            'credit_limit' => $incoming['credit_limit'] ?? null,
            'tax_location' => $incoming['tax_location'] ?? null,
            'price_level' => $incoming['price_level'] ?? null,
            'is_taxable' => $incoming['is_taxable'] ?? null,
            'is_active' => $incoming['is_active'] ?? null,
            'source' => 'import',
            'physical_address' => $this->formatAddressArrayForComparison($incoming['physical_address'] ?? null),
            'billing_address' => $this->formatAddressArrayForComparison($incoming['billing_address'] ?? null),
        ];

        // Find differences
        $differences = [];
        $fieldsToCompare = [
            'fb_id', 'company_name', 'formatted_name', 'detail', 'phone', 'phone_secondary',
            'fax', 'email', 'dot_number', 'customer_group', 'assigned_shop', 'sales_rep',
            'credit_terms', 'credit_limit', 'tax_location', 'price_level', 'is_taxable', 'is_active',
        ];

        foreach ($fieldsToCompare as $field) {
            $existingValue = $existingData[$field];
            $incomingValue = $incomingData[$field];

            // Normalize for comparison
            if (is_bool($existingValue)) {
                $existingValue = $existingValue ? 'Yes' : 'No';
            }
            if (is_bool($incomingValue)) {
                $incomingValue = $incomingValue ? 'Yes' : 'No';
            }

            if ($this->normalizeForComparison($existingValue) !== $this->normalizeForComparison($incomingValue)) {
                $differences[] = $field;
            }
        }

        // Check address differences
        if ($existingData['physical_address'] !== $incomingData['physical_address']) {
            $differences[] = 'physical_address';
        }
        if ($existingData['billing_address'] !== $incomingData['billing_address']) {
            $differences[] = 'billing_address';
        }

        return [
            'existing' => $existingData,
            'incoming' => $incomingData,
            'differences' => $differences,
            'match_score' => $candidate->match_score,
            'match_reasons' => $candidate->match_reasons,
        ];
    }

    /**
     * Merge an address if it doesn't already exist on the customer.
     */
    protected function mergeAddressIfNotExists(
        Customer $customer,
        array $addressData,
        string $type
    ): void {
        $dedupeKey = $this->customerService->dedupeAddressKey($addressData, $type);

        // Check if address already exists
        foreach ($customer->addresses as $existing) {
            $existingKey = $this->customerService->dedupeAddressKey([
                'line1' => $existing->line1,
                'postal_code' => $existing->postal_code,
                'city' => $existing->city,
            ], $existing->pivot->address_type);

            if ($existingKey === $dedupeKey) {
                return; // Already exists
            }
        }

        // Determine if this should be primary
        $hasAddressOfType = $customer->addresses()
            ->wherePivot('address_type', $type)
            ->exists();

        $this->customerService->attachAddress(
            $customer,
            $addressData,
            $type,
            !$hasAddressOfType
        );
    }

    /**
     * Format an Address model for comparison display.
     */
    protected function formatAddressForComparison($address): ?string
    {
        if (!$address) {
            return null;
        }

        return trim(implode(', ', array_filter([
            $address->line1,
            $address->line2,
            $address->city,
            $address->state,
            $address->postal_code,
        ])));
    }

    /**
     * Format an address array for comparison display.
     */
    protected function formatAddressArrayForComparison(?array $address): ?string
    {
        if (!$address || empty($address['line1'])) {
            return null;
        }

        return trim(implode(', ', array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postal_code'] ?? null,
        ])));
    }

    /**
     * Normalize a value for comparison.
     */
    protected function normalizeForComparison($value): string
    {
        if (is_null($value)) {
            return '';
        }

        return strtolower(trim((string) $value));
    }
}
