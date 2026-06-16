import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { Product } from '../lib/api';

const CART_STORAGE_KEY = 'safi_cart_items';

export interface CartItem {
  product: Product;
  quantity: number;
  subtotal: number;
}

export type CartActionResult =
  | { ok: true }
  | { ok: false; reason: 'out_of_stock' | 'stock_limit' | 'missing_product' | 'mixed_product_type' };

interface StoredCartItem {
  product: Product;
  quantity: number;
}

interface CartContextValue {
  items: CartItem[];
  totalItems: number;
  totalPrice: number;
  addProduct: (product: Product, quantity?: number) => CartActionResult;
  incrementProduct: (productId: string) => CartActionResult;
  decrementProduct: (productId: string) => CartActionResult;
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
  const storedItemsRef = useRef(storedItems);

  const commitStoredItems = useCallback((nextItems: StoredCartItem[]) => {
    storedItemsRef.current = nextItems;
    setStoredItems(nextItems);

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(nextItems));
    }
  }, []);

  useEffect(() => {
    storedItemsRef.current = storedItems;
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(storedItems));
  }, [storedItems]);

  const items = useMemo<CartItem[]>(() => storedItems.map((item) => ({
    product: item.product,
    quantity: item.quantity,
    subtotal: item.product.price * item.quantity,
  })), [storedItems]);

  const value = useMemo<CartContextValue>(() => {
    const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
    const totalPrice = items.reduce((sum, item) => sum + item.subtotal, 0);

    const addProduct = (product: Product, quantity = 1): CartActionResult => {
      const productId = String(product.id);
      const nextQuantity = Math.max(1, Math.floor(quantity));
      const stockLimit = getStockLimit(product);

      if (!isProductOrderable(product)) {
        return { ok: false, reason: 'out_of_stock' };
      }

      const currentItems = storedItemsRef.current;

      if (currentItems.some((item) => isDepositProduct(item.product) !== isDepositProduct(product))) {
        return { ok: false, reason: 'mixed_product_type' };
      }

      const currentQuantity = currentItems.find((item) => String(item.product.id) === productId)?.quantity || 0;

      if (stockLimit !== null && currentQuantity + nextQuantity > stockLimit) {
        return { ok: false, reason: 'stock_limit' };
      }

      commitStoredItems(upsertItem(currentItems, product, currentQuantity + nextQuantity));

      return { ok: true };
    };

    const updateQuantity = (productId: string, quantity: number): CartActionResult => {
      const currentItems = storedItemsRef.current;
      const currentItem = currentItems.find((item) => String(item.product.id) === String(productId));
      const nextQuantity = Math.max(1, Math.floor(quantity));

      if (!currentItem || !Number.isFinite(nextQuantity)) {
        return { ok: false, reason: 'missing_product' };
      }

      if (!isProductOrderable(currentItem.product)) {
        return { ok: false, reason: 'out_of_stock' };
      }

      const stockLimit = getStockLimit(currentItem.product);

      if (stockLimit !== null && nextQuantity > stockLimit) {
        return { ok: false, reason: 'stock_limit' };
      }

      commitStoredItems(upsertItem(currentItems, currentItem.product, nextQuantity));

      return { ok: true };
    };

    return {
      items,
      totalItems,
      totalPrice,
      addProduct,
      incrementProduct: (productId) => {
        const currentItem = storedItemsRef.current.find((item) => String(item.product.id) === String(productId));

        if (!currentItem) {
          return { ok: false, reason: 'missing_product' };
        }

        return updateQuantity(String(productId), currentItem.quantity + 1);
      },
      decrementProduct: (productId) => {
        const currentItem = storedItemsRef.current.find((item) => String(item.product.id) === String(productId));

        if (!currentItem) {
          return { ok: false, reason: 'missing_product' };
        }

        if (currentItem.quantity <= 1) {
          return { ok: true };
        }

        return updateQuantity(String(productId), currentItem.quantity - 1);
      },
      updateQuantity,
      removeProduct: (productId) => {
        commitStoredItems(storedItemsRef.current.filter((item) => String(item.product.id) !== String(productId)));
      },
      clearCart: () => commitStoredItems([]),
      getQuantity: (productId) => storedItemsRef.current.find((item) => String(item.product.id) === String(productId))?.quantity || 0,
      syncProducts: (products) => {
        const productMap = new Map(products.map((product) => [String(product.id), product]));

        commitStoredItems(storedItemsRef.current.map((item) => {
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
  }, [commitStoredItems, items]);

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
  return getStockLimit(product) ?? 0;
}

export function getStockLimit(product: Product): number | null {
  const stockValue = product.stockQuantity ?? product.stock;

  if (stockValue === null || stockValue === undefined || stockValue === '') {
    return null;
  }

  const stock = Number(stockValue);

  if (!Number.isFinite(stock)) {
    return null;
  }

  return Math.max(0, Math.floor(stock));
}

export function isProductOrderable(product: Product) {
  const stockLimit = getStockLimit(product);

  return (product.status || 'active') === 'active'
    && product.inStock !== false
    && (stockLimit === null || stockLimit > 0);
}

export function isDepositProduct(product: Product) {
  return Boolean(product.isDepositProduct || product.isDepositOnly);
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
