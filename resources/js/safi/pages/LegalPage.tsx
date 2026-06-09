import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { CreditCard, FileText, Landmark, RotateCcw, ShieldCheck, Truck } from 'lucide-react';
import { Container } from '../components/ui/Container';

const legalCards = [
  {
    to: '/payment',
    icon: CreditCard,
    key: 'legal.payment',
    fallback: 'Онлайн-оплата',
    text: 'Безопасная оплата банковскими картами Visa и Mastercard через защищенную платежную страницу TipTop Pay.',
  },
  {
    to: '/legal/offer',
    icon: FileText,
    key: 'legal.offer',
    fallback: 'Договор оферты',
    text: 'Условия продажи товаров Safi Life, оплаты, доставки, возврата и ответственности сторон.',
  },
  {
    to: '/legal/privacy',
    icon: ShieldCheck,
    key: 'legal.privacy',
    fallback: 'Политика конфиденциальности',
    text: 'Правила обработки персональных данных пользователей и покупателей на территории Республики Казахстан.',
  },
  {
    to: '/legal/delivery',
    icon: Truck,
    key: 'legal.delivery',
    fallback: 'Доставка',
    text: 'Порядок доставки заказов по Казахстану, подтверждения адреса и отслеживания заказа.',
  },
  {
    to: '/legal/refund',
    icon: RotateCcw,
    key: 'legal.refund',
    fallback: 'Возврат',
    text: 'Правила возврата денежных средств при онлайн-оплате и контакты для спорных ситуаций.',
  },
  {
    to: '/legal/requisites',
    icon: Landmark,
    key: 'legal.requisites',
    fallback: 'Реквизиты',
    text: 'Информация о юридическом лице, банковские реквизиты и официальные контакты компании.',
  },
];

const incomeFactors = [
  'Личной активности и усердия',
  'Объема продаж продукции',
  'Структуры команды и ее эффективности',
  'Соблюдения условий маркетинг-плана компании',
  'Общего состояния рынка',
];

export default function LegalPage() {
  const { t } = useTranslation();

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mx-auto max-w-4xl text-center">
            <span className="safi-kicker">Legal</span>
            <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
              {t('legal.title1', 'Правовая')}{' '}
              <span className="italic text-safi-gold">{t('legal.title2', 'информация')}</span>
            </h1>
            <p className="mx-auto mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
              Публичные документы Safi Life для оплаты, доставки, возврата, обработки персональных данных и разрешения спорных ситуаций.
            </p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mx-auto grid max-w-6xl gap-6 md:grid-cols-2 lg:grid-cols-3">
            {legalCards.map(({ to, key, fallback, text, icon: Icon }) => (
              <Link key={to} to={to} className="group rounded-[32px] border border-safi-border bg-safi-bg p-7 transition-all hover:-translate-y-1 hover:border-safi-green/30 hover:bg-white hover:shadow-xl">
                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-safi-green transition-colors group-hover:bg-safi-green group-hover:text-safi-gold">
                  <Icon className="h-5 w-5" />
                </div>
                <h2 className="font-serif text-2xl font-semibold leading-tight text-safi-green">{t(key, fallback)}</h2>
                <p className="mt-4 text-sm leading-7 text-safi-muted">{text}</p>
                <span className="mt-6 inline-flex text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">Открыть раздел</span>
              </Link>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center">
            <span className="safi-kicker">Income factors</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              От чего зависит результат партнера
            </h2>
          </div>

          <div className="mx-auto grid max-w-5xl gap-4 md:grid-cols-2">
            {incomeFactors.map((factor) => (
              <article key={factor} className="flex items-start gap-4 rounded-3xl border border-safi-border bg-white p-6">
                <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-safi-gold" />
                <p className="text-sm font-bold leading-7 text-safi-green">{factor}</p>
              </article>
            ))}
          </div>
        </Container>
      </section>
    </div>
  );
}
