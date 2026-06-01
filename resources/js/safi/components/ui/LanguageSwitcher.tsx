import React from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';
import { normalizeLanguage, setCurrentLanguage } from '../../lib/language';

const languages = [
  { code: 'ru', label: 'RU' },
  { code: 'kk', label: 'KZ' },
  { code: 'en', label: 'EN' },
  { code: 'mn', label: 'MN' },
] as const;

export function LanguageSwitcher({ className, dark = false }: { className?: string, dark?: boolean }) {
  const { i18n } = useTranslation();

  const changeLanguage = (lng: string) => {
    const language = setCurrentLanguage(lng);
    void i18n.changeLanguage(language);
  };

  const currentLang = normalizeLanguage(i18n.resolvedLanguage || i18n.language);

  const bgClass = dark ? 'border-white/10 bg-white/10' : 'border-safi-green/5 bg-[#F5F5F0]';
  const activeClass = 'text-safi-gold';
  const inactiveClass = dark ? 'text-white opacity-50 hover:opacity-100' : 'text-safi-green opacity-40 hover:opacity-100';
  const dividerClass = dark ? 'bg-white/20' : 'bg-safi-green/20';

  return (
    <div className={cn('flex w-fit items-center gap-2 rounded-full border px-3 py-1.5', bgClass, className)}>
      {languages.map((language, index) => (
        <React.Fragment key={language.code}>
          {index > 0 && <div className={cn('mx-0.5 h-3 w-px', dividerClass)} />}
          <button
            type="button"
            onClick={() => changeLanguage(language.code)}
            className={cn('cursor-pointer text-[10px] font-bold leading-none transition-opacity sm:text-xs', currentLang === language.code ? activeClass : inactiveClass)}
          >
            {language.label}
          </button>
        </React.Fragment>
      ))}
    </div>
  );
}
