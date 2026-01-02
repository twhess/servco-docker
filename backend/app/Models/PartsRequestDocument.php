<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartsRequestDocument extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'parts_request_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'uploaded_by_user_id',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    protected $appends = ['url', 'formatted_file_size'];

    // Relationships

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    // Accessors

    /**
     * Get public URL for the document (served through API download route)
     */
    public function getUrlAttribute(): string
    {
        // Return API download URL since storage symlink may not exist in Docker
        return url("/api/parts-requests/{$this->parts_request_id}/documents/{$this->id}/download");
    }

    /**
     * Get formatted file size (KB, MB, etc.)
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Check if document is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if document is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if document can be previewed in browser
     */
    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    /**
     * Get file extension from original filename
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
    }

    /**
     * Get icon name based on file type
     */
    public function getIconName(): string
    {
        if ($this->isImage()) {
            return 'image';
        }

        if ($this->isPdf()) {
            return 'picture_as_pdf';
        }

        $extension = $this->getExtension();

        return match ($extension) {
            'doc', 'docx' => 'description',
            'xls', 'xlsx' => 'table_chart',
            'zip', 'rar', '7z' => 'folder_zip',
            'txt' => 'article',
            default => 'insert_drive_file',
        };
    }

    /**
     * Delete the file from storage when model is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            Storage::disk('public')->delete($document->file_path);
        });
    }
}
