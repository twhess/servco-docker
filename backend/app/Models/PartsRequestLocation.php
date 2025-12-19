<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequestLocation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'parts_request_id',
        'runner_user_id',
        'captured_at',
        'lat',
        'lng',
        'accuracy_m',
        'speed_mps',
        'source',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy_m' => 'decimal:2',
        'speed_mps' => 'decimal:2',
    ];

    public function partsRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'runner_user_id');
    }

    // Convert speed to mph
    public function getSpeedMphAttribute(): ?float
    {
        return $this->speed_mps ? round($this->speed_mps * 2.23694, 1) : null;
    }
}
