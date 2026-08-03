import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { normalizeLanguage } from '../lib/language';
import { translateRuntimeLiteral } from './runtimeTranslations';

/**
 * Compatibility adapter for explicitly marked legacy UI literals.
 *
 * It uses the same i18next locale as the JSON dictionaries and never scans or
 * rewrites the DOM. Unapproved/missing values stay Russian by design.
 */
export function useUiText() {
  const { i18n } = useTranslation();
  const language = normalizeLanguage(i18n.resolvedLanguage || i18n.language);

  return useCallback(
    (source: string) => translateRuntimeLiteral(source, language),
    [language],
  );
}
