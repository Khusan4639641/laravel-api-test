import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { fallbackLegalSettings, getPublicLegalSettings, LegalSettings } from '../lib/api';

export default function ContactsPage() {
  const { t } = useTranslation();
  const [settings, setSettings] = useState<LegalSettings>(fallbackLegalSettings);

  useEffect(() => {
    let isMounted = true;

    getPublicLegalSettings()
      .then((legalSettings) => {
        if (isMounted) {
          setSettings(legalSettings);
        }
      })
      .catch(() => {
        // Contacts stay visible with safe defaults while settings API is unavailable.
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <div className="py-20 bg-safi-bg min-h-[calc(100vh-80px)] relative overflow-hidden flex flex-col justify-center">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10 w-full">
        <div className="grid md:grid-cols-2 gap-16 max-w-6xl mx-auto items-center">
          <div>
            <h1 className="text-4xl md:text-6xl font-serif font-bold mb-6 text-safi-green">{t('legal.contacts', 'Контакты')}</h1>
            <p className="text-safi-text opacity-70 mb-10 text-lg leading-relaxed">
              {t('contacts.title1', 'Свяжитесь')} {t('contacts.title2', 'с нами')} для получения консультации по продуктам, оплате, доставке, возвратам или вопросам партнерства.
            </p>

            <div className="space-y-8 relative">
              <div className="absolute left-0 top-0 bottom-0 w-px bg-safi-green/10"></div>
              <ContactItem label="Адрес" value={settings.actual_address} />
              <ContactItem label="Телефон" value={settings.support_phone} />
              <ContactItem label="Поддержка" value={settings.support_email} href={`mailto:${settings.support_email}`} />
              <ContactItem label="Споры и возвраты" value={settings.dispute_email} href={`mailto:${settings.dispute_email}`} />
              <ContactItem label="Юридическое лицо" value={`${settings.company_legal_name}, БИН ${settings.company_bin}`} />
            </div>
          </div>

          <div className="bg-white p-8 md:p-10 rounded-[40px] shadow-xl border border-safi-green/5 relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-safi-gold/10 rounded-bl-full -z-10"></div>
            <h3 className="text-3xl font-serif font-bold mb-6 text-safi-green">
              {t('contacts.supportTitle', 'Поддержка')} <span className="italic text-safi-gold">Safi Life</span>
            </h3>
            <p className="text-sm leading-7 text-safi-text/70">
              По вопросам заказов, онлайн-оплаты, доставки, возврата денежных средств и спорных операций используйте контакты компании или создайте обращение в личном кабинете.
            </p>
            <div className="mt-8 grid gap-3 sm:grid-cols-2">
              <Button to="/login" className="w-full">{t('nav.login', 'Вход')}</Button>
              <Button to="/login" variant="outline" className="w-full">{t('nav.cabinet', 'Кабинет')}</Button>
            </div>
            <div className="mt-8 rounded-3xl border border-safi-green/10 bg-[#F5F5F0] p-5 text-sm leading-7 text-safi-green/75">
              {t('contacts.supportNote', 'Если у вас уже есть аккаунт, откройте раздел “Поддержка” в кабинете и создайте обращение с темой и описанием вопроса.')}
            </div>
          </div>
        </div>
      </Container>
    </div>
  );
}

function ContactItem({ label, value, href }: { label: string; value: string; href?: string }) {
  const content = <span className="text-safi-green font-serif text-xl font-bold">{value}</span>;

  return (
    <div className="pl-6 relative">
      <div className="absolute left-[-4px] top-2 w-2 h-2 rounded-full bg-safi-gold"></div>
      <h4 className="text-[10px] uppercase font-bold text-safi-gold tracking-widest mb-1">{label}</h4>
      {href ? (
        <a href={href} className="transition-colors hover:text-safi-gold">
          {content}
        </a>
      ) : content}
    </div>
  );
}
