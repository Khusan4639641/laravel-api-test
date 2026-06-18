export const SAFI_TRANSLATE_STORAGE_KEY = 'safi_lang';
export const SAFI_LANGUAGE_CHANGED_EVENT = 'safi:language-changed';

const LEGACY_LANGUAGE_STORAGE_KEY = 'safi_language';
const GTRANSLATE_SCRIPT_ID = 'safi-gtranslate-widget';
const GTRANSLATE_SCRIPT_SRC = 'https://cdn.gtranslate.net/widgets/latest/dropdown.js';
const GTRANSLATE_WRAPPER_SELECTOR = '.gtranslate_wrapper';
const DEFAULT_LANGUAGE: SafiLanguage = 'ru';

export const safiLanguages = ['ru', 'kk', 'ky', 'en', 'mn'] as const;
export type SafiLanguage = typeof safiLanguages[number];
export type SafiAppLanguage = 'ru' | 'kz' | 'kg' | 'en' | 'mn';

interface GTranslateSettings {
  default_language: SafiLanguage;
  detect_browser_language: boolean;
  languages: SafiLanguage[];
  wrapper_selector: string;
}

declare global {
  interface Window {
    gtranslateSettings?: GTranslateSettings;
    doGTranslate?: (languagePair: string) => void;
  }
}

let loadPromise: Promise<boolean> | null = null;
let warnedLoadFailure = false;
let warnedApplyFailure = false;

export function normalizeGTranslateLanguage(language?: string | null): SafiLanguage {
  const normalized = String(language || '').trim().toLowerCase().split(/[-_,;]/)[0];

  if (normalized === 'kz') {
    return 'kk';
  }

  if (normalized === 'kg') {
    return 'ky';
  }

  return safiLanguages.includes(normalized as SafiLanguage)
    ? normalized as SafiLanguage
    : DEFAULT_LANGUAGE;
}

export function toAppLanguage(language: SafiLanguage | string): SafiAppLanguage {
  const normalized = normalizeGTranslateLanguage(language);

  if (normalized === 'kk') {
    return 'kz';
  }

  if (normalized === 'ky') {
    return 'kg';
  }

  return normalized;
}

export function getSavedLanguage(): SafiLanguage {
  if (!isBrowser()) {
    return DEFAULT_LANGUAGE;
  }

  const storedLanguage = window.localStorage.getItem(SAFI_TRANSLATE_STORAGE_KEY)
    || window.localStorage.getItem(LEGACY_LANGUAGE_STORAGE_KEY);
  const language = normalizeGTranslateLanguage(storedLanguage);

  saveLanguage(language, { emit: false });

  return language;
}

export function saveLanguage(language: SafiLanguage | string, options: { emit?: boolean } = {}): SafiLanguage {
  const normalized = normalizeGTranslateLanguage(language);

  if (!isBrowser()) {
    return normalized;
  }

  const previous = window.localStorage.getItem(SAFI_TRANSLATE_STORAGE_KEY);

  window.localStorage.setItem(SAFI_TRANSLATE_STORAGE_KEY, normalized);
  window.localStorage.setItem(LEGACY_LANGUAGE_STORAGE_KEY, toAppLanguage(normalized));
  document.documentElement.lang = normalized;

  if (options.emit !== false && previous !== normalized) {
    window.dispatchEvent(new CustomEvent(SAFI_LANGUAGE_CHANGED_EVENT, { detail: { language: normalized } }));
  }

  return normalized;
}

export async function loadGTranslateWidget(): Promise<boolean> {
  if (!isBrowser()) {
    return false;
  }

  ensureGTranslateSettings();

  if (findGTranslateSelect()) {
    return true;
  }

  if (loadPromise) {
    return loadPromise;
  }

  const existingScript = document.getElementById(GTRANSLATE_SCRIPT_ID)
    || document.querySelector(`script[src="${GTRANSLATE_SCRIPT_SRC}"]`);

  if (existingScript) {
    loadPromise = waitForGTranslateSelect().then(Boolean);
    return loadPromise;
  }

  loadPromise = new Promise<boolean>((resolve) => {
    const script = document.createElement('script');

    script.id = GTRANSLATE_SCRIPT_ID;
    script.src = GTRANSLATE_SCRIPT_SRC;
    script.defer = true;

    script.onload = () => {
      void waitForGTranslateSelect().then((select) => {
        resolve(Boolean(select));
      });
    };

    script.onerror = () => {
      script.remove();
      loadPromise = null;
      warnLoadFailure();
      resolve(false);
    };

    document.body.appendChild(script);
  });

  return loadPromise;
}

