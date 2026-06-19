import { useCallback, useEffect, useState } from 'react';
import { CheckCircle2, Lock, Trophy } from 'lucide-react';
import { Badge, ProgressBar } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getDashboardOverview, getDashboardPackages, getNumber, getPublicStatuses, Package, Status } from '../../lib/api';
import { cn } from '../../lib/utils';

export default function PackageStatus() {
  const { currentUser } = useDashboardContext();
  const [packages, setPackages] = useState<Package[]>([]);
  const [statuses, setStatuses] = useState<Status[]>([]);
  const [statusProgress, setStatusProgress] = useState({ leftPV: 0, rightPV: 0, weakLegPV: 0 });
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const currentPackageCode = normalizePackageCode(currentUser.packageCode || currentUser.packageName);
  const displayPackages = packages;
  const weakLegPV = statusProgress.weakLegPV || Math.min(statusProgress.leftPV, statusProgress.rightPV);
  const nextStatus = statuses.find((status) => status.pv > weakLegPV);
  const statusTargetPV = nextStatus?.pv || statuses[statuses.length - 1]?.pv || Math.max(weakLegPV, 1);
  const statusProgressPercent = statusTargetPV > 0 ? Math.min(100, Math.max(0, (weakLegPV / statusTargetPV) * 100)) : 0;

  const loadPackageData = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const [packageItems, statusItems, overviewResponse] = await Promise.all([
        getDashboardPackages(),
        getPublicStatuses(),
        getDashboardOverview(),
      ]);
      const overview = overviewResponse && typeof overviewResponse === 'object' ? overviewResponse as Record<string, unknown> : {};
      const structure = overview.structure && typeof overview.structure === 'object' ? overview.structure as Record<string, unknown> : {};
      const leftPV = getNumber(structure, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
      const rightPV = getNumber(structure, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;

      setPackages(packageItems);
      setStatuses(statusItems);
      setStatusProgress({
        leftPV,
        rightPV,
        weakLegPV: getNumber(structure, ['weak_leg_pv', 'weakLegPv', 'weak_leg_branch_pv', 'weakLegBranchPv'])
          ?? Math.min(leftPV, rightPV),
      });
    } catch (caughtError) {
      setPackages([]);
      setStatuses([]);
      setStatusProgress({ leftPV: 0, rightPV: 0, weakLegPV: 0 });
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadPackageData();
  }, [loadPackageData]);

  return (
    <div className="space-y-10">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Package status</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Пакет и статус</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Управление стартовым пакетом, апгрейдом и прогрессом по PV.
            </p>
            <p className="mt-3 max-w-2xl rounded-2xl border border-safi-gold/30 bg-safi-cream px-4 py-3 text-sm font-bold leading-6 text-safi-green">
              Пакет назначается администратором. Смена пакета доступна только через администратора.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">Пакет: {currentUser.packageName}</Badge>
            <Badge variant={currentUser.packageStatus === 'active' ? 'success' : 'default'}>Пакет: {currentUser.packageStatusLabel}</Badge>
            <Badge variant="default">Статус: {currentUser.status}</Badge>
          </div>
        </div>
      </section>

      {isLoading && (
        <LoadingState title="Загружаем пакеты" description="Получаем пакеты и статусы из API." />
      )}

      {!isLoading && loadError && (
        <ErrorState description={loadError} onRetry={loadPackageData} />
      )}

      {!isLoading && !loadError && (
        <>
      <section className="grid gap-5 md:grid-cols-3">
        {displayPackages.length === 0 && (
          <EmptyState
            title="Пакеты пока не опубликованы"
            description="Список пакетов появится после настройки в backend."
            className="md:col-span-3"
          />
        )}

        {displayPackages.map((pkg) => {
          const action = packageAction(pkg, currentPackageCode);
          const isCurrent = action.current;

          return (
            <article
              key={pkg.id}
              className={cn(
                'relative flex flex-col rounded-[32px] border p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)]',
                isCurrent ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white text-safi-green'
              )}
            >
              {isCurrent && <Trophy className="absolute right-6 top-6 h-6 w-6 text-safi-gold" />}
              <h2 className={`font-serif text-3xl font-semibold ${isCurrent ? 'text-white' : 'text-safi-green'}`}>{pkg.label || pkg.name}</h2>
              <div className={`mt-3 text-4xl font-extrabold ${isCurrent ? 'text-safi-gold' : 'text-safi-green'}`}>
                {pkg.price.toLocaleString('ru-RU')} ₸
              </div>
              <div className="mt-6 grid grid-cols-2 gap-3">
                <PackageMetric label="Реф." value={`${pkg.referralBonus}%`} dark={isCurrent} />
                <PackageMetric label="Бинар" value={pkg.binaryBonus ? `${pkg.binaryBonus}%` : '-'} dark={isCurrent} />
              </div>
              <ul className="mt-7 flex-1 space-y-3">
                {pkg.features.slice(0, 4).map((feature) => (
                  <li key={feature} className={`flex gap-3 text-sm leading-6 ${isCurrent ? 'text-white/75' : 'text-safi-muted'}`}>
                    <CheckCircle2 className="mt-1 h-4 w-4 shrink-0 text-safi-gold" />
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>
              <button
                type="button"
                disabled
                className={cn(
                  'mt-8 inline-flex items-center justify-center rounded-full px-4 py-3 text-center text-[10px] font-extrabold uppercase tracking-[0.16em] disabled:opacity-100',
                  isCurrent
                    ? 'cursor-default border border-white/15 bg-white/10 text-white'
                    : 'cursor-not-allowed border border-safi-border bg-safi-cream text-safi-muted'
                )}
              >
                {action.label}
              </button>
            </article>
          );
        })}
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
          <span className="safi-kicker">PV progress</span>
          <h2 className="mt-3 font-serif text-3xl font-semibold text-safi-green">Следующий статус: {nextStatus?.name || currentUser.status}</h2>
          <p className="mt-3 text-sm leading-7 text-safi-muted">
            Малая ветка PV: {weakLegPV.toLocaleString('ru-RU')} PV. Личный PV: {currentUser.personalPV.toLocaleString('ru-RU')} PV.
          </p>
          <div className="mt-8">
            <ProgressBar
              label={`${currentUser.status} -> ${nextStatus?.name || currentUser.status}`}
              current={weakLegPV}
              total={statusTargetPV}
              percentageOverride={statusProgressPercent}
            />
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
          <span className="safi-kicker">Statuses</span>
          {statuses.length === 0 && (
            <EmptyState
              title="Статусы пока не опубликованы"
              description="Статусная сетка появится после настройки данных."
              className="mt-6 min-h-[180px] shadow-none"
            />
          )}
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            {statuses.slice(0, 6).map((status) => {
              const achieved = weakLegPV >= status.pv;
              const current = currentUser.status.toLowerCase() === status.name.toLowerCase();

              return (
                <div key={status.id} className={`rounded-3xl border p-5 ${achieved ? 'border-safi-gold/40 bg-safi-cream' : 'border-safi-border bg-white'}`}>
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span className={`flex h-10 w-10 items-center justify-center rounded-full ${achieved ? 'bg-safi-gold text-safi-black' : 'bg-safi-cream text-safi-muted'}`}>
                      {achieved ? <CheckCircle2 className="h-5 w-5" /> : <Lock className="h-4 w-4" />}
                    </span>
                    {current && <Badge variant="gold">Текущий</Badge>}
                  </div>
                  <h3 className="font-serif text-xl font-semibold text-safi-green">{status.name}</h3>
                  <div className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{status.pv.toLocaleString('ru-RU')} PV</div>
                  <div className="mt-4 text-sm font-bold text-safi-gold">{status.reward}</div>
                </div>
              );
            })}
          </div>
        </article>
      </section>
        </>
      )}
    </div>
  );
}

function PackageMetric({ label, value, dark }: { label: string; value: string; dark?: boolean }) {
  return (
    <div className={`rounded-2xl border px-4 py-3 ${dark ? 'border-white/10 bg-white/[0.08]' : 'border-safi-border bg-safi-cream'}`}>
      <div className={`text-[9px] font-extrabold uppercase tracking-[0.16em] ${dark ? 'text-white/60' : 'text-safi-muted'}`}>{label}</div>
      <div className={`mt-1 text-lg font-extrabold ${dark ? 'text-safi-gold' : 'text-safi-green'}`}>{value}</div>
    </div>
  );
}

function normalizePackageCode(value?: string | null) {
  const code = String(value || '').trim().toUpperCase();

  if (['-', '—', 'NO PACKAGE', 'NONE', 'NULL', 'НЕТ ПАКЕТА', 'БЕЗ ПАКЕТА'].includes(code)) {
    return '';
  }

  if (code === 'СТАРТ') {
    return 'START';
  }

  if (code === 'ЭЛИТ' || code === 'ЭЛИТНЫЙ') {
    return 'ELITE';
  }

  return code;
}

const PACKAGE_ORDER: Record<string, number> = {
  START: 1,
  VIP: 2,
  ELITE: 3,
};

function packageAction(pkg: Package, currentPackageCode: string) {
  const code = normalizePackageCode(pkg.code || pkg.name);

  if (Boolean(pkg.current) || (code !== '' && code === currentPackageCode)) {
    return { current: true, label: 'Ваш текущий пакет' };
  }

  const targetRank = PACKAGE_ORDER[code];
  const currentRank = PACKAGE_ORDER[currentPackageCode];

  if (targetRank && currentRank && targetRank < currentRank) {
    return { current: false, label: 'Уже приобрели' };
  }

  return { current: false, label: 'Вы еще не приобрели' };
}
