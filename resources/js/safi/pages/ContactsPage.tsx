import React from 'react';
import { useTranslation } from 'react-i18next';
import { Clock, Mail, MapPin, Phone } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';

const contacts = [
  { label: 'Адрес', value: 'Республика Казахстан, г. Алматы', icon: MapPin },
  { label: 'Телефон', value: '+7 (700) 000-00-00', icon: Phone },
  { label: 'Email', value: 'info@safilife.kz', icon: Mail },
  { label: 'Время работы', value: 'Пн-Пт, 10:00-19:00', icon: Clock },
];

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function ContactsPage() {
  const { t } = useTranslation();

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
            <div>
              <span className="safi-kicker">Contacts</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                {t('contacts.title1', 'Свяжитесь')}{' '}
                <span className="italic text-safi-gold">{t('contacts.title2', 'с нами')}</span>
              </h1>
            </div>
            <p className="max-w-2xl text-base leading-8 text-safi-muted md:text-xl lg:justify-self-end">
              Получите консультацию по продуктам, стартовым пакетам или вопросам партнерства Safi Life.
            </p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-1">
              {contacts.map(({ label, value, icon: Icon }) => (
                <article key={label} className="rounded-3xl border border-safi-border bg-safi-cream p-7">
                  <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-safi-green">
                    <Icon className="h-5 w-5" />
                  </div>
                  <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
                  <div className="mt-2 font-serif text-2xl font-semibold leading-snug text-safi-green">{value}</div>
                </article>
              ))}
            </div>

            <div className="rounded-[36px] border border-safi-border bg-safi-bg p-6 shadow-[0_24px_70px_rgba(11,23,18,0.08)] md:p-9">
              <span className="safi-kicker">Request</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">
                Оставить <span className="italic text-safi-gold">заявку</span>
              </h2>
              <form className="mt-8 grid gap-5" onSubmit={(event) => event.preventDefault()}>
                <div className="grid gap-5 md:grid-cols-2">
                  <FormField label="Имя">
                    <input type="text" className={inputClass} placeholder={t('contacts.formName', 'Ваше имя')} />
                  </FormField>
                  <FormField label={t('contacts.formPhone', 'Телефон')}>
                    <input type="tel" className={inputClass} placeholder="+7 (___) ___-__-__" />
                  </FormField>
                </div>

                <FormField label="Меня интересует">
                  <select className={inputClass}>
                    <option>Продукция</option>
                    <option>Партнерство</option>
                    <option>Маркетинг-план</option>
                    <option>Другой вопрос</option>
                  </select>
                </FormField>

                <FormField label="Сообщение">
                  <textarea className={`${inputClass} min-h-32 resize-none`} placeholder="Коротко опишите вопрос" />
                </FormField>

                <Button type="submit" size="lg" className="w-full md:w-fit">
                  Отправить заявку
                </Button>
              </form>
            </div>
          </div>
        </Container>
      </section>

      <section className="border-t border-safi-border bg-safi-cream py-14">
        <Container>
          <div className="rounded-[36px] border border-safi-border bg-white p-8 md:p-10">
            <span className="safi-kicker">Office</span>
            <div className="mt-4 grid gap-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
              <h2 className="font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">
                Центральный контакт для партнеров и клиентов
              </h2>
              <p className="text-sm leading-7 text-safi-muted md:text-base">
                На этом этапе форма работает как frontend-макет: данные не отправляются в API. Подключение backend-обработчика лучше делать отдельной задачей.
              </p>
            </div>
          </div>
        </Container>
      </section>
    </div>
  );
}

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      {children}
    </label>
  );
}
