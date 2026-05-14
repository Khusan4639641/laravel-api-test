import { useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminBonuses, getApiErrorState, getArray, getNumber, getString } from '../../lib/api';

export default function AdminBonuses() {
  const [bonuses, setBonuses] = useState<Array<{ date: string; partnerId: string; partnerName: string; type: string; basis: string; percentage: string; amount: string; status: string }>>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadBonuses = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminBonuses();
      setBonuses(getArray(response, ['bonuses']).map((item) => {
        const bonus = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const user = bonus.user && typeof bonus.user === 'object' ? bonus.user as Record<string, unknown> : {};
        return {
          date: getString(bonus, ['created_at', 'calculated_at']) || '-',
          partnerId: getString(user, ['id', 'login']) || getString(bonus, ['user_id']) || '-',
          partnerName: getString(user, ['name']) || '-',
          type: getString(bonus, ['bonus_type']) || '-',
          basis: getString(bonus, ['description']) || 'Система',
          percentage: '',
          amount: `${(getNumber(bonus, ['amount']) ?? 0).toLocaleString('ru-RU')} тг`,
          status: getString(bonus, ['status']) || '-',
        };
      }));
    } catch (caughtError) {
      setBonuses([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить бонусы.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadBonuses();
  }, []);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Бонусы</h1>
          <p className="text-sm text-safi-text/70">Начисления и статистика</p>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadBonuses} />}
      {!isLoading && !error && bonuses.length === 0 && <EmptyState title="Бонусов пока нет" description="Начисления появятся после покупок и расчета бонусов." />}

      {!isLoading && !error && bonuses.length > 0 && (
        <AdminTable headers={['Дата', 'Партнёр', 'Тип бонуса', 'Обоснование', 'Сумма', 'Статус']}>
          {bonuses.map((b, i) => (
            <tr key={i} className="hover:bg-safi-green/5 transition-colors group">
              <td className="px-6 py-4 text-xs">{b.date}</td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{b.partnerName}</div>
                <div className="text-[10px] font-mono text-safi-text/50">{b.partnerId}</div>
              </td>
              <td className="px-6 py-4 font-bold text-sm">{b.type}</td>
              <td className="px-6 py-4">
                <div className="text-sm">{b.basis}</div>
                <div className="text-xs text-safi-gold font-bold mt-0.5">{b.percentage}</div>
              </td>
              <td className="px-6 py-4 font-bold text-green-600">+{b.amount}</td>
              <td className="px-6 py-4"><AdminBadge variant="success">{b.status}</AdminBadge></td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}
