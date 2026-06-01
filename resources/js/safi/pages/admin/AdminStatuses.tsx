import { useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminStatuses, getApiErrorState, Status } from '../../lib/api';
import { adminText } from '../../i18n/adminText';

export default function AdminStatuses() {
  const [statuses, setStatuses] = useState<Status[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadStatuses = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setStatuses(await getAdminStatuses());
    } catch (caughtError) {
      setStatuses([]);
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_25'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadStatuses();
  }, []);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0KHRgtCw0YLR_3')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0JvQtdGB0YLQ')}</p>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadStatuses} />}
      {!isLoading && !error && statuses.length === 0 && <EmptyState title={adminText('a_0KHRgtCw0YLR_4')} description={adminText('a_0KHQv9C40YHQ_2')} />}

      {!isLoading && !error && statuses.length > 0 && (
        <AdminTable headers={[adminText('a_0KHRgtCw0YLR'), adminText('a_0KPRgdC70L7Q'), adminText('a_0J_RgNC10LzQ'), adminText('a_0J_QsNGA0YLQ_10')]}>
          {statuses.map((status) => (
            <tr key={status.id} className="hover:bg-safi-green/5 transition-colors group">
              <td className="px-6 py-4 font-bold text-safi-green text-sm">{status.name}</td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold">{status.pv.toLocaleString('ru-RU')} PV</div>
              </td>
              <td className="px-6 py-4 font-bold max-w-[200px] truncate">{status.reward}</td>
              <td className="px-6 py-4"><AdminBadge variant="default">{status.partnersCount ?? 0}</AdminBadge></td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}
