<?php

namespace App\Support;

final class LocalizedValue
{
    public const LANGUAGES = ['ru', 'kk', 'ky', 'en', 'mn'];

    public const LEGACY_LANGUAGE_KEYS = ['kz', 'kg'];

    public static function normalize(?string $language): string
    {
        $language = strtolower(trim((string) $language));
        $language = explode(',', $language)[0] ?? '';
        $language = explode(';', $language)[0] ?? '';
        $language = preg_split('/[-_]/', $language)[0] ?? '';

        return match ($language) {
            'kz' => 'kk',
            'kg' => 'ky',
            'kk', 'ky', 'en', 'mn', 'ru' => $language,
            default => 'ru',
        };
    }

    public static function current(): string
    {
        return self::normalize(app()->getLocale());
    }

    /**
     * @param  array<string, mixed>|null  $translations
     */
    public static function get(?array $translations, mixed $fallback = null, ?string $language = null): mixed
    {
        if (! is_array($translations) || $translations === []) {
            return $fallback;
        }

        $language = self::normalize($language ?: app()->getLocale());
        $languageKeys = match ($language) {
            'kk' => ['kk', 'kz'],
            'ky' => ['ky', 'kg'],
            default => [$language],
        };

        foreach ($languageKeys as $languageKey) {
            $localized = $translations[$languageKey] ?? null;

            if ($localized !== null && $localized !== '') {
                return $localized;
            }
        }

        $russian = $translations['ru'] ?? null;

        return $russian !== null && $russian !== '' ? $russian : $fallback;
    }
}
