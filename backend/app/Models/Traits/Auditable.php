<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    /**
     * Boot the Auditable trait.
     */
    protected static function bootAuditable(): void
    {
        // Track creations
        static::created(function (Model $model) {
            $model->auditEvent('created', [], $model->getAuditableAttributes());
        });

        // Track updates
        static::updated(function (Model $model) {
            $original = $model->getOriginal();
            $changes = $model->getChanges();

            // Remove timestamps and audit fields from tracking
            $ignore = ['updated_at', 'updated_by', 'created_at', 'created_by'];
            $changes = array_diff_key($changes, array_flip($ignore));
            $original = array_diff_key($original, array_flip($ignore));

            if (!empty($changes)) {
                // Get only the changed values from original
                $oldValues = array_intersect_key($original, $changes);
                $model->auditEvent('updated', $oldValues, $changes);
            }
        });

        // Track deletions
        static::deleted(function (Model $model) {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'deleted'
                : 'soft_deleted';
            $model->auditEvent($event, $model->getAuditableAttributes(), []);
        });

        // Track restorations (if using soft deletes)
        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::restored(function (Model $model) {
                $model->auditEvent('restored', [], $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Create an audit log entry.
     */
    protected function auditEvent(string $event, array $oldValues, array $newValues): void
    {
        // Only audit if we have a user authenticated
        if (!auth()->check()) {
            return;
        }

        $changedFields = array_keys($newValues);

        AuditLog::create([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'event' => $event,
            'user_id' => auth()->id(),
            'old_values' => !empty($oldValues) ? $oldValues : null,
            'new_values' => !empty($newValues) ? $newValues : null,
            'changed_fields' => !empty($changedFields) ? $changedFields : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $this->getAuditMetadata(),
        ]);
    }

    /**
     * Get attributes to include in audit.
     * Override this method to customize what's audited.
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // Exclude sensitive fields by default
        $exclude = array_merge(
            ['password', 'remember_token', 'pin_code'],
            $this->getAuditExclusions()
        );

        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * Additional fields to exclude from auditing.
     * Override in model to customize.
     */
    protected function getAuditExclusions(): array
    {
        return [];
    }

    /**
     * Additional metadata to store with audit.
     * Override in model to customize.
     */
    protected function getAuditMetadata(): ?array
    {
        return null;
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent audit activity.
     */
    public function recentActivity(int $limit = 10)
    {
        return $this->auditLogs()->with('user')->limit($limit)->get();
    }
}
