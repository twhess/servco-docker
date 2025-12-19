<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartsRequest extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields, Auditable;

    protected $fillable = [
        'request_type_id',
        'reference_number',
        'vendor_name',
        'customer_name',
        'customer_phone',
        'customer_address',
        'customer_lat',
        'customer_lng',
        'origin_location_id',
        'origin_area_id',
        'origin_address',
        'origin_lat',
        'origin_lng',
        'receiving_location_id',
        'receiving_area_id',
        'requested_at',
        'requested_by_user_id',
        'pickup_run',
        'urgency_id',
        'status_id',
        'details',
        'special_instructions',
        'slack_notify_pickup',
        'slack_notify_delivery',
        'slack_channel',
        'assigned_runner_user_id',
        'assigned_at',
        'last_modified_by_user_id',
        'last_modified_at',
    ];

    protected $casts = [
        'customer_lat' => 'decimal:7',
        'customer_lng' => 'decimal:7',
        'origin_lat' => 'decimal:7',
        'origin_lng' => 'decimal:7',
        'requested_at' => 'datetime',
        'assigned_at' => 'datetime',
        'last_modified_at' => 'datetime',
        'pickup_run' => 'boolean',
        'slack_notify_pickup' => 'boolean',
        'slack_notify_delivery' => 'boolean',
    ];

    // Relationships

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(PartsRequestType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PartsRequestStatus::class, 'status_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(UrgencyLevel::class, 'urgency_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'origin_location_id');
    }

    public function originArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class, 'origin_area_id');
    }

    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'receiving_location_id');
    }

    public function receivingArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class, 'receiving_area_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function assignedRunner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_runner_user_id');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PartsRequestEvent::class)->orderBy('event_at', 'desc');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PartsRequestPhoto::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(PartsRequestLocation::class)->orderBy('captured_at', 'desc');
    }

    // Scopes

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_runner_user_id', $userId);
    }

    public function scopeByStatus($query, $statusName)
    {
        return $query->whereHas('status', function($q) use ($statusName) {
            $q->where('name', $statusName);
        });
    }

    public function scopeByUrgency($query, $urgencyName)
    {
        return $query->whereHas('urgency', function($q) use ($urgencyName) {
            $q->where('name', $urgencyName);
        });
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_runner_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('status', function($q) {
            $q->whereNotIn('name', ['delivered', 'canceled']);
        });
    }

    // Helper Methods

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_runner_user_id === $user->id;
    }

    public function canBeModifiedBy(User $user): bool
    {
        // Admins and dispatchers can always modify
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Assigned runner can update certain fields
        if ($this->isAssignedTo($user)) {
            return true;
        }

        return false;
    }

    public function requiresPickupPhoto(): bool
    {
        return in_array($this->requestType->name ?? '', ['pickup', 'transfer']);
    }

    public function requiresDeliveryPhoto(): bool
    {
        return true; // All requests require delivery photo
    }

    public function hasPickupPhoto(): bool
    {
        return $this->photos()->where('stage', 'pickup')->exists();
    }

    public function hasDeliveryPhoto(): bool
    {
        return $this->photos()->where('stage', 'delivery')->exists();
    }

    public function getOriginCoordinates(): ?array
    {
        if ($this->origin_lat && $this->origin_lng) {
            return ['lat' => (float)$this->origin_lat, 'lng' => (float)$this->origin_lng];
        }
        return null;
    }

    public function getDestinationCoordinates(): ?array
    {
        if ($this->customer_lat && $this->customer_lng) {
            return ['lat' => (float)$this->customer_lat, 'lng' => (float)$this->customer_lng];
        }
        if ($this->receivingLocation) {
            return [
                'lat' => (float)$this->receivingLocation->latitude,
                'lng' => (float)$this->receivingLocation->longitude
            ];
        }
        return null;
    }

    // Generate unique reference number
    public static function generateReferenceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastRequest = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRequest ? ((int)substr($lastRequest->reference_number, -4)) + 1 : 1;

        return 'PR-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
