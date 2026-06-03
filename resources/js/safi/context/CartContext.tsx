import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import type { Product } from '../lib/api';

const CART_STORAGE_KEY = 'safi_cart_items';

export interface CartItem {
  product: Product;
  quantity: number;
  subtotal: number;
  pvTotal: number;
}

export type CartActionResult =
  | { ok: true }
  | { ok: false; reason: 'out_of_stock' | 'stock_limit' | 'missing_product' };

interface StoredCartItem {
  product: Product;
  quantity: number;
}

interface CartContextValue {
  items: CartItem[];
  totalItems: number;
  totalPrice: number;
  totalPv: number;
  addProduct: (product: Product, quantity?: number) => CartActionResult;
  decrementProduct: (productId: string) => void;
  updateQuantity: (productId: string, quantity: number) => CartActionResult;
  removeProduct: (productId: string) => void;
  clearCart: () => void;
  getQuantity: (productId: string) => number;
  syncProducts: (products: Product[]) => void;
}

const CartContext = createContext<CartContextValue | null>(null);

function readStoredCart(): StoredCartItem[] {
  if (typeof window === 'undefined') {
    return [];
  }

  try {
    const parsed = JSON.parse(window.localStorage.getItem(CART_STORAGE_KEY) || '[]');

    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .map((item) => normalizeStoredItem(item))
      .filter((item): item is StoredCartItem => Boolean(item));
  } catch {
    return [];
  }
}

function normalizeStoredItem(value: unknown): StoredCartItem | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null;
  }

  const record = value as Record<string, unknown>;
  const product = record.product;
  const quantity = Math.floor(Number(record.quantity));

  if (!product || typeof product !== 'object' || Array.isArray(product) || !Number.isFinite(quantity) || quantity <= 0) {
    return null;
  }

  const productRecord = product as Product;

  if (!productRecord.id) {
    return null;
  }

  return {
    product: productRecord,
    quantity,
  };
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [storedItems, setStoredItems] = useState<StoredCartItem[]>(readStoredCart);

  useEffect(() => {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(storedItems));
  }, [storedItems]);

  const items = useMemo<CartItem[]>(() => storedItems.map((item) => ({
    product: item.product,
    quantity: item.quantity,
    subtotal: item.product.price * item.quantity,
    pvTotal: item.product.pv * item.quantity,
  })), [storedItems]);

  const value = useMemo<CartContextValue>(() => {
    const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
    const totalPrice = items.reduce((sum, item) => sum + item.subtotal, 0);
    const totalPv = items.reduce((sum, item) => sum + item.pvTotal, 0);

    const addProduct = (product: Product, quantity = 1): CartActionResult => {
      const productId = String(product.id);
      const nextQuantity = Math.max(1, Math.floor(quantity));
      const stock = getAvailableStock(product);

      if (!isProductOrderable(product)) {
        return { ok: false, reason: 'out_of_stock' };
      }

      const currentQuantity = storedItems.find((item) => String(item.product.id) === productId)?.quantity || 0;

      if (currentQuantity + nextQuantity > stock) {
        return { ok: false, reason: 'stock_limit' };
      }

      setStoredItems((current) => upsertItem(current, product, currentQuantity + nextQuantity));

      return { ok: true };
    };

    const updateQuantity = (productId: string, quantity: number): CartActionResult => {
      const currentItem = storedItems.find((item) => String(item.product.id) === String(productId));
      const nextQuantity = Math.floor(quantity);

      if (!currentItem) {
        return { ok: false, reason: 'missing_product' };
      }

      if (nextQuantity <= 0) {
        setStoredItems((current) => current.filter((item) => String(item.product.id) !== String(productId)));
        return { ok: true };
      }

      if (!isProductOrderable(currentItem.product)) {
        return { ok: false, reason: 'out_of_stock' };
      }

      if (nextQuantity > getAvailableStock(currentItem.product)) {
        return { ok: false, reason: 'stock_limit' };
      }

      setStoredItems((current) => upsertItem(current, currentItem.product, nextQuantity));

      return { ok: true };
    };

    return {
      items,
      totalItems,
      totalPrice,
      totalPv,
      addProduct,
      decrementProduct: (productId) => {
        const currentItem = storedItems.find((item) => String(item.product.id) === String(productId));

        if (!currentItem) {
          return;
        }

        updateQuantity(String(productId), currentItem.quantity - 1);
      },
      updateQuantity,
      removeProduct: (productId) => {
        setStoredItems((current) => current.filter((item) => String(item.product.id) !== String(productId)));
      },
      clearCart: () => setStoredItems([]),
      getQuantity: (productId) => storedItems.find((item) => String(item.product.id) === String(productId))?.quantity || 0,
      syncProducts: (products) => {
        const productMap = new Map(products.map((product) => [String(product.id), product]));

        setStoredItems((current) => current.map((item) => {
          const updatedProduct = productMap.get(String(item.product.id));

          if (updatedProduct) {
            return { ...item, product: updatedProduct };
          }

          return {
            ...item,
            product: {
              ...item.product,
              status: 'inactive',
              stock: 0,
              stockQuantity: 0,
              inStock: false,
            },
          };
        }));
      },
    };
  }, [items, storedItems]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const context = useContext(CartContext);

  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }

  return context;
}

export function getAvailableStock(product: Product) {
  return Math.max(0, Math.floor(Number(product.stockQuantity ?? product.stock ?? 0)));
}

export function isProductOrderable(product: Product) {
  return (product.status || 'active') === 'active' && getAvailableStock(product) > 0 && product.inStock !== false;
}

function upsertItem(items: StoredCartItem[], product: Product, quantity: number): StoredCartItem[] {
  const productId = String(product.id);
  const nextItem = { product, quantity };
  const exists = items.some((item) => String(item.product.id) === productId);

  if (!exists) {
    return [...items, nextItem];
  }

  return items.map((item) => String(item.product.id) === productId ? nextItem : item);
}

