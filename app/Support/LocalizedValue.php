<?php

namespace App\Support;

final class LocalizedValue
{
    public const LANGUAGES = ['ru', 'kz', 'kg', 'en', 'mn'];

    public static function normalize(?string $language): string
    {
        $language = strtolower((string) $language);
        $language = preg_split('/[-_,;]/', $language)[0] ?? '';

        return match ($language) {
            'kk' => 'kz',
            'kz', 'kg', 'en', 'mn', 'ru' => $language,
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

        $localized = $translations[$language]
            ?? ($language === 'kz' ? ($translations['kk'] ?? null) : null)
            ?? ($language === 'kg' ? ($translations['en'] ?? null) : null)
            ?? ($language !== 'ru' ? ($translations['en'] ?? null) : null)
            ?? $translations['ru']
            ?? null;

        if ($localized === null || $localized === '') {
            return $fallback;
        }

        return $localized;
    }
}
