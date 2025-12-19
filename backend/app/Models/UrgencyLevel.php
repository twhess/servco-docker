<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrgencyLevel extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function partsRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'urgency_id');
    }
}
