<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClosedDate extends Model
{
    use HasFactory, HasAuditFields, Auditable;

    protected $fillable = [
        'date',
        'name',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Relationships

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    /**
     * Scope to filter by year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeInRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    /**
     * Scope to get upcoming closed dates
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())->orderBy('date');
    }

    // Static Helpers

    /**
     * Check if a specific date is closed
     */
    public static function isDateClosed(Carbon $date): bool
    {
        return static::where('date', $date->toDateString())->exists();
    }

    /**
     * Get closed date for a specific date (if exists)
     */
    public static function getForDate(Carbon $date): ?static
    {
        return static::where('date', $date->toDateString())->first();
    }
}
