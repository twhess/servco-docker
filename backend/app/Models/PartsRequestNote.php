<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequestNote extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'parts_request_id',
        'content',
        'user_id',
        'is_edited',
        'edited_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    protected $appends = ['can_edit', 'can_delete'];

    // Relationships

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors for permission checks (set dynamically by controller)

    public function getCanEditAttribute(): bool
    {
        return $this->attributes['can_edit'] ?? false;
    }

    public function setCanEditAttribute(bool $value): void
    {
        $this->attributes['can_edit'] = $value;
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->attributes['can_delete'] ?? false;
    }

    public function setCanDeleteAttribute(bool $value): void
    {
        $this->attributes['can_delete'] = $value;
    }
}
