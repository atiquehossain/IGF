<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminAuditService
{
    public function record(
        ?Admin $actor,
        string $action,
        Model|string|null $target = null,
        array $changes = [],
        array $context = [],
        string $outcome = 'success'
    ): AdminAuditEvent {
        [$targetType, $targetId, $targetLabel] = $this->targetIdentity($target);
        $request = app()->bound('request') ? request() : null;
        $key = (string) config('app.key', 'ignite-admin-audit');

        return AdminAuditEvent::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'actor_admin_id' => $actor?->getKey(),
            'actor_name_snapshot' => $actor?->username,
            'action' => Str::limit($action, 100, ''),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label_snapshot' => $targetLabel,
            'outcome' => in_array($outcome, ['success', 'denied', 'failed'], true) ? $outcome : 'success',
            'changes' => $changes === [] ? null : $this->sanitize($changes),
            'context' => $this->sanitize(array_filter(array_merge([
                'route' => $request?->route()?->getName(),
                'method' => $request?->method(),
            ], $context), fn ($value) => $value !== null && $value !== '')) ?: null,
            'ip_hash' => ($ip = $request?->ip()) ? hash_hmac('sha256', $ip, $key) : null,
            'user_agent_hash' => ($agent = $request?->userAgent()) ? hash('sha256', $agent) : null,
            'created_at' => now(),
        ]);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function targetIdentity(Model|string|null $target): array
    {
        if (is_string($target)) {
            return [Str::limit($target, 100, ''), null, null];
        }

        if (!$target) {
            return [null, null, null];
        }

        $label = match (true) {
            $target instanceof Admin => $target->username,
            $target instanceof Role => $target->name,
            default => class_basename($target) . ' #' . $target->getKey(),
        };

        return [
            Str::limit($target->getMorphClass(), 100, ''),
            Str::limit((string) $target->getKey(), 64, ''),
            Str::limit((string) $label, 150, ''),
        ];
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|secret|token|credential|authorization|cookie|private[_-]?key|email|mobile|phone|address|image/i', $key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitize($childValue, is_string($childKey) ? $childKey : null);
            }

            return $clean;
        }

        if (is_string($value)) {
            return Str::limit($value, 1000, '…');
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
