<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        Model|string $entity,
        ?array $before = null,
        ?array $after = null,
        ?string $branchId = null,
        ?User $actor = null,
        ?string $entityId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'branch_id' => $branchId,
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity instanceof Model ? $entity::class : $entity,
            'entity_id' => $entity instanceof Model ? (string) $entity->getKey() : ($entityId ?? ''),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $this->redact($before),
            'after' => $this->redact($after),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'card_number', 'cvv', 'access_token', 'refresh_token'];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact($value);

                continue;
            }

            if (in_array((string) $key, $sensitive, true)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
