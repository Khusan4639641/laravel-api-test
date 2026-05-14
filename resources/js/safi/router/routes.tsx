import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { MainLayout } from '../components/layout/MainLayout';
import { DashboardLayout } from '../components/dashboard/DashboardLayout';

// Mock pages for now
const HomePage = React.lazy(() => import('../pages/HomePage'));
const AboutPage = React.lazy(() => import('../pages/AboutPage'));
const ProductsPage = React.lazy(() => import('../pages/ProductsPage'));
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

// Dashboard Pages
const Overview = React.lazy(() => import('../pages/dashboard/Overview'));
const Structure = React.lazy(() => import('../pages/dashboard/Structure'));
const Transactions = React.lazy(() => import('../pages/dashboard/Transactions'));
const Bonuses = React.lazy(() => import('../pages/dashboard/Bonuses'));
const PackageStatus = React.lazy(() => import('../pages/dashboard/PackageStatus'));
const Profile = React.lazy(() => import('../pages/dashboard/Profile'));
const Support = React.lazy(() => import('../pages/dashboard/Support'));
const Products = React.lazy(() => import('../pages/dashboard/Products'));
const News = React.lazy(() => import('../pages/dashboard/News'));

// Admin Pages
const AdminOverview = React.lazy(() => import('../pages/admin/AdminOverview'));
const AdminPartners = React.lazy(() => import('../pages/admin/AdminPartners'));
const AdminPartnerDetail = React.lazy(() => import('../pages/admin/AdminPartnerDetail'));
const AdminStructure = React.lazy(() => import('../pages/admin/AdminStructure'));
const AdminTransactions = React.lazy(() => import('../pages/admin/AdminTransactions'));
const AdminWithdrawals = React.lazy(() => import('../pages/admin/AdminWithdrawals'));
const AdminBonuses = React.lazy(() => import('../pages/admin/AdminBonuses'));
const AdminPackages = React.lazy(() => import('../pages/admin/AdminPackages'));
const AdminStatuses = React.lazy(() => import('../pages/admin/AdminStatuses'));
const AdminProducts = React.lazy(() => import('../pages/admin/AdminProducts'));
const AdminSupport = React.lazy(() => import('../pages/admin/AdminSupport'));
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
            <Route path="business" element={<BusinessPage />} />
            <Route path="marketing" element={<MarketingPlanPage />} />
            <Route path="how-to-start" element={<HowToStartPage />} />
            <Route path="news" element={<NewsPage />} />
            <Route path="faq" element={<FAQPage />} />
            <Route path="contacts" element={<ContactsPage />} />
            <Route path="register" element={<RegisterPage />} />
            <Route path="login" element={<LoginPage />} />
            <Route path="legal" element={<LegalPage />} />
          </Route>
          
          {/* Dashboard Routes */}
          <Route path="/dashboard" element={<DashboardLayout />}>
            <Route index element={<Overview />} />
            <Route path="structure" element={<Structure />} />
            <Route path="transactions" element={<Transactions />} />
            <Route path="bonuses" element={<Bonuses />} />
            <Route path="package-status" element={<PackageStatus />} />
            <Route path="products" element={<Products />} />
            <Route path="news" element={<News />} />
            <Route path="profile" element={<Profile />} />
            <Route path="support" element={<Support />} />
          </Route>

          {/* Admin Routes */}
          <Route path="/admin" element={<AdminLayoutComponent />}>
            <Route index element={<AdminOverview />} />
            <Route path="partners" element={<AdminPartners />} />
            <Route path="partners/:id" element={<AdminPartnerDetail />} />
            <Route path="structure" element={<AdminStructure />} />
            <Route path="transactions" element={<AdminTransactions />} />
            <Route path="withdrawals" element={<AdminWithdrawals />} />
            <Route path="bonuses" element={<AdminBonuses />} />
            <Route path="packages" element={<AdminPackages />} />
            <Route path="statuses" element={<AdminStatuses />} />
            <Route path="products" element={<AdminProducts />} />
            <Route path="news" element={<AdminNews />} />
            <Route path="support" element={<AdminSupport />} />
            <Route path="reports" element={<AdminReports />} />
            <Route path="settings" element={<AdminSettings />} />
          </Route>

          <Route path="/admin-preview" element={<AdminPreviewPage />} />
          
          {/* Catch all */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </React.Suspense>
    </BrowserRouter>
  );
}
