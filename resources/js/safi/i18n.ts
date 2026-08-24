import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import ru from './locales/ru.json';
import kk from './locales/kk.json';
import ky from './locales/ky.json';
import en from './locales/en.json';
import mn from './locales/mn.json';
import { getCurrentLocale, setCurrentLocale, supportedLocales } from './lib/language';

const isDevelopment = import.meta.env.DEV;

i18n
  .use(initReactI18next)
  .init({
    resources: {
      ru: { translation: ru },
      kk: { translation: kk },
      ky: { translation: ky },
      en: { translation: en },
      mn: { translation: mn },
    },
    lng: getCurrentLocale(),
    fallbackLng: 'ru',
    supportedLngs: [...supportedLocales],
    nonExplicitSupportedLngs: false,
    cleanCode: true,
    load: 'languageOnly',
    returnEmptyString: false,
    returnNull: false,
    saveMissing: isDevelopment,
    missingKeyHandler: (_languages, _namespace, key) => {
      if (isDevelopment) {
        console.warn(`[i18n] Missing translation key: ${key}`);
      }
    },
    interpolation: {
      escapeValue: false,
    },
  });

i18n.on('languageChanged', (locale) => {
  setCurrentLocale(locale);
});

export default i18n;
