import React from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Leaf, ShieldCheck, Users, WandSparkles } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';

const values = [
  { title: 'Качество', desc: 'Составы, категории и подача продукта должны быть понятны партнеру и клиенту.', icon: ShieldCheck },
  { title: 'Прозрачность', desc: 'Условия роста, бонусы и статусы показываются без скрытых требований.', icon: WandSparkles },
  { title: 'Поддержка', desc: 'Партнер получает систему, материалы и личный кабинет для ежедневной работы.', icon: Users },
  { title: 'Натуральность', desc: 'Фокус на продуктах для здоровья, красоты и бережного ежедневного ухода.', icon: Leaf },
];

const milestones = [
  { value: 'KZ', label: 'Отечественный фокус' },
  { value: '20%', label: 'Классический кэшбэк' },
  { value: '14', label: 'Дней между выплатами' },
];

export default function AboutPage() {
  const { t } = useTranslation();

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
            <div className="max-w-3xl">
              <span className="safi-kicker">About Safi Life</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                {t('about.title1', 'О компании')}{' '}
                <span className="italic text-safi-gold">Safi Life</span>
              </h1>
              <p className="mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
                {t(
                  'about.subtitle',
                  'Safi Life объединяет людей, продукты и предпринимательские возможности.'
                )}
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Button to="/business" size="lg">
                  Стать партнером
                  <ArrowRight className="h-4 w-4" />
                </Button>
                <Button to="/products" size="lg" variant="outline">
                  Смотреть продукты
                </Button>
              </div>
            </div>

            <div className="overflow-hidden rounded-[36px] border border-safi-border bg-white p-3 shadow-[0_24px_70px_rgba(11,23,18,0.10)]">
              <img
                src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&q=80&w=1100"
                alt="Команда Safi Life"
                className="h-[360px] w-full rounded-[28px] object-cover md:h-[480px]"
              />
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
            <div>
              <span className="safi-kicker">Mission</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Платформа для продукта, команды и роста
              </h2>
            </div>
            <div className="grid gap-5 md:grid-cols-2">
              <article className="rounded-3xl border border-safi-border bg-safi-cream p-7">
                <h3 className="font-serif text-3xl font-semibold text-safi-green">
                  {t('about.mission1', 'Наша')} <span className="italic text-safi-gold">{t('about.mission2', 'миссия')}</span>
                </h3>
                <p className="mt-4 text-sm leading-7 text-safi-muted">
                  {t(
                    'about.missionDesc',
                    'Мы создаём платформу, где каждый партнёр может развивать продажи, строить команду и получать поддержку на каждом этапе. Наша цель — дать людям возможность пользоваться качественной отечественной продукцией и при этом строить стабильный и прозрачный бизнес.'
                  )}
                </p>
              </article>
              <article className="rounded-3xl border border-safi-border bg-safi-cream p-7">
                <h3 className="font-serif text-3xl font-semibold text-safi-green">
                  Наш <span className="italic text-safi-gold">{t('about.path2', 'путь')}</span>
                </h3>
                <p className="mt-4 text-sm leading-7 text-safi-muted">
                  {t(
                    'about.pathDesc',
                    'Основной рынок Safi Life — Казахстан. В ближайшем будущем мы планируем расширение в другие страны СНГ, открывая новые возможности для наших текущих и будущих партнёров.'
                  )}
                </p>
              </article>
            </div>
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">{t('about.values', 'Ценности')}</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Что держит стиль Safi Life
            </h2>
          </div>

          <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            {values.map(({ title, desc, icon: Icon }) => (
              <article key={title} className="rounded-3xl border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="font-serif text-2xl font-semibold text-safi-green">{title}</h3>
                <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="bg-safi-green py-12 text-white md:py-16">
        <Container>
          <div className="grid divide-y divide-white/10 rounded-3xl border border-white/10 bg-white/[0.06] md:grid-cols-3 md:divide-x md:divide-y-0">
            {milestones.map((item) => (
              <div key={item.label} className="p-8 text-center">
                <div className="font-serif text-5xl font-semibold text-safi-gold">{item.value}</div>
                <div className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.18em] text-white/70">{item.label}</div>
              </div>
            ))}
          </div>
        </Container>
      </section>
    </div>
  );
}
