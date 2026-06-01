import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import {
  ArrowLeft,
  Calendar,
  Copy,
  CreditCard,
  Edit,
  KeyRound,
  Lock,
  Mail,
  MapPin,
  MessageSquare,
  Network,
  Phone,
  Shuffle,
  Unlock,
  User,
  X,
} from 'lucide-react';
import {
  ApiError,
  blockAdminPartner,
  changeAdminPartnerPackage,
  changeAdminPartnerPassword,
  changeAdminPartnerStatus,
  getAdminPackages,
  getAdminPartner,
  getAdminPartnerTransactions,
  getApiErrorState,
  getArray,
  getNumber,
  getString,
  Package,
  saveAdminPartnerNote,
  unblockAdminPartner,
  unwrapRecord,
} from '../../lib/api';

interface PartnerDetail {
  id: string;
  login: string;
  fullName: string;
  phone: string;
  email: string;
  city: string;
  sponsorId: string;
  sponsor: string;
  invitedCount: number;
  packageId: string;
  package: string;
  status: string;
  personalPV: number;
  teamPV: number;
  leftPV: number;
  rightPV: number;
  totalIncome: number;
  availableBalance: number;
  registrationDate: string;
  accountStatus: string;
  accountStatusLabel: string;
  adminNote: string;
}

interface PartnerTransaction {
  id: string;
  date: string;
  type: string;
  amount: string;
  status: string;
  comment: string;
}

interface Credentials {
  login: string;
  email: string;
  password: string;
  login_url: string;
}

const partnerDefaults: PartnerDetail = {
  id: '',
  login: '',
  fullName: '-',
  phone: '-',
  email: '-',
  city: '-',
  sponsorId: '',
  sponsor: '-',
  invitedCount: 0,
  packageId: '',
  package: '-',
  status: '-',
  personalPV: 0,
  teamPV: 0,
  leftPV: 0,
  rightPV: 0,
  totalIncome: 0,
  availableBalance: 0,
  registrationDate: '-',
  accountStatus: 'active',
  accountStatusLabel: 'Активен',
  adminNote: '',
};

const statusOptions = [
  'user',
  'manager',
  'leader',
  'director',
  'bronze_director',
  'silver_director',
  'gold_director',
  'platinum_director',
  'emerald_director',
  'diamond_director',
];

const inputClass = 'w-full rounded-xl border border-safi-green/10 bg-[#F5F5F0] px-4 py-3 text-sm font-bold text-safi-green outline-none transition-colors focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60';

