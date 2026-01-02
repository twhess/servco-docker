<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartsRequestImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'parts_request_id',
        'source',
        'original_filename',
        'stored_filename',
        'file_path',
        'thumbnail_path',
        'mime_type',
        'file_size',
        'original_size',
        'width',
        'height',
        'caption',
        'latitude',
        'longitude',
        'taken_at',
        'uploaded_by_user_id',
        'uploaded_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'original_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'taken_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    protected $appends = ['url', 'thumbnail_url', 'formatted_file_size'];

    // Source constants
    public const SOURCE_REQUESTER = 'requester';
    public const SOURCE_PICKUP = 'pickup';
    public const SOURCE_DELIVERY = 'delivery';

    // Relationships

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors

    /**
     * Get URL for the image (served through API route)
     */
    public function getUrlAttribute(): string
    {
        return url("/api/parts-requests/{$this->parts_request_id}/images/{$this->id}");
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return $this->url; // Fall back to main image
        }
        return url("/api/parts-requests/{$this->parts_request_id}/images/{$this->id}/thumbnail");
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    // Scopes

    public function scopeRequester($query)
    {
        return $query->where('source', self::SOURCE_REQUESTER);
    }

    public function scopePickup($query)
    {
        return $query->where('source', self::SOURCE_PICKUP);
    }

    public function scopeDelivery($query)
    {
        return $query->where('source', self::SOURCE_DELIVERY);
    }

    public function scopeRunner($query)
    {
        return $query->whereIn('source', [self::SOURCE_PICKUP, self::SOURCE_DELIVERY]);
    }

    // Helper methods

    public function isRequesterImage(): bool
    {
        return $this->source === self::SOURCE_REQUESTER;
    }

    public function isRunnerImage(): bool
    {
        return in_array($this->source, [self::SOURCE_PICKUP, self::SOURCE_DELIVERY]);
    }

    /**
     * Delete files from storage when model is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            Storage::disk('public')->delete($image->file_path);
            if ($image->thumbnail_path) {
                Storage::disk('public')->delete($image->thumbnail_path);
            }
        });
    }
}
