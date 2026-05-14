import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Check, LogIn, PackagePlus, PlayCircle } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { getPublicPackages, Package } from '../lib/api';

export default function HowToStartPage() {
  const { t } = useTranslation();
  const [packages, setPackages] = useState<Package[]>([]);
  const steps = [
    { title: t('howToStart.steps.0.title', 'Оставьте заявку или зарегистрируйтесь'), desc: t('howToStart.steps.0.desc', 'Воспользуйтесь реферальной ссылкой или заполните форму.') },
    { title: t('howToStart.steps.1.title', 'Выберите стартовый пакет'), desc: t('howToStart.steps.1.desc', 'Определитесь между BUSINESS, VIP или ELITE.') },
    { title: t('howToStart.steps.2.title', 'Доступ к кабинету'), desc: t('howToStart.steps.2.desc', 'Получите доступ к панели управления и обучению.') },
    { title: t('howToStart.steps.3.title', 'Пройдите обучение'), desc: t('howToStart.steps.3.desc', 'Изучите продукты компании и материалы для партнёров.') },
    { title: t('howToStart.steps.4.title', 'Начните продажи и приглашения'), desc: t('howToStart.steps.4.desc', 'Зарабатывайте реферальные и классические бонусы.') },
    { title: t('howToStart.steps.5.title', 'Развивайте бинар'), desc: t('howToStart.steps.5.desc', 'Стройте левую и правую ветки для получения 7-10% с меньшей ветки.') },
    { title: t('howToStart.steps.6.title', 'Достигайте статусов'), desc: t('howToStart.steps.6.desc', 'Получайте премии и отслеживайте рост команды.') },
  ];

  useEffect(() => {
    void getPublicPackages().then(setPackages).catch(() => setPackages([]));
  }, []);

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[1fr_0.9fr] lg:items-center">
            <div className="max-w-3xl">
              <span className="safi-kicker">Start with Safi</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                {t('howToStart.pageTitle', 'С чего')}{' '}
                <span className="italic text-safi-gold">{t('howToStart.title2', 'начать')}</span>
              </h1>
              <p className="mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
                {t('howToStart.pageSubtitle', '7 простых шагов к созданию бизнеса вместе с Safi Life')}
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Button size="lg" to="/register">
                  {t('howToStart.startBtn', 'Начать с Safi Life')}
                  <ArrowRight className="h-4 w-4" />
                </Button>
                <Button size="lg" variant="outline" to="/marketing">
                  Изучить план
                </Button>
              </div>
            </div>

            <div className="grid gap-4">
              <HeroStep icon={LogIn} title="Регистрация" desc="Аккаунт, реферальная ссылка и стартовая заявка." />
              <HeroStep icon={PackagePlus} title="Пакет" desc="Выбор уровня входа с понятными бонусами." />
              <HeroStep icon={PlayCircle} title="Запуск" desc="Продажи, рекомендации и рост структуры." />
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">7 steps</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Пошаговый старт без лишней сложности
            </h2>
          </div>

          <div className="mx-auto grid max-w-5xl gap-5">
            {steps.map((step, index) => (
              <article key={step.title} className="grid gap-5 rounded-3xl border border-safi-border bg-safi-cream p-6 md:grid-cols-[80px_1fr] md:p-7">
                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white font-serif text-3xl font-semibold text-safi-gold">
                  {index + 1}
                </div>
                <div>
                  <h3 className="font-serif text-2xl font-semibold text-safi-green">{step.title}</h3>
                  <p className="mt-3 text-sm leading-7 text-safi-muted">{step.desc}</p>
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div>
              <span className="safi-kicker">Packages</span>
              <h2 className="mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Стартовые пакеты
              </h2>
            </div>
            <Button to="/register" variant="outline">
              Зарегистрироваться
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>

          <div className="grid gap-5 md:grid-cols-3">
            {packages.map((pkg) => (
              <article key={pkg.id} className="rounded-3xl border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
                <h3 className="font-serif text-3xl font-semibold text-safi-green">{pkg.name}</h3>
                <div className="mt-3 text-4xl font-extrabold text-safi-green">{pkg.price.toLocaleString('ru-RU')} ₸</div>
                <ul className="mt-6 space-y-3">
                  {pkg.features.slice(0, 3).map((feature) => (
                    <li key={feature} className="flex gap-3 text-sm leading-6 text-safi-muted">
                      <Check className="mt-1 h-4 w-4 shrink-0 text-safi-gold" />
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </Container>
      </section>
    </div>
  );
}

function HeroStep({ icon: Icon, title, desc }: { icon: React.ComponentType<{ className?: string }>; title: string; desc: string }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
      <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
        <Icon className="h-5 w-5" />
      </div>
      <h3 className="font-serif text-2xl font-semibold text-safi-green">{title}</h3>
      <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
    </article>
  );
}
