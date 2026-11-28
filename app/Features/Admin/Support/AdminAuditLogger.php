<?php

namespace App\Features\Admin\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Shared audit writer for admin console actions. */
final class AdminAuditLogger
{
    public function log(string $action, Model $entity, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => (string) $entity->getKey(),
            'before_values' => $this->redact($before),
            'after_values' => $this->redact($after),
            'created_at' => now(),
        ]);
    }

    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $walk = static function (mixed $value) use (&$walk): mixed {
            if (! is_array($value)) {
                return $value;
            }

            foreach ($value as $key => $item) {
                if (preg_match('/secret|token|password|private.?key|api.?key|credential/i', (string) $key)) {
                    $value[$key] = '[redacted]';
                } else {
                    $value[$key] = $walk($item);
                }
            }

            return $value;
        };

        return $walk($values);
    }
}
