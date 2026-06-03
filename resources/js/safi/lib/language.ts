export const LANGUAGE_STORAGE_KEY = 'safi_language';

export const supportedLanguages = ['ru', 'kz', 'kg', 'en', 'mn'] as const;

export type SupportedLanguage = typeof supportedLanguages[number];

export function normalizeLanguage(language?: string | null): SupportedLanguage {
  const normalized = String(language || '').toLowerCase().split(/[-_,;]/)[0];

  if (normalized === 'kk') {
    return 'kz';
  }

  return supportedLanguages.includes(normalized as SupportedLanguage) ? normalized as SupportedLanguage : 'ru';
}

export function getCurrentLanguage(): SupportedLanguage {
  if (typeof window === 'undefined') {
    return 'ru';
  }

  const storedLanguage = window.localStorage.getItem(LANGUAGE_STORAGE_KEY);
  const language = normalizeLanguage(storedLanguage || window.navigator.language);

  if (storedLanguage !== language) {
    window.localStorage.setItem(LANGUAGE_STORAGE_KEY, language);
  }

  return language;
}

export function setCurrentLanguage(language: string): SupportedLanguage {
  const normalizedLanguage = normalizeLanguage(language);

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LANGUAGE_STORAGE_KEY, normalizedLanguage);
  }

  return normalizedLanguage;
}
