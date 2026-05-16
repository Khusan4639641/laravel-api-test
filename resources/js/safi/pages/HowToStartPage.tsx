import React from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { SectionTitle } from '../components/ui/SectionTitle';
import { Button } from '../components/ui/Button';

export default function HowToStartPage() {
  const { t } = useTranslation();
  const steps = [
    { title: t('howToStart.steps.0.title', 'Оставьте заявку или зарегистрируйтесь'), desc: t('howToStart.steps.0.desc', 'Воспользуйтесь реферальной ссылкой или заполните форму.') },
    { title: t('howToStart.steps.1.title', 'Выберите стартовый пакет'), desc: t('howToStart.steps.1.desc', 'Определитесь между BUSINESS, VIP или ELITE.') },
    { title: t('howToStart.steps.2.title', 'Доступ к кабинету'), desc: t('howToStart.steps.2.desc', 'Получите доступ к панели управления и обучению.') },
    { title: t('howToStart.steps.3.title', 'Пройдите обучение'), desc: t('howToStart.steps.3.desc', 'Изучите продукты компании и материалы для партнёров.') },
    { title: t('howToStart.steps.4.title', 'Начните продажи и приглашения'), desc: t('howToStart.steps.4.desc', 'Зарабатывайте реферальные и классические бонусы.') },
    { title: t('howToStart.steps.5.title', 'Развивайте бинар'), desc: t('howToStart.steps.5.desc', 'Стройте левую и правую ветки для получения 7-10% с меньшей ветки.') },
    { title: t('howToStart.steps.6.title', 'Достигайте статусов'), desc: t('howToStart.steps.6.desc', 'Получайте премии и отслеживайте рост команды.') },
  ];

  return (
    <div className="py-20 bg-safi-bg min-h-screen relative overflow-hidden">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10">
        <div className="text-center mb-16">
          <h2 className="text-4xl md:text-5xl font-serif font-bold text-safi-green mb-4">{t('howToStart.pageTitle', 'С чего')} <span className="italic text-safi-gold">{t('howToStart.title2', 'начать')}</span></h2>
          <p className="text-safi-text opacity-70 max-w-2xl mx-auto uppercase tracking-wider text-xs font-bold">{t('howToStart.pageSubtitle', '7 простых шагов к созданию бизнеса вместе с Safi Life')}</p>
        </div>
        
        <div className="max-w-3xl mx-auto space-y-6 mb-16">
          {steps.map((step, i) => (
            <div key={i} className="flex gap-6 items-start bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5 hover:-translate-y-1 transition-transform duration-300">
              <div className="w-12 h-12 bg-[#F5F5F0] text-safi-gold font-serif font-bold text-2xl rounded-[16px] flex items-center justify-center shrink-0">
                {i + 1}
              </div>
              <div className="pt-2">
                <h4 className="text-xl font-serif font-bold text-safi-green mb-2">{step.title}</h4>
                <p className="text-safi-text opacity-70 text-sm leading-relaxed">{step.desc}</p>
              </div>
            </div>
          ))}
        </div>
        
        <div className="text-center">
          <Button size="lg" to="/register">{t('howToStart.startBtn', 'Начать с Safi Life')}</Button>
        </div>
      </Container>
    </div>
  );
}
