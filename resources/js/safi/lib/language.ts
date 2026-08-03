export const LOCALE_STORAGE_KEY = 'safi_locale';
export const LOCALE_COOKIE_KEY = 'safi_locale';
export const LOCALE_CHANGED_EVENT = 'safi:locale-changed';

const LEGACY_STORAGE_KEYS = ['safi_lang', 'safi_language'] as const;
const LEGACY_TRANSLATOR_COOKIE = 'googtrans';
const LOCALE_COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

export const supportedLocales = ['ru', 'kk', 'ky', 'en', 'mn'] as const;
export type SupportedLocale = typeof supportedLocales[number];

// Compatibility aliases are accepted only while reading an old saved value or
// an incoming header. The locale stored and sent by the application is always
// canonical: kk for Kazakh and ky for Kyrgyz.
export function normalizeLocale(locale?: string | null): SupportedLocale {
  const candidate = String(locale || '')
    .trim()
    .toLowerCase()
    .split(',')[0]
    .split(';')[0]
    .split(/[-_]/)[0];

  if (candidate === 'kz') {
    return 'kk';
  }

  if (candidate === 'kg') {
    return 'ky';
  }

  return supportedLocales.includes(candidate as SupportedLocale)
    ? candidate as SupportedLocale
    : 'ru';
}

export function getCurrentLocale(): SupportedLocale {
  if (!isBrowser()) {
    return 'ru';
  }

  const saved = window.localStorage.getItem(LOCALE_STORAGE_KEY)
    || LEGACY_STORAGE_KEYS.map((key) => window.localStorage.getItem(key)).find(Boolean)
    || readCookie(LOCALE_COOKIE_KEY);
  const locale = normalizeLocale(saved);

  persistLocale(locale, false);
  clearLegacyTranslatorState();

  return locale;
}

export function setCurrentLocale(locale: string): SupportedLocale {
  const normalized = normalizeLocale(locale);

  if (!isBrowser()) {
    return normalized;
  }

  const previous = window.localStorage.getItem(LOCALE_STORAGE_KEY);

  persistLocale(normalized, true);
  clearLegacyTranslatorState();

  if (previous !== normalized) {
    window.dispatchEvent(new CustomEvent(LOCALE_CHANGED_EVENT, { detail: { locale: normalized } }));
  }

  return normalized;
}

// Backwards-compatible function name for API callers. Its value is canonical.
export const getCurrentLanguage = getCurrentLocale;
export const normalizeLanguage = normalizeLocale;
export type SupportedLanguage = SupportedLocale;

function persistLocale(locale: SupportedLocale, cleanLegacyKeys: boolean): void {
  window.localStorage.setItem(LOCALE_STORAGE_KEY, locale);

  if (cleanLegacyKeys) {
    LEGACY_STORAGE_KEYS.forEach((key) => window.localStorage.removeItem(key));
  }

  document.documentElement.lang = locale;
  document.cookie = `${LOCALE_COOKIE_KEY}=${locale}; Path=/; Max-Age=${LOCALE_COOKIE_MAX_AGE}; SameSite=Lax`;
}

function clearLegacyTranslatorState(): void {
  LEGACY_STORAGE_KEYS.forEach((key) => window.localStorage.removeItem(key));

  if (!readCookie(LEGACY_TRANSLATOR_COOKIE)) {
    return;
  }

  document.cookie = `${LEGACY_TRANSLATOR_COOKIE}=; Path=/; Max-Age=0; SameSite=Lax`;
}

function readCookie(name: string): string | null {
  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

function isBrowser(): boolean {
  return typeof window !== 'undefined' && typeof document !== 'undefined';
}
