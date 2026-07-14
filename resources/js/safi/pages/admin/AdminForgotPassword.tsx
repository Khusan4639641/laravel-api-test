import { FormEvent, ReactNode, useCallback, useEffect, useState } from 'react';
import { KeyRound, Mail, Phone, RefreshCw, Search, Shuffle, User, X } from 'lucide-react';
import { AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { MobileCardActions, MobileDataCard, MobileDataHeader, MobileDataList, MobileDataRow } from '../../components/ui/MobileData';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import {
  ApiError,
  getAdminForgotPasswordRequests,
  getApiErrorState,
  getArray,
  getNumber,
  getString,
  resetAdminForgotPasswordRequest,
} from '../../lib/api';

type FieldErrors = Record<string, string[]>;

interface ForgotPasswordRequestItem {
  id: string;
  requestedAt: string;
  userId: string;
  userName: string;
  userLogin: string;
  email: string;
  phone: string;
  status: string;
  statusLabel: string;
}

interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

const inputClass = 'w-full rounded-xl border border-safi-green/10 bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none transition-colors focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60';

export default function AdminForgotPassword() {
  const [requests, setRequests] = useState<ForgotPasswordRequestItem[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>({ currentPage: 1, lastPage: 1, perPage: 15, total: 0 });
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<ForgotPasswordRequestItem | null>(null);
  const [passwordForm, setPasswordForm] = useState({ password: '', password_confirmation: '' });
  const [passwordErrors, setPasswordErrors] = useState<FieldErrors>({});
  const [actionLoading, setActionLoading] = useState(false);
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const loadRequests = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminForgotPasswordRequests({
        status: 'pending',
        search: appliedSearch,
        page,
        per_page: meta.perPage,
      });

      setRequests(normalizeRequests(response));
      setMeta(normalizeMeta(response));
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить обращения.');
    } finally {
      setIsLoading(false);
    }
  }, [appliedSearch, page, meta.perPage]);

  useEffect(() => {
    void loadRequests();
  }, [loadRequests]);

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setAppliedSearch(search.trim());
  };

  const openResetModal = (item: ForgotPasswordRequestItem) => {
    setSelectedRequest(item);
    setPasswordForm({ password: '', password_confirmation: '' });
    setPasswordErrors({});
  };

  const closeResetModal = () => {
    if (actionLoading) {
      return;
    }

    setSelectedRequest(null);
    setPasswordForm({ password: '', password_confirmation: '' });
    setPasswordErrors({});
  };

  const submitResetPassword = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedRequest) {
      return;
    }

    setActionLoading(true);
    setPasswordErrors({});

    try {
      await resetAdminForgotPasswordRequest(selectedRequest.id, passwordForm);
      showToast('Новый пароль установлен.');
      setSelectedRequest(null);
      setPasswordForm({ password: '', password_confirmation: '' });
      setPasswordErrors({});
      await loadRequests();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setPasswordErrors(caughtError.errors || {});
        showToast(caughtError.message, 'error');
      } else {
        showToast('Не удалось установить новый пароль.', 'error');
      }
    } finally {
      setActionLoading(false);
    }
  };

  const generateAndSetPassword = () => {
    const password = generatePassword();
    setPasswordForm({ password, password_confirmation: password });
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="mb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-safi-gold">Админка</div>
          <h1 className="font-serif text-4xl font-bold text-safi-green">Забыли пароль</h1>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-safi-muted">
            Pending-заявки от пользователей. Администратор связывается с пользователем и вручную устанавливает новый пароль.
          </p>
        </div>

        <button
          type="button"
          onClick={loadRequests}
          disabled={isLoading}
          className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-border bg-white px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
        >
          <RefreshCw className="h-4 w-4" />
          Обновить
        </button>
      </div>

      <div className="rounded-[28px] border border-safi-green/5 bg-white p-5 shadow-sm">
        <form className="flex flex-col gap-3 md:flex-row" onSubmit={submitSearch}>
          <label className="relative flex-1">
            <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-xl border border-safi-green/10 bg-[#F5F5F0] py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none transition-colors placeholder:text-safi-muted/60 focus:border-safi-green"
              placeholder="Поиск по email, телефону, имени, login или ID"
            />
          </label>
          <button
            type="submit"
            className="cursor-pointer rounded-xl bg-safi-green px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white"
          >
            Найти
          </button>
        </form>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadRequests} />}
      {!isLoading && !error && requests.length === 0 && (
        <EmptyState title="Нет pending-заявок" description="Новые обращения на восстановление пароля появятся здесь." />
      )}

      {!isLoading && !error && requests.length > 0 && (
        <div className="overflow-hidden rounded-[28px] border border-safi-green/5 bg-white shadow-sm">
          <MobileDataList className="p-4">
            {requests.map((item) => (
              <MobileDataCard key={item.id}>
                <MobileDataHeader
                  title={`#${item.id}`}
                  meta={formatDateTime(item.requestedAt)}
                  action={<AdminBadge variant="warning">{item.statusLabel}</AdminBadge>}
                />
                <MobileDataRow label="Пользователь">
                  <div>{item.userName}</div>
                  <div className="mt-1 text-xs text-safi-muted">ID {item.userId}{item.userLogin ? ` · ${item.userLogin}` : ''}</div>
                </MobileDataRow>
                <MobileDataRow label="Email">{item.email}</MobileDataRow>
                <MobileDataRow label="Телефон">{item.phone}</MobileDataRow>
                <MobileCardActions>
                  <button
                    type="button"
                    onClick={() => openResetModal(item)}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-safi-green px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold"
                  >
                    <KeyRound className="h-4 w-4" />
                    Установить пароль
                  </button>
                </MobileCardActions>
              </MobileDataCard>
            ))}
          </MobileDataList>

          <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[980px] text-left text-sm">
              <thead className="bg-[#F5F5F0] text-[10px] uppercase tracking-widest text-safi-text/50">
                <tr>
                  <th className="px-5 py-4">ID</th>
                  <th className="px-5 py-4">Дата обращения</th>
                  <th className="px-5 py-4">Пользователь</th>
                  <th className="px-5 py-4">Email</th>
                  <th className="px-5 py-4">Телефон</th>
                  <th className="px-5 py-4">Статус</th>
                  <th className="px-5 py-4 text-right">Действия</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-safi-green/5">
                {requests.map((item) => (
                  <tr key={item.id} className="transition-colors hover:bg-safi-green/5">
                    <td className="px-5 py-4 font-mono font-bold text-safi-green">#{item.id}</td>
                    <td className="px-5 py-4 text-xs text-safi-muted">{formatDateTime(item.requestedAt)}</td>
                    <td className="px-5 py-4">
                      <div className="font-bold text-safi-green">{item.userName}</div>
                      <div className="mt-1 text-xs text-safi-muted">ID {item.userId}{item.userLogin ? ` · ${item.userLogin}` : ''}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2 font-bold text-safi-text">
                        <Mail className="h-4 w-4 text-safi-gold" />
                        {item.email}
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2 font-bold text-safi-text">
                        <Phone className="h-4 w-4 text-safi-gold" />
                        {item.phone}
                      </div>
                    </td>
                    <td className="px-5 py-4"><AdminBadge variant="warning">{item.statusLabel}</AdminBadge></td>
                    <td className="px-5 py-4 text-right">
                      <button
                        type="button"
                        onClick={() => openResetModal(item)}
                        className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-safi-green px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white"
                      >
                        <KeyRound className="h-4 w-4" />
                        Установить пароль
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-safi-green/5 px-4 py-4 text-sm text-safi-muted md:flex-row md:items-center md:justify-between md:px-5">
            <div>Всего: {meta.total}</div>
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={meta.currentPage <= 1}
                className="cursor-pointer rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green disabled:cursor-not-allowed disabled:opacity-50"
              >
                Назад
              </button>
              <span className="font-bold text-safi-green">{meta.currentPage} / {meta.lastPage}</span>
              <button
                type="button"
                onClick={() => setPage((current) => Math.min(meta.lastPage, current + 1))}
                disabled={meta.currentPage >= meta.lastPage}
                className="cursor-pointer rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green disabled:cursor-not-allowed disabled:opacity-50"
              >
                Вперёд
              </button>
            </div>
          </div>
        </div>
      )}

      {selectedRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-3 py-3 backdrop-blur-sm sm:px-4 sm:py-6">
          <div className="safi-responsive-modal w-full max-w-xl overflow-y-auto rounded-[28px] border border-safi-border bg-white p-5 shadow-[0_24px_70px_rgba(11,23,18,0.2)] sm:p-6">
            <div className="mb-6 flex items-center justify-between gap-4">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Установить новый пароль</h2>
              <button
                type="button"
                onClick={closeResetModal}
                className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                aria-label="Закрыть"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="mb-5 rounded-2xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm leading-6 text-safi-green">
              <div className="flex items-center gap-2 font-bold">
                <User className="h-4 w-4 text-safi-gold" />
                {selectedRequest.userName} · ID {selectedRequest.userId}
              </div>
              <div className="mt-2 text-safi-muted">{selectedRequest.email} · {selectedRequest.phone}</div>
            </div>

            <form className="space-y-5" onSubmit={submitResetPassword}>
              <FormField label="Новый пароль" error={passwordErrors.password?.[0]}>
                <input
                  type="password"
                  value={passwordForm.password}
                  onChange={(event) => setPasswordForm((current) => ({ ...current, password: event.target.value }))}
                  className={inputClass}
                  autoComplete="new-password"
                  required
                />
              </FormField>
              <FormField label="Повторите пароль" error={passwordErrors.password_confirmation?.[0]}>
                <input
                  type="password"
                  value={passwordForm.password_confirmation}
                  onChange={(event) => setPasswordForm((current) => ({ ...current, password_confirmation: event.target.value }))}
                  className={inputClass}
                  autoComplete="new-password"
                  required
                />
              </FormField>

              {passwordErrors.request?.[0] && (
                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                  {passwordErrors.request[0]}
                </div>
              )}

              <div className="flex flex-col gap-3 sm:flex-row">
                <button
                  type="button"
                  onClick={generateAndSetPassword}
                  disabled={actionLoading}
                  className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Shuffle className="h-4 w-4" />
                  Сгенерировать
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {actionLoading ? 'Сохраняем...' : 'Установить новый пароль'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

function FormField({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-bold text-red-600">{error}</span>}
    </label>
  );
}

function normalizeRequests(response: unknown): ForgotPasswordRequestItem[] {
  return getArray(response, ['data']).map((item) => normalizeRequest(isRecord(item) ? item : {}));
}

function normalizeRequest(record: Record<string, unknown>): ForgotPasswordRequestItem {
  const user = isRecord(record.user) ? record.user : {};

  return {
    id: getString(record, ['id']) || '-',
    requestedAt: getString(record, ['requested_at', 'created_at']) || '',
    userId: getString(record, ['user_id']) || getString(user, ['id']) || '-',
    userName: getString(user, ['name']) || getString(user, ['login']) || '-',
    userLogin: getString(user, ['login']) || '',
    email: getString(record, ['email']) || getString(user, ['email']) || '-',
    phone: getString(record, ['phone']) || getString(user, ['phone']) || '-',
    status: getString(record, ['status']) || 'pending',
    statusLabel: getString(record, ['status_label', 'statusLabel']) || 'Ожидает',
  };
}

function normalizeMeta(response: unknown): PaginationMeta {
  const record = isRecord(response) ? response : {};
  const meta = isRecord(record.meta) ? record.meta : {};

  return {
    currentPage: getNumber(meta, ['current_page', 'currentPage']) ?? 1,
    lastPage: getNumber(meta, ['last_page', 'lastPage']) ?? 1,
    perPage: getNumber(meta, ['per_page', 'perPage']) ?? 15,
    total: getNumber(meta, ['total']) ?? 0,
  };
}

function formatDateTime(value: string) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function generatePassword() {
  return `Safi${Math.random().toString(36).slice(2, 8)}${Math.floor(10 + Math.random() * 90)}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
