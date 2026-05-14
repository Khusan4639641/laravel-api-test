import { useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Search, Filter, Download } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminTransactions, getApiErrorState, getArray, getNumber, getString } from '../../lib/api';

export default function AdminTransactions() {
  const [transactions, setTransactions] = useState<Array<{ id: string; date: string; partnerId: string; partnerName: string; type: string; amount: string; status: string; comment: string }>>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const summary = [
    { label: 'Всего начислено', value: transactions.filter((trx) => trx.amount.startsWith('+')).reduce((sum, trx) => sum + amountValue(trx.amount), 0) },
    { label: 'Всего выплачено', value: transactions.filter((trx) => trx.amount.startsWith('-')).reduce((sum, trx) => sum + amountValue(trx.amount), 0) },
    { label: 'В обработке', value: transactions.filter((trx) => trx.status === 'pending').reduce((sum, trx) => sum + amountValue(trx.amount), 0) },
    { label: 'Отложено', value: 0 },
  ];

  const loadTransactions = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminTransactions();
      setTransactions(getArray(response, ['transactions']).map((item, index) => {
        const trx = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const user = trx.user && typeof trx.user === 'object' ? trx.user as Record<string, unknown> : {};
        const direction = getString(trx, ['direction']) || 'credit';
        const amount = getNumber(trx, ['amount']) ?? 0;
        return {
          id: getString(trx, ['id']) || String(index + 1),
          date: getString(trx, ['created_at']) || '-',
          partnerId: getString(user, ['id', 'login']) || getString(trx, ['user_id']) || '-',
          partnerName: getString(user, ['name']) || '-',
          type: getString(trx, ['type']) || '-',
          amount: `${direction === 'credit' ? '+' : '-'}${amount.toLocaleString('ru-RU')} тг`,
          status: getString(trx, ['status']) || '-',
          comment: getString(trx, ['description']) || '-',
        };
      }));
    } catch (caughtError) {
      setTransactions([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить транзакции.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadTransactions();
  }, []);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Транзакции</h1>
          <p className="text-sm text-safi-text/70">История всех финансовых операций в системе</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 bg-[#F5F5F0] hover:bg-safi-green/10 text-safi-green rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
          <Download className="w-4 h-4" /> Экспорт CSV
        </button>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
         {summary.map((item, i) => (
           <div key={i} className="bg-white p-6 rounded-2xl border border-safi-green/5 shadow-sm text-center">
             <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">{item.label}</div>
             <div className="text-xl font-bold text-safi-green">{item.value.toLocaleString('ru-RU')} ₸</div>
           </div>
         ))}
      </div>

      <div className="bg-white p-4 rounded-[24px] border border-safi-green/5 shadow-sm flex flex-col md:flex-row gap-4">
        <div className="flex-1 relative">
          <Search className="w-5 h-5 text-safi-text/40 absolute left-4 top-1/2 -translate-y-1/2" />
          <input 
            type="text" 
            placeholder="Поиск по партнёру, ID транзакции..." 
            className="w-full pl-12 pr-4 py-3 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
          />
        </div>
        <button className="flex items-center justify-center gap-2 px-6 py-3 bg-[#F5F5F0] hover:bg-safi-green/10 text-safi-green rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shrink-0">
          <Filter className="w-4 h-4" /> Фильтры
        </button>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadTransactions} />}
      {!isLoading && !error && transactions.length === 0 && <EmptyState title="Транзакций пока нет" description="Операции появятся после начислений, заказов или выплат." />}

      {!isLoading && !error && transactions.length > 0 && (
        <AdminTable headers={['Транзакция / Дата', 'Партнёр', 'Тип операции', 'Сумма', 'Статус', 'Источник / Комментарий']}>
          {transactions.map((trx, i) => (
            <tr key={i} className="hover:bg-safi-green/5 transition-colors cursor-pointer group">
              <td className="px-6 py-4">
                <div className="font-bold text-safi-text">{trx.id}</div>
                <div className="text-xs text-safi-text/50 mt-1">{trx.date}</div>
              </td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{trx.partnerName}</div>
                <div className="text-[10px] font-mono text-safi-text/50 mt-1">{trx.partnerId}</div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold">{trx.type}</div>
              </td>
              <td className="px-6 py-4">
                <div className={`font-bold ${trx.amount.startsWith('+') ? 'text-green-600' : 'text-red-500'}`}>
                  {trx.amount}
                </div>
              </td>
              <td className="px-6 py-4">
                <AdminBadge variant={trx.status === 'Отклонено' ? 'danger' : trx.status === 'В обработке' ? 'warning' : 'success'}>
                  {trx.status}
                </AdminBadge>
              </td>
              <td className="px-6 py-4 text-xs text-safi-text/70 max-w-[200px] truncate">
                {trx.comment || '-'}
              </td>
            </tr>
          ))}
        </AdminTable>
      )}
      
    </div>
  );
}

function amountValue(value: string) {
  const parsed = Number(value.replace(/[^\d.-]/g, ''));
  return Number.isFinite(parsed) ? Math.abs(parsed) : 0;
}