export async function changeGTranslateLanguage(language: SafiLanguage | string): Promise<boolean> {
  const normalized = saveLanguage(language);

  await loadGTranslateWidget();

  const applied = await applyLanguageWithRetry(normalized);

  if (!applied) {
    warnApplyFailure();
  }

  return applied;
}

export function applySavedGTranslateLanguage(): Promise<boolean> {
  return changeGTranslateLanguage(getSavedLanguage());
}

function ensureGTranslateSettings(): void {
  window.gtranslateSettings = {
    default_language: DEFAULT_LANGUAGE,
    detect_browser_language: false,
    languages: [...safiLanguages],
    wrapper_selector: GTRANSLATE_WRAPPER_SELECTOR,
  };
}

async function applyLanguageWithRetry(language: SafiLanguage, attempts = 16, delayMs = 250): Promise<boolean> {
  for (let attempt = 0; attempt < attempts; attempt += 1) {
    if (applyLanguageToWidget(language)) {
      return true;
    }

    await delay(delayMs);
  }

  return false;
}

function applyLanguageToWidget(language: SafiLanguage): boolean {
  const select = findGTranslateSelect();
  const value = select ? resolveSelectValue(select, language) : null;

  if (select && value !== null) {
    wakeGTranslateLibrary();
    select.value = value;
    select.dispatchEvent(new Event('input', { bubbles: true }));
    select.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
  }

  if (typeof window.doGTranslate === 'function') {
    window.doGTranslate(`${DEFAULT_LANGUAGE}|${language}`);
    return true;
  }

  return false;
}

function wakeGTranslateLibrary(): void {
  document.querySelectorAll<HTMLElement>(GTRANSLATE_WRAPPER_SELECTOR).forEach((element) => {
    element.dispatchEvent(new Event('focusin', { bubbles: true }));
    element.dispatchEvent(new Event('pointerenter'));
  });
}

function findGTranslateSelect(): HTMLSelectElement | null {
  if (!isBrowser()) {
    return null;
  }

  return document.querySelector<HTMLSelectElement>(`${GTRANSLATE_WRAPPER_SELECTOR} select, select.gt_selector`);
}

function resolveSelectValue(select: HTMLSelectElement, language: SafiLanguage): string | null {
  const options = Array.from(select.options);
  const exact = options.find((option) => option.value.toLowerCase() === language);

  if (exact) {
    return exact.value;
  }

  const pair = `${DEFAULT_LANGUAGE}|${language}`;
  const pairOption = options.find((option) => option.value.toLowerCase() === pair);

  if (pairOption) {
    return pairOption.value;
  }

  const matchingOption = options.find((option) => optionTargetsLanguage(option, language));

  return matchingOption?.value ?? null;
}

function optionTargetsLanguage(option: HTMLOptionElement, language: SafiLanguage): boolean {
  const values = [
    option.value,
    option.textContent || '',
    option.getAttribute('data-gt-lang') || '',
    option.getAttribute('data-lang') || '',
  ];

  return values.some((value) => {
    const normalizedValue = value.trim().toLowerCase();

    if (!normalizedValue) {
      return false;
    }

    const segments = normalizedValue.split(/[|/]/).filter(Boolean);
    const target = segments.length > 0 ? segments[segments.length - 1] : normalizedValue;

    return normalizeGTranslateLanguage(target) === language;
  });
}

function waitForGTranslateSelect(attempts = 20, delayMs = 250): Promise<HTMLSelectElement | null> {
  return new Promise((resolve) => {
    let currentAttempt = 0;

    const tick = () => {
      const select = findGTranslateSelect();

      if (select || currentAttempt >= attempts) {
        resolve(select);
        return;
      }

      currentAttempt += 1;
      window.setTimeout(tick, delayMs);
    };

    tick();
  });
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

function warnLoadFailure(): void {
  if (!warnedLoadFailure) {
    warnedLoadFailure = true;
    console.warn('[translate] GTranslate widget failed to load');
  }
}

function warnApplyFailure(): void {
  if (!warnedApplyFailure) {
    warnedApplyFailure = true;
    console.warn('[translate] GTranslate widget failed to load');
  }
}

function isBrowser(): boolean {
  return typeof window !== 'undefined' && typeof document !== 'undefined';
}
