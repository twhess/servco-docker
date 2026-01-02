<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields, Auditable;

    protected $fillable = [
        'fb_id',
        'external_id',
        'source',
        'company_name',
        'formatted_name',
        'detail',
        'normalized_name',
        'phone',
        'phone_secondary',
        'fax',
        'email',
        'dot_number',
        'customer_group',
        'assigned_shop',
        'associated_shops',
        'sales_rep',
        'credit_terms',
        'credit_limit',
        'tax_location',
        'price_level',
        'is_taxable',
        'tax_exempt_number',
        'discount',
        'default_labor_rate',
        'po_required_create_so',
        'po_required_create_invoice',
        'blanket_po_number',
        'portal_enabled',
        'portal_code',
        'portal_can_see_invoices',
        'portal_can_pay_invoices',
        'settings',
        'is_active',
        'notes',
        'external_created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'credit_limit' => 'decimal:2',
        'associated_shops' => 'array',
        'settings' => 'array',
        'external_created_at' => 'datetime',
        'portal_enabled' => 'boolean',
        'portal_can_see_invoices' => 'boolean',
        'portal_can_pay_invoices' => 'boolean',
        'po_required_create_so' => 'boolean',
        'po_required_create_invoice' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate normalized_name on create/update
        static::creating(function ($customer) {
            if (empty($customer->normalized_name) && !empty($customer->formatted_name)) {
                $customer->normalized_name = self::normalizeCustomerName($customer->formatted_name);
            }
        });

        static::updating(function ($customer) {
            if ($customer->isDirty('formatted_name')) {
                $customer->normalized_name = self::normalizeCustomerName($customer->formatted_name);
            }
        });
    }

    // Relationships

    /**
     * Get all addresses for this customer.
     */
    public function addresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'addressable')
            ->withPivot(['address_type', 'is_primary', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Get the user who created this customer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this customer.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    /**
     * Scope to active customers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to manually created customers.
     */
    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Scope to imported customers.
     */
    public function scopeImported($query)
    {
        return $query->where('source', 'import');
    }

    /**
     * Scope to search customers by name/DOT.
     * Uses fulltext search for better performance when available.
     */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);

        // For short terms (1-2 chars), use prefix LIKE search
        if (strlen($term) <= 2) {
            return $query->where('formatted_name', 'like', $term . '%');
        }

        // For longer terms, use MySQL fulltext search with boolean mode
        return $query->whereRaw(
            'MATCH(company_name, formatted_name, normalized_name, dot_number) AGAINST(? IN BOOLEAN MODE)',
            [$term . '*']
        );
    }

    // Static Methods

    /**
     * Normalize a customer name for duplicate detection.
     * - Lowercase
     * - Remove punctuation
     * - Collapse whitespace
     * - Remove common suffixes (inc, llc, corp, company, co, ltd)
     */
    public static function normalizeCustomerName(string $name): string
    {
        // Lowercase
        $normalized = strtolower($name);

        // Remove punctuation except spaces
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Remove common business suffixes
        $suffixes = [
            ' incorporated', ' inc', ' llc', ' llp', ' ltd', ' limited',
            ' corporation', ' corp', ' company', ' co', ' enterprises',
            ' trucking', ' transport', ' transportation', ' logistics',
        ];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                $normalized = substr($normalized, 0, -strlen($suffix));
            }
        }

        return trim($normalized);
    }

    /**
     * Parse company name into formatted_name and detail.
     * Everything before first "(" is formatted_name.
     * Everything after first "(" (trimmed, without trailing ")") is detail.
     */
    public static function parseCompanyName(string $companyName): array
    {
        $companyName = trim($companyName);

        $parenPos = strpos($companyName, '(');

        if ($parenPos === false) {
            return [
                'formatted_name' => $companyName,
                'detail' => null,
            ];
        }

        $formattedName = trim(substr($companyName, 0, $parenPos));
        $detail = trim(substr($companyName, $parenPos + 1));

        // Remove trailing parenthesis if present
        if (str_ends_with($detail, ')')) {
            $detail = substr($detail, 0, -1);
        }

        $detail = trim($detail);

        return [
            'formatted_name' => $formattedName,
            'detail' => $detail ?: null,
        ];
    }

    // Helper Methods

    /**
     * Get the primary physical address for this customer.
     */
    public function primaryPhysicalAddress(): ?Address
    {
        return $this->addresses()
            ->wherePivot('address_type', 'physical')
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Get the primary billing address for this customer.
     */
    public function primaryBillingAddress(): ?Address
    {
        return $this->addresses()
            ->wherePivot('address_type', 'billing')
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Get all physical addresses for this customer.
     */
    public function physicalAddresses()
    {
        return $this->addresses()
            ->wherePivot('address_type', 'physical')
            ->orderBy('addressables.is_primary', 'desc')
            ->orderBy('addressables.sort_order');
    }

    /**
     * Get all billing addresses for this customer.
     */
    public function billingAddresses()
    {
        return $this->addresses()
            ->wherePivot('address_type', 'billing')
            ->orderBy('addressables.is_primary', 'desc')
            ->orderBy('addressables.sort_order');
    }

    /**
     * Check if customer has any addresses.
     */
    public function hasAddresses(): bool
    {
        return $this->addresses()->exists();
    }
}
