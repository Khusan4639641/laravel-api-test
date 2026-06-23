import { useEffect, useState } from 'react';
import { Edit3, Search, Trash2 } from 'lucide-react';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import { deleteAdminBonus, getAdminBonuses, getApiErrorState, getNumber, getString, recalculateAdminBinaryBonuses, updateAdminBonus } from '../../lib/api';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { adminText } from '../../i18n/adminText';
import { defaultPaginationMeta, getPaginatedItems, normalizePaginationMeta } from '../../lib/pagination';
import type { PaginationMeta } from '../../lib/pagination';
import { transactionStatusLabel, transactionTypeLabel } from '../../lib/systemLabels';

export default function AdminBonuses() {
  const { currentUser } = useAdminContext();
  const isSuperAdmin = currentUser.role === 'super_admin';
  const [bonuses, setBonuses] = useState<Array<{ id: string; date: string; partnerId: string; partnerName: string; type: string; basis: string; percentage: string; amount: string; rawAmount: number; status: string }>>([]);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [meta, setMeta] = useState<PaginationMeta>(defaultPaginationMeta);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRecalculating, setIsRecalculating] = useState(false);
  const [editBonus, setEditBonus] = useState<{ id: string; amount: number } | null>(null);
  const [deleteBonusId, setDeleteBonusId] = useState<string | null>(null);
  const [manualAmount, setManualAmount] = useState('');
  const [manualReason, setManualReason] = useState('');
  const [isSavingManualAction, setIsSavingManualAction] = useState(false);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const hasActiveFilters = debouncedSearch.trim() !== '' || typeFilter !== '' || statusFilter !== '';

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const loadBonuses = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminBonuses({
        search: debouncedSearch.trim() || undefined,
        type: typeFilter || undefined,
        status: statusFilter || undefined,
        page,
        per_page: perPage,
      });
      const items = getPaginatedItems(response, 'bonuses');

      setMeta(normalizePaginationMeta(response, 'bonuses', page, perPage, items.length));
      setBonuses(items.map((item) => {
        const bonus = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const user = bonus.user && typeof bonus.user === 'object' ? bonus.user as Record<string, unknown> : {};
        const rawAmount = getNumber(bonus, ['amount']) ?? 0;

        return {
          id: getString(bonus, ['id']) || '',
          date: getString(bonus, ['created_at', 'calculated_at']) || '-',
          partnerId: getString(user, ['id', 'login']) || getString(bonus, ['user_id']) || '-',
          partnerName: getString(user, ['name']) || '-',
          type: transactionTypeLabel(getString(bonus, ['bonus_type', 'type']), getString(bonus, ['bonus_type_label', 'bonusTypeLabel', 'type_label', 'typeLabel']) || getString(bonus, ['bonus_type', 'type']) || '-'),
          basis: getString(bonus, ['description']) || adminText('a_0KHQuNGB0YLQ'),
          percentage: '',
          amount: `${rawAmount.toLocaleString('ru-RU')} ${adminText('currency_kzt_short')}`,
          rawAmount,
          status: transactionStatusLabel(getString(bonus, ['status']), getString(bonus, ['status_label', 'statusLabel']) || getString(bonus, ['status']) || '-'),
        };
      }));
    } catch (caughtError) {
      setBonuses([]);
      setMeta({ ...defaultPaginationMeta, per_page: perPage });
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0'));
    } finally {
      setIsLoading(false);
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
    void loadBonuses();
  }, [debouncedSearch, typeFilter, statusFilter, page, perPage]);

  const recalculateBinaryBonusesForAllPartners = async () => {
    setIsRecalculating(true);

    try {
      const response = await recalculateAdminBinaryBonuses();
      const record = response && typeof response === 'object' ? response as Record<string, unknown> : {};
      const processed = Number(record.processed_count ?? 0);
      const recalculated = Number(record.recalculated_count ?? 0);
      const created = Number(record.created_count ?? 0);
      const updated = Number(record.updated_count ?? 0);
      const skipped = Number(record.skipped_count ?? 0);
      const failed = Number(record.failed_count ?? 0);

      showToast(`Массовый расчёт выполнен: обработано ${processed}, создано ${created}, обновлено ${updated}, пропущено ${skipped}, ошибок ${failed}, всего ${recalculated}`, failed > 0 ? 'error' : 'success');
      await loadBonuses();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось выполнить массовый бинарный расчёт', 'error');
    } finally {
      setIsRecalculating(false);
    }
  };

  const openEditModal = (bonus: { id: string; rawAmount: number }) => {
    setEditBonus({ id: bonus.id, amount: bonus.rawAmount });
    setDeleteBonusId(null);
    setManualAmount(String(bonus.rawAmount));
    setManualReason('');
  };

  const openDeleteModal = (bonusId: string) => {
    setDeleteBonusId(bonusId);
    setEditBonus(null);
    setManualAmount('');
    setManualReason('');
  };

  const closeManualModal = () => {
    setEditBonus(null);
    setDeleteBonusId(null);
    setManualAmount('');
    setManualReason('');
  };

  const submitManualAction = async () => {
    if (!manualReason.trim()) {
      showToast('Укажите причину', 'error');
      return;
    }

    setIsSavingManualAction(true);

    try {
      if (editBonus) {
        await updateAdminBonus(editBonus.id, {
          amount: manualAmount,
          reason: manualReason.trim(),
        });
        showToast('Бонус обновлён');
      }

      if (deleteBonusId) {
        await deleteAdminBonus(deleteBonusId, {
          reason: manualReason.trim(),
        });
        showToast('Бонус удалён');
      }

      closeManualModal();
      await loadBonuses();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось сохранить изменение бонуса', 'error');
    } finally {
      setIsSavingManualAction(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0JHQvtC90YPR')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0J3QsNGH0LjR')}</p>
        </div>
        <div className="flex w-full flex-col gap-3 md:w-auto">
          <button
            type="button"
            onClick={recalculateBinaryBonusesForAllPartners}
            disabled={isRecalculating}
            className="cursor-pointer rounded-xl bg-safi-green px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isRecalculating ? adminText('a_0KHQvtGF0YDQ_2') : 'Запустить массовый бинарный расчёт'}
          </button>
        </div>
      </div>

      <div className="rounded-[24px] border border-safi-green/5 bg-white p-4 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row">
          <label className="relative flex-1">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-text/40" />
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Поиск по ID, партнёру, login, email или типу бонуса"
              className="w-full rounded-xl bg-[#F5F5F0] py-3 pl-12 pr-4 text-sm font-medium text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
            />
          </label>
          <select
            value={typeFilter}
            onChange={(event) => {
              setTypeFilter(event.target.value);
              setPage(1);
            }}
            className="cursor-pointer rounded-xl bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
            aria-label="Фильтр по типу бонуса"
          >
            <option value="">Все типы</option>
            <option value="referral_bonus">Реферальный</option>
            <option value="binary_bonus_main">Бинарный</option>
            <option value="status_bonus">Статусный</option>
            <option value="x2_bonus">X2</option>
            <option value="bonus_x2">Бонус X2</option>
            <option value="cashback">Cashback</option>
          </select>
          <select
            value={statusFilter}
            onChange={(event) => {
              setStatusFilter(event.target.value);
              setPage(1);
            }}
            className="cursor-pointer rounded-xl bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
            aria-label="Фильтр по статусу бонуса"
          >
            <option value="">Все статусы</option>
            <option value="pending">Ожидает</option>
            <option value="completed">Завершен</option>
            <option value="failed">Ошибка</option>
          </select>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadBonuses} />}

      {!isLoading && !error && (
        <section className="space-y-4">
          {bonuses.length === 0 ? (
            <EmptyState
              title={hasActiveFilters ? 'По вашему запросу ничего не найдено' : 'Записей пока нет'}
              description={hasActiveFilters ? 'Попробуйте изменить поиск или фильтры.' : adminText('a_0J3QsNGH0LjR_2')}
            />
          ) : (
            <AdminTable headers={[
              adminText('a_0JTQsNGC0LA'),
              adminText('a_0J_QsNGA0YLQ'),
              adminText('a_0KLQuNC_INCx'),
              adminText('a_0J7QsdC-0YHQ'),
              adminText('a_0KHRg9C80LzQ'),
              adminText('a_0KHRgtCw0YLR'),
              ...(isSuperAdmin ? ['Действия'] : []),
            ]}>
              {bonuses.map((b, i) => (
                <tr key={b.id || i} className="hover:bg-safi-green/5 transition-colors group">
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
                  {isSuperAdmin && (
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => openEditModal(b)}
                          className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white"
                          aria-label="Изменить бонус"
                        >
                          <Edit3 className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => openDeleteModal(b.id)}
                          className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-200 bg-white text-red-600 transition-colors hover:bg-red-600 hover:text-white"
                          aria-label="Удалить бонус"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
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

      {(editBonus || deleteBonusId) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/40 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
            <h2 className="font-serif text-2xl font-bold text-safi-green">
              {editBonus ? 'Изменить бонус' : 'Удалить бонус'}
            </h2>
            <p className="mt-2 text-sm leading-6 text-safi-text/70">
              {editBonus
                ? 'Баланс пользователя будет скорректирован на разницу суммы.'
                : 'Бонус будет отменён, баланс пользователя будет пересчитан обратной операцией.'}
            </p>

            {editBonus && (
              <label className="mt-5 block">
                <span className="text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">Новая сумма</span>
                <input
                  type="number"
                  min="1"
                  step="0.01"
                  value={manualAmount}
                  onChange={(event) => setManualAmount(event.target.value)}
                  className="mt-2 w-full rounded-xl bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
                />
              </label>
            )}

            <label className="mt-5 block">
              <span className="text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">Причина</span>
              <textarea
                value={manualReason}
                onChange={(event) => setManualReason(event.target.value)}
                rows={4}
                className="mt-2 w-full resize-none rounded-xl bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
                placeholder="Укажите причину для audit log"
              />
            </label>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={closeManualModal}
                disabled={isSavingManualAction}
                className="rounded-xl border border-safi-border px-5 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green disabled:opacity-60"
              >
                Отмена
              </button>
              <button
                type="button"
                onClick={submitManualAction}
                disabled={isSavingManualAction}
                className={`rounded-xl px-5 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-white disabled:opacity-60 ${deleteBonusId ? 'bg-red-600' : 'bg-safi-green'}`}
              >
                {isSavingManualAction ? 'Сохранение...' : editBonus ? 'Сохранить' : 'Удалить'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
