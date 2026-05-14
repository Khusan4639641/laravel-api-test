import React, { useState, useEffect, useRef, useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Container } from '../ui/Container';
import { Button } from '../ui/Button';
import { Menu, X } from 'lucide-react';
import { cn } from '../../lib/utils';
import { LanguageSwitcher } from '../ui/LanguageSwitcher';

export function Header() {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false);
  const location = useLocation();
  const { t, i18n } = useTranslation();

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

  useEffect(() => {
    let animationFrameId: number;

    const updateCalculations = () => {
      if (!measureRef.current || !containerRef.current) return;
      
      const containerWidth = containerRef.current.clientWidth;
      const children = Array.from(measureRef.current.children) as HTMLElement[];
      
      let width = 0;
      let count = 0;
      const gap = window.innerWidth >= 1280 ? 28 : 18;
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

  return (
    <header className="sticky top-0 z-50 w-full shrink-0 border-b border-safi-border/80 bg-safi-bg/90 backdrop-blur-xl">
      <Container>
        <div className="flex h-[72px] items-center justify-between gap-4 xl:gap-7">
          <Link to="/" className="flex items-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-safi-gold" onClick={closeMenu}>
            <img 
              alt="Safi Life" 
              className="h-10 w-[112px] shrink-0 object-contain" 
              src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png" 
            />
          </Link>

          {/* Desktop Nav */}
          <div ref={containerRef} className="hidden lg:flex items-center flex-1 h-full min-w-0 relative justify-center">
            <nav className="flex h-full items-center gap-[18px] xl:gap-7">
              {navLinks.slice(0, visibleItemsCount).map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                  className={cn(
                    'relative flex h-full items-center whitespace-nowrap text-[10px] font-extrabold uppercase tracking-[0.16em] transition-colors xl:text-[11px]',
                    'after:absolute after:bottom-0 after:left-0 after:h-px after:w-full after:origin-center after:scale-x-0 after:bg-safi-gold after:transition-transform',
                    location.pathname === link.path ? 'text-safi-green after:scale-x-100' : 'text-safi-muted hover:text-safi-green'
                  )}
                >
                  {link.name}
                </Link>
              ))}

              {/* Burger Menu for overflow */}
              {visibleItemsCount < navLinks.length && (
                <div className="relative flex items-center h-full desktop-more-menu">
                  <button 
                    className="mr-[-8px] flex shrink-0 items-center justify-center rounded-full p-2 text-safi-green transition-colors hover:bg-safi-cream hover:text-safi-gold"
                    onClick={(e) => {
                      e.stopPropagation();
                      setIsMoreMenuOpen(!isMoreMenuOpen);
                    }}
                    aria-label="More menu"
                  >
                    <Menu className="h-6 w-6" />
                  </button>
                  
                  {isMoreMenuOpen && (
                    <div className="absolute right-0 top-16 z-50 flex min-w-[230px] flex-col rounded-3xl border border-safi-border bg-white p-2 shadow-[0_28px_80px_rgba(11,23,18,0.14)] animate-in fade-in slide-in-from-top-4 duration-200">
                      {navLinks.slice(visibleItemsCount).map(link => (
                        <Link
                          key={link.path}
                          to={link.path}
                          onClick={closeMenu}
                          className={cn(
                            'rounded-2xl p-3 text-left text-[11px] font-extrabold uppercase tracking-[0.16em] transition-colors',
                            location.pathname === link.path ? 'bg-safi-cream text-safi-green' : 'text-safi-muted hover:bg-safi-cream hover:text-safi-green'
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
              className="invisible pointer-events-none absolute left-0 top-0 flex h-full w-max items-center gap-[18px] opacity-0 xl:gap-7"
              aria-hidden="true"
            >
              {navLinks.map((link) => (
                <div key={link.path} className="whitespace-nowrap text-[10px] font-extrabold uppercase tracking-[0.16em] xl:text-[11px]">
                  {link.name}
                </div>
              ))}
            </div>
          </div>

          <div className="hidden lg:flex items-center gap-3 shrink-0">
            <LanguageSwitcher />
            <Button variant="outline" size="sm" to="/login">{t('nav.login', 'Вход')}</Button>
            <Button size="sm" to="/register">{t('nav.register', 'Регистрация')}</Button>
          </div>

          {/* Mobile menu button */}
          <div className="flex shrink-0 items-center gap-3 lg:hidden">
            <LanguageSwitcher />
            <button
              className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green shadow-sm transition-colors hover:bg-safi-cream"
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
        <div className="absolute left-0 top-[72px] w-full border-b border-safi-border bg-safi-bg shadow-[0_28px_80px_rgba(11,23,18,0.12)] lg:hidden">
          <nav className="flex max-h-[calc(100vh-72px)] flex-col gap-2 overflow-y-auto px-5 pb-6 pt-5">
            {navLinks.map((link) => (
              <Link
                key={link.path}
                to={link.path}
                onClick={closeMenu}
                className={cn(
                  'rounded-2xl px-4 py-3 text-sm font-extrabold uppercase tracking-[0.16em] transition-colors',
                  location.pathname === link.path ? 'bg-safi-green text-white' : 'text-safi-green hover:bg-safi-cream'
                )}
              >
                {link.name}
              </Link>
            ))}
            <div className="my-3 h-px w-full bg-safi-border"></div>
            <Button variant="outline" to="/login" onClick={closeMenu} className="w-full justify-center">{t('nav.login', 'Вход')}</Button>
            <Button to="/register" onClick={closeMenu} className="w-full justify-center">{t('nav.register', 'Регистрация')}</Button>
          </nav>
        </div>
      )}
    </header>
  );
}
