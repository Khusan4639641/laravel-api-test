import React from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Footer } from './Footer';
import { FloatingContactButtons } from './FloatingContactButtons';

export function MainLayout() {
  return (
    <div className="flex min-h-screen flex-col bg-safi-bg font-sans text-safi-green">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
      <FloatingContactButtons />
    </div>
  );
}
