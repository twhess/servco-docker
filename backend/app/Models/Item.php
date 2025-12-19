<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'description',
        'qr_code',
        'current_location_id',
        'status',
        'notes',
    ];

    // Relationships

    /**
     * Current location of this item
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'current_location_id');
    }

    /**
     * Movement history for this item
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ItemMovement::class);
    }

    /**
     * Latest movement record
     */
    public function latestMovement(): HasMany
    {
        return $this->movements()->latest('moved_at')->limit(1);
    }

    // Scopes

    /**
     * Scope for items at vendor
     */
    public function scopeAtVendor($query)
    {
        return $query->where('status', 'at_vendor');
    }

    /**
     * Scope for items in transit
     */
    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    /**
     * Scope for items at shop
     */
    public function scopeAtShop($query)
    {
        return $query->where('status', 'at_shop');
    }

    /**
     * Scope for delivered items
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope for items at a specific location
     */
    public function scopeAtLocation($query, $locationId)
    {
        return $query->where('current_location_id', $locationId);
    }

    // Helper Methods

    /**
     * Check if item has QR code
     */
    public function hasQrCode(): bool
    {
        return !empty($this->qr_code);
    }

    /**
     * Get status display text
     */
    public function getStatusDisplay(): string
    {
        return match($this->status) {
            'at_vendor' => 'At Vendor',
            'in_transit' => 'In Transit',
            'at_shop' => 'At Shop',
            'delivered' => 'Delivered',
            default => ucfirst($this->status),
        };
    }
}
