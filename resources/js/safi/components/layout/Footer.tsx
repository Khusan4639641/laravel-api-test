import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Container } from '../ui/Container';
import { Camera, MessageCircle, Send } from 'lucide-react';

export function Footer() {
  const { t } = useTranslation();

  return (
    <footer className="bg-safi-green text-safi-bg py-16 relative overflow-hidden">
      <div className="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
      <Container>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
          <div>
            <Link to="/" className="flex items-center mb-6">
              <img 
                alt="Safi Life" 
                className="w-[120px] h-auto object-contain brightness-0 invert opacity-90 hover:opacity-100 transition-opacity" 
                src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png" 
              />
            </Link>
            <p className="text-sm text-safi-bg/70 mb-6 max-w-xs leading-relaxed">
              Натуральная продукция из Казахстана для здоровья и красоты. Ваш надежный партнер в развитии бизнеса.
            </p>
            <div className="flex gap-4">
              <a href="#" className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-safi-bg/70 hover:text-safi-gold hover:bg-white/10 transition-all border border-white/5"><Camera className="h-5 w-5" /></a>
              <a href="#" className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-safi-bg/70 hover:text-safi-gold hover:bg-white/10 transition-all border border-white/5"><MessageCircle className="h-5 w-5" /></a>
              <a href="#" className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-safi-bg/70 hover:text-safi-gold hover:bg-white/10 transition-all border border-white/5"><Send className="h-5 w-5" /></a>
            </div>
          </div>
          
          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">Компания</h3>
            <ul className="space-y-3 text-sm">
              <li><Link to="/about" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.about', 'О нас')}</Link></li>
              <li><Link to="/products" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.products', 'Продукты')}</Link></li>
              <li><Link to="/faq" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.faq', 'FAQ')}</Link></li>
              <li><Link to="/contacts" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.contacts', 'Контакты')}</Link></li>
              <li><Link to="/legal" className="text-safi-bg/70 hover:text-white transition-colors">Правовая информация</Link></li>
            </ul>
          </div>

          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">Партнёрам</h3>
            <ul className="space-y-3 text-sm">
              <li><Link to="/business" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.business', 'Возможность')}</Link></li>
              <li><Link to="/marketing" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.marketing', 'Маркетинг-план')}</Link></li>
              <li><Link to="/how-to-start" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.howToStart', 'Как начать')}</Link></li>
              <li><Link to="/login" className="text-safi-bg/70 hover:text-white transition-colors">{t('nav.login', 'Вход в кабинет')}</Link></li>
              <li><Link to="/dashboard" className="text-safi-gold hover:text-white transition-colors font-bold uppercase text-[10px] tracking-wider relative group">
                <span className="bg-white/10 px-2 py-1 rounded inline-block">Демо кабинета</span>
              </Link></li>
            </ul>
          </div>

          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">{t('nav.contacts', 'Контакты')}</h3>
            <ul className="space-y-3 text-sm text-safi-bg/70">
              <li className="flex flex-col">
                <span className="text-[10px] text-safi-gold uppercase tracking-wider mb-1">Офис</span>
                <span>Республика Казахстан, г. Алматы</span>
              </li>
              <li className="flex flex-col mt-4">
                <span className="text-[10px] text-safi-gold uppercase tracking-wider mb-1">Связь</span>
                <a href="mailto:info@safilife.kz" className="hover:text-white transition-colors">info@safilife.kz</a>
                <a href="tel:+77000000000" className="hover:text-white transition-colors">+7 (700) 000-00-00</a>
              </li>
            </ul>
          </div>
        </div>
        
        <div className="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-safi-bg/50">
          <p>© {new Date().getFullYear()} Safi Life. {t('footer.rights', 'Все права защищены.')}</p>
          <div className="mt-4 md:mt-0 max-w-xl text-center md:text-right">
            <p>Информация на сайте носит ознакомительный характер. Потенциальный доход не гарантируется.</p>
          </div>
        </div>
      </Container>
    </footer>
  );
}
