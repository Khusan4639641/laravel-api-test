import { ChangeEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Copy, Plus, Shuffle, Trash2 } from 'lucide-react';
import { EmptyState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { bulkCreateAdminPartners, getAdminUsers, getApiErrorState, getArray, getString } from '../../lib/api';

interface BulkRow {
  localId: number;
  name: string;
  login: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
  sponsor_id: string;
  branch: string;
  role: string;
}

interface SponsorOption {
  id: string;
  label: string;
}

interface BulkResult {
  row: number;
  login: string;
  email: string;
  password: string;
  status: 'created' | 'error';
  error?: string;
}

const emptyRow = (localId: number): BulkRow => ({
  localId,
  name: '',
  login: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  sponsor_id: '',
  branch: '',
  role: 'user',
});

const inputClass = 'w-full min-w-[160px] rounded-xl border border-safi-border bg-white px-3 py-2 text-sm font-bold text-safi-green outline-none transition-colors focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60';

export default function AdminPartnersBulkCreate() {
  const { currentUser } = useAdminContext();
  const [rows, setRows] = useState<BulkRow[]>([emptyRow(1), emptyRow(2), emptyRow(3)]);
  const [sponsors, setSponsors] = useState<SponsorOption[]>([]);
  const [results, setResults] = useState<BulkResult[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const canCreate = currentUser.role === 'super_admin';

  const createdResults = useMemo(() => results.filter((result) => result.status === 'created'), [results]);

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  useEffect(() => {
    const loadSponsors = async () => {
      try {
        const response = await getAdminUsers();
        setSponsors(getArray(response, ['users']).map((item, index) => {
          const user = item && typeof item === 'object' ? item as Record<string, unknown> : {};
          const id = getString(user, ['id']) || String(index + 1);
          const name = getString(user, ['name']) || getString(user, ['login']) || `Partner ${index + 1}`;
          const login = getString(user, ['login']);

          return {
            id,
            label: `${name}${login ? ` (${login})` : ''}`,
          };
        }));
      } catch {
        setSponsors([]);
      }
    };

    void loadSponsors();
  }, []);

  const updateRow = (localId: number, field: keyof BulkRow, value: string) => {
    setRows((current) => current.map((row) => row.localId === localId ? { ...row, [field]: value } : row));
  };

  const addRow = () => {
    setRows((current) => [...current, emptyRow(Math.max(0, ...current.map((row) => row.localId)) + 1)]);
  };

  const removeRow = (localId: number) => {
    setRows((current) => current.length === 1 ? current : current.filter((row) => row.localId !== localId));
  };

  const generateRowPassword = (localId: number) => {
    const password = generatePassword();
    setRows((current) => current.map((row) => row.localId === localId ? { ...row, password, password_confirmation: password } : row));
  };

  const generateAllPasswords = () => {
    setRows((current) => current.map((row) => {
      const password = generatePassword();

      return { ...row, password, password_confirmation: password };
    }));
    showToast('Пароли сгенерированы для всех строк', 'info');
  };

  const submitRows = async () => {
    setIsSubmitting(true);
    setResults([]);

    try {
      const response = await bulkCreateAdminPartners({
        partners: rows.map(({ localId, ...row }) => row),
      });
      const normalizedResults = normalizeResults(response, rows);

      setResults(normalizedResults);
      showToast('Массовое добавление завершено');
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось выполнить массовое добавление.', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  const copyAllCredentials = async () => {
    if (createdResults.length === 0) {
      return;
    }

    try {
      await navigator.clipboard.writeText(createdResults.map((result, index) => [
        `${index + 1})`,
        `Логин: ${result.login}`,
        `Email: ${result.email}`,
        `Пароль: ${result.password}`,
        'Ссылка для входа: https://safilife.kz/login',
      ].join('\n')).join('\n\n'));
      showToast('Все доступы скопированы');
    } catch {
      showToast('Не удалось скопировать доступы автоматически.', 'error');
    }
  };

  if (!canCreate) {
    return (
      <div className="space-y-8">
        <Link to="/admin/partners" className="inline-flex cursor-pointer items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold hover:underline">
          <ArrowLeft className="h-4 w-4" /> Назад
        </Link>
        <EmptyState title="Недостаточно прав" description="Массовое добавление партнёров доступно только super admin." />
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <Link to="/admin/partners" className="mb-4 inline-flex cursor-pointer items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold hover:underline">
              <ArrowLeft className="h-4 w-4" /> Назад
            </Link>
            <span className="safi-kicker">Bulk partners</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Массово добавить</h1>
          </div>
          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={addRow}
              disabled={rows.length >= 100}
              className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Plus className="h-4 w-4" /> Добавить строку
            </button>
            <button
              type="button"
              onClick={generateAllPasswords}
              className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green/10"
            >
              <Shuffle className="h-4 w-4" /> Пароли для всех
            </button>
            <button
              type="button"
              onClick={submitRows}
              disabled={isSubmitting || rows.length === 0}
              className="inline-flex cursor-pointer items-center justify-center rounded-full border border-safi-green bg-safi-green px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSubmitting ? 'Создание...' : 'Создать всех'}
            </button>
          </div>
        </div>
      </section>

      <section className="rounded-[28px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="max-h-[64vh] overflow-auto rounded-[28px]">
          <table className="min-w-[1380px] w-full text-left text-sm">
            <thead className="sticky top-0 z-10 bg-safi-green text-[10px] uppercase tracking-widest text-white">
              <tr>
                {['№', 'Имя', 'Логин', 'Email', 'Телефон', 'Пароль', 'Спонсор', 'Ветка', 'Роль', 'Действия'].map((header) => (
                  <th key={header} className="px-4 py-3 font-extrabold">{header}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-safi-border">
              {rows.map((row, index) => (
                <tr key={row.localId} className="bg-white align-top transition-colors hover:bg-safi-cream/50">
                  <td className="px-4 py-3 font-mono text-xs text-safi-muted">{index + 1}</td>
                  <td className="px-4 py-3"><BulkInput value={row.name} onChange={(event) => updateRow(row.localId, 'name', event.target.value)} /></td>
                  <td className="px-4 py-3"><BulkInput value={row.login} onChange={(event) => updateRow(row.localId, 'login', event.target.value)} /></td>
                  <td className="px-4 py-3"><BulkInput type="email" value={row.email} onChange={(event) => updateRow(row.localId, 'email', event.target.value)} /></td>
                  <td className="px-4 py-3"><BulkInput value={row.phone} onChange={(event) => updateRow(row.localId, 'phone', event.target.value)} /></td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <BulkInput value={row.password} onChange={(event) => {
                        updateRow(row.localId, 'password', event.target.value);
                        updateRow(row.localId, 'password_confirmation', event.target.value);
                      }} />
                      <button
                        type="button"
                        onClick={() => generateRowPassword(row.localId)}
                        className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-safi-border bg-safi-cream text-safi-green transition-colors hover:bg-safi-green/10"
                        title="Сгенерировать пароль"
                      >
                        <Shuffle className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <select value={row.sponsor_id} onChange={(event) => updateRow(row.localId, 'sponsor_id', event.target.value)} className={inputClass}>
                      <option value="">Без спонсора</option>
                      {sponsors.map((sponsor) => <option key={sponsor.id} value={sponsor.id}>{sponsor.label}</option>)}
                    </select>
                  </td>
                  <td className="px-4 py-3">
                    <select value={row.branch} onChange={(event) => updateRow(row.localId, 'branch', event.target.value)} className={inputClass} disabled={!row.sponsor_id}>
                      <option value="">Авто</option>
                      <option value="left">left</option>
                      <option value="right">right</option>
                    </select>
                  </td>
                  <td className="px-4 py-3">
                    <select value={row.role} onChange={(event) => updateRow(row.localId, 'role', event.target.value)} className={inputClass}>
                      <option value="user">user</option>
                      <option value="support">support</option>
                      <option value="accountant">accountant</option>
                      <option value="admin">admin</option>
                    </select>
                  </td>
                  <td className="px-4 py-3">
                    <button
                      type="button"
                      onClick={() => removeRow(row.localId)}
                      disabled={rows.length === 1}
                      className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-red-100 bg-red-50 text-red-600 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                      title="Удалить строку"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {results.length > 0 && (
        <section className="rounded-[28px] border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h2 className="font-serif text-2xl font-semibold text-safi-green">Результаты</h2>
            <button
              type="button"
              onClick={copyAllCredentials}
              disabled={createdResults.length === 0}
              className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-white px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Copy className="h-4 w-4" /> Скопировать все доступы
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-[760px] w-full text-left text-sm">
              <thead className="text-[10px] uppercase tracking-widest text-safi-muted">
                <tr>
                  {['Логин', 'Email', 'Пароль', 'Статус', 'Ошибка'].map((header) => <th key={header} className="pb-3">{header}</th>)}
                </tr>
              </thead>
              <tbody className="divide-y divide-safi-border">
                {results.map((result) => (
                  <tr key={`${result.row}-${result.login}`} className="align-top">
                    <td className="py-3 pr-4 font-mono font-bold text-safi-green">{result.login}</td>
                    <td className="py-3 pr-4">{result.email}</td>
                    <td className="py-3 pr-4 font-mono">{result.password || '-'}</td>
                    <td className={`py-3 pr-4 font-bold ${result.status === 'created' ? 'text-green-600' : 'text-red-600'}`}>{result.status}</td>
                    <td className="py-3 text-xs text-red-600">{result.error || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}

function BulkInput({ value, onChange, type = 'text' }: { value: string; onChange: (event: ChangeEvent<HTMLInputElement>) => void; type?: string }) {
  return <input type={type} value={value} onChange={onChange} className={inputClass} />;
}

function normalizeResults(response: unknown, rows: BulkRow[]): BulkResult[] {
  const created = getArray(response, ['created']).map((item) => {
    const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};
    const credentials = record.credentials && typeof record.credentials === 'object' ? record.credentials as Record<string, unknown> : {};
    const rowIndex = Number(record.row ?? 0);

    return {
      row: rowIndex,
      login: getString(credentials, ['login']) || rows[rowIndex]?.login || '-',
      email: getString(credentials, ['email']) || rows[rowIndex]?.email || '-',
      password: getString(credentials, ['password']) || '',
      status: 'created' as const,
    };
  });

  const failed = getArray(response, ['failed']).map((item) => {
    const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};
    const rowIndex = Number(record.row ?? 0);

    return {
      row: rowIndex,
      login: rows[rowIndex]?.login || '-',
      email: rows[rowIndex]?.email || '-',
      password: '',
      status: 'error' as const,
      error: flattenErrors(record.errors),
    };
  });

  return [...created, ...failed].sort((left, right) => left.row - right.row);
}

function flattenErrors(errors: unknown) {
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
    return 'Ошибка строки';
  }

  return Object.values(errors as Record<string, unknown>)
    .flatMap((value) => Array.isArray(value) ? value : [value])
    .filter((value): value is string => typeof value === 'string')
    .join(' ');
}

function generatePassword() {
  return `Safi${Math.random().toString(36).slice(2, 8)}${Math.floor(10 + Math.random() * 90)}`;
}
