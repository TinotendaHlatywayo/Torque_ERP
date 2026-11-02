<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SettingService
{
    /**
     * @return array<int, string>
     */
    public function allowedKeys(string $scopeType): array
    {
        return match ($scopeType) {
            'branch' => ['registration_notes'],
            'platform' => ['registration_notes'],
            default => [],
        };
    }

    public function get(string $scopeType, string $scopeId, string $key): mixed
    {
        $setting = Setting::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->first();

        return $setting?->value;
    }

    public function set(string $scopeType, string $scopeId, string $key, mixed $value, User $actor, AuditLogger $audit): Setting
    {
        if (! in_array($key, $this->allowedKeys($scopeType), true)) {
            throw new \InvalidArgumentException('The setting key is not allowed for this scope.');
        }

        return DB::transaction(function () use ($scopeType, $scopeId, $key, $value, $actor, $audit): Setting {
            $setting = Setting::where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $before = $setting?->value;

            if (! $setting) {
                $setting = Setting::create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'key' => $key,
                    'value' => ['value' => $value],
                    'updated_by' => $actor->id,
                ]);
            } else {
                $setting->update([
                    'value' => ['value' => $value],
                    'updated_by' => $actor->id,
                ]);
            }

            $audit->log(
                'setting.updated',
                $setting->refresh(),
                $before !== null ? ['key' => $key, 'value' => $before] : null,
                ['key' => $key, 'value' => $value],
                $scopeType === 'branch' ? $scopeId : null,
                $actor,
            );

            return $setting->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $key, ?string $branchId, ?string $platformId): mixed
    {
        if ($branchId !== null) {
            $branchValue = $this->get('branch', $branchId, $key);

            if ($branchValue !== null) {
                return $branchValue['value'] ?? null;
            }
        }

        if ($platformId !== null) {
            $platformValue = $this->get('platform', $platformId, $key);

            if ($platformValue !== null) {
                return $platformValue['value'] ?? null;
            }
        }

        return null;
    }
}
