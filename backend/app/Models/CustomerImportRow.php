<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_import_id',
        'row_number',
        'fb_id',
        'raw_data',
        'action',
        'customer_id',
        'message',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'row_number' => 'integer',
    ];

    // Relationships

    /**
     * Get the import this row belongs to.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(CustomerImport::class, 'customer_import_id');
    }

    /**
     * Get the customer created/updated by this row.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the merge candidate for this row (if any).
     */
    public function mergeCandidate(): HasOne
    {
        return $this->hasOne(CustomerMergeCandidate::class, 'import_row_id');
    }

    // Scopes

    /**
     * Scope to rows with a specific action.
     */
    public function scopeWithAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to rows that created customers.
     */
    public function scopeCreated($query)
    {
        return $query->where('action', 'created');
    }

    /**
     * Scope to rows that updated customers.
     */
    public function scopeUpdated($query)
    {
        return $query->where('action', 'updated');
    }

    /**
     * Scope to rows that need merge review.
     */
    public function scopeMergeNeeded($query)
    {
        return $query->where('action', 'merge_needed');
    }

    /**
     * Scope to rows that had errors.
     */
    public function scopeErrors($query)
    {
        return $query->where('action', 'error');
    }

    /**
     * Scope to rows that were skipped.
     */
    public function scopeSkipped($query)
    {
        return $query->where('action', 'skipped');
    }

    // Helpers

    /**
     * Check if this row resulted in a successful operation.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->action, ['created', 'updated']);
    }

    /**
     * Check if this row needs merge review.
     */
    public function needsMergeReview(): bool
    {
        return $this->action === 'merge_needed';
    }

    /**
     * Check if this row had an error.
     */
    public function hasError(): bool
    {
        return $this->action === 'error';
    }

    /**
     * Get the company name from raw data.
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->raw_data['Company Name'] ?? null;
    }
}
