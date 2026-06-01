/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { AppRouter } from './router/routes';
import { RuntimeTextLocalizer } from './i18n/runtimeTranslations';

export default function App() {
  return (
    <>
      <RuntimeTextLocalizer />
      <AppRouter />
    </>
  );
}
