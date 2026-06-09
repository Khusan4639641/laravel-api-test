<?php

namespace App\Support;

use App\Models\SystemSetting;

class LegalSettings
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'company_legal_name' => 'ТОО "Safi Life Kazakhstan"',
            'company_bin' => '000000000000',
            'legal_address' => 'Республика Казахстан, г. Алматы, адрес компании уточняется в настройках',
            'actual_address' => 'Республика Казахстан, г. Алматы, офис компании уточняется в настройках',
            'bank_name' => 'Банк компании',
            'iban' => 'KZ000000000000000000',
            'bik' => 'XXXXKZKX',
            'kbe' => '17',
            'support_phone' => '+7 (700) 000-00-00',
            'support_email' => 'support@safilife.kz',
            'dispute_email' => 'dispute@safilife.kz',
            'website_url' => 'https://safilife.kz',
            'director_name' => 'Директор Safi Life',
            'privacy_email' => 'privacy@safilife.kz',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaults() as $key => $value) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => self::typeFor($value),
                    'group' => self::groupForKey($key),
                ]
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public static function publicValues(): array
    {
        $values = self::defaults();

        SystemSetting::query()
            ->whereIn('key', self::keys())
            ->get(['key', 'value'])
            ->each(function (SystemSetting $setting) use (&$values): void {
                $value = self::stringValue($setting->value);

                if ($value !== null) {
                    $values[$setting->key] = $value;
                }
            });

        return $values;
    }

    public static function groupForKey(string $key): string
    {
        return match ($key) {
            'support_phone', 'support_email', 'dispute_email', 'privacy_email' => 'contacts',
            'website_url' => 'company',
            default => str_contains($key, '.') ? explode('.', $key, 2)[0] : 'legal',
        };
    }

    public static function typeFor(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_numeric($value) => 'number',
            is_array($value) => 'array',
            default => 'string',
        };
    }

    private static function stringValue(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $normalized = trim((string) $value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }
}
