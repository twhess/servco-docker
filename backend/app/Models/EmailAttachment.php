<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use HasFactory, HasAuditFields, Auditable;

    protected $fillable = [
        'email_message_id',
        'gmail_attachment_id',
        'filename',
        'mime_type',
        'file_size',
        'drive_file_id',
        'drive_web_view_link',
        'drive_download_link',
        'status',
        'error_message',
        'downloaded_at',
        'downloaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'downloaded_at' => 'datetime',
    ];

    protected $appends = ['formatted_file_size'];

    // Relationships

    /**
     * Get the email message this attachment belongs to.
     */
    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    /**
     * Get the user who downloaded this attachment.
     */
    public function downloadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'downloaded_by');
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors

    /**
     * Get human-readable file size.
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
     * Get file extension from filename.
     */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    // Scopes

    /**
     * Scope to pending attachments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to downloaded attachments.
     */
    public function scopeDownloaded($query)
    {
        return $query->where('status', 'downloaded');
    }

    /**
     * Scope to attachments with errors.
     */
    public function scopeWithErrors($query)
    {
        return $query->where('status', 'error');
    }

    // Helper Methods

    /**
     * Check if this attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if this attachment has been downloaded to Drive.
     */
    public function isDownloaded(): bool
    {
        return $this->status === 'downloaded' && !empty($this->drive_file_id);
    }
}
