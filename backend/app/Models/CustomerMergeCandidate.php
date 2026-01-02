<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMergeCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_import_id',
        'import_row_id',
        'matched_customer_id',
        'incoming_data',
        'match_score',
        'match_reasons',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_details',
    ];

    protected $casts = [
        'incoming_data' => 'array',
        'match_score' => 'decimal:2',
        'match_reasons' => 'array',
        'resolution_details' => 'array',
        'resolved_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the import this candidate belongs to.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(CustomerImport::class, 'customer_import_id');
    }

    /**
     * Get the import row this candidate is from.
     */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(CustomerImportRow::class, 'import_row_id');
    }

    /**
     * Get the matched customer.
     */
    public function matchedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'matched_customer_id');
    }

    /**
     * Get the user who resolved this candidate.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes

    /**
     * Scope to pending candidates.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to resolved candidates.
     */
    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['merged', 'created_new', 'skipped']);
    }

    /**
     * Scope to merged candidates.
     */
    public function scopeMerged($query)
    {
        return $query->where('status', 'merged');
    }

    /**
     * Scope to candidates created as new.
     */
    public function scopeCreatedNew($query)
    {
        return $query->where('status', 'created_new');
    }

    /**
     * Scope to skipped candidates.
     */
    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }

    /**
     * Scope to high-confidence matches.
     */
    public function scopeHighConfidence($query, float $threshold = 80)
    {
        return $query->where('match_score', '>=', $threshold);
    }

    // Helpers

    /**
     * Check if this candidate is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this candidate was resolved.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, ['merged', 'created_new', 'skipped']);
    }

    /**
     * Mark as merged.
     */
    public function markMerged(int $userId, array $details = []): void
    {
        $this->update([
            'status' => 'merged',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_details' => $details,
        ]);
    }

    /**
     * Mark as created new.
     */
    public function markCreatedNew(int $userId, int $customerId): void
    {
        $this->update([
            'status' => 'created_new',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_details' => ['created_customer_id' => $customerId],
        ]);

        // Update the import row with the new customer
        $this->importRow->update([
            'action' => 'created',
            'customer_id' => $customerId,
            'message' => 'Created as new customer after merge review',
        ]);
    }

    /**
     * Mark as skipped.
     */
    public function markSkipped(int $userId, ?string $reason = null): void
    {
        $this->update([
            'status' => 'skipped',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_details' => ['reason' => $reason],
        ]);

        // Update the import row
        $this->importRow->update([
            'action' => 'skipped',
            'message' => $reason ?? 'Skipped during merge review',
        ]);
    }

    /**
     * Get the incoming company name.
     */
    public function getIncomingCompanyNameAttribute(): ?string
    {
        return $this->incoming_data['company_name'] ?? null;
    }

    /**
     * Get match reason summary as string.
     */
    public function getMatchSummaryAttribute(): string
    {
        $reasons = $this->match_reasons ?? [];
        $matched = [];

        if ($reasons['name'] ?? false) {
            $matched[] = 'name';
        }
        if ($reasons['dot'] ?? false) {
            $matched[] = 'DOT#';
        }
        if ($reasons['phone'] ?? false) {
            $matched[] = 'phone';
        }
        if ($reasons['address'] ?? false) {
            $matched[] = 'address';
        }

        return empty($matched) ? 'fuzzy match' : 'matched on: ' . implode(', ', $matched);
    }
}
