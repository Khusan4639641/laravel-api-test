import React from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, FileText, ShieldCheck } from 'lucide-react';
import { Container } from '../components/ui/Container';

const incomeFactors = [
  'Личной активности и усердия',
  'Объема продаж продукции',
  'Структуры команды и её эффективности',
  'Соблюдения условий маркетинг-плана компании',
  'Общего состояния рынка',
];

const legalSections = [
  {
    title: 'Отказ от ответственности по доходам',
    icon: AlertTriangle,
    body: [
      'Любые заявления о доходах или заработках на данном сайте являются оценкой того, что вы могли бы заработать. Информация, представленная на сайте, носит исключительно ознакомительный характер.',
      'Мы не даем гарантий относительно того, что вы достигнете заявленных уровней дохода. Результаты партнеров могут отличаться.',
    ],
  },
  {
    title: 'О продуктах',
    icon: ShieldCheck,
    body: [
      'Продукция категории "Здоровье" не является лекарственным средством. Перед применением рекомендуется проконсультироваться со специалистом.',
      'Информация о продуктах не предназначена для диагностики, лечения или предотвращения каких-либо заболеваний.',
    ],
  },
  {
    title: 'Пользовательское соглашение и политика конфиденциальности',
    icon: FileText,
    body: [
      'Используя данный сайт и оставляя свои данные, вы соглашаетесь с условиями обработки персональных данных.',
      'Данный раздел находится в стадии наполнения и будет содержать полные юридические реквизиты компании.',
    ],
  },
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
              Правовая информация об использовании сайта Safi Life, продуктов компании и участии в партнерской программе.
            </p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mx-auto grid max-w-6xl gap-6">
            {legalSections.map(({ title, body, icon: Icon }) => (
              <article key={title} className="rounded-[32px] border border-safi-border bg-safi-bg p-7 md:p-9">
                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-safi-green">
                  <Icon className="h-5 w-5" />
                </div>
                <h2 className="font-serif text-3xl font-semibold leading-tight text-safi-green md:text-4xl">{title}</h2>
                <div className="mt-5 space-y-4 text-sm leading-7 text-safi-muted md:text-base">
                  {body.map((paragraph) => (
                    <p key={paragraph}>{paragraph}</p>
                  ))}
                </div>
              </article>
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
