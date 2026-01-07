<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    use HasFactory, HasAuditFields, Auditable;

    protected $fillable = [
        'gmail_message_id',
        'gmail_thread_id',
        'subject',
        'from_email',
        'from_name',
        'to_emails',
        'email_date',
        'snippet',
        'body_text',
        'body_html',
        'has_attachments',
        'attachment_count',
        'status',
        'processing_notes',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'to_emails' => 'array',
        'email_date' => 'datetime',
        'has_attachments' => 'boolean',
        'processed_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get all attachments for this email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Get the user who processed this email.
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
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

    // Scopes

    /**
     * Scope to emails with attachments.
     */
    public function scopeWithAttachments($query)
    {
        return $query->where('has_attachments', true);
    }

    /**
     * Scope to unprocessed emails.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('status', 'unprocessed');
    }

    /**
     * Scope to processed emails.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope to search emails by subject or sender.
     */
    public function scopeSearch($query, string $term)
    {
        $term = '%' . trim($term) . '%';

        return $query->where(function ($q) use ($term) {
            $q->where('subject', 'like', $term)
              ->orWhere('from_email', 'like', $term)
              ->orWhere('from_name', 'like', $term);
        });
    }

    // Accessors

    /**
     * Get the sender display name.
     */
    public function getSenderDisplayAttribute(): string
    {
        return $this->from_name ?: $this->from_email;
    }

    /**
     * Get a preview of the email content.
     */
    public function getPreviewAttribute(): string
    {
        return $this->snippet ?: substr(strip_tags($this->body_text ?? $this->body_html ?? ''), 0, 200);
    }
}
