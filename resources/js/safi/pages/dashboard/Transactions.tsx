import { useCallback, useEffect, useMemo, useState } from 'react';
import { ArrowDownToLine, ArrowUpFromLine, Calendar, CreditCard, Filter, RefreshCcw } from 'lucide-react';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { cn } from '../../lib/utils';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getDashboardTransactions, getNumber, getString } from '../../lib/api';
import { getPaginatedItems, normalizePaginationMeta, type PaginationMeta } from '../../lib/pagination';
import { transactionStatusLabel, transactionTypeLabel } from '../../lib/systemLabels';

type TransactionFilter = 'all' | 'credits' | 'withdrawals' | 'cashback';
type PaginationItem = number | 'ellipsis';

interface TransactionRow {
  id: string;
  date: string;
  typeCode: string;
  type: string;
  amount: string;
  affectsBalance: boolean;
  affectsBalanceLabel: string;
  statusCode: string;
  status: string;
  source: string;
  comment: string;
  paymentStrategyLabel?: string;
}

const filters: Array<{ label: string; value: TransactionFilter }> = [
  { label: 'Все', value: 'all' },
  { label: 'Начисления', value: 'credits' },
  { label: 'Выводы', value: 'withdrawals' },
  { label: 'Кэшбэк', value: 'cashback' },
];
const defaultTransactionsMeta: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
};
const perPageOptions = [10, 20, 50];

