import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { GoogleOAuthProvider } from '@react-oauth/google';
import './styles/variables.css';

import { CartProvider }     from './context/CartContext';
import { FavoriteProvider } from './context/FavoriteContext';
import { CategoryProvider } from './context/CategoryContext';
import { isAdmin, isAuthenticated } from './services/authService';

import Home            from './pages/home/Home';
import Brands          from './pages/brands/Brands';
import Promotions      from './pages/promotions/Promotions';
import Login           from './pages/auth/Login';
import Register        from './pages/auth/Register';
import VerifyEmail      from './pages/auth/VerifyEmail';
import ProductDetail   from './pages/product/ProductDetail';
import Profile         from './pages/profile/Profile';
import Cart            from './pages/cart/Cart';
import Orders          from './pages/orders/Orders';
import AdminLayout     from './pages/admin/AdminLayout';
import AdminDashboard  from './pages/admin/AdminDashboard';
import AdminCategories from './pages/admin/AdminCategories';
import AdminProducts   from './pages/admin/AdminProducts';
import AdminOrders     from './pages/admin/AdminOrders';
import AdminBrands     from './pages/admin/AdminBrands';
import AdminPromotions from './pages/admin/AdminPromotions';
import AdminTypes      from './pages/admin/AdminTypes';
import Favorites       from './pages/favorites/Favorites';
import Chatbot          from './components/Chatbot';

function RequireAuth({ children }) {
  if (!isAuthenticated()) return <Navigate to="/login" replace />;
  return children;
}

function RequireAdmin() {
  if (!isAuthenticated()) return <Navigate to="/login" replace />;
  if (!isAdmin())         return <Navigate to="/"      replace />;
  return <AdminLayout />;
}

export default function App() {
  return (
    <GoogleOAuthProvider clientId={process.env.REACT_APP_GOOGLE_CLIENT_ID}>
    <CategoryProvider>
    <CartProvider>
      <FavoriteProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/"            element={<Home />} />
          <Route path="/brands"      element={<Brands />} />
          <Route path="/promotions"  element={<Promotions />} />
          <Route path="/login"       element={<Login />} />
          <Route path="/register"    element={<Register />} />
          <Route path="/verify-email" element={<VerifyEmail />} />
          <Route path="/product/:id" element={<ProductDetail />} />
          <Route path="/cart"        element={<Cart />} />

          <Route path="/profile"   element={<RequireAuth><Profile /></RequireAuth>} />
          <Route path="/orders"    element={<RequireAuth><Orders /></RequireAuth>} />
          <Route path="/favorites" element={<RequireAuth><Favorites /></RequireAuth>} />

          <Route path="/admin" element={<RequireAdmin />}>
            <Route index              element={<AdminDashboard />} />
            <Route path="categories"  element={<AdminCategories />} />
            <Route path="products"    element={<AdminProducts />} />
            <Route path="orders"      element={<AdminOrders />} />
            <Route path="brands"      element={<AdminBrands />} />
            <Route path="promotions"  element={<AdminPromotions />} />
            <Route path="types"       element={<AdminTypes />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
      <Chatbot />
      </FavoriteProvider>
    </CartProvider>
    </CategoryProvider>
    </GoogleOAuthProvider>
  );
}
