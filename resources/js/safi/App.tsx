/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { AppRouter } from './router/routes';
import { CartProvider } from './context/CartContext';

export default function App() {
  return (
    <CartProvider>
      <AppRouter />
    </CartProvider>
  );
}
