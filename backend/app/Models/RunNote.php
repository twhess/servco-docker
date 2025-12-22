<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_instance_id',
        'note_type',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships

    /**
     * The run instance this note belongs to
     */
    public function runInstance(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class);
    }

    /**
     * User who created this note
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes

    /**
     * Scope to notes of a specific type
     */
    public function scopeByType($query, string $noteType)
    {
        return $query->where('note_type', $noteType);
    }

    /**
     * Scope to recent notes (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }
}
