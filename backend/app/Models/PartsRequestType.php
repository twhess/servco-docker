<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartsRequestType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function partsRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'request_type_id');
    }
}
