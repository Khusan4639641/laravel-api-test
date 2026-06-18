import { FormEvent, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Search, Filter, Download, Pencil, Trash2, X } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { deleteAdminTransaction, getAdminTransactions, getApiErrorState, getNumber, getString, updateAdminTransactionAmount } from '../../lib/api';
import { adminText } from '../../i18n/adminText';
import { defaultPaginationMeta, getPaginatedItems, normalizePaginationMeta } from '../../lib/pagination';
import type { PaginationMeta } from '../../lib/pagination';
import { transactionStatusLabel, transactionTypeLabel } from '../../lib/systemLabels';

type TransactionRow = {
  id: string;
  date: string;
  partnerId: string;
  partnerName: string;
  type: string;
  direction: string;
  rawAmount: number;
  amount: string;
  affectsBalance: boolean;
  affectsBalanceLabel: string;
  statusCode: string;
  status: string;
  comment: string;
  paymentStrategyLabel?: string;
};

type TransactionSummary = {
  operationTurnover: number;
  totalCredited: number;
  totalPaid: number;
  pending: number;
  deferredDeposit: number;
};

const emptySummary: TransactionSummary = {
  operationTurnover: 0,
  totalCredited: 0,
  totalPaid: 0,
  pending: 0,
  deferredDeposit: 0,
};

type TransactionActionModal =
  | { mode: 'edit'; transaction: TransactionRow; amount: string; reason: string }
  | { mode: 'delete'; transaction: TransactionRow; reason: string };

