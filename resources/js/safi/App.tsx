/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { AppRouter } from './router/routes';
import { RuntimeTextLocalizer } from './i18n/runtimeTranslations';
import { CartProvider } from './context/CartContext';

export default function App() {
  return (
    <CartProvider>
      <RuntimeTextLocalizer />
      <AppRouter />
    </CartProvider>
  );
}
