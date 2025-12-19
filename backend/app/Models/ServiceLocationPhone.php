<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLocationPhone extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'label',
        'phone_number',
        'extension',
        'is_primary',
        'is_public',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * The location this phone belongs to
     */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    /**
     * Get formatted phone number with extension
     */
    public function getFormattedNumber(): string
    {
        $formatted = $this->phone_number;
        if ($this->extension) {
            $formatted .= ' ext. ' . $this->extension;
        }
        return $formatted;
    }
}
