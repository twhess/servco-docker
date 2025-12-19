<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLocationEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'label',
        'email',
        'is_primary',
        'is_public',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * The location this email belongs to
     */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}
