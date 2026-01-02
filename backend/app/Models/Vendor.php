<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields, Auditable;

    protected $fillable = [
        'name',
        'legal_name',
        'normalized_name',
        'phone',
        'email',
        'notes',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Boot the model and auto-generate normalized_name on create/update.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Vendor $vendor) {
            if (empty($vendor->normalized_name) && !empty($vendor->name)) {
                $vendor->normalized_name = self::normalizeVendorName($vendor->name);
            }
        });

        static::updating(function (Vendor $vendor) {
            if ($vendor->isDirty('name') && !$vendor->isDirty('normalized_name')) {
                $vendor->normalized_name = self::normalizeVendorName($vendor->name);
            }
        });
    }

    /**
     * Normalize vendor name for duplicate detection.
     * - Lowercase
     * - Remove punctuation
     * - Collapse whitespace
     * - Remove common suffixes (inc, llc, corp, company, co, ltd)
     */
    public static function normalizeVendorName(string $name): string
    {
        $normalized = strtolower($name);

        // Remove punctuation
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        // Remove common business suffixes
        $suffixes = [
            ' incorporated',
            ' inc',
            ' llc',
            ' corporation',
            ' corp',
            ' company',
            ' co',
            ' limited',
            ' ltd',
            ' llp',
            ' lp',
        ];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                $normalized = substr($normalized, 0, -strlen($suffix));
                break;
            }
        }

        return trim($normalized);
    }

    // Relationships

    /**
     * Get all addresses associated with this vendor.
     */
    public function addresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'addressable')
            ->withPivot(['address_type', 'is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderBy('addressables.is_primary', 'desc')
            ->orderBy('addressables.sort_order');
    }

    /**
     * Get all parts requests for this vendor.
     */
    public function partsRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class);
    }

    // Scopes

    /**
     * Scope to active vendors only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search vendors by name.
     * Uses fulltext search for better performance when available,
     * falls back to LIKE search for short terms.
     */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);

        // For short terms (1-2 chars), use prefix LIKE search (can use index)
        if (strlen($term) <= 2) {
            return $query->where('name', 'like', $term . '%');
        }

        // For longer terms, use MySQL fulltext search with boolean mode
        // This is much faster than LIKE '%term%' for larger datasets
        return $query->whereRaw(
            'MATCH(name, legal_name, normalized_name) AGAINST(? IN BOOLEAN MODE)',
            [$term . '*']
        );
    }

    // Helper Methods

    /**
     * Get the primary pickup address for this vendor.
     */
    public function primaryAddress(): ?Address
    {
        return $this->addresses()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Get all pickup-type addresses for this vendor.
     */
    public function pickupAddresses(): Collection
    {
        return $this->addresses()
            ->wherePivot('address_type', 'pickup')
            ->get();
    }

    /**
     * Check if vendor has any addresses.
     */
    public function hasAddresses(): bool
    {
        return $this->addresses()->exists();
    }
}
