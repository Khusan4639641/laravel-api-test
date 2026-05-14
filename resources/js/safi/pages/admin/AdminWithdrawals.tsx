import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle, Search, XCircle } from 'lucide-react';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, approveAdminWithdrawal, getAdminWithdrawals, rejectAdminWithdrawal } from '../../lib/api';

interface AdminWithdrawalRow {
  id: string;
  date: string;
  partnerId: string;
  partnerName: string;
  amount: string;
  method: string;
  reqs: string;
  bank: string;
  iin: string;
  status: string;
  comment: string;
  processedDate: string;
}

export default function AdminWithdrawals() {
  const [withdrawals, setWithdrawals] = useState<AdminWithdrawalRow[]>([]);
  const [query, setQuery] = useState('');
  const [pendingId, setPendingId] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadError, setLoadError] = useState('');
  const [isLoading, setIsLoading] = useState(true);

  const loadWithdrawals = async () => {
    setIsLoading(true);
    setLoadError('');

    try {
      const response = await getAdminWithdrawals();
      const data = normalizeWithdrawals(response);

      setWithdrawals(data);
    } catch {
      setWithdrawals([]);
      setLoadError('Не удалось загрузить заявки на вывод.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadWithdrawals();
  }, []);

  const visibleWithdrawals = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery) {
      return withdrawals;
    }

    return withdrawals.filter((withdrawal) =>
      `${withdrawal.id} ${withdrawal.partnerName} ${withdrawal.partnerId}`.toLowerCase().includes(normalizedQuery)
    );
  }, [withdrawals, query]);

  const stats = useMemo(() => ({
    new: withdrawals.filter((withdrawal) => withdrawal.status === 'Новая заявка').length,
    processing: withdrawals.filter((withdrawal) => withdrawal.status === 'В обработке').length,
    approved: withdrawals.filter((withdrawal) => withdrawal.status === 'Выплачено' || withdrawal.status === 'Одобрено').length,
    rejected: withdrawals.filter((withdrawal) => withdrawal.status === 'Отклонено').length,
  }), [withdrawals]);

  const handleAction = async (withdrawalId: string, action: 'approve' | 'reject') => {
    setPendingId(withdrawalId);
    setMessage('');
    setError('');

    try {
      if (action === 'approve') {
        await approveAdminWithdrawal(withdrawalId);
      } else {
        await rejectAdminWithdrawal(withdrawalId);
      }

      setMessage(action === 'approve' ? 'Заявка одобрена.' : 'Заявка отклонена.');
      await loadWithdrawals();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось обработать заявку.');
      }
    } finally {
      setPendingId('');
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin withdrawals</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Заявки на вывод</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Проверка реквизитов, одобрение и отклонение заявок партнеров.
            </p>
          </div>
        </div>
      </section>

      {(message || error) && (
        <div className={`rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {error || message}
        </div>
      )}

      <section className="rounded-3xl border border-amber-200 bg-amber-50 p-5">
        <div className="flex items-start gap-3">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
          <div className="text-sm leading-6 text-amber-900">
            <strong className="block">Внимание администраторам</strong>
            Перед подтверждением выплаты проверьте реквизиты партнера, доступный баланс и историю операций.
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatBox title="Новые" value={String(stats.new)} color="text-blue-600" />
        <StatBox title="В обработке" value={String(stats.processing)} color="text-amber-600" />
        <StatBox title="Одобрено" value={String(stats.approved)} color="text-emerald-600" />
        <StatBox title="Отклонено" value={String(stats.rejected)} color="text-red-600" />
      </section>

      <section className="rounded-[28px] border border-safi-border bg-white p-4 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <label className="relative block">
          <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-muted" />
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Поиск по ID заявки или ФИО партнера"
            className="w-full rounded-full border border-safi-border bg-safi-cream py-3 pl-12 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
          />
        </label>
      </section>

      {isLoading && <LoadingState />}
      {!isLoading && loadError && <ErrorState description={loadError} onRetry={loadWithdrawals} />}
      {!isLoading && !loadError && visibleWithdrawals.length === 0 && (
        <EmptyState title="Заявок не найдено" description={query ? 'Попробуйте изменить поисковый запрос.' : 'Заявки появятся после запросов на вывод.'} />
      )}

      {!isLoading && !loadError && visibleWithdrawals.length > 0 && (
        <AdminTable headers={['Заявка / дата', 'Партнер', 'Сумма / способ', 'Реквизиты', 'Статус', 'Действия']}>
          {visibleWithdrawals.map((withdrawal) => (
            <tr key={withdrawal.id} className="transition-colors hover:bg-safi-cream/70">
              <td className="px-6 py-5">
                <div className="font-bold text-safi-green">{withdrawal.id}</div>
                <div className="mt-1 text-xs text-safi-muted">{withdrawal.date}</div>
              </td>
              <td className="px-6 py-5">
                <div className="font-bold text-safi-green">{withdrawal.partnerName}</div>
                <div className="mt-1 font-mono text-[10px] text-safi-muted">{withdrawal.partnerId}</div>
              </td>
              <td className="px-6 py-5">
                <div className="mb-1 text-lg font-bold text-safi-green">{withdrawal.amount}</div>
                <div className="text-xs text-safi-muted">{withdrawal.method}</div>
              </td>
              <td className="px-6 py-5">
                <div className="max-w-[220px] truncate font-mono text-sm">{withdrawal.reqs}</div>
                <div className="mt-1 text-xs text-safi-muted">{withdrawal.bank} / ИИН: {withdrawal.iin}</div>
              </td>
              <td className="px-6 py-5">
                <AdminBadge variant={getWithdrawalBadgeVariant(withdrawal.status)}>{withdrawal.status}</AdminBadge>
                {withdrawal.comment && <div className="mt-2 text-[10px] text-red-600">{withdrawal.comment}</div>}
              </td>
              <td className="px-6 py-5">
                <div className="flex items-center justify-center gap-2">
                  {isProcessed(withdrawal.status) ? (
                    <span className="text-xs font-bold uppercase tracking-[0.14em] text-safi-muted">{withdrawal.processedDate}</span>
                  ) : (
                    <>
                      <button
                        type="button"
                        disabled={pendingId === withdrawal.id}
                        onClick={() => handleAction(withdrawal.id, 'approve')}
                        className="flex w-20 flex-col items-center justify-center gap-1 rounded-xl border border-emerald-100 bg-emerald-50 p-2 text-[8px] font-extrabold uppercase tracking-[0.12em] text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white disabled:opacity-50"
                      >
                        <CheckCircle className="h-5 w-5" />
                        {pendingId === withdrawal.id ? '...' : 'Одобр.'}
                      </button>
                      <button
                        type="button"
                        disabled={pendingId === withdrawal.id}
                        onClick={() => handleAction(withdrawal.id, 'reject')}
                        className="flex w-20 flex-col items-center justify-center gap-1 rounded-xl border border-red-100 bg-red-50 p-2 text-[8px] font-extrabold uppercase tracking-[0.12em] text-red-700 transition-colors hover:bg-red-600 hover:text-white disabled:opacity-50"
                      >
                        <XCircle className="h-5 w-5" />
                        {pendingId === withdrawal.id ? '...' : 'Откл.'}
                      </button>
                    </>
                  )}
                </div>
              </td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}

function StatBox({ title, value, color }: { title: string; value: string; color: string }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</div>
      <div className={`mt-2 font-serif text-3xl font-semibold ${color}`}>{value}</div>
    </article>
  );
}

function normalizeWithdrawals(response: unknown): AdminWithdrawalRow[] {
  return getArray(response).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const userRecord = isRecord(record.user) ? record.user : isRecord(record.partner) ? record.partner : undefined;

    return {
      id: getString(record, ['id', 'uuid', 'number']) || `W-${index + 1}`,
      date: getString(record, ['date', 'created_at', 'createdAt']) || '-',
      partnerId: getString(userRecord, ['partner_id', 'partnerId', 'id']) || getString(record, ['partner_id', 'partnerId', 'user_id']) || '-',
      partnerName: getString(userRecord, ['name', 'full_name', 'fullName']) || getString(record, ['partner_name', 'partnerName', 'user_name']) || '-',
      amount: formatAmount(record.amount ?? record.sum),
      method: getString(record, ['method', 'payment_method', 'paymentMethod']) || 'Карта партнера',
      reqs: getString(record, ['reqs', 'requisites', 'card', 'iban']) || '-',
      bank: getString(record, ['bank']) || '-',
      iin: getString(record, ['iin', 'tax_id', 'taxId']) || '-',
      status: normalizeStatus(getString(record, ['status']) || 'Новая заявка'),
      comment: getString(record, ['comment', 'reject_reason', 'rejectReason']) || '',
      processedDate: getString(record, ['processed_date', 'processedDate', 'paid_at', 'updated_at']) || '-',
    };
  });
}

function getArray(response: unknown) {
  if (Array.isArray(response)) {
    return response;
  }

  if (isRecord(response)) {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (isRecord(response.data) && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    if (Array.isArray(response.withdrawals)) {
      return response.withdrawals;
    }
  }

  return [];
}

function getWithdrawalBadgeVariant(status: string) {
  if (status === 'Отклонено') {
    return 'danger';
  }

  if (status === 'В обработке') {
    return 'warning';
  }

  if (status === 'Выплачено' || status === 'Одобрено') {
    return 'success';
  }

  return 'default';
}

function isProcessed(status: string) {
  return ['Выплачено', 'Отклонено', 'Одобрено'].includes(status);
}

function normalizeStatus(status: string) {
  const normalized = status.toLowerCase();

  if (['approved', 'paid', 'completed'].includes(normalized)) {
    return 'Выплачено';
  }

  if (['rejected', 'declined', 'failed'].includes(normalized)) {
    return 'Отклонено';
  }

  if (['processing', 'in_progress'].includes(normalized)) {
    return 'В обработке';
  }

  if (['pending', 'new'].includes(normalized)) {
    return 'Новая заявка';
  }

  return status;
}

function formatAmount(value: unknown) {
  if (typeof value === 'number') {
    return `${value.toLocaleString('ru-RU')} тг`;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    return value.includes('тг') || value.includes('₸') ? value : `${value} тг`;
  }

  return '0 тг';
}

function getString(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
