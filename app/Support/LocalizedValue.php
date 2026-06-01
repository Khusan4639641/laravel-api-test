<?php

namespace App\Support;

final class LocalizedValue
{
    public const LANGUAGES = ['ru', 'kk', 'en', 'mn'];

    public static function normalize(?string $language): string
    {
        $language = strtolower((string) $language);
        $language = preg_split('/[-_,;]/', $language)[0] ?? '';

        return match ($language) {
            'kg', 'kz' => 'kk',
            'kk', 'en', 'mn', 'ru' => $language,
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

        $localized = $translations[$language] ?? $translations['ru'] ?? null;

        if ($localized === null || $localized === '') {
            return $fallback;
        }

        return $localized;
    }
}
