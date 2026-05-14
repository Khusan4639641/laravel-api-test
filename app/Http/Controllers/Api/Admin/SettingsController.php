<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $this->ensureDefaults();

        $settings = SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return response()->json([
            'settings' => $settings->pluck('value', 'key')->all(),
            'data' => SystemSettingResource::collection($settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['nullable', 'array'],
        ]);

        $payload = $validated['settings'] ?? $request->except(['_token', '_method']);

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9_.-]+$/i', $key)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $this->typeFor($value),
                    'group' => str_contains($key, '.') ? str($key)->before('.')->toString() : 'general',
                ]
            );
        }

        return $this->index();
    }

    private function ensureDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $this->typeFor($value),
                    'group' => str_contains($key, '.') ? str($key)->before('.')->toString() : 'general',
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'company.name' => 'Safi Life',
            'withdrawals.minimum_amount' => 10000,
            'withdrawals.methods.card' => true,
            'withdrawals.methods.business_account' => true,
            'withdrawals.methods.usdt' => false,
        ];
    }

    private function typeFor(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_numeric($value) => 'number',
            is_array($value) => 'array',
            default => 'string',
        };
    }
}
