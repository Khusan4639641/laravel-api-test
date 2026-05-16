import React from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';

export function LanguageSwitcher({ className, dark = false }: { className?: string, dark?: boolean }) {
  const { i18n } = useTranslation();

  const changeLanguage = (lng: string) => {
    i18n.changeLanguage(lng);
  };

  const currentLang = i18n.language;

  const bgClass = dark ? 'border-white/10 bg-white/10' : 'border-safi-green/5 bg-[#F5F5F0]';
  const activeClass = 'text-safi-gold';
  const inactiveClass = dark ? 'text-white opacity-50 hover:opacity-100' : 'text-safi-green opacity-40 hover:opacity-100';
  const dividerClass = dark ? 'bg-white/20' : 'bg-safi-green/20';

  return (
    <div className={cn('flex w-fit items-center gap-2 rounded-full border px-3 py-1.5', bgClass, className)}>
      <button 
        onClick={() => changeLanguage('ru')}
        className={cn('text-[10px] font-bold leading-none transition-opacity sm:text-xs', currentLang === 'ru' ? activeClass : inactiveClass)}
      >
        RU
      </button>
      <div className={cn('mx-0.5 h-3 w-px', dividerClass)} />
      <button 
        onClick={() => changeLanguage('kg')}
        className={cn('text-[10px] font-bold leading-none transition-opacity sm:text-xs', currentLang === 'kg' ? activeClass : inactiveClass)}
      >
        KG
      </button>
      <div className={cn('mx-0.5 h-3 w-px', dividerClass)} />
      <button 
        onClick={() => changeLanguage('mn')}
        className={cn('text-[10px] font-bold leading-none transition-opacity sm:text-xs', currentLang === 'mn' ? activeClass : inactiveClass)}
      >
        MN
      </button>
    </div>
  );
}
