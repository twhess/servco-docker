<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequestItem extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'parts_request_id',
        'description',
        'quantity',
        'part_number',
        'notes',
        'is_verified',
        'verified_by_user_id',
        'verified_at',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    // Helper Methods

    /**
     * Mark item as verified by the given user
     */
    public function verify(User $user): void
    {
        $this->update([
            'is_verified' => true,
            'verified_by_user_id' => $user->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Unverify the item
     */
    public function unverify(): void
    {
        $this->update([
            'is_verified' => false,
            'verified_by_user_id' => null,
            'verified_at' => null,
        ]);
    }

    /**
     * Get formatted display string
     */
    public function getDisplayString(): string
    {
        $str = "{$this->quantity}x {$this->description}";
        if ($this->part_number) {
            $str .= " (#{$this->part_number})";
        }
        return $str;
    }
}
