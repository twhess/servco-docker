<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'file_path',
        'original_filename',
        'status',
        'total_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'merge_needed_count',
        'error_count',
        'started_at',
        'finished_at',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'merge_needed_count' => 'integer',
        'error_count' => 'integer',
    ];

    // Relationships

    /**
     * Get the user who uploaded this import.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get all rows for this import.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(CustomerImportRow::class);
    }

    /**
     * Get all merge candidates for this import.
     */
    public function mergeCandidates(): HasMany
    {
        return $this->hasMany(CustomerMergeCandidate::class);
    }

    // Status Methods

    /**
     * Mark the import as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the import as completed with counts.
     */
    public function markCompleted(array $counts = []): void
    {
        $this->update(array_merge([
            'status' => 'completed',
            'finished_at' => now(),
        ], $counts));
    }

    /**
     * Mark the import as failed.
     */
    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
        ]);
    }

    // Scopes

    /**
     * Scope to pending imports.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to processing imports.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to completed imports.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to failed imports.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helpers

    /**
     * Get the processed row count.
     */
    public function getProcessedCountAttribute(): int
    {
        return $this->created_count + $this->updated_count + $this->skipped_count + $this->merge_needed_count + $this->error_count;
    }

    /**
     * Get the success rate as a percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        $successful = $this->created_count + $this->updated_count;
        return round(($successful / $this->total_rows) * 100, 2);
    }

    /**
     * Check if import has pending merges.
     */
    public function hasPendingMerges(): bool
    {
        return $this->mergeCandidates()->where('status', 'pending')->exists();
    }

    /**
     * Get count of pending merges.
     */
    public function getPendingMergeCountAttribute(): int
    {
        return $this->mergeCandidates()->where('status', 'pending')->count();
    }
}
