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
        // Parts Runner routing fields
        'run_instance_id',
        'pickup_stop_id',
        'dropoff_stop_id',
        'parent_request_id',
        'segment_order',
        'is_segment',
        'item_id',
        'scheduled_for_date',
        'not_before_datetime',
        'override_run_instance_id',
        'override_reason',
        'override_by_user_id',
        'override_at',
        'is_archived',
        'archived_at',
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
        // Parts Runner routing casts
        'segment_order' => 'integer',
        'is_segment' => 'boolean',
        'scheduled_for_date' => 'date',
        'not_before_datetime' => 'datetime',
        'override_at' => 'datetime',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
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

    /**
     * Run instance this request is assigned to
     */
    public function runInstance(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class, 'run_instance_id');
    }

    /**
     * Override run instance (admin-selected)
     */
    public function overrideRunInstance(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class, 'override_run_instance_id');
    }

    /**
     * Pickup stop on the route
     */
    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'pickup_stop_id');
    }

    /**
     * Dropoff stop on the route
     */
    public function dropoffStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'dropoff_stop_id');
    }

    /**
     * Parent request (for multi-leg segments)
     */
    public function parentRequest(): BelongsTo
    {
        return $this->belongsTo(PartsRequest::class, 'parent_request_id');
    }

    /**
     * Child segments (for multi-leg routing)
     */
    public function childSegments(): HasMany
    {
        return $this->hasMany(PartsRequest::class, 'parent_request_id')->orderBy('segment_order');
    }

    /**
     * Linked inventory item (optional)
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * User who overrode auto-assignment
     */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
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

    /**
     * Scope to requests scheduled for a specific date
     */
    public function scopeScheduledFor($query, $date)
    {
        return $query->whereDate('scheduled_for_date', $date);
    }

    /**
     * Scope to requests scheduled for today
     */
    public function scopeScheduledToday($query)
    {
        return $query->whereDate('scheduled_for_date', today());
    }

    /**
     * Scope to requests visible to runners (today or past, not future scheduled)
     */
    public function scopeVisibleToRunner($query)
    {
        return $query->where(function($q) {
            $q->whereNull('scheduled_for_date')
              ->orWhereDate('scheduled_for_date', '<=', today());
        });
    }

    /**
     * Scope to future scheduled requests (dispatcher view only)
     */
    public function scopeFutureScheduled($query)
    {
        return $query->whereDate('scheduled_for_date', '>', today());
    }

    /**
     * Scope to non-archived requests
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope to archived requests
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope to parent requests only (not segments)
     */
    public function scopeParentsOnly($query)
    {
        return $query->where('is_segment', false);
    }

    /**
     * Scope to segment requests only
     */
    public function scopeSegmentsOnly($query)
    {
        return $query->where('is_segment', true);
    }

    /**
     * Scope to requests assigned to a specific run
     */
    public function scopeForRun($query, int $runId)
    {
        return $query->where('run_instance_id', $runId);
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

    /**
     * Check if this is a multi-leg request (has child segments)
     */
    public function isMultiLeg(): bool
    {
        return $this->childSegments()->exists();
    }

    /**
     * Check if this is a segment (part of multi-leg request)
     */
    public function isSegment(): bool
    {
        return $this->is_segment;
    }

    /**
     * Get root request (parent if segment, self if not)
     */
    public function getRootRequest(): PartsRequest
    {
        return $this->isSegment() ? $this->parentRequest : $this;
    }

    /**
     * Check if transfer type request needs staging
     */
    public function requiresStaging(): bool
    {
        if ($this->requestType->name !== 'transfer') {
            return false;
        }

        // Check if status indicates not yet staged
        return !in_array($this->status->name ?? '', ['ready_to_transfer', 'picked_up', 'delivered']);
    }

    /**
     * Get available actions for this request based on current state and user
     */
    public function availableActions(User $user): \Illuminate\Support\Collection
    {
        $userRole = $this->determineUserRole($user);

        return PartsRequestAction::active()
            ->forRequestType($this->request_type_id)
            ->fromStatus($this->status_id)
            ->forRole($userRole)
            ->ordered()
            ->get();
    }

    /**
     * Determine user's role for action permissions
     */
    private function determineUserRole(User $user): string
    {
        if ($user->hasPermission('parts_requests.assign_to_run')) {
            return 'dispatcher';
        }

        if ($user->hasPermission('parts_requests.pickup')) {
            return 'runner';
        }

        if ($user->hasPermission('parts_requests.stage_transfer')) {
            return 'shop_staff';
        }

        return 'shop_staff'; // Default fallback
    }

    /**
     * Check if this request is scheduled for a future date
     */
    public function isScheduledForFuture(): bool
    {
        return $this->scheduled_for_date && $this->scheduled_for_date->gt(today());
    }

    /**
     * Check if this request is visible today (scheduled for today or past, or not scheduled)
     */
    public function isVisibleToday(): bool
    {
        return !$this->scheduled_for_date || $this->scheduled_for_date->lte(today());
    }

    /**
     * Create item movement record when picked up or delivered
     * Called from RequestWorkflowService after status changes
     */
    public function createItemMovement(string $stage, int $fromLocationId, int $toLocationId, User $user): ?ItemMovement
    {
        if (!$this->item_id) {
            return null;
        }

        // Create ItemMovement record via InventoryIntegrationService
        // This is placeholder - actual implementation in service layer
        return null;
    }

    /**
     * Check if admin has overridden auto-assignment
     */
    public function hasOverride(): bool
    {
        return !is_null($this->override_run_instance_id);
    }

    /**
     * Get effective run instance (override if exists, otherwise assigned)
     */
    public function getEffectiveRunInstance(): ?RunInstance
    {
        if ($this->hasOverride()) {
            return $this->overrideRunInstance;
        }

        return $this->runInstance;
    }
}