export default function AdminPartnerDetail() {
  const { id } = useParams();
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [partner, setPartner] = useState<PartnerDetail>({ ...partnerDefaults, id: id || '' });
  const [transactions, setTransactions] = useState<PartnerTransaction[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [note, setNote] = useState('');
  const [actionLoading, setActionLoading] = useState('');
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);
  const [packageModalOpen, setPackageModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [passwordForm, setPasswordForm] = useState({ password: '', password_confirmation: '' });
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
  const [credentials, setCredentials] = useState<Credentials | null>(null);
  const [selectedPackageId, setSelectedPackageId] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('');
  const isBlocked = ['blocked', 'inactive'].includes(partner.accountStatus);

  const weakBranch = useMemo(() => (partner.leftPV < partner.rightPV ? 'Левая' : 'Правая'), [partner.leftPV, partner.rightPV]);

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const loadPartner = async () => {
    if (!id) {
      setIsLoading(false);
      setError('Не указан ID партнера.');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const [partnerResponse, transactionsResponse, packagesResponse] = await Promise.all([
        getAdminPartner(id),
        getAdminPartnerTransactions(id, 10),
        getAdminPackages(),
      ]);
      const normalizedPartner = normalizePartner(partnerResponse, id);

      setPartner(normalizedPartner);
      setNote(normalizedPartner.adminNote);
      setSelectedPackageId(normalizedPartner.packageId);
      setSelectedStatus(normalizedPartner.status);
      setTransactions(normalizeTransactions(transactionsResponse));
      setPackages(packagesResponse);
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить партнера.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadPartner();
  }, [id]);

  const refreshPartnerAfterAction = async () => {
    await loadPartner();
  };

  const toggleBlock = async () => {
    if (!partner.id) {
      return;
    }

    setActionLoading('block');

    try {
      if (isBlocked) {
        await unblockAdminPartner(partner.id);
        showToast('Партнёр разблокирован');
      } else {
        await blockAdminPartner(partner.id);
        showToast('Партнёр заблокирован');
      }

      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось изменить блокировку.', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const submitPassword = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('password');
    setPasswordErrors({});
    setCredentials(null);

    try {
      const response = await changeAdminPartnerPassword(partner.id, passwordForm);
      setCredentials(normalizeCredentials(response, partner));
      setPasswordForm({ password: '', password_confirmation: '' });
      showToast('Пароль изменён');
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setPasswordErrors(caughtError.errors || {});
        showToast(caughtError.message, 'error');
      } else {
        showToast('Не удалось изменить пароль.', 'error');
      }
    } finally {
      setActionLoading('');
    }
  };

  const submitPackage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('package');

    try {
      await changeAdminPartnerPackage(partner.id, selectedPackageId);
      setPackageModalOpen(false);
      showToast('Пакет изменён');
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось изменить пакет.', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const submitStatus = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('status');

    try {
      await changeAdminPartnerStatus(partner.id, selectedStatus);
      setStatusModalOpen(false);
      showToast('Статус изменён');
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось изменить статус.', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const saveNote = async () => {
    setActionLoading('note');

    try {
      await saveAdminPartnerNote(partner.id, note);
      showToast('Заметка сохранена');
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось сохранить заметку.', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const copyCredentials = async () => {
    if (!credentials) {
      return;
    }

    try {
      await navigator.clipboard.writeText(formatCredentials(credentials));
      showToast('Доступы скопированы');
    } catch {
      showToast('Не удалось скопировать доступы автоматически.', 'error');
    }
  };

  const closePasswordModal = () => {
    setPasswordModalOpen(false);
    setPasswordForm({ password: '', password_confirmation: '' });
    setPasswordErrors({});
    setCredentials(null);
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div className="flex items-center gap-4">
          <Link to="/admin/partners" className="cursor-pointer p-3 bg-white rounded-xl border border-safi-green/5 shadow-sm text-safi-text/60 hover:text-safi-green hover:bg-[#F5F5F0] transition-colors" title="Назад к партнёрам">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{partner.fullName}</h1>
            <div className="flex items-center gap-3">
              <span className="text-sm font-mono text-safi-text/70 bg-[#F5F5F0] px-2 py-0.5 rounded">{partner.login || partner.id}</span>
              <AdminBadge variant={isBlocked ? 'danger' : 'success'}>{partner.accountStatusLabel}</AdminBadge>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setPasswordModalOpen(true)}
            disabled={!partner.id || isLoading}
            className="flex cursor-pointer items-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <KeyRound className="w-4 h-4" /> Изменить пароль
          </button>
          <button
            type="button"
            onClick={toggleBlock}
            disabled={!partner.id || actionLoading === 'block'}
            className={`flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${isBlocked ? 'bg-green-500/10 text-green-600 hover:bg-green-500/20' : 'bg-red-500/10 text-red-600 hover:bg-red-500/20'}`}
          >
            {isBlocked ? <Unlock className="w-4 h-4" /> : <Lock className="w-4 h-4" />}
            {actionLoading === 'block' ? 'Сохранение...' : isBlocked ? 'Разблокировать' : 'Заблокировать'}
          </button>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadPartner} />}
      {!isLoading && !error && !partner.id && (
        <EmptyState title="Партнер не найден" description="Проверьте ID партнера и повторите запрос." />
      )}

      {!isLoading && !error && partner.id && (
        <div className="grid lg:grid-cols-3 gap-8">
          <div className="space-y-8">
            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-6">
                <User className="w-5 h-5 text-safi-gold" /> Основная информация
              </h3>

              <div className="space-y-4">
                <InfoRow icon={Phone} label="Телефон" value={partner.phone} />
                <InfoRow icon={Mail} label="Email" value={partner.email} />
                <InfoRow icon={MapPin} label="Город" value={partner.city} />
                <InfoRow icon={Calendar} label="Регистрация" value={partner.registrationDate} />
                <div className="flex items-center justify-between p-3 bg-[#F5F5F0] rounded-xl text-sm mt-4">
                  <span className="text-safi-text/60">Спонсор:</span>
                  {partner.sponsorId ? (
                    <Link to={`/admin/partners/${partner.sponsorId}`} className="cursor-pointer font-bold font-mono text-safi-green hover:underline">{partner.sponsor}</Link>
                  ) : (
                    <span className="font-bold font-mono text-safi-green">{partner.sponsor}</span>
                  )}
                </div>
              </div>

              <div className="mt-6 pt-6 border-t border-safi-green/5 grid grid-cols-2 gap-4">
                <div>
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Текущий пакет</div>
                  <AdminBadge variant="gold">{partner.package}</AdminBadge>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedPackageId(partner.packageId);
                      setPackageModalOpen(true);
                    }}
                    className="mt-2 flex cursor-pointer items-center gap-1 text-[10px] text-safi-gold hover:underline"
                  >
                    <Edit className="w-3 h-3" /> Изменить
                  </button>
                </div>
                <div>
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Статус</div>
                  <AdminBadge variant="default">{partner.status}</AdminBadge>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedStatus(partner.status);
                      setStatusModalOpen(true);
                    }}
                    className="mt-2 flex cursor-pointer items-center gap-1 text-[10px] text-safi-gold hover:underline"
                  >
                    <Edit className="w-3 h-3" /> Изменить
                  </button>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-gold/30">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-4">
                <MessageSquare className="w-5 h-5 text-safi-gold" /> Заметка администратора
              </h3>
              <textarea
                value={note}
                onChange={(event) => setNote(event.target.value)}
                className="w-full h-32 bg-[#F5F5F0] rounded-xl p-4 text-sm font-medium border-none focus:ring-2 focus:ring-safi-gold/50 outline-none resize-none"
                placeholder="Оставьте внутренний комментарий о партнёре (виден только администраторам)..."
              />
              <button
                type="button"
                onClick={saveNote}
                disabled={actionLoading === 'note'}
                className="mt-3 w-full cursor-pointer rounded-xl bg-safi-green py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'note' ? 'Сохранение...' : 'Сохранить заметку'}
              </button>
            </div>
          </div>

          <div className="lg:col-span-2 space-y-8">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <MiniStat title="Доступно" value={`${partner.availableBalance.toLocaleString('ru-RU')} ₸`} />
              <MiniStat title="Всего заработал" value={`${partner.totalIncome.toLocaleString('ru-RU')} ₸`} />
              <MiniStat title="Личный PV" value={partner.personalPV} />
              <MiniStat title="Командный PV" value={partner.teamPV} />
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                  <Network className="w-5 h-5 text-safi-gold" /> Обзор структуры
                </h3>
                <Link to={`/admin/structure?user_id=${encodeURIComponent(partner.id)}`} className="cursor-pointer text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">
                  Открыть полное дерево →
                </Link>
              </div>

              <div className="flex flex-col md:flex-row gap-6 items-center">
                <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Левая ветка</div>
                  <div className="text-2xl font-bold text-safi-green">{partner.leftPV.toLocaleString('ru-RU')} PV</div>
                </div>

                <div className="w-12 h-12 rounded-full border border-safi-green/10 flex items-center justify-center shrink-0">VS</div>

                <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Правая ветка</div>
                  <div className="text-2xl font-bold text-safi-green">{partner.rightPV.toLocaleString('ru-RU')} PV</div>
                </div>
              </div>

              <div className="mt-6 flex justify-between items-center px-4">
                <div className="text-sm"><span className="text-safi-text/60">Лично пригласил:</span> <span className="font-bold">{partner.invitedCount}</span></div>
                <div className="text-sm"><span className="text-safi-text/60">Слабая ветка:</span> <span className="font-bold text-safi-gold">{weakBranch}</span></div>
              </div>
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                  <CreditCard className="w-5 h-5 text-safi-gold" /> Последние транзакции
                </h3>
                <Link to={`/admin/transactions?user_id=${encodeURIComponent(partner.id)}`} className="cursor-pointer text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">
                  Смотреть все →
                </Link>
              </div>

              {transactions.length === 0 ? (
                <EmptyState
                  title="Транзакций пока нет"
                  description="Операции партнёра появятся после начислений, заказов или выплат."
                  className="min-h-[180px] shadow-none"
                />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[680px] text-left text-sm">
                    <thead className="text-[10px] uppercase tracking-widest text-safi-text/50">
                      <tr>
                        <th className="pb-3">Дата</th>
                        <th className="pb-3">Тип</th>
                        <th className="pb-3">Сумма</th>
                        <th className="pb-3">Статус</th>
                        <th className="pb-3">Комментарий</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-safi-green/5">
                      {transactions.map((transaction) => (
                        <tr key={transaction.id} className="transition-colors hover:bg-safi-green/5">
                          <td className="py-3 pr-4 text-xs text-safi-text/60">{transaction.date}</td>
                          <td className="py-3 pr-4 font-bold text-safi-green">{transaction.type}</td>
                          <td className="py-3 pr-4 font-bold text-safi-green">{transaction.amount}</td>
                          <td className="py-3 pr-4"><AdminBadge variant="success">{transaction.status}</AdminBadge></td>
                          <td className="py-3 text-xs text-safi-text/70">{transaction.comment}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {passwordModalOpen && (
        <Modal title="Изменить пароль" onClose={closePasswordModal}>
          <form className="space-y-5" onSubmit={submitPassword}>
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
            <div className="flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => {
                  const generatedPassword = generatePassword();
                  setPasswordForm({ password: generatedPassword, password_confirmation: generatedPassword });
                  setCredentials(null);
                }}
                className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10"
              >
                <Shuffle className="h-4 w-4" /> Сгенерировать пароль
              </button>
              <button
                type="submit"
                disabled={actionLoading === 'password'}
                className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'password' ? 'Сохранение...' : 'Сохранить'}
              </button>
            </div>
          </form>

          {credentials && (
            <CredentialsBlock credentials={credentials} onCopy={copyCredentials} />
          )}
        </Modal>
      )}

      {packageModalOpen && (
        <Modal title="Изменить пакет" onClose={() => setPackageModalOpen(false)}>
          <form className="space-y-5" onSubmit={submitPackage}>
            <FormField label="Пакет">
              <select value={selectedPackageId} onChange={(event) => setSelectedPackageId(event.target.value)} className={inputClass} required>
                <option value="">Выберите пакет</option>
                {packages.map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
            </FormField>
            <button
              type="submit"
              disabled={actionLoading === 'package'}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'package' ? 'Сохранение...' : 'Сохранить'}
            </button>
          </form>
        </Modal>
      )}

      {statusModalOpen && (
        <Modal title="Изменить статус" onClose={() => setStatusModalOpen(false)}>
          <form className="space-y-5" onSubmit={submitStatus}>
            <FormField label="Статус">
              <select value={selectedStatus} onChange={(event) => setSelectedStatus(event.target.value)} className={inputClass} required>
                {statusOptions.map((status) => (
                  <option key={status} value={status}>{status}</option>
                ))}
              </select>
            </FormField>
            <button
              type="submit"
              disabled={actionLoading === 'status'}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'status' ? 'Сохранение...' : 'Сохранить'}
            </button>
          </form>
        </Modal>
      )}
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-4 py-6 backdrop-blur-sm">
      <div className="w-full max-w-xl rounded-[28px] border border-safi-border bg-white p-6 shadow-[0_24px_70px_rgba(11,23,18,0.2)]">
        <div className="mb-6 flex items-center justify-between gap-4">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-white"
            aria-label="Закрыть"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        {children}
      </div>
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

function CredentialsBlock({ credentials, onCopy }: { credentials: Credentials; onCopy: () => void }) {
  return (
    <div className="mt-6 rounded-2xl border border-safi-gold/30 bg-[#F5F5F0] p-4">
      <div className="grid gap-3 text-sm">
        <CredentialLine label="Логин" value={credentials.login} />
        <CredentialLine label="Email" value={credentials.email} />
        <CredentialLine label="Новый пароль" value={credentials.password} />
      </div>
      <button
        type="button"
        onClick={onCopy}
        className="mt-4 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-green bg-white px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
      >
        <Copy className="h-4 w-4" /> Скопировать доступы
      </button>
    </div>
  );
}

function CredentialLine({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{label}</div>
      <div className="mt-1 break-all font-mono font-bold text-safi-green">{value}</div>
    </div>
  );
}

function InfoRow({ icon: Icon, label, value }: { icon: any; label: string; value: string }) {
  return (
    <div className="flex items-center gap-4 group">
      <div className="w-10 h-10 rounded-xl bg-[#F5F5F0] flex items-center justify-center text-safi-green/50 group-hover:text-safi-green group-hover:bg-safi-green/10 transition-colors">
        <Icon className="w-4 h-4" />
      </div>
      <div>
        <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/40">{label}</div>
        <div className="text-sm font-bold text-safi-text">{value}</div>
      </div>
    </div>
  );
}

function MiniStat({ title, value }: { title: string; value: string | number }) {
  return (
    <div className="bg-white p-4 rounded-2xl border border-safi-green/5 shadow-sm text-center">
      <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-1">{title}</div>
      <div className="text-lg font-bold text-safi-green">{value}</div>
    </div>
  );
}

function normalizePartner(response: unknown, fallbackId: string): PartnerDetail {
  const user = unwrapRecord(response, ['user']);
  const profile = isRecord(user.profile) ? user.profile : {};
  const sponsor = isRecord(user.sponsor) ? user.sponsor : {};
  const pkg = isRecord(user.current_package) ? user.current_package : isRecord(user.package) ? user.package : {};
  const wallets = Array.isArray(user.wallets) ? user.wallets.filter(isRecord) : [];
  const accountStatus = getString(user, ['account_status']) || 'active';
  const availableBalance = wallets.reduce((sum, wallet) => sum + (getNumber(wallet, ['balance']) ?? 0), 0);

  return {
    id: getString(user, ['id']) || fallbackId,
    login: getString(user, ['login']) || fallbackId,
    fullName: getString(user, ['name']) || '-',
    phone: getString(profile, ['phone']) || '-',
    email: getString(user, ['email']) || '-',
    city: getString(profile, ['city']) || '-',
    sponsorId: getString(user, ['sponsor_id']) || '',
    sponsor: getString(sponsor, ['name', 'login', 'id']) || getString(user, ['sponsor_id']) || '-',
    invitedCount: getNumber(user, ['referrals_count']) ?? 0,
    packageId: getString(user, ['current_package_id']) || '',
    package: getString(pkg, ['name', 'code']) || '-',
    status: getString(user, ['status']) || 'user',
    personalPV: getNumber(user, ['total_pv']) ?? 0,
    teamPV: (getNumber(user, ['left_pv']) ?? 0) + (getNumber(user, ['right_pv']) ?? 0),
    leftPV: getNumber(user, ['left_pv']) ?? 0,
    rightPV: getNumber(user, ['right_pv']) ?? 0,
    totalIncome: 0,
    availableBalance,
    registrationDate: getString(user, ['created_at']) || '-',
    accountStatus,
    accountStatusLabel: ['blocked', 'inactive'].includes(accountStatus) ? 'Заблокирован' : 'Активен',
    adminNote: getString(user, ['admin_note']) || '',
  };
}

function normalizeTransactions(response: unknown): PartnerTransaction[] {
  return getArray(response, ['transactions']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const direction = getString(record, ['direction']) || 'credit';
    const amount = getNumber(record, ['amount']) ?? 0;

    return {
      id: getString(record, ['id']) || String(index + 1),
      date: getString(record, ['created_at']) || '-',
      type: getString(record, ['type']) || '-',
      amount: `${direction === 'credit' ? '+' : '-'}${amount.toLocaleString('ru-RU')} ₸`,
      status: getString(record, ['status']) || '-',
      comment: getString(record, ['description']) || '-',
    };
  });
}

function normalizeCredentials(response: unknown, partner: PartnerDetail): Credentials {
  const record = isRecord(response) ? response : {};
  const credentials = isRecord(record.credentials) ? record.credentials : {};

  return {
    login: getString(credentials, ['login']) || partner.login,
    email: getString(credentials, ['email']) || partner.email,
    password: getString(credentials, ['password']) || '',
    login_url: getString(credentials, ['login_url', 'loginUrl']) || 'https://safilife.kz/login',
  };
}

function formatCredentials(credentials: Credentials) {
  return [
    `Логин: ${credentials.login}`,
    `Email: ${credentials.email}`,
    `Пароль: ${credentials.password}`,
    `Ссылка для входа: ${credentials.login_url}`,
  ].join('\n');
}

function generatePassword() {
  return `Safi${Math.random().toString(36).slice(2, 8)}${Math.floor(10 + Math.random() * 90)}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
