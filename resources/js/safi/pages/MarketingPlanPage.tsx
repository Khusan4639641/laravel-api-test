import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Check, Calculator, Gift, Network, RefreshCw, Wallet } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { statuses } from '../data/statuses';
import { packages } from '../data/packages';

const bonusTypes = [
  {
    title: 'Реферальный бонус',
    value: '5-10%',
    desc: 'Бонус за личные рекомендации при покупке пакета новым партнером.',
    icon: Wallet,
  },
  {
    title: 'Бинарный бонус',
    value: '7-10%',
    desc: 'Начисляется с меньшей ветки структуры при образовании бинарной пары.',
    icon: Network,
  },
  {
    title: 'Статусный бонус',
    value: 'PV',
    desc: 'Премии и подарки за достижение определенных объемов накопленных PV.',
    icon: Gift,
  },
  {
    title: 'Bonus X2',
    value: 'x2',
    desc: 'Дополнительная механика роста при достижении статусов партнерами первой линии.',
    icon: RefreshCw,
  },
  {
    title: 'Кэшбэк',
    value: '20%',
    desc: 'Классический возврат за личные покупки продукции из каталога Safi Life.',
    icon: Calculator,
  },
];

export default function MarketingPlanPage() {
  const { t } = useTranslation();
  const [selectedPackage, setSelectedPackage] = useState(packages[1].id);
  const [personalSales, setPersonalSales] = useState(100000);
  const [leftVol, setLeftVol] = useState(500000);
  const [rightVol, setRightVol] = useState(400000);

  const activePackage = packages.find((pkg) => pkg.id === selectedPackage) || packages[1];
  const estimatedReferral = (personalSales * activePackage.referralBonus) / 100;
  const lesserBranch = Math.min(leftVol, rightVol);
  const estimatedBinary = activePackage.binaryBonus ? (lesserBranch * activePackage.binaryBonus) / 100 : 0;
  const totalEstimated = estimatedReferral + estimatedBinary;

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[1fr_0.9fr] lg:items-center">
            <div className="max-w-3xl">
              <span className="safi-kicker">Binary + Classic</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                {t('marketing.title1', 'Маркетинг')}-{t('marketing.title2', 'план')}{' '}
                <span className="italic text-safi-gold">Safi Life</span>
              </h1>
              <p className="mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
                Прозрачная модель бонусов: личные продажи, бинарная структура, статусы, депозит и кэшбэк.
              </p>
              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Button to="/register" size="lg">
                  Начать
                  <ArrowRight className="h-4 w-4" />
                </Button>
                <Button to="/business" size="lg" variant="outline">
                  Возможности
                </Button>
              </div>
            </div>

            <div className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_24px_70px_rgba(11,23,18,0.10)]">
              <span className="safi-kicker">Plan summary</span>
              <div className="mt-6 grid grid-cols-2 gap-3">
                <SummaryMetric label="Кэшбэк" value="20%" />
                <SummaryMetric label="Выплаты" value="14 дней" />
                <SummaryMetric label="Бинар" value="7-10%" />
                <SummaryMetric label="Реф." value="5-10%" />
              </div>
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">Bonuses</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              {t('marketing.bonusTypes', 'Виды бонусов')}
            </h2>
          </div>

          <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-5">
            {bonusTypes.map(({ title, value, desc, icon: Icon }) => (
              <article key={title} className="rounded-3xl border border-safi-border bg-safi-cream p-6">
                <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-safi-green">
                  <Icon className="h-5 w-5" />
                </div>
                <div className="font-serif text-4xl font-semibold text-safi-gold">{value}</div>
                <h3 className="mt-4 font-serif text-2xl font-semibold text-safi-green">{title}</h3>
                <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">Calculator</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Калькулятор дохода
            </h2>
            <p className="mx-auto mt-5 max-w-2xl text-base leading-8 text-safi-muted">
              Примерный расчет для демонстрации механики. Он не является гарантией начислений.
            </p>
          </div>

          <div className="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div className="rounded-[36px] border border-safi-border bg-white p-7 md:p-9 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
              <FieldGroup label="Ваш пакет">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                  {packages.map((pkg) => (
                    <button
                      key={pkg.id}
                      type="button"
                      onClick={() => setSelectedPackage(pkg.id)}
                      className={`rounded-full border px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-all ${
                        selectedPackage === pkg.id
                          ? 'border-safi-green bg-safi-green text-white'
                          : 'border-safi-border bg-safi-cream text-safi-muted hover:border-safi-green hover:text-safi-green'
                      }`}
                    >
                      {pkg.name}
                    </button>
                  ))}
                </div>
              </FieldGroup>

              <div className="mt-8 space-y-8">
                <RangeField
                  label="Личные продажи, ₸"
                  value={personalSales}
                  max={1000000}
                  step={10000}
                  onChange={setPersonalSales}
                />
                <RangeField
                  label="Объем левой ветки, ₸"
                  value={leftVol}
                  max={5000000}
                  step={50000}
                  onChange={setLeftVol}
                />
                <RangeField
                  label="Объем правой ветки, ₸"
                  value={rightVol}
                  max={5000000}
                  step={50000}
                  onChange={setRightVol}
                />
              </div>
            </div>

            <div className="rounded-[36px] border border-safi-green bg-safi-green p-7 text-white md:p-9 shadow-[0_24px_70px_rgba(11,23,18,0.14)]">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">Пример расчета</span>
              <div className="mt-8 space-y-5">
                <ResultRow label="Ориентировочный реферальный бонус" value={estimatedReferral} />
                <ResultRow label="Ориентировочный бинарный бонус" value={estimatedBinary} />
              </div>
              <div className="mt-10 border-t border-white/10 pt-8">
                <div className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-white/60">Примерный общий доход</div>
                <div className="mt-3 font-serif text-5xl font-semibold text-safi-gold md:text-6xl">
                  {totalEstimated.toLocaleString('ru-RU')} ₸
                </div>
              </div>
              <p className="mt-8 text-xs uppercase leading-6 tracking-[0.14em] text-white/50">
                Расчет предварительный и зависит от активности партнера, продаж и условий маркетинг-плана.
              </p>
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">Statuses</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Статусы и вознаграждения
            </h2>
          </div>

          <div className="overflow-x-auto rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
            <table className="w-full min-w-[760px] text-left">
              <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green">
                <tr>
                  <th className="px-6 py-5">Статус</th>
                  <th className="px-6 py-5">Накоплено PV</th>
                  <th className="px-6 py-5">Потенциал</th>
                  <th className="px-6 py-5">Вознаграждение</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-safi-border">
                {statuses.map((status) => (
                  <tr key={status.id} className="transition-colors hover:bg-safi-cream/70">
                    <td className="px-6 py-5 font-serif text-xl font-semibold text-safi-green">{status.name}</td>
                    <td className="px-6 py-5 text-sm font-bold text-safi-muted">{status.pv.toLocaleString('ru-RU')} PV</td>
                    <td className="px-6 py-5 text-sm font-bold text-safi-muted">{status.incomePotential.toLocaleString('ru-RU')} ₸</td>
                    <td className="px-6 py-5 text-sm font-extrabold text-safi-gold">{status.reward}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mx-auto mt-10 max-w-3xl rounded-3xl border border-safi-border bg-white p-7 text-center text-xs font-bold uppercase leading-6 tracking-[0.14em] text-safi-muted">
            Все бонусы начисляются согласно действующему маркетинг-{t('marketing.title2', 'план')}у компании. Доход не гарантирован и зависит от активности партнера, продаж, структуры и выполнения условий.
          </div>
        </Container>
      </section>
    </div>
  );
}

function SummaryMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-3xl border border-safi-border bg-safi-cream p-5 text-center">
      <div className="font-serif text-3xl font-semibold text-safi-green">{value}</div>
      <div className="mt-2 text-[9px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
    </div>
  );
}

function FieldGroup({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-3 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</label>
      {children}
    </div>
  );
}

function RangeField({
  label,
  value,
  max,
  step,
  onChange,
}: {
  label: string;
  value: number;
  max: number;
  step: number;
  onChange: (value: number) => void;
}) {
  return (
    <FieldGroup label={label}>
      <input
        type="range"
        min="0"
        max={max}
        step={step}
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
        className="w-full accent-safi-gold"
      />
      <div className="mt-2 text-right text-sm font-extrabold text-safi-green">{value.toLocaleString('ru-RU')} ₸</div>
    </FieldGroup>
  );
}

function ResultRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-start justify-between gap-5 border-b border-white/10 pb-5">
      <div className="flex gap-3 text-sm leading-6 text-white/75">
        <Check className="mt-1 h-4 w-4 shrink-0 text-safi-gold" />
        <span>{label}</span>
      </div>
      <span className="shrink-0 font-extrabold text-safi-gold">{value.toLocaleString('ru-RU')} ₸</span>
    </div>
  );
}
