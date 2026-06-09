import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { MainLayout } from '../components/layout/MainLayout';
import { DashboardLayout } from '../components/dashboard/DashboardLayout';
import { features } from '../config/features';

// Public pages
const HomePage = React.lazy(() => import('../pages/HomePage'));
const AboutPage = React.lazy(() => import('../pages/AboutPage'));
const ProductsPage = React.lazy(() => import('../pages/ProductsPage'));
const CartPage = React.lazy(() => import('../pages/CartPage'));
const BusinessPage = React.lazy(() => import('../pages/BusinessPage'));
const MarketingPlanPage = React.lazy(() => import('../pages/MarketingPlanPage'));
const HowToStartPage = React.lazy(() => import('../pages/HowToStartPage'));
const NewsPage = React.lazy(() => import('../pages/NewsPage'));
const ContactsPage = React.lazy(() => import('../pages/ContactsPage'));
const FAQPage = React.lazy(() => import('../pages/FAQPage'));
const RegisterPage = React.lazy(() => import('../pages/RegisterPage'));
const LoginPage = React.lazy(() => import('../pages/LoginPage'));
const AdminPreviewPage = React.lazy(() => import('../pages/AdminPreviewPage'));
const LegalPage = React.lazy(() => import('../pages/LegalPage'));
const LegalInfoPage = React.lazy(() => import('../pages/LegalInfoPage'));
const PaymentResultPage = React.lazy(() => import('../pages/PaymentResultPage'));

// Dashboard Pages
const Overview = React.lazy(() => import('../pages/dashboard/Overview'));
const Structure = React.lazy(() => import('../pages/dashboard/Structure'));
const Transactions = React.lazy(() => import('../pages/dashboard/Transactions'));
const Bonuses = React.lazy(() => import('../pages/dashboard/Bonuses'));
const PackageStatus = React.lazy(() => import('../pages/dashboard/PackageStatus'));
const Profile = React.lazy(() => import('../pages/dashboard/Profile'));
const Support = React.lazy(() => import('../pages/dashboard/Support'));
const Products = React.lazy(() => import('../pages/dashboard/Products'));
const Orders = React.lazy(() => import('../pages/dashboard/Orders'));
const OrderDetail = React.lazy(() => import('../pages/dashboard/OrderDetail'));
const News = React.lazy(() => import('../pages/dashboard/News'));

// Admin Pages
const AdminHome = React.lazy(() => import('../pages/admin/AdminHome'));
const AdminPartners = React.lazy(() => import('../pages/admin/AdminPartners'));
const AdminPartnersBulkCreate = React.lazy(() => import('../pages/admin/AdminPartnersBulkCreate'));
const AdminPartnerDetail = React.lazy(() => import('../pages/admin/AdminPartnerDetail'));
const AdminStructure = React.lazy(() => import('../pages/admin/AdminStructure'));
const AdminTransactions = React.lazy(() => import('../pages/admin/AdminTransactions'));
const AdminWithdrawals = React.lazy(() => import('../pages/admin/AdminWithdrawals'));
const AdminBonuses = React.lazy(() => import('../pages/admin/AdminBonuses'));
const AdminPackages = React.lazy(() => import('../pages/admin/AdminPackages'));
const AdminStatuses = React.lazy(() => import('../pages/admin/AdminStatuses'));
const AdminProducts = React.lazy(() => import('../pages/admin/AdminProducts'));
const AdminOrders = React.lazy(() => import('../pages/admin/AdminOrders'));
const AdminSupport = React.lazy(() => import('../pages/admin/AdminSupport'));
const AdminProfile = React.lazy(() => import('../pages/admin/AdminProfile'));
const AdminReports = React.lazy(() => import('../pages/admin/AdminReports'));
const AdminSettings = React.lazy(() => import('../pages/admin/AdminSettings'));
const AdminNews = React.lazy(() => import('../pages/admin/AdminNews'));
const AdminLayoutComponent = React.lazy(() => import('../components/admin/AdminLayout').then(module => ({ default: module.AdminLayout })));

