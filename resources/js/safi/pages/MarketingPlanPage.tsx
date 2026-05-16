import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { SectionTitle } from '../components/ui/SectionTitle';
import { Button } from '../components/ui/Button';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
import { getApiErrorState, getPublicPackages, getPublicStatuses, Package, Status } from '../lib/api';

export default function MarketingPlanPage() {
  const { t } = useTranslation();
  const [packages, setPackages] = useState<Package[]>([]);
  const [statuses, setStatuses] = useState<Status[]>([]);
  const [packagesLoading, setPackagesLoading] = useState(true);
  const [statusesLoading, setStatusesLoading] = useState(true);
  const [packagesError, setPackagesError] = useState<string | null>(null);
  const [statusesError, setStatusesError] = useState<string | null>(null);
  const [selectedPackage, setSelectedPackage] = useState('');
  const [personalSales, setPersonalSales] = useState(100000);
  const [leftVol, setLeftVol] = useState(500000);
  const [rightVol, setRightVol] = useState(400000);

  const loadPackages = React.useCallback(async () => {
    setPackagesLoading(true);
    setPackagesError(null);

    try {
      const items = await getPublicPackages();
      setPackages(items);
      setSelectedPackage((current) => current || items[1]?.id || items[0]?.id || '');
    } catch (caughtError) {
      setPackages([]);
      setSelectedPackage('');
      setPackagesError(getApiErrorState(caughtError).error);
    } finally {
      setPackagesLoading(false);
    }
  }, []);

  const loadStatuses = React.useCallback(async () => {
    setStatusesLoading(true);
    setStatusesError(null);

    try {
      setStatuses(await getPublicStatuses());
    } catch (caughtError) {
      setStatuses([]);
      setStatusesError(getApiErrorState(caughtError).error);
    } finally {
      setStatusesLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadPackages();
    void loadStatuses();
  }, [loadPackages, loadStatuses]);

  const activePackage = useMemo(
    () => packages.find((pkg) => pkg.id === selectedPackage) || packages[1] || packages[0],
    [packages, selectedPackage]
  );

  const estimatedReferral = activePackage ? (personalSales * activePackage.referralBonus) / 100 : 0;
  const lesserBranch = Math.min(leftVol, rightVol);
  const estimatedBinary = activePackage?.binaryBonus ? (lesserBranch * activePackage.binaryBonus) / 100 : 0;
  const totalEstimated = estimatedReferral + estimatedBinary;

  return (
    <div className="py-20 bg-gray-50">
      <Container>
        <SectionTitle
          title={`${t('marketing.title1', 'Маркетинг')}-${t('marketing.title2', 'план')} Safi Life`}
          subtitle="Бинар + Классика"
        />

        <div className="mb-20">
          <h3 className="text-4xl font-serif text-safi-green mb-8 text-center">
            <span className="italic text-safi-gold">Виды</span> бонусов
          </h3>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[
              { title: `${t('marketing.bonus1_title', 'Реферальный бонус')} (5-10%)`, desc: t('marketing.bonus1_desc', 'Получайте бонус за личные рекомендации при покупке пакета новым партнёром.') },
              { title: `${t('marketing.bonus2_title', 'Бинарный бонус')} (7-10%)`, desc: t('marketing.bonus2_desc', 'Начисляется с меньшей ветки вашей структуры при образовании бинарной пары.') },
              { title: 'Статусный бонус', desc: 'Единоразовые премии и подарки за достижение определенных объемов PV.' },
              { title: 'Bonus X2', desc: 'Если партнеры первой линии достигают статусов, вы удваиваете свой успех.' },
              { title: 'Депозит 10%', desc: 'Часть бинарного бонуса сохраняется на балансе для будущих покупок.' },
              { title: `Классика (${t('marketing.bonus4_title', 'Кэшбэк 20%')})`, desc: t('marketing.bonus4_desc', 'Возврат средств за личные покупки продуктов из каталога.') }
            ].map((item, i) => (
              <div key={item.title} className="bg-white p-8 rounded-[32px] shadow-sm border border-safi-green/5 hover:-translate-y-1 transition-transform duration-300">
                <div className="w-12 h-12 rounded-2xl bg-[#F5F5F0] flex items-center justify-center text-safi-gold font-serif text-2xl font-bold mb-4">{i + 1}</div>
                <h4 className="text-xl font-serif font-bold text-safi-green mb-2">{item.title}</h4>
                <p className="text-safi-text text-sm opacity-70 leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="mb-20 bg-white rounded-[40px] p-8 md:p-12 border border-safi-green/5 shadow-xl max-w-5xl mx-auto overflow-hidden relative">
          <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-safi-green/5 rounded-full blur-3xl translate-x-1/3 -translate-y-1/3 z-0 pointer-events-none"></div>

          <h3 className="text-3xl md:text-4xl font-serif font-bold mb-10 text-center text-safi-green relative z-10">
            <span className="italic text-safi-gold">Калькулятор дохода</span> (Пример)
          </h3>

          <div className="grid md:grid-cols-2 gap-12 relative z-10">
            <div className="space-y-8">
              <div>
                <label className="block text-[10px] tracking-widest font-bold text-safi-green uppercase opacity-80 mb-3">Ваш пакет</label>

                {packagesLoading && (
                  <div className="rounded-xl bg-[#F5F5F0] border border-safi-green/5 px-4 py-3 text-xs font-bold uppercase tracking-widest text-safi-green opacity-70">
                    Загружаем пакеты...
                  </div>
                )}

                {!packagesLoading && packagesError && (
                  <div className="rounded-xl bg-[#F5F5F0] border border-safi-green/5 p-4">
                    <p className="text-sm text-safi-text opacity-70 leading-relaxed mb-4">{packagesError}</p>
                    <Button type="button" variant="outline" onClick={loadPackages}>Повторить</Button>
                  </div>
                )}

                {!packagesLoading && !packagesError && packages.length === 0 && (
                  <div className="rounded-xl bg-[#F5F5F0] border border-safi-green/5 px-4 py-3 text-xs font-bold uppercase tracking-widest text-safi-green opacity-70">
                    Пакеты пока не опубликованы.
                  </div>
                )}

                {!packagesLoading && !packagesError && packages.length > 0 && (
                  <div className="grid grid-cols-2 gap-3">
                    {packages.map((pkg) => (
                      <button
                        key={pkg.id}
                        type="button"
                        onClick={() => setSelectedPackage(pkg.id)}
                        className={`py-3 px-4 rounded-xl text-xs font-bold uppercase tracking-widest transition-all ${
                          selectedPackage === pkg.id
                            ? 'bg-safi-green text-white shadow-md'
                            : 'bg-[#F5F5F0] text-safi-green hover:bg-safi-green/10 border border-safi-green/5'
                        }`}
                      >
                        {pkg.name}
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <RangeField
                label="Личные продажи (Рефералы), ₸"
                value={personalSales}
                max={1000000}
                step={10000}
                onChange={setPersonalSales}
              />
              <RangeField
                label="Объем Левой ветки, ₸"
                value={leftVol}
                max={5000000}
                step={50000}
                onChange={setLeftVol}
              />
              <RangeField
                label="Объем Правой ветки, ₸"
                value={rightVol}
                max={5000000}
                step={50000}
                onChange={setRightVol}
              />
            </div>

            <div className="bg-safi-green rounded-[32px] p-8 flex flex-col justify-center border border-safi-green/10 text-white relative overflow-hidden">
              <div className="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full blur-2xl z-0"></div>
              <div className="space-y-6 mb-8 relative z-10">
                <div className="flex justify-between items-center pb-4 border-b border-white/10 gap-6">
                  <span className="text-white/80 text-sm">Ориентировочный реферальный бонус</span>
                  <span className="font-bold text-safi-gold shrink-0">{estimatedReferral.toLocaleString('ru-RU')} ₸</span>
                </div>
                <div className="flex justify-between items-center pb-4 border-b border-white/10 gap-6">
                  <span className="text-white/80 text-sm">Ориентировочный бинарный бонус</span>
                  <span className="font-bold text-safi-gold shrink-0">{estimatedBinary.toLocaleString('ru-RU')} ₸</span>
                </div>
              </div>
              <div className="relative z-10">
                <div className="text-[10px] text-safi-gold uppercase tracking-widest font-bold mb-2">Примерный общий доход</div>
                <div className="text-4xl md:text-5xl font-serif font-bold text-white">{totalEstimated.toLocaleString('ru-RU')} ₸</div>
              </div>
              <p className="text-[10px] opacity-50 mt-8 text-center relative z-10">
                Расчёт является предварительным примером и не является гарантией начислений.
              </p>
            </div>
          </div>
        </div>

        <div className="mb-20">
          <h3 className="text-4xl font-serif text-safi-green mb-8 text-center">
            <span className="italic text-safi-gold">Статусы</span> и вознаграждения
          </h3>

          {statusesLoading && (
            <LoadingState title="Загружаем статусы" description="Получаем таблицу статусов из публичного API." />
          )}

          {!statusesLoading && statusesError && (
            <ErrorState description={statusesError} onRetry={loadStatuses} />
          )}

          {!statusesLoading && !statusesError && statuses.length === 0 && (
            <EmptyState
              title="Статусы пока не опубликованы"
              description="Таблица статусов появится после настройки данных."
            />
          )}

          {!statusesLoading && !statusesError && statuses.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-left bg-white rounded-[32px] shadow-sm overflow-hidden border border-safi-green/5">
                <thead className="bg-[#F5F5F0] text-[10px] text-safi-green uppercase tracking-widest font-bold">
                  <tr>
                    <th className="p-6 border-b border-safi-green/5">Статус</th>
                    <th className="p-6 border-b border-safi-green/5">Накоплено PV</th>
                    <th className="p-6 border-b border-safi-green/5">Вознаграждение</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-safi-green/5">
                  {statuses.map((status) => (
                    <tr key={status.id} className="hover:bg-[#F5F5F0] transition-colors">
                      <td className="p-6 font-bold text-safi-green">{status.name}</td>
                      <td className="p-6 text-safi-text opacity-70 font-medium">{status.pv.toLocaleString('ru-RU')} PV</td>
                      <td className="p-6 text-safi-gold font-bold">{status.reward}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="text-center max-w-2xl mx-auto text-[10px] text-safi-text opacity-50 uppercase tracking-wider">
          Все бонусы начисляются согласно действующему маркетинг-{t('marketing.title2', 'план')}у компании. Доход не гарантирован и зависит от активности партнёра, продаж, структуры и выполнения условий.
        </div>
      </Container>
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
    <div>
      <label className="block text-[10px] tracking-widest font-bold text-safi-green uppercase opacity-80 mb-3">{label}</label>
      <input
        type="range"
        min="0"
        max={max}
        step={step}
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
        className="w-full accent-safi-gold cursor-pointer"
      />
      <div className="text-right text-sm font-bold text-safi-green mt-2">{value.toLocaleString('ru-RU')} ₸</div>
    </div>
  );
}
