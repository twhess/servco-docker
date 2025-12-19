<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartsRequestStatus extends Model
{
    use HasFactory;

    protected $table = 'parts_request_statuses';
    protected $fillable = ['name'];

    public function partsRequests(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'status_id');
    }
}
