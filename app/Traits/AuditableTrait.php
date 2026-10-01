<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait AuditableTrait
{
    public static function bootAuditableTrait(): void
    {
        static::created(function (Model $model) {
            static::writeAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();

            // restore() soft delete lewat save(), jadi terlihat sebagai perubahan deleted_at
            $action = array_key_exists('deleted_at', $changes) ? 'restored' : 'updated';

            static::writeAudit($model, $action, $model->getRawOriginal(), $changes);
        });

        static::deleted(function (Model $model) {
            static::writeAudit($model, 'deleted', $model->getRawOriginal(), null);
        });
    }

    /** Kolom yang tidak pernah dicatat: rahasia dan noise. */
    protected static function auditIgnored(): array
    {
        return array_flip([
            'password',
            'remember_token',
            'created_at',
            'updated_at',
            'last_login_at',
        ]);
    }

    protected static function writeAudit(Model $model, string $action, ?array $old, ?array $new): void
    {
        $ignored = static::auditIgnored();

        if ($new !== null) {
            $new = array_diff_key($new, $ignored);

            // Tidak ada perubahan yang berarti (mis. hanya updated_at atau last_login_at)
            if ($new === []) {
                return;
            }
        }

        if ($old !== null) {
            $old = $new !== null
                ? array_intersect_key($old, $new)
                : array_diff_key($old, $ignored);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}