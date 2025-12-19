<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartsRequestPhoto extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'parts_request_id',
        'stage',
        'file_path',
        'taken_at',
        'taken_by_user_id',
        'lat',
        'lng',
        'notes',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by_user_id');
    }

    // Get full URL to photo
    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    // Delete photo file when model is deleted
    protected static function booted()
    {
        static::deleted(function ($photo) {
            if (Storage::exists($photo->file_path)) {
                Storage::delete($photo->file_path);
            }
        });
    }
}
