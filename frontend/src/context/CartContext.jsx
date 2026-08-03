import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import { isAuthenticated } from '../services/authService';
import { cartApi, guestEventApi } from '../services/cartService';

const CartContext = createContext(null);
const STORAGE_KEY = 'smartcart_cart';

function loadLocal() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  } catch {
    return [];
  }
}

function saveLocal(items) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

function mapBackendCart(data) {
  if (!data?.items) return [];
  return data.items.map(i => ({
    id: i.productId,
    itemId: i.id,
    name: i.productName,
    price: i.unitPrice,
    qty: i.quantity,
    image: i.productImage,
    slug: i.productSlug,
    stock: i.availableStock,
  }));
}

// Normalize a product object (from API or local) into a cart item
function toCartItem(product, qty) {
  // Honor an active promotion's discounted price, same as the backend does
  // for authenticated carts — otherwise a guest's cart shows the full price.
  const price = product.promotion?.newPrice ?? product.price;
  return {
    id: product.id,
    name: product.name,
    price: parseFloat(price),
    stock: product.stock ?? null,
    // API products have `images` (array); synced items already have `image`
    image: product.images?.[0] ?? product.image ?? null,
    slug: product.slug ?? null,
    qty,
  };
}

export function CartProvider({ children }) {
  const [items, setItems] = useState(loadLocal);
  const itemsRef = useRef(items);
  itemsRef.current = items;

  const syncWithBackend = useCallback(async () => {
    const local = loadLocal();
    try {
      const call =
        local.length > 0
          ? cartApi.syncCart(
              local.map(i => ({ productId: i.id, quantity: i.qty })),
              'merge',
            )
          : cartApi.getCart();
      const res = await call;
      const mapped = mapBackendCart(res.data);
      setItems(mapped);
      saveLocal(mapped);
    } catch {}
  }, []);

  // On mount: sync if already authenticated (returning user with stored token)
  useEffect(() => {
    if (isAuthenticated()) syncWithBackend();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const addToCart = useCallback(async (product, qty = 1) => {
    if (isAuthenticated()) {
      try {
        const res = await cartApi.addItem(product.id, qty);
        const mapped = mapBackendCart(res.data);
        setItems(mapped);
        saveLocal(mapped);
        return;
      } catch {}
    }
    const item = toCartItem(product, qty);
    setItems(prev => {
      const existing = prev.find(i => i.id === item.id);
      const next = existing
        ? prev.map(i => (i.id === item.id ? { ...i, qty: i.qty + qty } : i))
        : [...prev, item];
      saveLocal(next);
      return next;
    });
    // Authenticated cart-adds are tracked server-side automatically; a
    // guest's cart is local-only, so it needs an explicit session event
    // for the recommendation engine to see it.
    guestEventApi.track(product.id, 'cart').catch(() => {});
  }, []);

  const removeFromCart = useCallback(async id => {
    if (isAuthenticated()) {
      const item = itemsRef.current.find(i => i.id === id);
      if (item?.itemId) {
        try {
          const res = await cartApi.removeItem(item.itemId);
          const mapped = mapBackendCart(res.data);
          setItems(mapped);
          saveLocal(mapped);
          return;
        } catch {}
      }
    }
    setItems(prev => {
      const next = prev.filter(i => i.id !== id);
      saveLocal(next);
      return next;
    });
  }, []);

  const updateQty = useCallback(async (id, qty) => {
    if (qty < 1) return;
    if (isAuthenticated()) {
      const item = itemsRef.current.find(i => i.id === id);
      if (item?.itemId) {
        try {
          const res = await cartApi.updateItem(item.itemId, qty);
          const mapped = mapBackendCart(res.data);
          setItems(mapped);
          saveLocal(mapped);
          return;
        } catch {}
      }
    }
    setItems(prev => {
      const next = prev.map(i => (i.id === id ? { ...i, qty } : i));
      saveLocal(next);
      return next;
    });
  }, []);

  const clearCart = useCallback(async () => {
    if (isAuthenticated()) {
      try {
        await cartApi.clearCart();
      } catch {}
    }
    setItems([]);
    saveLocal([]);
  }, []);

  // Reset the local cart mirror only (no backend call) — used on logout so
  // the displayed cart doesn't linger and isn't re-merged into the backend
  // cart (additively) on the next login.
  const resetCart = useCallback(() => {
    setItems([]);
    saveLocal([]);
  }, []);

  const cartCount = items.reduce((s, i) => s + i.qty, 0);
  const cartTotal = items.reduce((s, i) => s + i.price * i.qty, 0);

  return (
    <CartContext.Provider
      value={{
        items,
        addToCart,
        removeFromCart,
        updateQty,
        clearCart,
        resetCart,
        cartCount,
        cartTotal,
        syncWithBackend,
      }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  return useContext(CartContext);
}
