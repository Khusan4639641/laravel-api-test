import { ReactNode, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, ArrowUpCircle, CreditCard, FileText, Package, Search, Settings, TrendingUp, Users } from 'lucide-react';
import { AdminStatCard } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminOverview, getApiErrorState } from '../../lib/api';
import { adminText } from '../../i18n/adminText';

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
  recentTransactions: AdminOverviewTransaction[];
  chart: unknown[];
}

interface AdminOverviewTransaction {
  id: string;
  partnerId: string;
  partnerName: string;
  amount: number;
  direction: string;
  status: string;
  createdAt: string;
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
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_5'));
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
    recentTransactions: [],
    chart: [],
  };
  const hasChartData = currentSummary.chart.length > 0;

  const cards = useMemo(() => [
    { title: adminText('a_0JLRgdC10LPQ'), value: currentSummary.usersTotal.toLocaleString('ru-RU'), icon: Users, trend: `${adminText('active_count')}: ${currentSummary.activeUsers.toLocaleString('ru-RU')}` },
    { title: adminText('a_0J7QsdGJ0LjQ'), value: formatMoney(currentSummary.revenue), icon: TrendingUp },
    { title: adminText('a_0JLRi9C_0LvQ'), value: formatMoney(currentSummary.bonusesPaid), icon: CreditCard },
    { title: adminText('a_0J7QttC40LTQ'), value: formatMoney(currentSummary.pendingWithdrawalsAmount), icon: ArrowUpCircle, trend: `${currentSummary.pendingWithdrawals} ${adminText('applications_count')}`, className: 'border-safi-gold/30 bg-safi-gold/5' },
    { title: adminText('a_0JDQutGC0LjQ'), value: currentSummary.activeUsers.toLocaleString('ru-RU'), icon: Activity },
    { title: adminText('a_0J3QtdCw0LrR'), value: currentSummary.inactiveUsers.toLocaleString('ru-RU'), icon: Users },
    { title: adminText('a_0J_RgNC-0LTQ'), value: currentSummary.packagesSold.toLocaleString('ru-RU'), icon: Package },
    { title: adminText('a_0J7QsdGJ0LjQ_2'), value: `${currentSummary.totalPV.toLocaleString('ru-RU')} PV`, icon: Activity },
  ], [currentSummary]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin dashboard</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">{adminText('a_0KHQstC-0LTQ')}</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">{adminText('a_0J7QsdGJ0LDR')}</p>
          </div>
        </div>
      </section>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadSummary} />}
      {!isLoading && !error && !summary && <EmptyState title={adminText('a_0KHQstC-0LTQ_2')} description={adminText('a_0JTQsNC90L3R')} />}

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
            <h2 className="font-serif text-3xl font-semibold text-safi-green">{adminText('a_0JPRgNCw0YTQ')}</h2>
            <p className="mt-3 max-w-md text-sm leading-7 text-safi-muted">
              {hasChartData ? adminText('a_0JfQtNC10YHR') : adminText('overview_chart_empty')}
            </p>
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-green bg-safi-green p-8 text-white shadow-[0_18px_48px_rgba(11,23,18,0.08)]">
          <h2 className="font-serif text-3xl font-semibold text-white">{adminText('a_0JHRi9GB0YLR')}</h2>
          <div className="mt-7 space-y-3">
            <QuickLink to="/admin/partners" icon={<Search className="h-4 w-4" />} label={adminText('a_0J3QsNC50YLQ')} />
            <QuickLink to="/admin/withdrawals" icon={<ArrowUpCircle className="h-4 w-4" />} label={`${adminText('a_0JfQsNGP0LLQ')}: ${currentSummary.pendingWithdrawals}`} />
            <QuickLink to="/admin/transactions" icon={<CreditCard className="h-4 w-4" />} label={adminText('a_0KLRgNCw0L3Q')} />
            <QuickLink to="/admin/reports" icon={<FileText className="h-4 w-4" />} label={adminText('a_0J7RgtGH0LXR')} />
            <QuickLink to="/admin/settings" icon={<Settings className="h-4 w-4" />} label={adminText('a_0J3QsNGB0YLR')} />
          </div>
        </article>
      </section>

      <section className="rounded-[32px] border border-safi-border bg-white p-8 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="mb-6 flex items-center justify-between gap-4">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">{adminText('a_0J_QvtGB0LvQ')}</h2>
          <Link to="/admin/transactions" className="text-xs font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:text-safi-gold">
            {adminText('a_0KHQvNC-0YLR')}
          </Link>
        </div>

        {currentSummary.recentTransactions.length === 0 ? (
          <div className="flex min-h-44 flex-col items-center justify-center rounded-[28px] border border-dashed border-safi-border bg-safi-cream p-8 text-center">
            <CreditCard className="mb-4 h-10 w-10 text-safi-gold" />
            <h3 className="font-serif text-2xl font-semibold text-safi-green">{adminText('a_0KLRgNCw0L3Q_2')}</h3>
            <p className="mt-3 max-w-md text-sm leading-7 text-safi-muted">{adminText('a_0J7Qv9C10YDQ_2')}</p>
          </div>
        ) : (
          <div className="divide-y divide-safi-border/70">
            {currentSummary.recentTransactions.map((transaction) => (
              <div key={transaction.id} className="grid gap-3 py-4 text-sm md:grid-cols-[1fr_1fr_auto] md:items-center">
                <div>
                  <div className="safi-numeric font-bold text-safi-text">#{transaction.id}</div>
                  <div className="mt-1 text-xs text-safi-muted">{transaction.createdAt || '-'}</div>
                </div>
                <div>
                  <div className="font-bold text-safi-green">{transaction.partnerName}</div>
                  <div className="safi-numeric mt-1 text-xs text-safi-muted">ID {transaction.partnerId}</div>
                </div>
                <div className={`safi-numeric font-bold ${transaction.direction === 'debit' ? 'text-red-500' : 'text-green-600'}`}>
                  {formatSignedMoney(transaction.amount, transaction.direction)}
                </div>
              </div>
            ))}
          </div>
        )}
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
    recentTransactions: getArrayValue(root, 'recent_transactions').map(normalizeOverviewTransaction),
    chart: getArrayValue(root, 'chart'),
  };
}

function normalizeOverviewTransaction(item: unknown, index: number): AdminOverviewTransaction {
  const transaction = isRecord(item) ? item : {};
  const user = isRecord(transaction.user) ? transaction.user : {};

  return {
    id: getStringValue(transaction.id) || String(index + 1),
    partnerId: getStringValue(user.id) || getStringValue(transaction.user_id) || '-',
    partnerName: getStringValue(user.name) || '-',
    amount: getNumericValue(transaction.amount),
    direction: getStringValue(transaction.direction) || 'credit',
    status: getStringValue(transaction.status) || '-',
    createdAt: getStringValue(transaction.created_at) || '-',
  };
}

function getRecord(record: Record<string, unknown>, key: string) {
  return isRecord(record[key]) ? record[key] as Record<string, unknown> : {};
}

function getArrayValue(record: Record<string, unknown>, key: string) {
  return Array.isArray(record[key]) ? record[key] as unknown[] : [];
}

function getStringValue(value: unknown) {
  if (typeof value === 'string' && value.trim() !== '') {
    return value;
  }

  if (typeof value === 'number') {
    return String(value);
  }

  return undefined;
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

function formatSignedMoney(value: number, direction: string) {
  const sign = direction === 'debit' ? '-' : '+';

  return `${sign}${value.toLocaleString('ru-RU')} ₸`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
