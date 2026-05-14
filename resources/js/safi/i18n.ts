import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import ru from './locales/ru.json';
import kg from './locales/kg.json';
import mn from './locales/mn.json';

i18n
  .use(initReactI18next)
  .init({
    resources: {
      ru: { translation: ru },
      kg: { translation: kg },
      mn: { translation: mn },
    },
    lng: 'ru',
    fallbackLng: 'ru',
    interpolation: {
      escapeValue: false, // react already safes from xss
    },
  });

export default i18n;
