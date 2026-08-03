import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { categoryApi } from '../services/cartService';

const CategoryContext = createContext(null);

export function CategoryProvider({ children }) {
  const [categories, setCategories] = useState([]);
  const [leafCategories, setLeafCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  const loadCategories = useCallback(async () => {
    setLoading(true);
    try {
      const res = await categoryApi.list();
      const tree = res.data || [];
      setCategories(tree);
      setLeafCategories(
        tree.flatMap(parent =>
          parent.children.map(child => ({
            id: child.id,
            name: child.name,
            parentName: parent.name,
          })),
        ),
      );
    } catch {
      setCategories([]);
      setLeafCategories([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  return (
    <CategoryContext.Provider
      value={{ categories, leafCategories, loading, reloadCategories: loadCategories }}
    >
      {children}
    </CategoryContext.Provider>
  );
}

export function useCategories() {
  return useContext(CategoryContext);
}
