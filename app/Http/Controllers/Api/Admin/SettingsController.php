<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Support\LocalizedValue;
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
            'settings' => $settings
                ->mapWithKeys(fn (SystemSetting $setting): array => [
                    $setting->key => $this->localizedValue($setting->value),
                ])
                ->all(),
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
            'withdrawals.payout_period_days' => 14,
            'withdrawals.methods.card_account' => true,
            'withdrawals.methods.ip_account' => true,
            'withdrawals.methods.usdt' => false,
            'contacts.public' => 'Алматы, Казахстан',
            'support.email' => 'support@safilife.test',
            'support.phone' => '+7 700 000 00 00',
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

    private function localizedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $hasLanguageKeys = collect(LocalizedValue::LANGUAGES)
            ->contains(fn (string $language): bool => array_key_exists($language, $value));

        return $hasLanguageKeys ? LocalizedValue::get($value, $value['ru'] ?? null) : $value;
    }
}
