import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { isAuthenticated } from '../services/authService';
import { favoriteApi } from '../services/cartService';

const FavoriteContext = createContext(null);

export function FavoriteProvider({ children }) {
  const [ids,      setIds]      = useState(new Set()); // Set<productId> for O(1) lookup
  const [items,    setItems]    = useState([]);         // full favorite item list for the page
  const [loading,  setLoading]  = useState(false);

  const loadFavorites = useCallback(async () => {
    if (!isAuthenticated()) { setIds(new Set()); setItems([]); return; }
    setLoading(true);
    try {
      const res  = await favoriteApi.list();
      const data = res.data.data || [];
      setItems(data);
      setIds(new Set(data.map(f => f.productId)));
    } catch {
      setIds(new Set()); setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadFavorites(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const isFavorite = useCallback((productId) => ids.has(productId), [ids]);

  const toggleFavorite = useCallback(async (productId) => {
    if (ids.has(productId)) {
      try {
        await favoriteApi.remove(productId);
        setIds(prev => { const n = new Set(prev); n.delete(productId); return n; });
        setItems(prev => prev.filter(f => f.productId !== productId));
      } catch {}
    } else {
      try {
        const res = await favoriteApi.add(productId);
        setIds(prev => new Set([...prev, productId]));
        setItems(prev => [res.data, ...prev]);
      } catch {}
    }
  }, [ids]);

  return (
    <FavoriteContext.Provider value={{ ids, items, loading, isFavorite, toggleFavorite, loadFavorites, favCount: ids.size }}>
      {children}
    </FavoriteContext.Provider>
  );
}

export function useFavorites() {
  return useContext(FavoriteContext);
}
