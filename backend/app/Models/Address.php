<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'label',
        'company_name',
        'attention',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'instructions',
        'lat',
        'lng',
        'is_validated',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_validated' => 'boolean',
    ];

    protected $appends = [
        'full_address',
        'one_line_address',
    ];

    // Relationships

    /**
     * Get all vendors that have this address.
     */
    public function vendors(): MorphedByMany
    {
        return $this->morphedByMany(Vendor::class, 'addressable')
            ->withPivot(['address_type', 'is_primary', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Get all customers that have this address.
     */
    public function customers(): MorphedByMany
    {
        return $this->morphedByMany(Customer::class, 'addressable')
            ->withPivot(['address_type', 'is_primary', 'sort_order'])
            ->withTimestamps();
    }

    // Accessors

    /**
     * Get formatted multi-line address.
     */
    public function getFullAddressAttribute(): string
    {
        $lines = [];

        if ($this->company_name) {
            $lines[] = $this->company_name;
        }

        if ($this->attention) {
            $lines[] = 'ATTN: ' . $this->attention;
        }

        $lines[] = $this->line1;

        if ($this->line2) {
            $lines[] = $this->line2;
        }

        $lines[] = "{$this->city}, {$this->state} {$this->postal_code}";

        if ($this->country && $this->country !== 'US') {
            $lines[] = $this->country;
        }

        return implode("\n", $lines);
    }

    /**
     * Get single-line address format.
     */
    public function getOneLineAddressAttribute(): string
    {
        $parts = [$this->line1];

        if ($this->line2) {
            $parts[] = $this->line2;
        }

        $parts[] = $this->city;
        $parts[] = $this->state;
        $parts[] = $this->postal_code;

        return implode(', ', $parts);
    }

    // Scopes

    /**
     * Scope to addresses in a specific city/state.
     */
    public function scopeInLocation($query, string $city, string $state)
    {
        return $query->where('city', $city)->where('state', $state);
    }

    /**
     * Scope to validated addresses only.
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    // Helper Methods

    /**
     * Check if this address has coordinates.
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->lat) && !is_null($this->lng);
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinates(): ?array
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        return [
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
        ];
    }

    /**
     * Get display label (label if exists, otherwise one-line address).
     */
    public function getDisplayLabel(): string
    {
        if ($this->label) {
            return $this->label . ' - ' . $this->one_line_address;
        }

        return $this->one_line_address;
    }
}
