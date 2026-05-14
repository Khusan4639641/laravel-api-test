import { ReactNode, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, ArrowUpCircle, CreditCard, FileText, Package, Search, Settings, TrendingUp, Users } from 'lucide-react';
import { AdminStatCard } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminOverview, getApiErrorState } from '../../lib/api';

interface AdminOverviewSummary {
  usersTotal: number;
  activeUsers: number;
  inactiveUsers: number;
  revenue: number;
  bonusesPaid: number;
  pendingWithdrawals: number;
  pendingWithdrawalsAmount: number;
  packagesSold: number;
  totalPV: number;
}

export default function AdminOverview() {
  const [summary, setSummary] = useState<AdminOverviewSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadSummary = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminOverview();
      setSummary(normalizeOverview(response));
    } catch (caughtError) {
      setSummary(null);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить сводку.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadSummary();
  }, []);

  const currentSummary = summary || {
    usersTotal: 0,
    activeUsers: 0,
    inactiveUsers: 0,
    revenue: 0,
    bonusesPaid: 0,
    pendingWithdrawals: 0,
    pendingWithdrawalsAmount: 0,
    packagesSold: 0,
    totalPV: 0,
  };

  const cards = useMemo(() => [
    { title: 'Всего партнеров', value: currentSummary.usersTotal.toLocaleString('ru-RU'), icon: Users, trend: `Активные: ${currentSummary.activeUsers.toLocaleString('ru-RU')}` },
    { title: 'Общий оборот', value: formatMoney(currentSummary.revenue), icon: TrendingUp },
    { title: 'Выплачено бонусов', value: formatMoney(currentSummary.bonusesPaid), icon: CreditCard },
    { title: 'Ожидает вывода', value: formatMoney(currentSummary.pendingWithdrawalsAmount), icon: ArrowUpCircle, trend: `${currentSummary.pendingWithdrawals} заявок`, className: 'border-safi-gold/30 bg-safi-gold/5' },
    { title: 'Активные партнеры', value: currentSummary.activeUsers.toLocaleString('ru-RU'), icon: Activity },
    { title: 'Неактивные партнеры', value: currentSummary.inactiveUsers.toLocaleString('ru-RU'), icon: Users },
    { title: 'Продано пакетов', value: currentSummary.packagesSold.toLocaleString('ru-RU'), icon: Package },
    { title: 'Общий PV', value: `${currentSummary.totalPV.toLocaleString('ru-RU')} PV`, icon: Activity },
  ], [currentSummary]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin dashboard</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Сводка</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Общая статистика платформы Safi Life по партнерам, обороту и заявкам.
            </p>
          </div>
        </div>
      </section>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadSummary} />}
      {!isLoading && !error && !summary && <EmptyState title="Сводка пока пустая" description="Данные появятся после первых операций в системе." />}

      {!isLoading && !error && summary && (
        <>
      <section className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <AdminStatCard
            key={card.title}
            title={card.title}
            value={card.value}
            icon={card.icon}
            trend={card.trend}
            className={card.className}
          />
        ))}
      </section>

      <section className="grid gap-8 lg:grid-cols-[1.25fr_0.75fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-8 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="flex min-h-72 flex-col items-center justify-center rounded-[28px] border border-dashed border-safi-border bg-safi-cream p-8 text-center">
            <TrendingUp className="mb-4 h-12 w-12 text-safi-gold" />
            <h2 className="font-serif text-3xl font-semibold text-safi-green">График роста структуры</h2>
            <p className="mt-3 max-w-md text-sm leading-7 text-safi-muted">
              Здесь будет отображаться динамика партнеров, оборота и PV после подключения аналитического endpoint.
            </p>
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-green bg-safi-green p-8 text-white shadow-[0_18px_48px_rgba(11,23,18,0.08)]">
          <h2 className="font-serif text-3xl font-semibold text-white">Быстрые действия</h2>
          <div className="mt-7 space-y-3">
            <QuickLink to="/admin/partners" icon={<Search className="h-4 w-4" />} label="Найти партнера" />
            <QuickLink to="/admin/withdrawals" icon={<ArrowUpCircle className="h-4 w-4" />} label={`Заявки на вывод: ${currentSummary.pendingWithdrawals}`} />
            <QuickLink to="/admin/transactions" icon={<CreditCard className="h-4 w-4" />} label="Транзакции" />
            <QuickLink to="/admin/reports" icon={<FileText className="h-4 w-4" />} label="Отчеты" />
            <QuickLink to="/admin/settings" icon={<Settings className="h-4 w-4" />} label="Настройки системы" />
          </div>
        </article>
      </section>
        </>
      )}
    </div>
  );
}

function QuickLink({ to, icon, label }: { to: string; icon: ReactNode; label: string }) {
  return (
    <Link to={to} className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.08] p-4 text-sm font-bold text-white/90 transition-colors hover:bg-white/15">
      <span className="text-safi-gold">{icon}</span>
      {label}
    </Link>
  );
}

function normalizeOverview(response: unknown): AdminOverviewSummary {
  const root = isRecord(response) ? response : {};
  const users = getRecord(root, 'users');
  const orders = getRecord(root, 'orders');
  const bonuses = getRecord(root, 'bonuses');
  const withdrawals = getRecord(root, 'withdrawals');

  return {
    usersTotal: getNumericValue(users.total),
    activeUsers: getNumericValue(users.active),
    inactiveUsers: getNumericValue(users.inactive),
    revenue: getNumericValue(orders.revenue),
    bonusesPaid: getNumericValue(bonuses.paid || bonuses.total),
    pendingWithdrawals: getNumericValue(withdrawals.pending),
    pendingWithdrawalsAmount: getNumericValue(withdrawals.pending_amount),
    packagesSold: getNumericValue(orders.packages_sold || orders.total),
    totalPV: getNumericValue(orders.total_pv),
  };
}

function getRecord(record: Record<string, unknown>, key: string) {
  return isRecord(record[key]) ? record[key] as Record<string, unknown> : {};
}

function getNumericValue(value: unknown) {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  return 0;
}

function formatMoney(value: number) {
  return `${value.toLocaleString('ru-RU')} ₸`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