export function AppRouter() {
  return (
    <BrowserRouter>
      <React.Suspense fallback={<div className="flex h-screen items-center justify-center p-4">Загрузка...</div>}>
        <Routes>
          <Route path="/" element={<MainLayout />}>
            <Route index element={<HomePage />} />
            <Route path="about" element={<AboutPage />} />
            <Route path="products" element={<ProductsPage />} />
            <Route path="cart" element={<CartPage />} />
            <Route path="business" element={<BusinessPage />} />
            <Route path="marketing" element={<MarketingPlanPage />} />
            <Route path="how-to-start" element={<HowToStartPage />} />
            <Route path="news" element={<NewsPage />} />
            <Route path="faq" element={<FAQPage />} />
            <Route path="contacts" element={<ContactsPage />} />
            <Route path="register" element={<RegisterPage />} />
            <Route path="register-ref-branch" element={<RegisterPage />} />
            <Route path="login" element={<LoginPage />} />
            <Route path="legal" element={<LegalPage />} />
            <Route path="payment" element={<LegalInfoPage type="payment" />} />
            <Route path="payment/success" element={<PaymentResultPage result="success" />} />
            <Route path="payment/fail" element={<PaymentResultPage result="fail" />} />
            <Route path="legal/offer" element={<LegalInfoPage type="offer" />} />
            <Route path="legal/privacy" element={<LegalInfoPage type="privacy" />} />
            <Route path="legal/delivery" element={<LegalInfoPage type="delivery" />} />
            <Route path="legal/refund" element={<LegalInfoPage type="refund" />} />
            <Route path="legal/requisites" element={<LegalInfoPage type="requisites" />} />
            <Route path="offer" element={<Navigate to="/legal/offer" replace />} />
            <Route path="privacy" element={<Navigate to="/legal/privacy" replace />} />
            <Route path="refund" element={<Navigate to="/legal/refund" replace />} />
            <Route path="requisites" element={<Navigate to="/legal/requisites" replace />} />
          </Route>
          
          {/* Dashboard Routes */}
          <Route path="/dashboard" element={<DashboardLayout />}>
            <Route index element={<Overview />} />
            <Route path="structure" element={<Structure />} />
            <Route path="transactions" element={<Transactions />} />
            <Route path="bonuses" element={<Bonuses />} />
            <Route path="package" element={<PackageStatus />} />
            <Route path="package-status" element={<PackageStatus />} />
            <Route path="products" element={<Products />} />
            <Route path="orders" element={<Orders />} />
            <Route path="orders/:id" element={<OrderDetail />} />
            <Route path="news" element={<News />} />
            <Route path="profile" element={<Profile />} />
            <Route path="support" element={features.support ? <Support /> : <Navigate to="/dashboard" replace />} />
          </Route>

          {/* Admin Routes */}
          <Route path="/admin" element={<AdminLayoutComponent />}>
            <Route index element={<AdminHome />} />
            <Route path="partners" element={<AdminPartners />} />
            <Route path="partners/bulk-create" element={<AdminPartnersBulkCreate />} />
            <Route path="partners/:id" element={<AdminPartnerDetail />} />
            <Route path="structure" element={<AdminStructure />} />
            <Route path="transactions" element={<AdminTransactions />} />
            <Route path="withdrawals" element={<AdminWithdrawals />} />
            <Route path="bonuses" element={<AdminBonuses />} />
            <Route path="packages" element={<AdminPackages />} />
            <Route path="statuses" element={<AdminStatuses />} />
            <Route path="products" element={<AdminProducts />} />
            <Route path="orders" element={<AdminOrders />} />
            <Route path="news" element={<AdminNews />} />
            <Route path="support" element={features.support ? <AdminSupport /> : <Navigate to="/admin" replace />} />
            <Route path="reports" element={<AdminReports />} />
            <Route path="settings" element={<AdminSettings />} />
          </Route>

          {/* Support Routes */}
          {features.support ? (
            <Route path="/support" element={<AdminLayoutComponent />}>
              <Route index element={<AdminSupport />} />
              <Route path="tickets" element={<AdminSupport />} />
              <Route path="profile" element={<AdminProfile />} />
            </Route>
          ) : (
            <Route path="/support/*" element={<SupportUnavailable />} />
          )}

          <Route path="/admin-preview" element={<AdminPreviewPage />} />
          
          {/* Catch all */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </React.Suspense>
    </BrowserRouter>
  );
}

function SupportUnavailable() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-safi-bg px-5 text-center text-safi-green">
      <section className="max-w-md rounded-[32px] border border-safi-border bg-white p-8 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
        <div className="font-serif text-3xl font-semibold">Раздел временно недоступен</div>
        <p className="mt-3 text-sm leading-7 text-safi-muted">
          Этот раздел временно скрыт во frontend. Основные разделы кабинета доступны в меню.
        </p>
      </section>
    </main>
  );
}
