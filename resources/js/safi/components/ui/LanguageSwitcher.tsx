import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';
import {
  SAFI_LANGUAGE_CHANGED_EVENT,
  changeGTranslateLanguage,
  getSavedLanguage,
  saveLanguage,
  SafiLanguage,
  toAppLanguage,
} from '../../lib/gtranslate';

const languages = [
  { code: 'ru', label: 'RU' },
  { code: 'kk', label: 'KZ' },
  { code: 'ky', label: 'KG' },
  { code: 'en', label: 'EN' },
  { code: 'mn', label: 'MN' },
] as const;

export function LanguageSwitcher({ className, dark = false }: { className?: string, dark?: boolean }) {
  const { i18n } = useTranslation();
  const [currentLang, setCurrentLang] = useState<SafiLanguage>(() => getSavedLanguage());

  useEffect(() => {
    const syncLanguage = () => {
      setCurrentLang(getSavedLanguage());
    };
    const handleLanguageChanged = () => {
      setCurrentLang(getSavedLanguage());
    };

    window.addEventListener(SAFI_LANGUAGE_CHANGED_EVENT, handleLanguageChanged);
    window.addEventListener('storage', syncLanguage);

    return () => {
      window.removeEventListener(SAFI_LANGUAGE_CHANGED_EVENT, handleLanguageChanged);
      window.removeEventListener('storage', syncLanguage);
    };
  }, []);

  const changeLanguage = (lng: SafiLanguage) => {
    const language = saveLanguage(lng);

    setCurrentLang(language);
    void i18n.changeLanguage(toAppLanguage(language));
    void changeGTranslateLanguage(language);
  };

  const bgClass = dark ? 'border-white/10 bg-white/10' : 'border-safi-green/5 bg-[#F5F5F0]';
  const activeClass = 'text-safi-gold';
  const inactiveClass = dark ? 'text-white opacity-50 hover:opacity-100' : 'text-safi-green opacity-40 hover:opacity-100';
  const dividerClass = dark ? 'bg-white/20' : 'bg-safi-green/20';

  return (
    <div className={cn('notranslate flex w-fit items-center gap-2 rounded-full border px-3 py-1.5', bgClass, className)} translate="no">
      {languages.map((language, index) => (
        <React.Fragment key={language.code}>
          {index > 0 && <div className={cn('mx-0.5 h-3 w-px', dividerClass)} />}
          <button
            type="button"
            onClick={() => changeLanguage(language.code)}
            aria-pressed={currentLang === language.code}
            className={cn('cursor-pointer text-[10px] font-bold leading-none transition-opacity sm:text-xs', currentLang === language.code ? activeClass : inactiveClass)}
            translate="no"
          >
            <span className="notranslate" translate="no">{language.label}</span>
          </button>
        </React.Fragment>
      ))}
    </div>
  );
}
