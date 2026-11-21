import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { CartContext, type CartContextValue, type CartLine } from '@/contexts/cart-context';

const STORAGE_KEY = 'tms-storefront-cart-v1';

function readStoredLines(): CartLine[] {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter((line): line is CartLine => {
      if (typeof line !== 'object' || line === null) {
        return false;
      }

      const candidate = line as Partial<CartLine>;
      return (
        typeof candidate.productSlug === 'string' &&
        typeof candidate.name === 'string' &&
        typeof candidate.priceValue === 'number' &&
        typeof candidate.currencyCode === 'string' &&
        typeof candidate.quantity === 'number' &&
        candidate.quantity > 0
      );
    });
  } catch {
    return [];
  }
}

function normalizeQuantity(quantity: number, maxStock: number | null): number {
  const floored = Math.max(1, Math.floor(quantity));
  return maxStock === null ? Math.min(floored, 999) : Math.min(floored, Math.max(1, maxStock), 999);
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>(() => readStoredLines());

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
  }, [lines]);

  const addItem = useCallback((line: CartLine) => {
    setLines((current) => {
      const existing = current.find((item) => item.productSlug === line.productSlug);
      if (!existing) {
        return [...current, { ...line, quantity: normalizeQuantity(line.quantity, line.maxStock) }];
      }

      return current.map((item) =>
        item.productSlug === line.productSlug
          ? { ...item, ...line, quantity: normalizeQuantity(item.quantity + line.quantity, line.maxStock) }
          : item,
      );
    });
  }, []);

  const updateQuantity = useCallback((productSlug: string, quantity: number) => {
    setLines((current) =>
      current.map((item) =>
        item.productSlug === productSlug
          ? { ...item, quantity: normalizeQuantity(quantity, item.maxStock) }
          : item,
      ),
    );
  }, []);

  const removeItem = useCallback((productSlug: string) => {
    setLines((current) => current.filter((item) => item.productSlug !== productSlug));
  }, []);

  const clearCart = useCallback(() => {
    setLines([]);
  }, []);

  const value = useMemo<CartContextValue>(() => {
    return {
      lines,
      itemCount: lines.reduce((total, line) => total + line.quantity, 0),
      totalValue: lines.reduce((total, line) => total + line.priceValue * line.quantity, 0),
      addItem,
      updateQuantity,
      removeItem,
      clearCart,
    };
  }, [addItem, clearCart, lines, removeItem, updateQuantity]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}