export default function Transactions() {
  const [filter, setFilter] = useState<TransactionFilter>('all');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [transactions, setTransactions] = useState<TransactionRow[]>([]);
  const [transactionsMeta, setTransactionsMeta] = useState<PaginationMeta>(defaultTransactionsMeta);
  const [dashboardSummary, setDashboardSummary] = useState({
    totalEarned: 0,
    available: 0,
    pending: 0,
    withdrawn: 0,
  });
  const balance = useMemo(() => dashboardSummary, [dashboardSummary]);

  const loadTransactions = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getDashboardTransactions({
        page,
        per_page: perPage,
        ...(filter !== 'all' ? { filter } : {}),
      });
      const summaryRecord = response && typeof response === 'object' && 'summary' in response
        ? (response as Record<string, unknown>).summary
        : {};
      const summary = summaryRecord && typeof summaryRecord === 'object' ? summaryRecord as Record<string, unknown> : {};
      setDashboardSummary({
        totalEarned: getNumber(summary, ['total_earned', 'totalEarned']) ?? 0,
        available: getNumber(summary, ['available']) ?? 0,
        pending: getNumber(summary, ['pending']) ?? 0,
        withdrawn: getNumber(summary, ['withdrawn']) ?? 0,
      });
      const rows = getPaginatedItems(response, 'transactions');
      setTransactions(rows.map((item, index) => {
        const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const direction = getString(record, ['direction']) || 'credit';
        const amount = getNumber(record, ['amount']) ?? 0;
        const rawType = getString(record, ['type']) || 'operation';
        const statusCode = getString(record, ['status']) || 'completed';
        return {
          id: getString(record, ['id']) || String(index + 1),
          date: getString(record, ['created_at', 'createdAt']) || '-',
          typeCode: rawType,
          type: transactionTypeLabel(rawType, getString(record, ['type_label', 'typeLabel']) || rawType),
          amount: formatTransactionAmount(direction, amount),
          affectsBalance: record.affects_balance !== false && record.affectsBalance !== false,
          affectsBalanceLabel: getString(record, ['affects_balance_label', 'affectsBalanceLabel']) || 'Не влияет на баланс',
          statusCode,
          status: transactionStatusLabel(statusCode, getString(record, ['status_label', 'statusLabel']) || statusCode),
          source: getString(record, ['description']) || 'Система',
          comment: getString(record, ['description']) || '',
          paymentStrategyLabel: getString(record, ['payment_strategy_label', 'paymentStrategyLabel']),
        };
      }));
      setTransactionsMeta(normalizePaginationMeta(response, 'transactions', page, perPage, rows.length));
    } catch (caughtError) {
      setTransactions([]);
      setTransactionsMeta({ ...defaultTransactionsMeta, per_page: perPage });
      setDashboardSummary({ totalEarned: 0, available: 0, pending: 0, withdrawn: 0 });
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, [filter, page, perPage]);

  useEffect(() => {
    void loadTransactions();
  }, [loadTransactions]);

  const visibleTransactions = transactions;
  const paginationItems = getPaginationItems(transactionsMeta.current_page, transactionsMeta.last_page);
  const canGoPrev = transactionsMeta.current_page > 1 && !isLoading;
  const canGoNext = transactionsMeta.current_page < transactionsMeta.last_page && !isLoading;
  const changePage = (nextPage: number) => {
    if (nextPage < 1 || nextPage > transactionsMeta.last_page || nextPage === transactionsMeta.current_page || isLoading) {
      return;
    }

    setPage(nextPage);
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Transactions</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">История транзакций</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Начисления, выводы, кэшбэк и служебные операции кошелька.
            </p>
          </div>
          <button type="button" className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white">
            <ArrowDownToLine className="h-4 w-4" />
            Экспорт
          </button>
        </div>
      </section>

      {isLoading && (
        <LoadingState title="Загружаем транзакции" description="Получаем операции кошелька из API." />
      )}

      {!isLoading && error && (
        <ErrorState description={error} onRetry={loadTransactions} />
      )}

      {!isLoading && !error && (
        <>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard title="Общий заработок" value={`${balance.totalEarned.toLocaleString('ru-RU')} ₸`} icon={<CreditCard className="h-5 w-5" />} variant="dark" />
        <StatCard title="Доступно" value={`${balance.available.toLocaleString('ru-RU')} ₸`} />
        <StatCard title="Ожидает" value={`${balance.pending.toLocaleString('ru-RU')} ₸`} icon={<RefreshCcw className="h-5 w-5" />} />
        <StatCard title="Выведено" value={`${balance.withdrawn.toLocaleString('ru-RU')} ₸`} icon={<ArrowUpFromLine className="h-5 w-5" />} />
      </section>

      <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="border-b border-safi-border p-6 md:p-7">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex gap-2 overflow-x-auto">
              {filters.map((item) => (
                <button
                  key={item.value}
                  type="button"
                  onClick={() => {
                    setFilter(item.value);
                    setPage(1);
                  }}
                  className={cn(
                    'shrink-0 rounded-full border px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-colors',
                    filter === item.value ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-safi-cream text-safi-muted hover:text-safi-green'
                  )}
                >
                  {item.label}
                </button>
              ))}
            </div>
            <div className="flex gap-3">
              <div className="flex items-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-sm font-bold text-safi-green">
                <Calendar className="h-4 w-4 text-safi-gold" />
                Май 2026
              </div>
              <button type="button" className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-safi-cream text-safi-green">
                <Filter className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <div className="hidden overflow-x-auto md:block">
          <table className="w-full min-w-[840px] text-left">
            <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
              <tr>
                <th className="px-7 py-4">Дата / ID</th>
                <th className="px-7 py-4">Операция</th>
                <th className="px-7 py-4">Детали</th>
                <th className="px-7 py-4">Статус</th>
                <th className="px-7 py-4 text-right">Сумма</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-safi-border text-sm">
              {visibleTransactions.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-7 py-8">
                    <EmptyState
                      title="Транзакций пока нет"
                      description="Операции появятся после начислений, покупок или выводов."
                      className="min-h-[180px] shadow-none"
                    />
                  </td>
                </tr>
              )}

              {visibleTransactions.map((transaction) => (
                <tr key={transaction.id} className="transition-colors hover:bg-safi-cream/70">
                  <td className="px-7 py-5">
                    <div className="font-extrabold text-safi-green">{transaction.date}</div>
                    <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{transaction.id}</div>
                  </td>
                  <td className="px-7 py-5 font-extrabold text-safi-green">{transaction.type}</td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{transaction.source}</div>
                    <div className="mt-1 text-xs text-safi-muted">{transaction.comment}</div>
                    {transaction.paymentStrategyLabel && (
                      <div className="mt-2 text-xs font-bold text-safi-gold">{transaction.paymentStrategyLabel}</div>
                    )}
                    {!transaction.affectsBalance && (
                      <div className="mt-2 inline-flex rounded-full bg-safi-cream px-2 py-1 text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                        {transaction.affectsBalanceLabel}
                      </div>
                    )}
                  </td>
                  <td className="px-7 py-5">
                    <Badge variant={transactionStatusVariant(transaction.statusCode)}>
                      {transaction.status}
                    </Badge>
                  </td>
                  <td className="px-7 py-5 text-right">
                    <span className={cn('font-extrabold', transaction.amount.startsWith('+') ? 'text-green-700' : transaction.amount.startsWith('-') ? 'text-red-600' : 'text-safi-muted')}>
                      {transaction.amount}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="divide-y divide-safi-border md:hidden">
          {visibleTransactions.length === 0 && (
            <div className="p-5">
              <EmptyState
                title="Транзакций пока нет"
                description="Операции появятся после начислений, покупок или выводов."
                className="min-h-[180px] shadow-none"
              />
            </div>
          )}

          {visibleTransactions.map((transaction) => (
            <article key={transaction.id} className="p-5">
              <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                  <h2 className="font-extrabold text-safi-green">{transaction.type}</h2>
                  <p className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{transaction.date} / {transaction.id}</p>
                </div>
                <span className={cn('shrink-0 text-right font-extrabold', transaction.amount.startsWith('+') ? 'text-green-700' : transaction.amount.startsWith('-') ? 'text-red-600' : 'text-safi-muted')}>
                  {transaction.amount}
                </span>
              </div>
              <div className="mt-4 space-y-3">
                <p className="text-xs leading-6 text-safi-muted">{transaction.source}</p>
                {transaction.comment && transaction.comment !== transaction.source && (
                  <p className="text-xs leading-6 text-safi-muted">{transaction.comment}</p>
                )}
                {transaction.paymentStrategyLabel && (
                  <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-gold">{transaction.paymentStrategyLabel}</p>
                )}
                {!transaction.affectsBalance && (
                  <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">{transaction.affectsBalanceLabel}</p>
                )}
                <Badge variant={transactionStatusVariant(transaction.statusCode)}>
                  {transaction.status}
                </Badge>
              </div>
            </article>
          ))}
        </div>

        <div className="flex flex-col gap-4 border-t border-safi-border bg-white px-5 py-4 md:px-7">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="text-sm font-bold text-safi-muted">
              Показано: <span className="text-safi-green">{transactionsMeta.total > 0 ? `${transactionsMeta.from}-${transactionsMeta.to}` : '0'}</span>
              {' '}из <span className="text-safi-green">{transactionsMeta.total.toLocaleString('ru-RU')}</span>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <label className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                Показывать по
                <select
                  value={perPage}
                  onChange={(event) => {
                    setPerPage(Number(event.target.value));
                    setPage(1);
                  }}
                  disabled={isLoading}
                  className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60"
                  aria-label="Показывать по"
                >
                  {perPageOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>

              <div className="flex w-full items-center justify-between gap-2 sm:w-auto lg:hidden">
                <button
                  type="button"
                  onClick={() => changePage(transactionsMeta.current_page - 1)}
                  disabled={!canGoPrev}
                  className="rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Назад
                </button>
                <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                  Страница {transactionsMeta.current_page} из {transactionsMeta.last_page}
                </div>
                <button
                  type="button"
                  onClick={() => changePage(transactionsMeta.current_page + 1)}
                  disabled={!canGoNext}
                  className="rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Вперёд
                </button>
              </div>

              <div className="hidden flex-wrap items-center gap-1 lg:flex">
                <button
                  type="button"
                  onClick={() => changePage(transactionsMeta.current_page - 1)}
                  disabled={!canGoPrev}
                  className="flex h-9 min-w-9 items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-sm font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Назад"
                >
                  ‹
                </button>
                {paginationItems.map((item, index) => item === 'ellipsis' ? (
                  <span
                    key={`ellipsis-${index}`}
                    className="flex h-9 min-w-9 items-center justify-center px-2 text-sm font-extrabold text-safi-muted"
                    aria-hidden="true"
                  >
                    …
                  </span>
                ) : (
                  <button
                    key={item}
                    type="button"
                    onClick={() => changePage(item)}
                    disabled={item === transactionsMeta.current_page || isLoading}
                    aria-current={item === transactionsMeta.current_page ? 'page' : undefined}
                    className={[
                      'flex h-9 min-w-9 items-center justify-center rounded-full border px-3 text-xs font-extrabold transition-colors disabled:cursor-default',
                      item === transactionsMeta.current_page
                        ? 'border-safi-green bg-safi-green text-white shadow-[0_8px_22px_rgba(29,78,54,0.18)]'
                        : 'border-safi-border bg-safi-cream text-safi-green hover:border-safi-green hover:bg-white disabled:opacity-60',
                    ].join(' ')}
                  >
                    {item}
                  </button>
                ))}
                <button
                  type="button"
                  onClick={() => changePage(transactionsMeta.current_page + 1)}
                  disabled={!canGoNext}
                  className="flex h-9 min-w-9 items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-sm font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Вперёд"
                >
                  ›
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
        </>
      )}
    </div>
  );
}

function getPaginationItems(currentPage: number, totalPages: number): PaginationItem[] {
  const safeTotalPages = Math.max(1, Math.floor(totalPages));
  const safeCurrentPage = Math.min(Math.max(Math.floor(currentPage), 1), safeTotalPages);

  if (safeTotalPages <= 7) {
    return pageRange(1, safeTotalPages);
  }

  if (safeCurrentPage <= 4) {
    return [...pageRange(1, 5), 'ellipsis', safeTotalPages];
  }

  if (safeCurrentPage >= safeTotalPages - 3) {
    return [1, 'ellipsis', ...pageRange(safeTotalPages - 4, safeTotalPages)];
  }

  return [1, 'ellipsis', safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1, 'ellipsis', safeTotalPages];
}

function pageRange(start: number, end: number): number[] {
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function formatTransactionAmount(direction: string, amount: number) {
  if (direction === 'credit') {
    return `+${amount.toLocaleString('ru-RU')} ₸`;
  }

  if (direction === 'debit') {
    return `-${amount.toLocaleString('ru-RU')} ₸`;
  }

  return `${amount.toLocaleString('ru-RU')} ₸`;
}

function transactionStatusVariant(status: string) {
  const normalized = status.toLowerCase();

  if (['rejected', 'declined', 'failed', 'cancelled'].includes(normalized)) {
    return 'danger';
  }

  if (['pending', 'new', 'processing', 'in_progress'].includes(normalized)) {
    return 'warning';
  }

  return 'success';
}
