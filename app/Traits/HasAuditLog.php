<?php

namespace App\Traits;

use App\Models\AuditLog;

trait HasAuditLog
{
    /**
     * Fields never written to the audit log regardless of the model.
     */
    private static array $globalAuditExclude = [
        'password', 'pin', 'remember_token', 'email_verified_at',
        'created_at', 'updated_at',
    ];

    public static function bootHasAuditLog(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', [], $m->getAttributes()));

        static::updated(fn ($m) => $m->writeAudit('updated', $m->getOriginal(), $m->getChanges()));

        static::deleted(fn ($m) => $m->writeAudit('deleted', $m->getAttributes(), []));
    }

    private function writeAudit(string $action, array $old, array $new): void
    {
        $modelExclude = $this->auditExclude ?? [];
        $exclude      = array_merge(self::$globalAuditExclude, $modelExclude);

        $filter = fn (array $attrs) => collect($attrs)->except($exclude)->all();

        AuditLog::create([
            'user_id'         => auth()->id(),
            'action'          => $action,
            'auditable_type'  => static::class,
            'auditable_id'    => (string) $this->getKey(),
            'auditable_label' => $this->auditLabel(),
            'old_values'      => $action !== 'created'  ? $filter($old) : null,
            'new_values'      => $action !== 'deleted'   ? $filter($new) : null,
        ]);
    }

    /**
     * Override in model for a more descriptive label.
     */
    protected function auditLabel(): string
    {
        return $this->name ?? $this->title ?? (string) $this->getKey();
    }
}
