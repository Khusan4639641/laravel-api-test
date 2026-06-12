import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Container } from '../ui/Container';

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
          </div>

          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">Компания</h3>
            <ul className="space-y-3 text-sm">
              <li><FooterLink to="/about">{t('nav.about', 'О нас')}</FooterLink></li>
              <li><FooterLink to="/products">{t('nav.products', 'Продукты')}</FooterLink></li>
              <li><FooterLink to="/faq">{t('nav.faq', 'FAQ')}</FooterLink></li>
              <li><FooterLink to="/contacts">{t('legal.contacts', 'Контакты')}</FooterLink></li>
              <li><FooterLink to="/legal">{t('legal.title1', 'Правовая')} {t('legal.title2', 'информация')}</FooterLink></li>
            </ul>
          </div>

          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">Документы</h3>
            <ul className="space-y-3 text-sm">
              <li><FooterLink to="/payment">{t('legal.payment', 'Онлайн-оплата')}</FooterLink></li>
              <li><FooterLink to="/legal/offer">{t('legal.offer', 'Договор оферты')}</FooterLink></li>
              <li><FooterLink to="/legal/privacy">{t('legal.privacy', 'Политика конфиденциальности')}</FooterLink></li>
              <li><FooterLink to="/legal/delivery">{t('legal.delivery', 'Доставка')}</FooterLink></li>
              <li><FooterLink to="/legal/refund">{t('legal.refund', 'Возврат')}</FooterLink></li>
              <li><FooterLink to="/legal/requisites">{t('legal.requisites', 'Реквизиты')}</FooterLink></li>
            </ul>
          </div>

          <div>
            <h3 className="text-safi-gold font-bold uppercase tracking-widest text-xs mb-6">Партнерам</h3>
            <ul className="space-y-3 text-sm">
              <li><FooterLink to="/business">{t('nav.business', 'Возможность')}</FooterLink></li>
              <li><FooterLink to="/marketing">{t('nav.marketing', 'Маркетинг-план')}</FooterLink></li>
              <li><FooterLink to="/how-to-start">{t('nav.howToStart', 'Как начать')}</FooterLink></li>
              <li><FooterLink to="/login">{t('nav.login', 'Вход в кабинет')}</FooterLink></li>
            </ul>

            <div className="mt-8 space-y-3 text-sm text-safi-bg/70">
              <div className="flex flex-col">
                <span className="text-[10px] text-safi-gold uppercase tracking-wider mb-1">Офис</span>
                <span>Республика Казахстан, г. Алматы</span>
              </div>
              <div className="flex flex-col">
                <span className="text-[10px] text-safi-gold uppercase tracking-wider mb-1">Связь</span>
                <a href="mailto:info@safilife.kz" className="hover:text-white transition-colors">info@safilife.kz</a>
              </div>
            </div>
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

function FooterLink({ to, children }: { to: string; children: React.ReactNode }) {
  return (
    <Link to={to} className="text-safi-bg/70 hover:text-white transition-colors">
      {children}
    </Link>
  );
}
