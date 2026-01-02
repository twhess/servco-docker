<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Customer;
use App\Models\CustomerImport;
use App\Models\CustomerImportRow;
use App\Models\CustomerMergeCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class CustomerImportService
{
    protected CustomerService $customerService;

    // Threshold for creating merge candidates (score out of 100)
    protected float $mergeThreshold = 50;

    // CSV column to DB field mapping
    protected array $columnMap = [
        'ID' => 'fb_id',
        'Company Name' => 'company_name',
        'Customer Active' => 'is_active',
        'Created' => 'external_created_at',
        'Customer Group' => 'customer_group',
        'Customer Main Phone' => 'phone',
        'Customer Secondary Phone' => 'phone_secondary',
        'Fax' => 'fax',
        'DOT #' => 'dot_number',
        'External Id' => 'external_id',
        'Assigned Shop' => 'assigned_shop',
        'Associated Shops' => 'associated_shops',
        'Sales Rep' => 'sales_rep',
        'Credit Terms' => 'credit_terms',
        'Credit Limit' => 'credit_limit',
        'Tax Location (Rate)' => 'tax_location',
        'Price Level' => 'price_level',
        'Taxable' => 'is_taxable',
        'Tax Exempt #' => 'tax_exempt_number',
        'Discount' => 'discount',
        'Default Labor Rate' => 'default_labor_rate',
        'PO Required to Create SO' => 'po_required_create_so',
        'PO Required to Create Invoice' => 'po_required_create_invoice',
        'Blanket PO #' => 'blanket_po_number',
        'Portal is on' => 'portal_enabled',
        'Portal Code' => 'portal_code',
        'Can See Invoices' => 'portal_can_see_invoices',
        'Can Pay Invoices' => 'portal_can_pay_invoices',
        'Display Notes on SO' => 'notes',
    ];

    // Physical address fields
    protected array $physicalAddressMap = [
        'Physical Address Name' => 'label',
        'Physical Address Line 1' => 'line1',
        'Physical Address Line 2' => 'line2',
        'Physical Address City' => 'city',
        'Physical Address State' => 'state',
        'Physical Address Zip/Postal Code' => 'postal_code',
        'Physical Address Country' => 'country',
    ];

    // Billing address fields
    protected array $billingAddressMap = [
        'Billing Address Name' => 'label',
        'Billing Address Line 1' => 'line1',
        'Billing Address Line 2' => 'line2',
        'Billing Address City' => 'city',
        'Billing Address State' => 'state',
        'Billing Address Zip/Postal Code' => 'postal_code',
        'Billing Address Country' => 'country',
    ];

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Process a customer import.
     * Main entry point - called by job or synchronously.
     */
    public function processImport(CustomerImport $import): void
    {
        Log::info('Starting customer import', ['import_id' => $import->id]);

        try {
            $csv = Reader::createFromPath(Storage::disk('local')->path($import->file_path), 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $headers = $csv->getHeader();

            $counts = [
                'total_rows' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'merge_needed_count' => 0,
                'error_count' => 0,
            ];

            $rowNumber = 0;

            foreach ($records as $record) {
                $rowNumber++;
                $counts['total_rows']++;

                try {
                    $parsedData = $this->parseRow($record);
                    $action = $this->upsertOrQueue($import, $parsedData, $rowNumber, $record);
                    $counts[$action . '_count']++;
                } catch (\Exception $e) {
                    Log::error('Error processing import row', [
                        'import_id' => $import->id,
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                    ]);

                    CustomerImportRow::create([
                        'customer_import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'fb_id' => $record['ID'] ?? null,
                        'raw_data' => $record,
                        'action' => 'error',
                        'message' => $e->getMessage(),
                    ]);

                    $counts['error_count']++;
                }
            }

            $import->markCompleted($counts);

            Log::info('Customer import completed', [
                'import_id' => $import->id,
                'counts' => $counts,
            ]);

        } catch (\Exception $e) {
            Log::error('Customer import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse a CSV row into our data structure.
     */
    public function parseRow(array $csvRow): array
    {
        $data = [
            'source' => 'import',
        ];

        // Map basic fields
        foreach ($this->columnMap as $csvColumn => $dbField) {
            if (isset($csvRow[$csvColumn]) && $csvRow[$csvColumn] !== '') {
                $value = trim($csvRow[$csvColumn]);

                // Type conversions
                if (in_array($dbField, ['is_active', 'is_taxable', 'po_required_create_so', 'po_required_create_invoice', 'portal_enabled', 'portal_can_see_invoices', 'portal_can_pay_invoices'])) {
                    $value = $this->parseBoolean($value);
                } elseif ($dbField === 'credit_limit') {
                    $value = $this->parseDecimal($value);
                } elseif ($dbField === 'external_created_at') {
                    $value = $this->parseDate($value);
                } elseif ($dbField === 'associated_shops') {
                    $value = $this->parseCommaSeparated($value);
                }

                $data[$dbField] = $value;
            }
        }

        // Parse company name
        if (!empty($data['company_name'])) {
            $parsed = $this->customerService->parseCompanyName($data['company_name']);
            $data['formatted_name'] = $parsed['formatted_name'];
            $data['detail'] = $parsed['detail'];
        }

        // Parse physical address
        $physicalAddress = $this->parseAddress($csvRow, $this->physicalAddressMap);
        if ($this->isValidAddress($physicalAddress)) {
            $data['physical_address'] = $physicalAddress;
        }

        // Parse billing address
        $billingAddress = $this->parseAddress($csvRow, $this->billingAddressMap);
        if ($this->isValidAddress($billingAddress)) {
            $data['billing_address'] = $billingAddress;
        }

        // Store extra fields in settings JSON
        $extraFields = $this->parseExtraFields($csvRow);
        if (!empty($extraFields)) {
            $data['settings'] = $extraFields;
        }

        return $data;
    }

    /**
     * Upsert or queue for merge.
     * Returns: 'created' | 'updated' | 'merge_needed' | 'skipped'
     */
    public function upsertOrQueue(
        CustomerImport $import,
        array $parsedData,
        int $rowNumber,
        array $rawData
    ): string {
        $fbId = $parsedData['fb_id'] ?? null;

        // Check if customer exists by fb_id
        $existingByFbId = null;
        if (!empty($fbId)) {
            $existingByFbId = Customer::where('fb_id', $fbId)->first();
        }

        if ($existingByFbId) {
            // UPDATE existing customer
            return $this->updateExistingCustomer($import, $existingByFbId, $parsedData, $rowNumber, $rawData);
        }

        // No fb_id match - check for duplicates among manual entries
        $duplicates = $this->customerService->findDuplicates(
            $parsedData['formatted_name'] ?? $parsedData['company_name'] ?? '',
            $parsedData['dot_number'] ?? null,
            $parsedData['phone'] ?? null,
            $parsedData['physical_address'] ?? null,
            1, // Only need the best match
            $this->mergeThreshold
        );

        if (!empty($duplicates)) {
            // Found potential duplicate - queue for merge review
            return $this->queueForMerge($import, $duplicates[0], $parsedData, $rowNumber, $rawData);
        }

        // No duplicates - create new customer
        return $this->createNewCustomer($import, $parsedData, $rowNumber, $rawData);
    }

    /**
     * Update an existing customer from import.
     */
    protected function updateExistingCustomer(
        CustomerImport $import,
        Customer $customer,
        array $parsedData,
        int $rowNumber,
        array $rawData
    ): string {
        DB::transaction(function () use ($customer, $parsedData) {
            // Don't overwrite source if it's manual
            if ($customer->source === 'manual') {
                unset($parsedData['source']);
            }

            // Update customer fields
            $this->customerService->updateCustomer($customer, $parsedData);

            // Handle addresses
            $this->syncAddressesFromImport($customer, $parsedData);
        });

        CustomerImportRow::create([
            'customer_import_id' => $import->id,
            'row_number' => $rowNumber,
            'fb_id' => $parsedData['fb_id'] ?? null,
            'raw_data' => $rawData,
            'action' => 'updated',
            'customer_id' => $customer->id,
            'message' => 'Updated existing customer',
        ]);

        return 'updated';
    }

    /**
     * Create a new customer from import.
     */
    protected function createNewCustomer(
        CustomerImport $import,
        array $parsedData,
        int $rowNumber,
        array $rawData
    ): string {
        $customer = null;

        DB::transaction(function () use ($parsedData, &$customer) {
            $customer = Customer::create($this->prepareCustomerData($parsedData));

            // Create addresses
            if (!empty($parsedData['physical_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $parsedData['physical_address'],
                    'physical',
                    true
                );
            }

            if (!empty($parsedData['billing_address'])) {
                $this->customerService->attachAddress(
                    $customer,
                    $parsedData['billing_address'],
                    'billing',
                    true
                );
            }
        });

        CustomerImportRow::create([
            'customer_import_id' => $import->id,
            'row_number' => $rowNumber,
            'fb_id' => $parsedData['fb_id'] ?? null,
            'raw_data' => $rawData,
            'action' => 'created',
            'customer_id' => $customer->id,
            'message' => 'Created new customer',
        ]);

        return 'created';
    }

    /**
     * Queue a row for merge review.
     */
    protected function queueForMerge(
        CustomerImport $import,
        array $matchedCandidate,
        array $parsedData,
        int $rowNumber,
        array $rawData
    ): string {
        $importRow = CustomerImportRow::create([
            'customer_import_id' => $import->id,
            'row_number' => $rowNumber,
            'fb_id' => $parsedData['fb_id'] ?? null,
            'raw_data' => $rawData,
            'action' => 'merge_needed',
            'message' => 'Potential duplicate found: ' . $matchedCandidate['formatted_name'] . ' (score: ' . $matchedCandidate['score'] . ')',
        ]);

        CustomerMergeCandidate::create([
            'customer_import_id' => $import->id,
            'import_row_id' => $importRow->id,
            'matched_customer_id' => $matchedCandidate['id'],
            'incoming_data' => $parsedData,
            'match_score' => $matchedCandidate['score'],
            'match_reasons' => $matchedCandidate['reasons'],
            'status' => 'pending',
        ]);

        return 'merge_needed';
    }

    /**
     * Sync addresses from import data to customer.
     * Uses deduplication to avoid creating duplicate addresses.
     */
    protected function syncAddressesFromImport(Customer $customer, array $parsedData): void
    {
        // Physical address
        if (!empty($parsedData['physical_address'])) {
            $this->createAddressIfNotExists(
                $customer,
                $parsedData['physical_address'],
                'physical'
            );
        }

        // Billing address
        if (!empty($parsedData['billing_address'])) {
            $this->createAddressIfNotExists(
                $customer,
                $parsedData['billing_address'],
                'billing'
            );
        }
    }

    /**
     * Create address if not already exists (by dedupe key).
     */
    protected function createAddressIfNotExists(
        Customer $customer,
        array $addressData,
        string $type
    ): void {
        $dedupeKey = $this->customerService->dedupeAddressKey($addressData, $type);

        // Check if this address already exists for this customer
        foreach ($customer->addresses as $existing) {
            $existingKey = $this->customerService->dedupeAddressKey([
                'line1' => $existing->line1,
                'postal_code' => $existing->postal_code,
                'city' => $existing->city,
            ], $existing->pivot->address_type);

            if ($existingKey === $dedupeKey) {
                // Address already exists - skip
                return;
            }
        }

        // Check if this is the first address of this type (make it primary)
        $hasAddressOfType = $customer->addresses()
            ->wherePivot('address_type', $type)
            ->exists();

        $this->customerService->attachAddress(
            $customer,
            $addressData,
            $type,
            !$hasAddressOfType // Make primary if first of this type
        );
    }

    /**
     * Parse an address from CSV row.
     */
    protected function parseAddress(array $csvRow, array $fieldMap): array
    {
        $address = [];

        foreach ($fieldMap as $csvColumn => $dbField) {
            if (isset($csvRow[$csvColumn]) && $csvRow[$csvColumn] !== '') {
                $value = trim($csvRow[$csvColumn]);

                // Normalize state to 2 chars
                if ($dbField === 'state' && strlen($value) > 2) {
                    $value = $this->normalizeState($value);
                }

                // Default country
                if ($dbField === 'country' && empty($value)) {
                    $value = 'US';
                }

                $address[$dbField] = $value;
            }
        }

        // Default country if not set
        if (!isset($address['country'])) {
            $address['country'] = 'US';
        }

        return $address;
    }

    /**
     * Check if an address has minimum required fields.
     */
    protected function isValidAddress(array $address): bool
    {
        return !empty($address['line1']) && !empty($address['city']) && !empty($address['postal_code']);
    }

    /**
     * Parse extra fields that don't map directly to our schema.
     */
    protected function parseExtraFields(array $csvRow): array
    {
        $mappedColumns = array_merge(
            array_keys($this->columnMap),
            array_keys($this->physicalAddressMap),
            array_keys($this->billingAddressMap)
        );

        $extra = [];

        foreach ($csvRow as $column => $value) {
            if (!in_array($column, $mappedColumns) && !empty($value)) {
                $extra[$column] = trim($value);
            }
        }

        return $extra;
    }

    /**
     * Parse boolean values from CSV.
     */
    public function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['yes', 'true', '1', 'y', 'on']);
    }

    /**
     * Parse decimal values from CSV.
     */
    protected function parseDecimal($value): ?float
    {
        if (empty($value)) {
            return null;
        }

        // Remove currency symbols and commas
        $value = preg_replace('/[^0-9.-]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Parse date values from CSV.
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse comma-separated values into array.
     */
    protected function parseCommaSeparated($value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Normalize state name to 2-letter code.
     */
    protected function normalizeState(string $state): string
    {
        $states = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
            'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
            'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
            'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
            'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
            'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
            'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
            'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
            'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
        ];

        $normalized = strtolower(trim($state));

        return $states[$normalized] ?? substr(strtoupper($state), 0, 2);
    }

    /**
     * Prepare customer data for create/update.
     */
    protected function prepareCustomerData(array $data): array
    {
        // Remove address arrays
        unset($data['physical_address'], $data['billing_address']);

        // Filter to only fillable fields
        $fillable = (new Customer())->getFillable();
        return array_intersect_key($data, array_flip($fillable));
    }
}