export default function AdminTransactions() {
  const { currentUser } = useAdminContext();
  const [searchParams] = useSearchParams();
  const userIdFilter = searchParams.get('user_id') || undefined;
  const searchParam = searchParams.get('search') || '';
  const [search, setSearch] = useState(searchParam);
  const [debouncedSearch, setDebouncedSearch] = useState(search.trim());
  const [transactions, setTransactions] = useState<TransactionRow[]>([]);
  const [summary, setSummary] = useState<TransactionSummary>(emptySummary);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [meta, setMeta] = useState<PaginationMeta>(defaultPaginationMeta);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const [actionModal, setActionModal] = useState<TransactionActionModal | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [isSubmittingAction, setIsSubmittingAction] = useState(false);
  const hasSearch = debouncedSearch.trim() !== '';
  const currentRole = currentUser.role.toLowerCase();
  const canEditTransactions = currentRole === 'admin' || currentRole === 'super_admin';
  const canDeleteTransactions = currentRole === 'super_admin';
  const showActions = canEditTransactions || canDeleteTransactions;
  const summaryCards = [
    { label: adminText('Оборот операций'), value: summary.operationTurnover },
    { label: adminText('a_0JLRgdC10LPQ_5'), value: summary.totalCredited },
    { label: adminText('a_0JLRgdC10LPQ_6'), value: summary.totalPaid },
    { label: adminText('a_0JIg0L7QsdGA'), value: summary.pending },
    { label: adminText('Отложено / Депозит'), value: summary.deferredDeposit },
  ];

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const loadTransactions = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminTransactions({
        user_id: userIdFilter,
        search: debouncedSearch.trim() || undefined,
        page,
        per_page: perPage,
      });
      const summaryRecord = response && typeof response === 'object' && 'summary' in response
        ? (response as Record<string, unknown>).summary
        : {};
      const summaryData = summaryRecord && typeof summaryRecord === 'object' ? summaryRecord as Record<string, unknown> : {};
      setSummary({
        operationTurnover: getNumber(summaryData, ['operation_turnover', 'operationTurnover']) ?? 0,
        totalCredited: getNumber(summaryData, ['total_credited', 'totalCredited']) ?? 0,
        totalPaid: getNumber(summaryData, ['total_paid', 'totalPaid']) ?? 0,
        pending: getNumber(summaryData, ['pending']) ?? 0,
        deferredDeposit: getNumber(summaryData, ['deferred_deposit', 'deferredDeposit']) ?? 0,
      });
      const items = getPaginatedItems(response, 'transactions');

      setMeta(normalizePaginationMeta(response, 'transactions', page, perPage, items.length));
      setTransactions(items.map((item, index) => {
        const trx = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const user = trx.user && typeof trx.user === 'object' ? trx.user as Record<string, unknown> : {};
        const direction = getString(trx, ['direction']) || 'credit';
        const amount = getNumber(trx, ['amount']) ?? 0;
        const rawType = getString(trx, ['type']) || '-';
        const statusCode = getString(trx, ['status']) || '-';
        return {
          id: getString(trx, ['id']) || String(index + 1),
          date: getString(trx, ['created_at']) || '-',
          partnerId: getString(user, ['id', 'login']) || getString(trx, ['user_id']) || '-',
          partnerName: getString(user, ['name']) || '-',
          type: transactionTypeLabel(rawType, getString(trx, ['type_label', 'typeLabel']) || rawType),
          direction,
          rawAmount: amount,
          amount: formatTransactionAmount(direction, amount),
          affectsBalance: trx.affects_balance !== false && trx.affectsBalance !== false,
          affectsBalanceLabel: getString(trx, ['affects_balance_label', 'affectsBalanceLabel']) || adminText('Не влияет на баланс'),
          statusCode,
          status: transactionStatusLabel(statusCode, getString(trx, ['status_label', 'statusLabel']) || statusCode),
          comment: getString(trx, ['description']) || '-',
          paymentStrategyLabel: getString(trx, ['payment_strategy_label', 'paymentStrategyLabel']),
        };
      }));
    } catch (caughtError) {
      setTransactions([]);
      setSummary(emptySummary);
      setMeta({ ...defaultPaginationMeta, per_page: perPage });
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_32'));
    } finally {
      setIsLoading(false);
    }
  };

  const openEditModal = (transaction: TransactionRow) => {
    setActionError(null);
    setActionModal({
      mode: 'edit',
      transaction,
      amount: String(transaction.rawAmount),
      reason: '',
    });
  };

  const openDeleteModal = (transaction: TransactionRow) => {
    setActionError(null);
    setActionModal({
      mode: 'delete',
      transaction,
      reason: '',
    });
  };

  const submitEdit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!actionModal || actionModal.mode !== 'edit') {
      return;
    }

    setIsSubmittingAction(true);
    setActionError(null);

    try {
      await updateAdminTransactionAmount(actionModal.transaction.id, {
        amount: actionModal.amount,
        reason: actionModal.reason,
      });
      setActionModal(null);
      showToast('Транзакция обновлена');
      await loadTransactions();
    } catch (caughtError) {
      const message = getApiErrorState(caughtError).error || 'Не удалось обновить транзакцию';
      setActionError(message);
      showToast(message, 'error');
    } finally {
      setIsSubmittingAction(false);
    }
  };

  const submitDelete = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!actionModal || actionModal.mode !== 'delete') {
      return;
    }

    setIsSubmittingAction(true);
    setActionError(null);

    try {
      await deleteAdminTransaction(actionModal.transaction.id, {
        reason: actionModal.reason,
      });
      setActionModal(null);
      showToast('Транзакция удалена');
      await loadTransactions();
    } catch (caughtError) {
      const message = getApiErrorState(caughtError).error || 'Не удалось удалить транзакцию';
      setActionError(message);
      showToast(message, 'error');
    } finally {
      setIsSubmittingAction(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setPage(1);
      setDebouncedSearch(search.trim());
    }, 300);

    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setSearch(searchParam);
    setPage(1);
  }, [searchParam]);

  useEffect(() => {
    void loadTransactions();
  }, [debouncedSearch, userIdFilter, page, perPage]);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0KLRgNCw0L3Q')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0JjRgdGC0L7R_2')}</p>
        </div>
        <button className="flex cursor-not-allowed items-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green opacity-60 transition-colors" disabled title={adminText('a_0K3QutGB0L_Q_2')}>
          <Download className="w-4 h-4" />{adminText('a_0K3QutGB0L_Q_3')}</button>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
         {summaryCards.map((item, i) => (
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
            placeholder={adminText('a_0J_QvtC40YHQ_3')}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="w-full pl-12 pr-4 py-3 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
          />
        </div>
        <button className="flex cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-[#F5F5F0] px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green opacity-60 transition-colors shrink-0" disabled title={adminText('a_0KTQuNC70YzR')}>
          <Filter className="w-4 h-4" />{adminText('a_0KTQuNC70YzR_2')}</button>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadTransactions} />}
      {!isLoading && !error && (
        <section className="space-y-4">
          {transactions.length === 0 ? (
            <EmptyState
              title={hasSearch ? 'По вашему запросу ничего не найдено' : 'Записей пока нет'}
              description={hasSearch ? adminText('a_0J_QvtC_0YDQ') : adminText('a_0J7Qv9C10YDQ_2')}
            />
          ) : (
            <AdminTable headers={[
              adminText('transaction_id_date'),
              adminText('partner_id_header'),
              adminText('a_0KLQuNC_INC-'),
              adminText('a_0KHRg9C80LzQ'),
              adminText('a_0KHRgtCw0YLR'),
              adminText('a_0JjRgdGC0L7R_3'),
              ...(showActions ? ['Действия'] : []),
            ]}>
              {transactions.map((trx, i) => (
                <tr key={i} className="hover:bg-safi-green/5 transition-colors group">
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
                    {!trx.affectsBalance && (
                      <div className="mt-1 inline-flex rounded-full bg-[#F5F5F0] px-2 py-1 text-[9px] font-bold uppercase tracking-widest text-safi-text/50">
                        {trx.affectsBalanceLabel}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <div className={`font-bold ${trx.amount.startsWith('+') ? 'text-green-600' : trx.amount.startsWith('-') ? 'text-red-500' : 'text-safi-text'}`}>
                      {trx.amount}
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <AdminBadge variant={transactionBadgeVariant(trx.statusCode)}>
                      {trx.status}
                    </AdminBadge>
                  </td>
                  <td className="px-6 py-4 text-xs text-safi-text/70 max-w-[200px] truncate">
                    <div>{trx.comment || '-'}</div>
                    {trx.paymentStrategyLabel && (
                      <div className="mt-1 font-bold text-safi-gold">{trx.paymentStrategyLabel}</div>
                    )}
                  </td>
                  {showActions && (
                    <td className="px-6 py-4">
                      <div className="flex flex-wrap gap-2">
                        {canEditTransactions && (
                          <button
                            type="button"
                            onClick={() => openEditModal(trx)}
                            className="inline-flex items-center gap-2 rounded-xl border border-safi-border bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white"
                          >
                            <Pencil className="h-3.5 w-3.5" />
                            Изменить
                          </button>
                        )}
                        {canDeleteTransactions && (
                          <button
                            type="button"
                            onClick={() => openDeleteModal(trx)}
                            className="inline-flex items-center gap-2 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-red-600 transition-colors hover:border-red-200 hover:bg-red-100"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                            Удалить
                          </button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </AdminTable>
          )}

          <AdminPagination
            meta={meta}
            onPageChange={setPage}
            onPerPageChange={(nextPerPage) => {
              setPerPage(nextPerPage);
              setPage(1);
            }}
          />
        </section>
      )}

      {actionModal && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-safi-green/40 px-4 py-8 backdrop-blur-sm">
          <form
            className="w-full max-w-lg rounded-3xl border border-safi-border bg-white p-6 shadow-[0_24px_80px_rgba(11,23,18,0.18)]"
            onSubmit={actionModal.mode === 'edit' ? submitEdit : submitDelete}
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Транзакция #{actionModal.transaction.id}</div>
                <h2 className="mt-2 font-serif text-2xl font-semibold text-safi-green">
                  {actionModal.mode === 'edit' ? 'Изменить сумму' : 'Удалить транзакцию'}
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setActionModal(null)}
                className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-safi-cream text-safi-green"
                aria-label="Закрыть"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="mt-5 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-sm text-safi-green">
              <div className="font-bold">{actionModal.transaction.partnerName}</div>
              <div className="mt-1 text-xs text-safi-muted">{actionModal.transaction.type} · {actionModal.transaction.amount}</div>
            </div>

            {actionModal.mode === 'edit' ? (
              <label className="mt-5 block">
                <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Новая сумма</span>
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={actionModal.amount}
                  onChange={(event) => setActionModal({ ...actionModal, amount: event.target.value })}
                  className="w-full rounded-2xl border border-safi-border bg-white px-4 py-3 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
                  required
                />
              </label>
            ) : (
              <div className="mt-5 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
                Транзакция будет удалена, баланс пользователя будет пересчитан, связанное уведомление исчезнет.
              </div>
            )}

            <label className="mt-5 block">
              <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Причина</span>
              <textarea
                rows={4}
                value={actionModal.reason}
                onChange={(event) => setActionModal({ ...actionModal, reason: event.target.value })}
                className="w-full resize-none rounded-2xl border border-safi-border bg-white px-4 py-3 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
                placeholder={actionModal.mode === 'edit' ? 'Корректировка суммы по заявке администратора' : 'Удаление ошибочной транзакции'}
                required
                minLength={3}
                maxLength={500}
              />
            </label>

            {actionError && (
              <div className="mt-4 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {actionError}
              </div>
            )}

            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={() => setActionModal(null)}
                className="rounded-full border border-safi-border bg-white px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green"
                disabled={isSubmittingAction}
              >
                Отмена
              </button>
              <button
                type="submit"
                className={`rounded-full px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white disabled:opacity-60 ${actionModal.mode === 'delete' ? 'bg-red-600' : 'bg-safi-green'}`}
                disabled={isSubmittingAction}
              >
                {isSubmittingAction ? 'Сохранение...' : actionModal.mode === 'edit' ? 'Сохранить' : 'Удалить'}
              </button>
            </div>
          </form>
        </div>
      )}
      
    </div>
  );
}

function formatTransactionAmount(direction: string, amount: number) {
  if (direction === 'credit') {
    return `+${amount.toLocaleString('ru-RU')} ${adminText('currency_kzt_short')}`;
  }

  if (direction === 'debit') {
    return `-${amount.toLocaleString('ru-RU')} ${adminText('currency_kzt_short')}`;
  }

  return `${amount.toLocaleString('ru-RU')} ${adminText('currency_kzt_short')}`;
}

function transactionBadgeVariant(status: string) {
  const normalized = status.toLowerCase();

  if (['rejected', 'declined', 'failed', 'cancelled'].includes(normalized)) {
    return 'danger';
  }

  if (['pending', 'new', 'processing', 'in_progress'].includes(normalized)) {
    return 'warning';
  }

  return 'success';
}
