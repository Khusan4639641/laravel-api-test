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

  return (
    <header className="sticky top-0 z-50 w-full border-b border-safi-green/10 bg-white/50 backdrop-blur-md shrink-0">
      <Container>
        <div className="flex h-20 items-center justify-between gap-4 xl:gap-8">
          <Link to="/" className="flex items-center" onClick={closeMenu}>
            <img 
              alt="Safi Life" 
              className="w-[100px] h-[40px] object-contain shrink-0" 
              src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png" 
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
            <Button variant="outline" size="sm" to="/login" className="px-5">{t('nav.login', 'Вход')}</Button>
            <Button size="sm" to="/register" className="px-5">{t('nav.register', 'Регистрация')}</Button>
          </div>

          {/* Mobile menu button */}
          <div className="lg:hidden flex items-center gap-4 shrink-0">
            <LanguageSwitcher />
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
            <Button variant="outline" to="/login" onClick={closeMenu} className="w-full justify-center">{t('nav.login', 'Вход')}</Button>
            <Button to="/register" onClick={closeMenu} className="w-full justify-center">{t('nav.register', 'Регистрация')}</Button>
          </nav>
        </div>
      )}
    </header>
  );
}
