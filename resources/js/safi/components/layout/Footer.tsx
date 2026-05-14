import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Container } from '../ui/Container';
import { Camera, MessageCircle, Send } from 'lucide-react';

export function Footer() {
  const { t } = useTranslation();

  return (
    <footer className="border-t border-safi-border bg-safi-green py-14 text-safi-bg md:py-16">
      <Container>
        <div className="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-[1.2fr_0.8fr_0.8fr_1fr] lg:gap-12">
          <div>
            <Link to="/" className="mb-6 flex w-fit items-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-safi-gold">
              <img 
                alt="Safi Life" 
                className="h-auto w-[128px] object-contain brightness-0 invert opacity-95 transition-opacity hover:opacity-100" 
                src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png" 
              />
            </Link>
            <p className="mb-6 max-w-sm text-sm leading-7 text-safi-bg/70">
              Натуральная продукция из Казахстана для здоровья и красоты. Ваш надежный партнер в развитии бизнеса.
            </p>
            <div className="flex gap-3">
              <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-safi-bg/70 transition-colors hover:border-safi-gold/60 hover:text-safi-gold" aria-label="Instagram"><Camera className="h-5 w-5" /></a>
              <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-safi-bg/70 transition-colors hover:border-safi-gold/60 hover:text-safi-gold" aria-label="WhatsApp"><MessageCircle className="h-5 w-5" /></a>
              <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-safi-bg/70 transition-colors hover:border-safi-gold/60 hover:text-safi-gold" aria-label="Telegram"><Send className="h-5 w-5" /></a>
            </div>
          </div>
          
          <div>
            <h3 className="mb-5 text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">Компания</h3>
            <ul className="space-y-3 text-sm">
              <li><Link to="/about" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.about', 'О нас')}</Link></li>
              <li><Link to="/products" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.products', 'Продукты')}</Link></li>
              <li><Link to="/faq" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.faq', 'FAQ')}</Link></li>
              <li><Link to="/contacts" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.contacts', 'Контакты')}</Link></li>
              <li><Link to="/legal" className="text-safi-bg/70 transition-colors hover:text-white">Правовая информация</Link></li>
            </ul>
          </div>

          <div>
            <h3 className="mb-5 text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">Партнёрам</h3>
            <ul className="space-y-3 text-sm">
              <li><Link to="/business" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.business', 'Возможность')}</Link></li>
              <li><Link to="/marketing" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.marketing', 'Маркетинг-план')}</Link></li>
              <li><Link to="/how-to-start" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.howToStart', 'Как начать')}</Link></li>
              <li><Link to="/login" className="text-safi-bg/70 transition-colors hover:text-white">{t('nav.login', 'Вход в кабинет')}</Link></li>
              <li><Link to="/dashboard" className="relative font-extrabold uppercase tracking-[0.14em] text-safi-gold transition-colors hover:text-white">
                <span className="inline-block rounded-full border border-safi-gold/25 bg-white/[0.06] px-3 py-1 text-[10px]">Демо кабинета</span>
              </Link></li>
            </ul>
          </div>

          <div>
            <h3 className="mb-5 text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">{t('nav.contacts', 'Контакты')}</h3>
            <ul className="space-y-5 text-sm text-safi-bg/70">
              <li className="flex flex-col">
                <span className="mb-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">Офис</span>
                <span>Республика Казахстан, г. Алматы</span>
              </li>
              <li className="flex flex-col">
                <span className="mb-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">Связь</span>
                <a href="mailto:info@safilife.kz" className="transition-colors hover:text-white">info@safilife.kz</a>
                <a href="tel:+77000000000" className="transition-colors hover:text-white">+7 (700) 000-00-00</a>
              </li>
            </ul>
          </div>
        </div>
        
        <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-white/10 pt-7 text-xs leading-6 text-safi-bg/50 md:flex-row md:items-center">
          <p>© {new Date().getFullYear()} Safi Life. {t('footer.rights', 'Все права защищены.')}</p>
          <div className="max-w-xl md:text-right">
            <p>Информация на сайте носит ознакомительный характер. Потенциальный доход не гарантируется.</p>
          </div>
        </div>
      </Container>
    </footer>
  );
}
