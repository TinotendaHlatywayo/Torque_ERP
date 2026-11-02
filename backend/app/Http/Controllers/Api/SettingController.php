<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function branchIndex(Request $request, Branch $branch): JsonResponse
    {
        $settings = Setting::where('scope_type', 'branch')
            ->where('scope_id', $branch->id)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value['value'] ?? null]);

        return response()->json([
            'data' => $settings,
            'allowed_keys' => $this->settings->allowedKeys('branch'),
        ]);
    }

    public function branchUpdate(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['present'],
        ]);

        try {
            $setting = $this->settings->set('branch', $branch->id, $data['key'], $data['value'], $request->user(), app(AuditLogger::class));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => [
            'key' => $setting->key,
            'value' => $setting->value['value'] ?? null,
        ]]);
    }

    public function platformIndex(Request $request, Platform $platform): JsonResponse
    {
        $settings = Setting::where('scope_type', 'platform')
            ->where('scope_id', $platform->id)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value['value'] ?? null]);

        return response()->json([
            'data' => $settings,
            'allowed_keys' => $this->settings->allowedKeys('platform'),
        ]);
    }

    public function platformUpdate(Request $request, Platform $platform): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['present'],
        ]);

        try {
            $setting = $this->settings->set('platform', $platform->id, $data['key'], $data['value'], $request->user(), app(AuditLogger::class));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => [
            'key' => $setting->key,
            'value' => $setting->value['value'] ?? null,
        ]]);
    }
}
