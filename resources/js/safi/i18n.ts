import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import ru from './locales/ru.json';
import kz from './locales/kz.json';
import kg from './locales/kg.json';
import en from './locales/en.json';
import mn from './locales/mn.json';
import { getCurrentLanguage, normalizeLanguage, setCurrentLanguage } from './lib/language';

i18n
  .use(initReactI18next)
  .init({
    resources: {
      ru: { translation: ru },
      kz: { translation: kz },
      kg: { translation: kg },
      en: { translation: en },
      mn: { translation: mn },
    },
    lng: getCurrentLanguage(),
    fallbackLng: 'ru',
    supportedLngs: ['ru', 'kz', 'kg', 'en', 'mn'],
    cleanCode: true,
    interpolation: {
      escapeValue: false, // react already safes from xss
    },
  });

i18n.on('languageChanged', (language) => {
  setCurrentLanguage(normalizeLanguage(language));
});

export default i18n;
