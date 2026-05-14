import { useCallback, useEffect, useMemo, useState } from 'react';
import { ArrowDownToLine, ArrowUpFromLine, Calendar, CreditCard, Filter, RefreshCcw } from 'lucide-react';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { cn } from '../../lib/utils';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getArray, getDashboardTransactions, getNumber, getString } from '../../lib/api';

const filters = ['Все', 'Начисления', 'Выводы', 'Кэшбэк'];

export default function Transactions() {
  const [filter, setFilter] = useState('Все');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [transactions, setTransactions] = useState<Array<{ id: string; date: string; type: string; amount: string; status: string; source: string; comment: string }>>([]);
  const balance = useMemo(() => {
    const totalEarned = transactions.filter((transaction) => transaction.amount.startsWith('+')).reduce((sum, transaction) => sum + Number(transaction.amount.replace(/[^\d.-]/g, '')), 0);
    const withdrawn = transactions.filter((transaction) => transaction.amount.startsWith('-')).reduce((sum, transaction) => sum + Math.abs(Number(transaction.amount.replace(/[^\d.-]/g, ''))), 0);
    return { totalEarned, available: Math.max(totalEarned - withdrawn, 0), pending: 0, withdrawn };
  }, [transactions]);

  const loadTransactions = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getDashboardTransactions();
      setTransactions(getArray(response, ['transactions']).map((item, index) => {
        const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const direction = getString(record, ['direction']) || 'credit';
        const amount = getNumber(record, ['amount']) ?? 0;
        return {
          id: getString(record, ['id']) || String(index + 1),
          date: getString(record, ['created_at']) || '',
          type: getString(record, ['type']) || 'Операция',
          amount: `${direction === 'credit' ? '+' : '-'}${amount.toLocaleString('ru-RU')} ₸`,
          status: getString(record, ['status']) || 'completed',
          source: getString(record, ['description']) || 'Система',
          comment: getString(record, ['description']) || '',
        };
      }));
    } catch (caughtError) {
      setTransactions([]);
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadTransactions();
  }, [loadTransactions]);

  const visibleTransactions = useMemo(() => {
    if (filter === 'Все') {
      return transactions;
    }

    if (filter === 'Начисления') {
      return transactions.filter((transaction) => transaction.amount.startsWith('+'));
    }

    if (filter === 'Выводы') {
      return transactions.filter((transaction) => transaction.type.includes('Вывод'));
    }

    return transactions.filter((transaction) => transaction.type.includes('Кэшбэк'));
  }, [filter, transactions]);

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
                  key={item}
                  type="button"
                  onClick={() => setFilter(item)}
                  className={cn(
                    'shrink-0 rounded-full border px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-colors',
                    filter === item ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-safi-cream text-safi-muted hover:text-safi-green'
                  )}
                >
                  {item}
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
                    <div className="font-extrabold text-safi-green">{transaction.date.split(' ')[0]}</div>
                    <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{transaction.id}</div>
                  </td>
                  <td className="px-7 py-5 font-extrabold text-safi-green">{transaction.type}</td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{transaction.source}</div>
                    <div className="mt-1 text-xs text-safi-muted">{transaction.comment}</div>
                  </td>
                  <td className="px-7 py-5">
                    <Badge variant={transaction.status === 'Начислено' || transaction.status === 'Выплачено' ? 'success' : 'warning'}>
                      {transaction.status}
                    </Badge>
                  </td>
                  <td className="px-7 py-5 text-right">
                    <span className={cn('font-extrabold', transaction.amount.startsWith('+') ? 'text-green-700' : 'text-safi-muted')}>
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
                <div>
                  <h2 className="font-extrabold text-safi-green">{transaction.type}</h2>
                  <p className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{transaction.date} / {transaction.id}</p>
                </div>
                <span className={cn('font-extrabold', transaction.amount.startsWith('+') ? 'text-green-700' : 'text-safi-muted')}>
                  {transaction.amount}
                </span>
              </div>
              <div className="mt-4 flex items-end justify-between gap-4">
                <p className="text-xs leading-6 text-safi-muted">{transaction.source}</p>
                <Badge variant={transaction.status === 'Начислено' || transaction.status === 'Выплачено' ? 'success' : 'warning'}>
                  {transaction.status}
                </Badge>
              </div>
            </article>
          ))}
        </div>
      </section>
        </>
      )}
    </div>
  );
}
