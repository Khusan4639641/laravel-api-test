import React from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';

export function LanguageSwitcher({ className, dark = false }: { className?: string, dark?: boolean }) {
  const { i18n } = useTranslation();

  const changeLanguage = (lng: string) => {
    i18n.changeLanguage(lng);
  };

  const currentLang = i18n.language;

  const bgClass = dark ? "border-white/10 bg-white/10" : "border-safi-border bg-safi-cream/80";
  const activeClass = dark ? "bg-white text-safi-green shadow-sm" : "bg-white text-safi-green shadow-sm";
  const inactiveClass = dark ? "text-white/55 hover:text-white" : "text-safi-muted hover:text-safi-green";

  return (
    <div className={cn("flex w-fit items-center gap-1 rounded-full border p-1", bgClass, className)}>
      <button 
        onClick={() => changeLanguage('ru')}
        className={cn("rounded-full px-2.5 py-1 text-[10px] font-extrabold leading-none tracking-[0.12em] transition-colors sm:text-xs", currentLang === 'ru' ? activeClass : inactiveClass)}
      >
        RU
      </button>
      <button 
        onClick={() => changeLanguage('kg')}
        className={cn("rounded-full px-2.5 py-1 text-[10px] font-extrabold leading-none tracking-[0.12em] transition-colors sm:text-xs", currentLang === 'kg' ? activeClass : inactiveClass)}
      >
        KG
      </button>
      <button 
        onClick={() => changeLanguage('mn')}
        className={cn("rounded-full px-2.5 py-1 text-[10px] font-extrabold leading-none tracking-[0.12em] transition-colors sm:text-xs", currentLang === 'mn' ? activeClass : inactiveClass)}
      >
        MN
      </button>
    </div>
  );
}
