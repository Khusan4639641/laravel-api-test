import React from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { SectionTitle } from '../components/ui/SectionTitle';

export default function AboutPage() {
  const { t } = useTranslation();
  return (
    <div className="py-20 bg-safi-bg min-h-screen relative overflow-hidden">
      <div className="absolute top-0 left-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 -translate-x-1/4 pointer-events-none"></div>
      <div className="absolute bottom-0 right-0 w-[400px] h-[400px] bg-safi-gold/5 rounded-full blur-3xl translate-y-1/4 translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10">
        <div className="text-center mb-16">
          <h2 className="text-4xl md:text-6xl font-serif font-bold text-safi-green mb-6">{t('about.title1', 'О компании')} <span className="italic text-safi-gold">Safi Life</span></h2>
          <p className="text-safi-text opacity-70 max-w-2xl mx-auto uppercase tracking-wider text-xs font-bold leading-relaxed">{t('about.subtitle', 'Safi Life объединяет людей, продукты и предпринимательские возможности.')}</p>
        </div>

        <div className="grid lg:grid-cols-2 gap-16 items-center mb-32 max-w-6xl mx-auto">
          <div className="order-2 lg:order-1 space-y-10">
            <div>
              <h3 className="text-3xl font-serif font-bold text-safi-green mb-4">{t('about.mission1', 'Наша')} <span className="italic text-safi-gold">{t('about.mission2', 'миссия')}</span></h3>
              <p className="text-safi-text opacity-80 leading-relaxed text-lg">
                {t('about.missionDesc', 'Мы создаём платформу, где каждый партнёр может развивать продажи, строить команду и получать поддержку на каждом этапе. Наша цель — дать людям возможность пользоваться качественной отечественной продукцией и при этом строить стабильный и прозрачный бизнес.')}
              </p>
            </div>
            <div>
              <h3 className="text-3xl font-serif font-bold text-safi-green mb-4">Наш <span className="italic text-safi-gold">{t('about.path2', 'путь')}</span></h3>
              <p className="text-safi-text opacity-80 leading-relaxed text-lg">
                {t('about.pathDesc', 'Основной рынок Safi Life — Казахстан. В ближайшем будущем мы планируем расширение в другие страны СНГ, открывая новые возможности для наших текущих и будущих партнёров.')}
              </p>
            </div>
          </div>
          <div className="relative order-1 lg:order-2">
            <div className="absolute inset-0 bg-safi-green/10 rounded-[40px] translate-x-6 translate-y-6"></div>
            <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&q=80&w=800" alt="Team" className="relative rounded-[40px] z-10 w-full object-cover shadow-xl aspect-square lg:aspect-auto h-[500px]" />
          </div>
        </div>
        
        <div className="text-center mb-12">
          <h2 className="text-3xl md:text-5xl font-serif font-bold text-safi-green">{t('about.values', 'Ценности')} <span className="italic text-safi-gold">Safi Life</span></h2>
        </div>
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
          {['Качество', 'Прозрачность', 'Поддержка партнёров', 'Развитие команды', 'Натуральность', 'Доступность'].map((val, i) => (
            <div key={i} className="bg-white border border-safi-green/5 p-8 rounded-[32px] text-center shadow-sm hover:-translate-y-1 transition-transform duration-300">
              <h4 className="text-xl font-serif text-safi-green font-bold">{val}</h4>
            </div>
          ))}
        </div>
      </Container>
    </div>
  );
}
