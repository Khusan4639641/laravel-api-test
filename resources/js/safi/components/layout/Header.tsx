import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Container } from '../ui/Container';
import { Button } from '../ui/Button';
import { Menu, ShoppingCart, X } from 'lucide-react';
import { cn } from '../../lib/utils';
import { LanguageSwitcher } from '../ui/LanguageSwitcher';
import { useCart } from '../../context/CartContext';
import { ApiError, apiRequest, endpoints, getAuthToken, logout } from '../../lib/api';

type HeaderAuthState = 'checking' | 'authenticated' | 'guest';

interface HeaderSession {
  role: string;
  cabinetPath: string;
}

const BACKOFFICE_ROLES = ['super_admin', 'admin', 'accountant', 'support'];

export function Header() {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false);
  const location = useLocation();
  const { t, i18n } = useTranslation();
  const { totalItems } = useCart();
  const [authState, setAuthState] = useState<HeaderAuthState>(() => getAuthToken() ? 'checking' : 'guest');
  const [session, setSession] = useState<HeaderSession | null>(null);

  const navLinks = useMemo(() => [
    { name: t('nav.home', 'Главная'), path: '/' },
    { name: t('nav.about', 'О компании'), path: '/about' },
    { name: t('nav.products', 'Продукты'), path: '/products' },
    { name: t('nav.business', 'Возможность'), path: '/business' },
    { name: t('nav.marketing', 'Маркетинг-план'), path: '/marketing' },
    { name: t('nav.howToStart', 'Как начать'), path: '/how-to-start' },
    { name: t('nav.faq', 'FAQ'), path: '/faq' },
    { name: t('nav.contacts', 'Контакты'), path: '/contacts' },
  ], [t]);

  const [visibleItemsCount, setVisibleItemsCount] = useState(navLinks.length);
  const containerRef = useRef<HTMLDivElement>(null);
  const measureRef = useRef<HTMLDivElement>(null);
  const isAuthenticated = authState === 'authenticated' || (authState === 'checking' && Boolean(getAuthToken()));
  const cabinetPath = session?.cabinetPath || '/dashboard';

  useEffect(() => {
    let isActive = true;
    const token = getAuthToken();

    if (!token) {
      setSession(null);
      setAuthState('guest');
      return;
    }

    setAuthState('checking');

    void apiRequest(endpoints.auth.me, {
      method: 'GET',
      auth: true,
      redirectOnUnauthorized: false,
    })
      .then((response) => {
        if (!isActive) {
          return;
        }

        const role = getRoleFromAuthResponse(response);
        setSession({
          role,
          cabinetPath: getCabinetPathForRole(role),
        });
        setAuthState('authenticated');
      })
      .catch((error) => {
        if (!isActive) {
          return;
        }

        if (error instanceof ApiError && error.status === 401) {
          setSession(null);
          setAuthState('guest');
          return;
        }

        if (getAuthToken()) {
          setSession({
            role: 'user',
            cabinetPath: '/dashboard',
          });
          setAuthState('authenticated');
          return;
        }

        setSession(null);
        setAuthState('guest');
      });

    return () => {
      isActive = false;
    };
  }, [location.pathname]);

  useEffect(() => {
    let animationFrameId: number;

    const updateCalculations = () => {
      if (!measureRef.current || !containerRef.current) return;
      
      const containerWidth = containerRef.current.clientWidth;
      const children = Array.from(measureRef.current.children) as HTMLElement[];
      
      let width = 0;
      let count = 0;
      const gap = window.innerWidth >= 1280 ? 32 : 20; // xl gap-8 is 32px, lg gap-5 is 20px
      const moreBtnWidth = 50; 
      
      for (let i = 0; i < children.length; i++) {
        const itemWidth = children[i].offsetWidth;
        const widthToAdd = itemWidth + (i > 0 ? gap : 0);
        
        if (width + widthToAdd + (i < children.length - 1 ? moreBtnWidth : 0) > containerWidth) {
          break;
        }
        width += widthToAdd;
        count++;
      }
      
      setVisibleItemsCount(count > 0 ? count : 0);
    };

    const observer = new ResizeObserver(() => {
      cancelAnimationFrame(animationFrameId);
      animationFrameId = requestAnimationFrame(updateCalculations);
    });

    if (containerRef.current) {
      observer.observe(containerRef.current);
    }
    
    // Also observe the measure container as fonts loading or translations changing can affect its width
    if (measureRef.current) {
      observer.observe(measureRef.current);
    }
    
    updateCalculations();

    return () => {
      observer.disconnect();
      cancelAnimationFrame(animationFrameId);
    };
  }, [navLinks, i18n.language]);

  // Click outside to close the `More` menu
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      const target = event.target as HTMLElement;
      if (isMoreMenuOpen && !target.closest('.desktop-more-menu')) {
        setIsMoreMenuOpen(false);
      }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, [isMoreMenuOpen]);

  const closeMenu = () => {
    setIsMobileMenuOpen(false);
    setIsMoreMenuOpen(false);
  };

  const handleLogout = useCallback(() => {
    closeMenu();
    setSession(null);
    setAuthState('guest');
    void logout('/');
  }, []);

  return (
    <header className="sticky top-0 z-50 w-full border-b border-safi-green/10 bg-white/50 backdrop-blur-md shrink-0">
      <Container>
        <div className="flex h-20 items-center justify-between gap-4 xl:gap-8">
          <Link to="/" className="notranslate flex items-center" onClick={closeMenu} translate="no">
            <img 
              alt="Safi Life" 
              className="notranslate w-[100px] h-[40px] object-contain shrink-0"
              src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png" 
              translate="no"
            />
          </Link>

          {/* Desktop Nav */}
          <div ref={containerRef} className="hidden lg:flex items-center flex-1 h-full min-w-0 relative justify-center">
            <nav className="flex items-center xl:gap-8 gap-5 h-full">
              {navLinks.slice(0, visibleItemsCount).map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                  className={cn(
                    'text-[10px] xl:text-xs font-bold uppercase tracking-[0.15em] transition-colors hover:text-safi-gold whitespace-nowrap',
                    location.pathname === link.path ? 'text-safi-gold border-b border-safi-gold' : 'text-safi-green opacity-80'
                  )}
                >
                  {link.name}
                </Link>
              ))}

              {/* Burger Menu for overflow */}
              {visibleItemsCount < navLinks.length && (
                <div className="relative flex items-center h-full desktop-more-menu">
                  <button 
                    className="p-2 -mr-2 text-safi-green hover:text-safi-gold transition-colors flex items-center justify-center shrink-0"
                    onClick={(e) => {
                      e.stopPropagation();
                      setIsMoreMenuOpen(!isMoreMenuOpen);
                    }}
                    aria-label="More menu"
                  >
                    <Menu className="h-6 w-6" />
                  </button>
                  
                  {isMoreMenuOpen && (
                    <div className="absolute top-16 right-0 bg-white border border-safi-green/10 shadow-2xl rounded-2xl min-w-[220px] flex flex-col p-3 z-50 animate-in fade-in slide-in-from-top-4 duration-200">
                      {navLinks.slice(visibleItemsCount).map(link => (
                        <Link
                          key={link.path}
                          to={link.path}
                          onClick={closeMenu}
                          className={cn(
                            'text-xs uppercase tracking-widest font-bold p-3 rounded-xl transition-colors text-left',
                            location.pathname === link.path ? 'bg-safi-green/5 text-safi-gold' : 'text-safi-green hover:bg-[#F5F5F0]'
                          )}
                        >
                          {link.name}
                        </Link>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </nav>

            {/* Hidden measuring container */}
            <div 
              ref={measureRef} 
              className="absolute top-0 left-0 flex items-center xl:gap-8 gap-5 opacity-0 pointer-events-none invisible w-max h-full"
              aria-hidden="true"
            >
              {navLinks.map((link) => (
                <div key={link.path} className="text-[10px] xl:text-xs font-bold uppercase tracking-[0.15em] whitespace-nowrap">
                  {link.name}
                </div>
              ))}
            </div>
          </div>

          <div className="hidden lg:flex items-center gap-3 shrink-0">
            <LanguageSwitcher />
            <Link
              to="/cart"
              onClick={closeMenu}
              className="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-safi-gold"
              aria-label={t('cart.title', 'Корзина')}
            >
              <ShoppingCart className="h-5 w-5" />
              {totalItems > 0 && (
                <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-safi-gold px-1 text-[10px] font-bold leading-none text-safi-green shadow-sm">
                  {totalItems}
                </span>
              )}
            </Link>
            {isAuthenticated ? (
              <>
                <Button variant="outline" size="sm" to={cabinetPath} className="px-5">{t('nav.cabinet', 'Кабинет')}</Button>
                <Button variant="ghost" size="sm" onClick={handleLogout} className="px-4">{t('nav.logout', 'Выйти')}</Button>
              </>
            ) : (
              <>
                <Button variant="outline" size="sm" to="/login" className="px-5">{t('nav.login', 'Вход')}</Button>
                <Button size="sm" to="/login" className="px-5">{t('nav.cabinet', 'Кабинет')}</Button>
              </>
            )}
          </div>

          {/* Mobile menu button */}
          <div className="lg:hidden flex items-center gap-4 shrink-0">
            <LanguageSwitcher />
            <Link
              to="/cart"
              onClick={closeMenu}
              className="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-safi-gold"
              aria-label={t('cart.title', 'Корзина')}
            >
              <ShoppingCart className="h-5 w-5" />
              {totalItems > 0 && (
                <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-safi-gold px-1 text-[10px] font-bold leading-none text-safi-green shadow-sm">
                  {totalItems}
                </span>
              )}
            </Link>
            <button
              className="p-2 text-safi-green"
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              aria-label="Toggle menu"
            >
              {isMobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>
          </div>
        </div>
      </Container>

      {/* Mobile Nav */}
      {isMobileMenuOpen && (
        <div className="lg:hidden absolute top-20 left-0 w-full bg-white border-b border-safi-green/5 shadow-xl pb-6 rounded-b-[32px] max-h-[calc(100vh-80px)] overflow-y-auto">
          <nav className="flex flex-col px-6 pt-6 pb-6 gap-2">
            {navLinks.map((link) => (
              <Link
                key={link.path}
                to={link.path}
                onClick={closeMenu}
                className={cn(
                  'text-sm uppercase tracking-widest font-bold p-3 rounded-xl transition-colors',
                  location.pathname === link.path ? 'bg-safi-green/5 text-safi-gold' : 'text-safi-green hover:bg-[#F5F5F0]'
                )}
              >
                {link.name}
              </Link>
            ))}
            <div className="h-px w-full bg-safi-green/5 my-4"></div>
            {isAuthenticated ? (
              <>
                <Button variant="outline" to={cabinetPath} onClick={closeMenu} className="w-full justify-center">{t('nav.cabinet', 'Кабинет')}</Button>
                <Button variant="ghost" onClick={handleLogout} className="w-full justify-center">{t('nav.logout', 'Выйти')}</Button>
              </>
            ) : (
              <>
                <Button variant="outline" to="/login" onClick={closeMenu} className="w-full justify-center">{t('nav.login', 'Вход')}</Button>
                <Button to="/login" onClick={closeMenu} className="w-full justify-center">{t('nav.cabinet', 'Кабинет')}</Button>
              </>
            )}
          </nav>
        </div>
      )}
    </header>
  );
}

function getCabinetPathForRole(role: string) {
  return BACKOFFICE_ROLES.includes(role.toLowerCase()) ? '/admin' : '/dashboard';
}

function getRoleFromAuthResponse(response: unknown) {
  const record = unwrapAuthUserRecord(response);
  const directRole = getStringValue(record, ['role', 'user_role', 'role_name', 'type']);

  if (directRole) {
    return directRole.toLowerCase();
  }

  const role = record.role;

  if (isRecord(role)) {
    return (getStringValue(role, ['name', 'slug']) || 'user').toLowerCase();
  }

  const roles = record.roles;

  if (Array.isArray(roles)) {
    const backofficeRole = roles
      .map((item) => typeof item === 'string' ? item : isRecord(item) ? getStringValue(item, ['name', 'slug']) : undefined)
      .find((item): item is string => Boolean(item && BACKOFFICE_ROLES.includes(item.toLowerCase())));

    return (backofficeRole || 'user').toLowerCase();
  }

  return 'user';
}

function unwrapAuthUserRecord(response: unknown): Record<string, unknown> {
  if (!isRecord(response)) {
    return {};
  }

  if (isRecord(response.user)) {
    return response.user;
  }

  if (isRecord(response.data)) {
    if (isRecord(response.data.user)) {
      return response.data.user;
    }

    return response.data;
  }

  return response;
}

function getStringValue(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
