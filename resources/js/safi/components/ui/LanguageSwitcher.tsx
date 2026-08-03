import React from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';
import {
  getCurrentLocale,
  LOCALE_CHANGED_EVENT,
  setCurrentLocale,
  type SupportedLocale,
} from '../../lib/language';
import { NoTranslate } from './NoTranslate';

export const languageOptions = [
  { code: 'ru', label: 'RU' },
  { code: 'kk', label: 'KZ' },
  { code: 'ky', label: 'KG' },
  { code: 'en', label: 'EN' },
  { code: 'mn', label: 'MN' },
] as const satisfies ReadonlyArray<{ code: SupportedLocale; label: string }>;

export function LanguageSwitcher({ className, dark = false }: { className?: string; dark?: boolean }) {
  const { i18n } = useTranslation();
  const [currentLocale, setCurrentLocaleState] = React.useState<SupportedLocale>(() => getCurrentLocale());

  React.useEffect(() => {
    const syncLocale = () => setCurrentLocaleState(getCurrentLocale());

    window.addEventListener(LOCALE_CHANGED_EVENT, syncLocale);
    window.addEventListener('storage', syncLocale);

    return () => {
      window.removeEventListener(LOCALE_CHANGED_EVENT, syncLocale);
      window.removeEventListener('storage', syncLocale);
    };
  }, []);

  const changeLanguage = (locale: SupportedLocale) => {
    const canonicalLocale = setCurrentLocale(locale);

    setCurrentLocaleState(canonicalLocale);
    void i18n.changeLanguage(canonicalLocale);
  };

  const bgClass = dark ? 'border-white/10 bg-white/10' : 'border-safi-green/5 bg-[#F5F5F0]';
  const inactiveClass = dark ? 'text-white opacity-50 hover:opacity-100' : 'text-safi-green opacity-40 hover:opacity-100';
  const dividerClass = dark ? 'bg-white/20' : 'bg-safi-green/20';

  return (
    <NoTranslate as="div" className={cn('flex w-fit flex-wrap items-center gap-2 rounded-full border px-3 py-1.5', bgClass, className)}>
      {languageOptions.map((language, index) => (
        <React.Fragment key={language.code}>
          {index > 0 && <div className={cn('mx-0.5 h-3 w-px', dividerClass)} />}
          <button
            type="button"
            onClick={() => changeLanguage(language.code)}
            aria-label={`Switch language to ${language.label}`}
            aria-pressed={currentLocale === language.code}
            className={cn(
              'cursor-pointer text-[10px] font-bold leading-none transition-opacity sm:text-xs',
              currentLocale === language.code ? 'text-safi-gold' : inactiveClass
            )}
          >
            {language.label}
          </button>
        </React.Fragment>
      ))}
    </NoTranslate>
  );
}
