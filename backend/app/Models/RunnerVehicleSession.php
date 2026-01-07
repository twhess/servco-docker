<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunnerVehicleSession extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'user_id',
        'run_id',
        'vehicle_location_id',
        'is_generic',
        'generic_vehicle_type',
        'generic_vehicle_description',
        'generic_license_plate',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_generic' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(RunInstance::class, 'run_id');
    }

    public function vehicleLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'vehicle_location_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForRun($query, int $runId)
    {
        return $query->where('run_id', $runId);
    }

    // Accessors

    /**
     * Get a display name for the vehicle.
     */
    public function getVehicleDisplayNameAttribute(): string
    {
        if ($this->is_generic) {
            $parts = [];
            if ($this->generic_vehicle_description) {
                $parts[] = $this->generic_vehicle_description;
            } elseif ($this->generic_vehicle_type) {
                $parts[] = ucfirst($this->generic_vehicle_type);
            } else {
                $parts[] = 'Generic Vehicle';
            }
            if ($this->generic_license_plate) {
                $parts[] = "({$this->generic_license_plate})";
            }
            return implode(' ', $parts);
        }

        return $this->vehicleLocation?->name ?? 'Unknown Vehicle';
    }

    // Methods

    /**
     * End this vehicle session.
     */
    public function end(): void
    {
        $this->ended_at = now();
        $this->save();
    }

    /**
     * Get the current active session for a user.
     */
    public static function getActiveForUser(int $userId): ?self
    {
        return static::forUser($userId)->active()->first();
    }

    /**
     * Start a new vehicle session for a user with a known vehicle.
     */
    public static function startWithVehicle(int $userId, int $vehicleLocationId, ?int $runId = null): self
    {
        // End any existing active session
        static::forUser($userId)->active()->update(['ended_at' => now()]);

        return static::create([
            'user_id' => $userId,
            'run_id' => $runId,
            'vehicle_location_id' => $vehicleLocationId,
            'is_generic' => false,
            'started_at' => now(),
        ]);
    }

    /**
     * Start a new vehicle session for a user with generic vehicle details.
     */
    public static function startWithGeneric(
        int $userId,
        string $vehicleType,
        ?string $description = null,
        ?string $licensePlate = null,
        ?int $runId = null
    ): self {
        // End any existing active session
        static::forUser($userId)->active()->update(['ended_at' => now()]);

        return static::create([
            'user_id' => $userId,
            'run_id' => $runId,
            'is_generic' => true,
            'generic_vehicle_type' => $vehicleType,
            'generic_vehicle_description' => $description,
            'generic_license_plate' => $licensePlate,
            'started_at' => now(),
        ]);
    }
}
