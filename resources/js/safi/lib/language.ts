export const LANGUAGE_STORAGE_KEY = 'safi_language';
export const VISUAL_LANGUAGE_STORAGE_KEY = 'safi_lang';

export const supportedLanguages = ['ru', 'kz', 'kg', 'en', 'mn'] as const;

export type SupportedLanguage = typeof supportedLanguages[number];

export function normalizeLanguage(language?: string | null): SupportedLanguage {
  const normalized = String(language || '').toLowerCase().split(/[-_,;]/)[0];

  if (normalized === 'kk') {
    return 'kz';
  }

  if (normalized === 'ky') {
    return 'kg';
  }

  return supportedLanguages.includes(normalized as SupportedLanguage) ? normalized as SupportedLanguage : 'ru';
}

export function toVisualLanguage(language?: string | null): 'ru' | 'kk' | 'ky' | 'en' | 'mn' {
  const normalizedLanguage = normalizeLanguage(language);

  if (normalizedLanguage === 'kz') {
    return 'kk';
  }

  if (normalizedLanguage === 'kg') {
    return 'ky';
  }

  return normalizedLanguage;
}

export function getCurrentLanguage(): SupportedLanguage {
  if (typeof window === 'undefined') {
    return 'ru';
  }

  const storedLanguage = window.localStorage.getItem(LANGUAGE_STORAGE_KEY)
    || window.localStorage.getItem(VISUAL_LANGUAGE_STORAGE_KEY);
  const language = normalizeLanguage(storedLanguage || 'ru');

  if (storedLanguage !== language) {
    window.localStorage.setItem(LANGUAGE_STORAGE_KEY, language);
  }

  window.localStorage.setItem(VISUAL_LANGUAGE_STORAGE_KEY, toVisualLanguage(language));

  return language;
}

export function setCurrentLanguage(language: string): SupportedLanguage {
  const normalizedLanguage = normalizeLanguage(language);

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LANGUAGE_STORAGE_KEY, normalizedLanguage);
    window.localStorage.setItem(VISUAL_LANGUAGE_STORAGE_KEY, toVisualLanguage(normalizedLanguage));
  }

  return normalizedLanguage;
}
