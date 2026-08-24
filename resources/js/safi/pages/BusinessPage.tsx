import React from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { SectionTitle } from '../components/ui/SectionTitle';
import { useUiText } from '../i18n/useUiText';

export default function BusinessPage() {
  const { t } = useTranslation();
  const ui = useUiText();
  return (
    <div className="py-20 bg-safi-bg min-h-screen relative overflow-hidden">
      <div className="absolute top-0 right-0 w-[600px] h-[600px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
      
      <Container className="relative z-10">
        <div className="text-center mb-16">
          <h2 className="text-4xl md:text-5xl font-serif font-bold text-safi-green mb-4"><span className="italic text-safi-gold">{t('business.title1', 'Стать партнёром')}</span> Safi Life</h2>
          <p className="text-safi-text opacity-70 max-w-2xl mx-auto uppercase tracking-wider text-xs font-bold">{t('business.subtitle', 'Возможность дополнительного дохода и развития собственного бизнеса')}</p>
        </div>
        
        <div className="max-w-4xl mx-auto space-y-16">
          <div className="bg-white p-8 md:p-12 rounded-[40px] shadow-sm border border-safi-green/5 relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-safi-gold/5 rounded-bl-full -z-10"></div>
            <h3 className="text-3xl font-serif font-bold text-safi-green mb-8 text-center">{t('business.forWho1', 'Для кого')} <span className="italic text-safi-gold">{t('business.forWho2', 'этот бизнес?')}</span></h3>
            <ul className="grid sm:grid-cols-2 gap-6 text-safi-text">
              {['Люди, ищущие дополнительный заработок', 'Начинающие предприниматели', 'Женщины в декрете', 'Студенты', 'Поклонники натуральных продуктов', 'Действующие MLM партнеры'].map((item) => (
                <li key={item} className="flex items-center gap-4 bg-[#F5F5F0] p-4 rounded-xl"><div className="w-2 h-2 bg-safi-gold rounded-full" />{ui(item)}</li>
              ))}
            </ul>
          </div>

          <div className="p-8 md:p-12 bg-safi-green rounded-[40px] relative overflow-hidden text-white shadow-xl">
             <div className="absolute -top-20 -left-20 w-64 h-64 bg-white/5 rounded-full blur-2xl z-0"></div>
            <h3 className="text-3xl font-serif font-bold mb-10 text-center relative z-10">{t('business.why1', 'Почему')} <span className="italic text-safi-gold">{t('business.why2', 'Safi Life?')}</span></h3>
            <div className="space-y-6 relative z-10 text-white/90 text-lg">
              {[
                'Не нужно искусственно "выращивать лидеров". Маркетинг-план позволяет зарабатывать без скрытых обязательств.',
                'Отсутствие требований подтверждения статуса. Ваши достижения сохраняются.',
                'Накопительная система баллов (PV). Вы не теряете объемы при переходе в новый период.',
                'Расчёт бинарного бонуса каждые 15 дней. Расчёт выполняется по малой ветке с разделением 90% в основной кошелёк и 10% в депозитный.',
              ].map((item) => <p key={item} className="flex items-start gap-4"><span className="text-safi-gold font-bold mt-1">✓</span><span>{ui(item)}</span></p>)}
            </div>
          </div>

          <div className="text-center p-8 border border-safi-green/10 rounded-[24px] bg-white text-[10px] uppercase tracking-widest text-safi-text opacity-50 font-bold max-w-2xl mx-auto shadow-sm">
            {t('business.disclaimer', '«Потенциальный доход зависит от личных продаж, активности, структуры команды и выполнения условий маркетинг-плана. Информация не является гарантией заработка.»')}
          </div>
        </div>
      </Container>
    </div>
  );
}
