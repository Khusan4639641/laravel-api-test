import React from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Check, LineChart, Network, PackageCheck, Wallet } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { packages } from '../data/packages';

const audience = [
  'Люди, ищущие дополнительный заработок',
  'Начинающие предприниматели',
  'Женщины в декрете',
  'Студенты',
  'Поклонники натуральных продуктов',
  'Действующие MLM партнеры',
];

const advantages = [
  {
    title: 'Продуктовая основа',
    desc: 'Бизнес начинается с личного опыта, повторных покупок и понятных категорий продукта.',
    icon: PackageCheck,
  },
  {
    title: 'Бинарная структура',
    desc: 'Левая и правая ветка помогают видеть рост команды и расчет меньшей ветки.',
    icon: Network,
  },
  {
    title: 'Регулярные выплаты',
    desc: 'Цикл выплат каждые 14 дней делает финансовую часть более предсказуемой.',
    icon: Wallet,
  },
  {
    title: 'Рост по статусам',
    desc: 'PV, пакеты и статусы показывают следующий понятный шаг в развитии.',
    icon: LineChart,
  },
];

export default function BusinessPage() {
  const { t } = useTranslation();

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[1fr_0.9fr] lg:items-center">
            <div className="max-w-3xl">
              <span className="safi-kicker">Business opportunity</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                <span className="italic text-safi-gold">{t('business.title1', 'Стать партнёром')}</span>{' '}
                Safi Life
              </h1>
              <p className="mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
                {t('business.subtitle', 'Возможность дополнительного дохода и развития собственного бизнеса')}
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Button to="/register" size="lg">
                  Начать бизнес
                  <ArrowRight className="h-4 w-4" />
                </Button>
                <Button to="/marketing" size="lg" variant="outline">
                  Маркетинг-план
                </Button>
              </div>
            </div>

            <div className="rounded-[36px] border border-safi-border bg-white p-8 shadow-[0_24px_70px_rgba(11,23,18,0.10)]">
              <span className="safi-kicker">Binary + Classic</span>
              <div className="mt-6 grid gap-4">
                <HeroMetric value="10%" label="Реферальный бонус" />
                <HeroMetric value="7-10%" label="Бинарный бонус" />
                <HeroMetric value="20%" label="Кэшбэк за покупки" />
              </div>
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">{t('business.forWho1', 'Для кого')}</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Этот бизнес подходит разным сценариям
            </h2>
          </div>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {audience.map((item) => (
              <article key={item} className="flex items-start gap-4 rounded-3xl border border-safi-border bg-safi-cream p-6">
                <span className="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-safi-gold text-safi-black">
                  <Check className="h-4 w-4" />
                </span>
                <h3 className="font-serif text-xl font-semibold leading-snug text-safi-green">{item}</h3>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
            <div>
              <span className="safi-kicker">{t('business.why1', 'Почему')} Safi Life</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Понятная механика роста без лишней сложности
              </h2>
            </div>
            <div className="grid gap-5 md:grid-cols-2">
              {advantages.map(({ title, desc, icon: Icon }) => (
                <article key={title} className="rounded-3xl border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
                  <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
                    <Icon className="h-5 w-5" />
                  </div>
                  <h3 className="font-serif text-2xl font-semibold text-safi-green">{title}</h3>
                  <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
                </article>
              ))}
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div>
              <span className="safi-kicker">Start packages</span>
              <h2 className="mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Выберите уровень старта
              </h2>
            </div>
            <Button to="/marketing" variant="outline">
              Смотреть план
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>

          <div className="grid gap-5 md:grid-cols-3">
            {packages.map((pkg) => (
              <article
                key={pkg.id}
                className={`rounded-3xl border p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] ${
                  pkg.isPopular ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white text-safi-green'
                }`}
              >
                <h3 className={`font-serif text-3xl font-semibold ${pkg.isPopular ? 'text-white' : 'text-safi-green'}`}>{pkg.name}</h3>
                <div className={`mt-3 text-4xl font-extrabold ${pkg.isPopular ? 'text-safi-gold' : 'text-safi-green'}`}>
                  {pkg.price.toLocaleString('ru-RU')} ₸
                </div>
                <div className={`mt-5 text-sm leading-7 ${pkg.isPopular ? 'text-white/75' : 'text-safi-muted'}`}>
                  Реферальный бонус {pkg.referralBonus}%, бинарный бонус {pkg.binaryBonus || 0}%.
                </div>
              </article>
            ))}
          </div>

          <div className="mx-auto mt-12 max-w-3xl rounded-3xl border border-safi-border bg-white p-7 text-center text-xs font-bold uppercase leading-6 tracking-[0.14em] text-safi-muted">
            {t('business.disclaimer', '«Потенциальный доход зависит от личных продаж, активности, структуры команды и выполнения условий маркетинг-плана. Информация не является гарантией заработка.»')}
          </div>
        </Container>
      </section>
    </div>
  );
}

function HeroMetric({ value, label }: { value: string; label: string }) {
  return (
    <div className="flex items-center justify-between gap-5 rounded-3xl border border-safi-border bg-safi-cream p-5">
      <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      <span className="font-serif text-4xl font-semibold text-safi-gold">{value}</span>
    </div>
  );
}
