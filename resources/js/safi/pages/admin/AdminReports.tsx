import { useEffect, useMemo, useState } from 'react';
import { Download, FileText, Package, RefreshCw, TrendingUp, Users, Wallet } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminReportsSummary, getApiErrorState } from '../../lib/api';
import { cn } from '../../lib/utils';
import { adminText } from '../../i18n/adminText';

interface ReportSummary {
  totalUsers: number;
  totalTurnover: number;
  totalBonusPaid: number;
  pendingWithdrawals: number;
  totalPv: number;
  packagesSold: number;
}

interface ChartPoint {
  period: string;
  turnover: number;
  bonuses: number;
  withdrawals: number;
  users: number;
  packageSales: number;
  pv: number;
}

interface ReportState {
  summary: ReportSummary;
  chart: ChartPoint[];
}

const emptyReportState: ReportState = {
  summary: {
    totalUsers: 0,
    totalTurnover: 0,
    totalBonusPaid: 0,
    pendingWithdrawals: 0,
    totalPv: 0,
    packagesSold: 0,
  },
  chart: [],
};

export default function AdminReports() {
  const [reports, setReports] = useState<ReportState>(emptyReportState);
  const [periodFilter, setPeriodFilter] = useState('6');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exportStatus, setExportStatus] = useState('');

  const loadReports = async () => {
    setIsLoading(true);
    setError(null);
    setExportStatus('');

    try {
      setReports(normalizeReports(await getAdminReportsSummary()));
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_22'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadReports();
  }, []);

  const visibleChart = useMemo(() => {
    if (periodFilter === 'all') {
      return reports.chart;
    }

    return reports.chart.slice(-Number(periodFilter));
  }, [reports.chart, periodFilter]);

  const exportCsv = () => {
    if (visibleChart.length === 0) {
      return;
    }

    const rows = [
      ['period', 'turnover', 'bonuses', 'withdrawals', 'users', 'package_sales', 'pv'],
      ...visibleChart.map((item) => [
        item.period,
        item.turnover,
        item.bonuses,
        item.withdrawals,
        item.users,
        item.packageSales,
        item.pv,
      ]),
    ];
    const csv = rows.map((row) => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `safi-reports-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
    setExportStatus(adminText('a_Q1NWINGN0LrR'));
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="mb-1 font-serif text-3xl font-bold text-safi-green">{adminText('a_0J7RgtGH0ZHR')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0KTQuNC90LDQ_2')}</p>
        </div>

        <div className="flex flex-col gap-3 sm:flex-row">
          <select
            value={periodFilter}
            onChange={(event) => setPeriodFilter(event.target.value)}
            className="cursor-pointer rounded-xl border border-safi-green/10 bg-white px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green outline-none transition-colors hover:border-safi-green/30 focus:border-safi-green"
          >
            <option value="3">{adminText('a_MyDQvNC10YHR')}</option>
            <option value="6">{adminText('a_NiDQvNC10YHR')}</option>
            <option value="all">{adminText('a_0JLRgdC1INC_')}</option>
          </select>
          <button
            type="button"
            onClick={loadReports}
            disabled={isLoading}
            className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-border bg-white px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />{adminText('a_0J7QsdC90L7Q')}</button>
          <button
            type="button"
            onClick={exportCsv}
            disabled={isLoading || visibleChart.length === 0}
            title={visibleChart.length === 0 ? adminText('a_0J3QtdGCINC0') : adminText('a_0KHQutCw0YfQ')}
            className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-safi-green px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Download className="h-4 w-4" />{adminText('a_0K3QutGB0L_Q')}</button>
        </div>
      </div>

      {exportStatus && <div className="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">{exportStatus}</div>}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadReports} />}

      {!isLoading && !error && (
        <>
          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <ReportCard title={adminText('a_0J7QsdC-0YDQ')} value={`${formatMoney(reports.summary.totalTurnover)} ₸`} icon={TrendingUp} />
            <ReportCard title={adminText('a_0JHQvtC90YPR_3')} value={`${formatMoney(reports.summary.totalBonusPaid)} ₸`} icon={Wallet} />
            <ReportCard title={adminText('a_0JfQsNGP0LLQ')} value={`${formatMoney(reports.summary.pendingWithdrawals)} ₸`} icon={Download} />
            <ReportCard title={adminText('a_0J_QsNGA0YLQ_8')} value={formatNumber(reports.summary.totalUsers)} icon={Users} />
            <ReportCard title={adminText('a_UFYgLyDQn9Cw')} value={`${formatNumber(reports.summary.totalPv)} PV`} subValue={`${formatNumber(reports.summary.packagesSold)} ${adminText('sales_count')}`} icon={Package} />
          </section>

          <section className="rounded-[32px] border border-safi-green/5 bg-white p-6 shadow-sm md:p-8">
            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="font-serif text-2xl font-bold text-safi-green">{adminText('a_0JTQuNC90LDQ')}</h2>
                <p className="mt-1 text-sm text-safi-text/60">{adminText('a_0J7QsdC-0YDQ_2')}</p>
              </div>
              <div className="flex items-center gap-2 rounded-full bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-text/50">
                <FileText className="h-4 w-4" />
                /api/admin/reports/summary
              </div>
            </div>

            {visibleChart.length === 0 ? (
              <EmptyState title={adminText('a_0JTQsNC90L3R_3')} description={adminText('a_0JTQuNCw0LPR')} className="min-h-[240px] shadow-none" />
            ) : (
              <ReportChart data={visibleChart} />
            )}
          </section>
        </>
      )}
    </div>
  );
}

function ReportCard({ title, value, subValue, icon: Icon }: { title: string; value: string; subValue?: string; icon: any }) {
  return (
    <article className="rounded-3xl border border-safi-green/5 bg-white p-5 shadow-sm">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{title}</div>
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F5F5F0] text-safi-gold">
          <Icon className="h-5 w-5" />
        </div>
      </div>
      <div className="font-serif text-2xl font-bold text-safi-green">{value}</div>
      {subValue && <div className="mt-2 text-xs font-bold text-safi-text/50">{subValue}</div>}
    </article>
  );
}

function ReportChart({ data }: { data: ChartPoint[] }) {
  const maxMoney = Math.max(...data.map((item) => Math.max(item.turnover, item.bonuses, item.withdrawals)), 1);
  const maxPv = Math.max(...data.map((item) => item.pv), 1);

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap gap-3 text-[10px] font-bold uppercase tracking-widest text-safi-text/50">
        <Legend color="bg-safi-green" label={adminText('a_0J7QsdC-0YDQ')} />
        <Legend color="bg-safi-gold" label={adminText('a_0JHQvtC90YPR')} />
        <Legend color="bg-red-400" label={adminText('a_0JLRi9Cy0L7Q')} />
        <Legend color="bg-blue-400" label="PV" />
      </div>
      <div className="overflow-x-auto">
        <div className="min-w-[860px] space-y-5">
          {data.map((item) => (
            <div key={item.period} className="grid grid-cols-[90px_1fr_150px] items-center gap-4 rounded-2xl border border-safi-green/5 bg-[#F5F5F0]/60 p-4">
              <div className="font-mono text-xs font-bold text-safi-green">{item.period}</div>
              <div className="space-y-2">
                <Bar color="bg-safi-green" value={item.turnover} max={maxMoney} label={`${formatMoney(item.turnover)} ₸`} />
                <Bar color="bg-safi-gold" value={item.bonuses} max={maxMoney} label={`${formatMoney(item.bonuses)} ₸`} />
                <Bar color="bg-red-400" value={item.withdrawals} max={maxMoney} label={`${formatMoney(item.withdrawals)} ₸`} />
                <Bar color="bg-blue-400" value={item.pv} max={maxPv} label={`${formatNumber(item.pv)} PV`} />
              </div>
              <div className="space-y-1 text-right text-[10px] font-bold uppercase tracking-widest text-safi-text/50">
                <div>{adminText('a_0J_QsNGA0YLQ_9')}<span className="text-safi-green">{item.users}</span></div>
                <div>{adminText('a_0J_QsNC60LXR_6')}<span className="text-safi-green">{item.packageSales}</span></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Legend({ color, label }: { color: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-2">
      <span className={cn('h-2.5 w-2.5 rounded-full', color)} />
      {label}
    </span>
  );
}

function Bar({ color, value, max, label }: { color: string; value: number; max: number; label: string }) {
  return (
    <div className="grid grid-cols-[1fr_110px] items-center gap-3">
      <div className="h-3 overflow-hidden rounded-full bg-white">
        <div className={cn('h-full min-w-[3px] rounded-full', color)} style={{ width: `${Math.max((value / max) * 100, value > 0 ? 3 : 0)}%` }} />
      </div>
      <div className="text-right text-[10px] font-bold text-safi-green">{label}</div>
    </div>
  );
}

function normalizeReports(response: unknown): ReportState {
  const record = isRecord(response) ? response : {};
  const summary = getRecord(record, 'summary');
  const partners = getRecord(record, 'partners');
  const finance = getRecord(record, 'finance');
  const packages = getRecord(record, 'packages');

  return {
    summary: {
      totalUsers: getNumber(summary.total_users) || getNumber(partners.total),
      totalTurnover: getNumber(summary.total_turnover) || getNumber(finance.revenue),
      totalBonusPaid: getNumber(summary.total_bonus_paid) || getNumber(finance.bonuses_paid),
      pendingWithdrawals: getNumber(summary.pending_withdrawals) || getNumber(finance.pending_withdrawals),
      totalPv: getNumber(summary.total_pv) || getNumber(packages.pv),
      packagesSold: getNumber(packages.sold),
    },
    chart: getArray(record, 'chart').map((item) => {
      const row = isRecord(item) ? item : {};

      return {
        period: getString(row.period) || '-',
        turnover: getNumber(row.turnover),
        bonuses: getNumber(row.bonuses),
        withdrawals: getNumber(row.withdrawals),
        users: getNumber(row.users),
        packageSales: getNumber(row.package_sales ?? row.packageSales),
        pv: getNumber(row.pv),
      };
    }),
  };
}

function formatMoney(value: number) {
  return Math.round(value).toLocaleString('ru-RU');
}

function formatNumber(value: number) {
  return Math.round(value).toLocaleString('ru-RU');
}

function getRecord(record: Record<string, unknown>, key: string) {
  return isRecord(record[key]) ? record[key] as Record<string, unknown> : {};
}

function getArray(record: Record<string, unknown>, key: string) {
  return Array.isArray(record[key]) ? record[key] as unknown[] : [];
}

function getString(value: unknown) {
  if (typeof value === 'string' && value.trim() !== '') {
    return value;
  }

  return undefined;
}

function getNumber(value: unknown) {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  return 0;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
