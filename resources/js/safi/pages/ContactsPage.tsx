import React from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';

export default function ContactsPage() {
  const { t } = useTranslation();
  return (
    <div className="py-20 bg-safi-bg min-h-[calc(100vh-80px)] relative overflow-hidden flex flex-col justify-center">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10 w-full">
        <div className="grid md:grid-cols-2 gap-16 max-w-6xl mx-auto items-center">
          <div>
            <h1 className="text-4xl md:text-6xl font-serif font-bold mb-6 text-safi-green">Контакты</h1>
            <p className="text-safi-text opacity-70 mb-10 text-lg leading-relaxed">{t('contacts.title1', 'Свяжитесь')} {t('contacts.title2', 'с нами')} для получения консультации по продуктам или вопросам партнерства.</p>
            
            <div className="space-y-8 relative">
              <div className="absolute left-0 top-0 bottom-0 w-px bg-safi-green/10"></div>
              <div className="pl-6 relative">
                <div className="absolute left-[-4px] top-2 w-2 h-2 rounded-full bg-safi-gold"></div>
                <h4 className="text-[10px] uppercase font-bold text-safi-gold tracking-widest mb-1">Адрес</h4>
                <p className="text-safi-green font-serif text-xl font-bold">Республика Казахстан, г. Алматы</p>
              </div>
              <div className="pl-6 relative">
                <div className="absolute left-[-4px] top-2 w-2 h-2 rounded-full bg-safi-gold"></div>
                <h4 className="text-[10px] uppercase font-bold text-safi-gold tracking-widest mb-1">{t('contacts.formPhone', 'Телефон')}</h4>
                <p className="text-safi-green font-serif text-xl font-bold">+7 (700) 000-00-00</p>
              </div>
              <div className="pl-6 relative">
                <div className="absolute left-[-4px] top-2 w-2 h-2 rounded-full bg-safi-gold"></div>
                <h4 className="text-[10px] uppercase font-bold text-safi-gold tracking-widest mb-1">Email</h4>
                <p className="text-safi-green font-serif text-xl font-bold">info@safilife.kz</p>
              </div>
            </div>
          </div>
          
          <div className="bg-white p-8 md:p-10 rounded-[40px] shadow-xl border border-safi-green/5 relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-safi-gold/10 rounded-bl-full -z-10"></div>
            <h3 className="text-3xl font-serif font-bold mb-8 text-safi-green">Оставить <span className="italic text-safi-gold">заявку</span></h3>
            <form className="space-y-6" onSubmit={e => e.preventDefault()}>
              <div>
                <label className="block text-[10px] uppercase tracking-widest font-bold text-safi-green opacity-80 mb-2">Имя</label>
                <input type="text" className="w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all placeholder:text-safi-text/40" placeholder={t('contacts.formName', 'Ваше имя')} />
              </div>
              <div>
                <label className="block text-[10px] uppercase tracking-widest font-bold text-safi-green opacity-80 mb-2">Телефон</label>
                <input type="tel" className="w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all placeholder:text-safi-text/40" placeholder="+7 (___) ___-__-__" />
              </div>
              <div>
                <label className="block text-[10px] uppercase tracking-widest font-bold text-safi-green opacity-80 mb-2">Меня интересует</label>
                <select className="w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all text-sm text-safi-green">
                  <option>Продукция</option>
                  <option>Партнерство</option>
                  <option>Другой вопрос</option>
                </select>
              </div>
              <Button type="submit" className="w-full mt-2">Отправить</Button>
            </form>
          </div>
        </div>
      </Container>
    </div>
  );
}
