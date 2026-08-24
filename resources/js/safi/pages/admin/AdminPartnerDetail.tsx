import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { AdminBadge } from '../../components/admin/ui';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { MobileDataCard, MobileDataHeader, MobileDataList, MobileDataRow } from '../../components/ui/MobileData';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';
import { NoTranslate } from '../../components/ui/NoTranslate';
import { adminText } from '../../i18n/adminText';
import {
  ArrowLeft,
  Calendar,
  Calculator,
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
  Trash2,
  Unlock,
  User,
  X,
} from 'lucide-react';
import {
  ApiError,
  AdminPartnerDeletePreview,
  blockAdminPartner,
  changeAdminPartnerBalance,
  changeAdminPartnerPackage,
  changeAdminPartnerPassword,
  changeAdminPartnerStatus,
  deleteAdminPartner,
  getAdminPackages,
  getAdminPartner,
  getAdminPartnerDeletePreview,
  getAdminPartnerTransactions,
  getApiErrorState,
  getArray,
  getNumber,
  getString,
  Package,
  recalculateAdminPartnerBinaryBonus,
  saveAdminPartnerNote,
  unblockAdminPartner,
  updateAdminPartnerIdentity,
  unwrapRecord,
} from '../../lib/api';
import { formatPv } from '../../lib/format';
import { accountStatusLabel, mlmStatusLabel, packageLabel, transactionStatusLabel, transactionTypeLabel } from '../../lib/systemLabels';
import { getPartnerPackageStatus, partnerPackageStatusLabel, type PartnerPackageStatus } from '../../lib/partnerStatus';

interface PartnerDetail {
  id: string;
  login: string;
  fullName: string;
  firstName: string;
  lastName: string;
  phone: string;
  email: string;
  avatarUrl: string;
  city: string;
  sponsorId: string;
  sponsor: string;
  invitedCount: number;
  packageId: string;
  packageCode: string;
  package: string;
  packageStatus: PartnerPackageStatus;
  packageStatusLabel: string;
  statusCode: string;
  status: string;
  personalPV: number;
  teamPV: number;
  leftPV: number;
  rightPV: number;
  weakLegPV: number;
  totalIncome: number;
  availableBalance: number;
  packageActivityPV: number;
  packageActivityAmount: number;
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
  firstName: '',
  lastName: '',
  phone: '-',
  email: '-',
  avatarUrl: '',
  city: '-',
  sponsorId: '',
  sponsor: '-',
  invitedCount: 0,
  packageId: '',
  packageCode: '',
  package: '-',
  packageStatus: 'inactive',
  packageStatusLabel: 'Неактивен',
  statusCode: 'user',
  status: '-',
  personalPV: 0,
  teamPV: 0,
  leftPV: 0,
  rightPV: 0,
  weakLegPV: 0,
  totalIncome: 0,
  availableBalance: 0,
  packageActivityPV: 0,
  packageActivityAmount: 0,
  registrationDate: '-',
  accountStatus: 'active',
  accountStatusLabel: adminText('a_0JDQutGC0LjQ_2'),
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
  const navigate = useNavigate();
  const { currentUser } = useAdminContext();
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [partner, setPartner] = useState<PartnerDetail>({ ...partnerDefaults, id: id || '' });
  const [transactions, setTransactions] = useState<PartnerTransaction[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [note, setNote] = useState('');
  const [actionLoading, setActionLoading] = useState('');
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);
  const [identityModalOpen, setIdentityModalOpen] = useState(false);
  const [balanceModalOpen, setBalanceModalOpen] = useState(false);
  const [packageModalOpen, setPackageModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [binaryRecalculateModalOpen, setBinaryRecalculateModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deletePreview, setDeletePreview] = useState<AdminPartnerDeletePreview | null>(null);
  const [deleteReason, setDeleteReason] = useState('');
  const [deleteUnderstood, setDeleteUnderstood] = useState(false);
  const [deleteSubtree, setDeleteSubtree] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [passwordForm, setPasswordForm] = useState({ password: '', password_confirmation: '' });
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
  const [identityForm, setIdentityForm] = useState({ first_name: '', last_name: '', phone: '', email: '' });
  const [identityErrors, setIdentityErrors] = useState<Record<string, string[]>>({});
  const [balanceForm, setBalanceForm] = useState<{ mode: 'set' | 'adjust'; amount: string; comment: string }>({ mode: 'set', amount: '', comment: '' });
  const [balanceErrors, setBalanceErrors] = useState<Record<string, string[]>>({});
  const [credentials, setCredentials] = useState<Credentials | null>(null);
  const [selectedPackageId, setSelectedPackageId] = useState('');
  const [applyPackageBusinessEffects, setApplyPackageBusinessEffects] = useState(true);
  const [selectedStatus, setSelectedStatus] = useState('');
  const [applyStatusBonusEffects, setApplyStatusBonusEffects] = useState(false);
  const isBlocked = partner.accountStatus === 'blocked';
  const canCalculateBinary = ['admin', 'super_admin'].includes(currentUser.role.toLowerCase());
  const canUpdateBalance = currentUser.role.toLowerCase() === 'super_admin';
  const canDeletePartner = currentUser.role.toLowerCase() === 'super_admin';
  const canUpdateIdentity = currentUser.role.toLowerCase() === 'super_admin';

  const weakBranch = useMemo(() => (partner.leftPV < partner.rightPV ? adminText('a_0JvQtdCy0LDR') : adminText('a_0J_RgNCw0LLQ')), [partner.leftPV, partner.rightPV]);

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const loadPartner = async () => {
    if (!id) {
      setIsLoading(false);
      setError(adminText('a_0J3QtSDRg9C6'));
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
      setApplyPackageBusinessEffects(true);
      setSelectedStatus(normalizedPartner.statusCode);
      setApplyStatusBonusEffects(false);
      setTransactions(normalizeTransactions(transactionsResponse));
      setPackages(packagesResponse);
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_8'));
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
        showToast(adminText('a_0J_QsNGA0YLQ_2'));
      } else {
        await blockAdminPartner(partner.id);
        showToast(adminText('a_0J_QsNGA0YLQ_3'));
      }

      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_9'), 'error');
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
      showToast(adminText('a_0J_QsNGA0L7Q'));
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setPasswordErrors(caughtError.errors || {});
        showToast(caughtError.message, 'error');
      } else {
        showToast(adminText('a_0J3QtSDRg9C0_10'), 'error');
      }
    } finally {
      setActionLoading('');
    }
  };

  const openIdentityModal = () => {
    setIdentityForm({
      first_name: partner.firstName,
      last_name: partner.lastName,
      phone: partner.phone === '-' ? '' : partner.phone,
      email: partner.email === '-' ? '' : partner.email,
    });
    setIdentityErrors({});
    setIdentityModalOpen(true);
  };

  const submitIdentity = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('identity');
    setIdentityErrors({});

    try {
      await updateAdminPartnerIdentity(partner.id, identityForm);
      setIdentityModalOpen(false);
      showToast('Данные пользователя обновлены.');
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setIdentityErrors(caughtError.errors || {});
        showToast(caughtError.message, 'error');
      } else {
        showToast('Не удалось обновить данные пользователя.', 'error');
      }
    } finally {
      setActionLoading('');
    }
  };

  const openBalanceModal = () => {
    setBalanceForm({
      mode: 'set',
      amount: Number.isFinite(partner.availableBalance) ? String(partner.availableBalance) : '',
      comment: '',
    });
    setBalanceErrors({});
    setBalanceModalOpen(true);
  };

  const submitBalance = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('balance');
    setBalanceErrors({});

    try {
      const response = await changeAdminPartnerBalance(partner.id, {
        mode: balanceForm.mode,
        amount: Number(balanceForm.amount),
        comment: balanceForm.comment.trim() || undefined,
      });
      const record = isRecord(response) ? response : {};
      const data = isRecord(record.data) ? record.data : {};
      const nextBalance = getNumber(data, ['new_balance', 'newBalance'])
        ?? getNumber(record, ['new_balance', 'newBalance'])
        ?? partner.availableBalance;

      setPartner((current) => ({
        ...current,
        availableBalance: nextBalance,
      }));
      setBalanceModalOpen(false);
      setBalanceForm({ mode: 'set', amount: '', comment: '' });
      showToast('Баланс обновлён');

      const transactionsResponse = await getAdminPartnerTransactions(partner.id, 10);
      setTransactions(normalizeTransactions(transactionsResponse));
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setBalanceErrors(caughtError.errors || {});
        showToast(caughtError.message, 'error');
      } else {
        showToast('Не удалось обновить баланс', 'error');
      }
    } finally {
      setActionLoading('');
    }
  };

  const submitPackage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('package');

    try {
      await changeAdminPartnerPackage(partner.id, selectedPackageId, applyPackageBusinessEffects);
      setPackageModalOpen(false);
      showToast(adminText('a_0J_QsNC60LXR_3'));
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_11'), 'error');
    } finally {
      setActionLoading('');
    }
  };

  const submitStatus = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActionLoading('status');

    try {
      await changeAdminPartnerStatus(partner.id, selectedStatus, applyStatusBonusEffects);
      setStatusModalOpen(false);
      setApplyStatusBonusEffects(false);
      showToast(adminText('a_0KHRgtCw0YLR_2'));
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_12'), 'error');
    } finally {
      setActionLoading('');
    }
  };

  const recalculateBinaryBonus = async () => {
    if (!partner.id) {
      return;
    }

    setActionLoading('binary');

    try {
      const response = await recalculateAdminPartnerBinaryBonus(partner.id);
      const record = isRecord(response) ? response : {};
      const data = isRecord(record.data) ? record.data : {};
      const eligible = data.eligible !== false;
      const reason = getString(data, ['reason']);

      setBinaryRecalculateModalOpen(false);

      if (eligible) {
        showToast(getString(record, ['message']) || 'Бинар пересчитан');
      } else {
        showToast(`Бинар не начислен${reason ? `: ${reason}` : ''}`, 'info');
      }

      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось рассчитать бинарный бонус', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const saveNote = async () => {
    setActionLoading('note');

    try {
      await saveAdminPartnerNote(partner.id, note);
      showToast(adminText('a_0JfQsNC80LXR'));
      await refreshPartnerAfterAction();
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_13'), 'error');
    } finally {
      setActionLoading('');
    }
  };

  const openDeleteModal = async () => {
    if (!partner.id) {
      return;
    }

    setActionLoading('delete-preview');
    setDeleteError(null);
    setDeleteReason('');
    setDeleteUnderstood(false);
    setDeleteSubtree(false);

    try {
      const preview = await getAdminPartnerDeletePreview(partner.id, false);
      setDeletePreview(preview);
      setDeleteModalOpen(true);
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || 'Не удалось загрузить preview удаления', 'error');
    } finally {
      setActionLoading('');
    }
  };

  const refreshDeletePreview = async (withSubtree: boolean) => {
    if (!partner.id) {
      return;
    }

    setActionLoading('delete-preview');
    setDeleteError(null);

    try {
      setDeletePreview(await getAdminPartnerDeletePreview(partner.id, withSubtree));
    } catch (caughtError) {
      setDeleteError(getApiErrorState(caughtError).error || 'Не удалось обновить preview удаления');
    } finally {
      setActionLoading('');
    }
  };

  const submitDeletePartner = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!deleteReason.trim() || !deleteUnderstood) {
      return;
    }

    if (deletePreview?.has_children && !deleteSubtree) {
      setDeleteError('У партнёра есть структура. Выберите удаление вместе с поддеревом.');
      return;
    }

    setActionLoading('delete');
    setDeleteError(null);

    try {
      await deleteAdminPartner(partner.id, {
        delete_subtree: deleteSubtree,
        reason: deleteReason.trim(),
      });
      showToast('Партнёр удалён, перерасчёт выполнен');
      setDeleteModalOpen(false);
      window.setTimeout(() => navigate('/admin/partners'), 600);
    } catch (caughtError) {
      setDeleteError(getApiErrorState(caughtError).error || 'Не удалось удалить партнёра');
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
      showToast(adminText('a_0JTQvtGB0YLR'));
    } catch {
      showToast(adminText('a_0J3QtSDRg9C0_14'), 'error');
    }
  };

  const closePasswordModal = () => {
    setPasswordModalOpen(false);
    setPasswordForm({ password: '', password_confirmation: '' });
    setPasswordErrors({});
    setCredentials(null);
  };

  const closeIdentityModal = () => {
    if (actionLoading === 'identity') {
      return;
    }

    setIdentityModalOpen(false);
    setIdentityForm({ first_name: '', last_name: '', phone: '', email: '' });
    setIdentityErrors({});
  };

  const closeBalanceModal = () => {
    if (actionLoading === 'balance') {
      return;
    }

    setBalanceModalOpen(false);
    setBalanceForm({ mode: 'set', amount: '', comment: '' });
    setBalanceErrors({});
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

      <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div className="flex min-w-0 items-center gap-4">
          <Link to="/admin/partners" className="cursor-pointer p-3 bg-white rounded-xl border border-safi-green/5 shadow-sm text-safi-text/60 hover:text-safi-green hover:bg-[#F5F5F0] transition-colors" title={adminText('a_0J3QsNC30LDQ')}>
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <PartnerAvatar name={partner.fullName} avatarUrl={partner.avatarUrl} />
          <div className="min-w-0">
            <NoTranslate as="h1" className="mb-1 font-serif text-3xl font-bold text-safi-green">{partner.fullName}</NoTranslate>
            <div className="flex flex-wrap items-center gap-2 sm:gap-3">
              <NoTranslate className="rounded bg-[#F5F5F0] px-2 py-0.5 font-mono text-sm text-safi-text/70">{partner.login || partner.id}</NoTranslate>
              <AdminBadge variant={isBlocked ? 'danger' : 'default'}>Аккаунт: {partner.accountStatusLabel}</AdminBadge>
            </div>
          </div>
        </div>

        <div className="safi-responsive-actions w-full md:w-auto md:justify-end">
          {canUpdateIdentity && (
            <button
              type="button"
              onClick={openIdentityModal}
              disabled={!partner.id || isLoading || actionLoading === 'identity'}
              className="flex cursor-pointer items-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Edit className="w-4 h-4" />
              {actionLoading === 'identity' ? adminText('Сохранение...') : adminText('Редактировать данные')}
            </button>
          )}
          <button
            type="button"
            onClick={() => setPasswordModalOpen(true)}
            disabled={!partner.id || isLoading}
            className="flex cursor-pointer items-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <KeyRound className="w-4 h-4" />{adminText('a_0JjQt9C80LXQ')}</button>
          {canUpdateBalance && (
            <button
              type="button"
              onClick={openBalanceModal}
              disabled={!partner.id || isLoading || actionLoading === 'balance'}
              className="flex cursor-pointer items-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <CreditCard className="w-4 h-4" />
              {actionLoading === 'balance' ? adminText('Сохранение...') : adminText('Изменить баланс')}
            </button>
          )}
          <button
            type="button"
            onClick={toggleBlock}
            disabled={!partner.id || actionLoading === 'block'}
            className={`flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${isBlocked ? 'bg-green-500/10 text-green-600 hover:bg-green-500/20' : 'bg-red-500/10 text-red-600 hover:bg-red-500/20'}`}
          >
            {isBlocked ? <Unlock className="w-4 h-4" /> : <Lock className="w-4 h-4" />}
            {actionLoading === 'block' ? adminText('a_0KHQvtGF0YDQ_2') : isBlocked ? adminText('a_0KDQsNC30LHQ') : adminText('a_0JfQsNCx0LvQ')}
          </button>
          {canCalculateBinary && (
              <button
                type="button"
                onClick={() => setBinaryRecalculateModalOpen(true)}
                disabled={!partner.id || actionLoading === 'binary'}
                className="flex cursor-pointer items-center gap-2 rounded-xl bg-safi-green px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
              <Calculator className="w-4 h-4" />
              {actionLoading === 'binary' ? adminText('a_0KHQvtGF0YDQ_2') : adminText('Рассчитать бинар')}
            </button>
          )}
          {canDeletePartner && (
            <button
              type="button"
              onClick={openDeleteModal}
              disabled={!partner.id || isLoading || actionLoading === 'delete-preview'}
              className="flex cursor-pointer items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Trash2 className="w-4 h-4" />
              {actionLoading === 'delete-preview' ? adminText('Загрузка...') : adminText('Удалить партнёра')}
            </button>
          )}
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadPartner} />}
      {!isLoading && !error && !partner.id && (
        <EmptyState title={adminText('a_0J_QsNGA0YLQ_4')} description={adminText('a_0J_RgNC-0LLQ')} />
      )}

      {!isLoading && !error && partner.id && (
        <div className="grid lg:grid-cols-3 gap-8">
          <div className="space-y-8">
            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-6">
                <User className="w-5 h-5 text-safi-gold" />{adminText('a_0J7RgdC90L7Q')}</h3>

              <div className="space-y-4">
                <InfoRow icon={Phone} label={adminText('a_0KLQtdC70LXR')} value={partner.phone} />
                <InfoRow icon={Mail} label="Email" value={partner.email} />
                <InfoRow icon={MapPin} label={adminText('a_0JPQvtGA0L7Q')} value={partner.city} />
                <InfoRow icon={Calendar} label={adminText('a_0KDQtdCz0LjR')} value={partner.registrationDate} />
                <div className="flex items-center justify-between p-3 bg-[#F5F5F0] rounded-xl text-sm mt-4">
                  <span className="text-safi-text/60">{adminText('a_0KHQv9C-0L3R')}</span>
                  {partner.sponsorId ? (
                    <Link to={`/admin/partners/${partner.sponsorId}`} className="cursor-pointer font-bold font-mono text-safi-green hover:underline"><NoTranslate>{partner.sponsor}</NoTranslate></Link>
                  ) : (
                    <NoTranslate className="font-mono font-bold text-safi-green">{partner.sponsor}</NoTranslate>
                  )}
                </div>
              </div>

              <div className="mt-6 pt-6 border-t border-safi-green/5 grid grid-cols-2 gap-4">
                <div>
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">{adminText('a_0KLQtdC60YPR')}</div>
                  <AdminBadge variant="gold">{partner.package}</AdminBadge>
                  <div className="mt-2">
                    <AdminBadge variant={partner.packageStatus === 'active' ? 'success' : 'warning'}>
                      Пакет: {partner.packageStatusLabel}
                    </AdminBadge>
                  </div>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedPackageId(partner.packageId);
                      setApplyPackageBusinessEffects(true);
                      setPackageModalOpen(true);
                    }}
                    className="mt-2 flex cursor-pointer items-center gap-1 text-[10px] text-safi-gold hover:underline"
                  >
                    <Edit className="w-3 h-3" />{adminText('a_0JjQt9C80LXQ_2')}</button>
                </div>
                <div>
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">{adminText('a_TUxNINGB0YLQ')}</div>
                  <AdminBadge variant="default">{partner.status}</AdminBadge>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedStatus(partner.statusCode);
                      setApplyStatusBonusEffects(false);
                      setStatusModalOpen(true);
                    }}
                    className="mt-2 flex cursor-pointer items-center gap-1 text-[10px] text-safi-gold hover:underline"
                  >
                    <Edit className="w-3 h-3" />{adminText('a_0JjQt9C80LXQ_2')}</button>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-gold/30">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-4">
                <MessageSquare className="w-5 h-5 text-safi-gold" />{adminText('a_0JfQsNC80LXR_2')}</h3>
              <textarea
                value={note}
                onChange={(event) => setNote(event.target.value)}
                className="w-full h-32 bg-[#F5F5F0] rounded-xl p-4 text-sm font-medium border-none focus:ring-2 focus:ring-safi-gold/50 outline-none resize-none"
                placeholder={adminText('a_0J7RgdGC0LDQ')}
              />
              <button
                type="button"
                onClick={saveNote}
                disabled={actionLoading === 'note'}
                className="mt-3 w-full cursor-pointer rounded-xl bg-safi-green py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'note' ? adminText('a_0KHQvtGF0YDQ_2') : adminText('a_0KHQvtGF0YDQ_3')}
              </button>
            </div>
          </div>

          <div className="lg:col-span-2 space-y-8">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
              <MiniStat title={adminText('a_0JTQvtGB0YLR_2')} value={`${partner.availableBalance.toLocaleString('ru-RU')} ₸`} />
              <MiniStat title={adminText('a_0JLRgdC10LPQ_2')} value={`${partner.totalIncome.toLocaleString('ru-RU')} ₸`} />
              <MiniStat title="Личный PV" value={formatPv(partner.personalPV)} />
              <MiniStat title="Командный PV" value={formatPv(partner.teamPV)} />
              <MiniStat title="Малая ветка PV" value={formatPv(partner.weakLegPV)} />
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                  <Network className="w-5 h-5 text-safi-gold" />{adminText('a_0J7QsdC30L7R')}</h3>
                <Link to={`/admin/structure?root_id=${encodeURIComponent(partner.id)}`} className="cursor-pointer text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">{adminText('a_0J7RgtC60YDR')}</Link>
              </div>

              <div className="flex flex-col md:flex-row gap-6 items-center">
                <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">{adminText('a_0JvQtdCy0LDR_2')}</div>
                  <div className="text-2xl font-bold text-safi-green">{formatBranchPv('л', partner.leftPV)}</div>
                </div>

                <div className="w-12 h-12 rounded-full border border-safi-green/10 flex items-center justify-center shrink-0">VS</div>

                <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">{adminText('a_0J_RgNCw0LLQ_2')}</div>
                  <div className="text-2xl font-bold text-safi-green">{formatBranchPv('п', partner.rightPV)}</div>
                </div>
              </div>

              <div className="mt-6 flex justify-between items-center px-4">
                <div className="text-sm"><span className="text-safi-text/60">{adminText('a_0JvQuNGH0L3Q')}</span> <span className="font-bold">{partner.invitedCount}</span></div>
                <div className="text-sm"><span className="text-safi-text/60">{adminText('a_0KHQu9Cw0LHQ')}</span> <span className="font-bold text-safi-gold">{weakBranch}</span></div>
                <div className="text-sm"><span className="text-safi-text/60">Малая ветка PV:</span> <span className="font-bold text-safi-gold">{formatPv(partner.weakLegPV)}</span></div>
              </div>
            </div>

            <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                  <CreditCard className="w-5 h-5 text-safi-gold" />{adminText('a_0J_QvtGB0LvQ')}</h3>
                <Link to={`/admin/transactions?user_id=${encodeURIComponent(partner.id)}`} className="cursor-pointer text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">{adminText('a_0KHQvNC-0YLR')}</Link>
              </div>

              {transactions.length === 0 ? (
                <EmptyState
                  title={adminText('a_0KLRgNCw0L3Q_2')}
                  description={adminText('a_0J7Qv9C10YDQ')}
                  className="min-h-[180px] shadow-none"
                />
              ) : (
                <>
                  <MobileDataList>
                    {transactions.map((transaction) => (
                      <MobileDataCard key={transaction.id}>
                        <MobileDataHeader
                          title={transaction.type}
                          meta={transaction.date}
                          action={<AdminBadge variant="success">{transaction.status}</AdminBadge>}
                        />
                        <MobileDataRow label={adminText('a_0KHRg9C80LzQ')}>{transaction.amount}</MobileDataRow>
                        <MobileDataRow label={adminText('a_0JrQvtC80LzQ')}>{transaction.comment || '-'}</MobileDataRow>
                      </MobileDataCard>
                    ))}
                  </MobileDataList>

                  <div className="hidden overflow-x-auto md:block">
                    <table className="w-full min-w-[680px] text-left text-sm">
                      <thead className="text-[10px] uppercase tracking-widest text-safi-text/50">
                        <tr>
                          <th className="pb-3">{adminText('a_0JTQsNGC0LA')}</th>
                          <th className="pb-3">{adminText('a_0KLQuNC_')}</th>
                          <th className="pb-3">{adminText('a_0KHRg9C80LzQ')}</th>
                          <th className="pb-3">{adminText('a_0KHRgtCw0YLR')}</th>
                          <th className="pb-3">{adminText('a_0JrQvtC80LzQ')}</th>
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
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {identityModalOpen && (
        <Modal title={adminText('Редактировать пользователя')} onClose={closeIdentityModal}>
          <form className="space-y-5" onSubmit={submitIdentity}>
            <div className="grid gap-4 md:grid-cols-2">
              <FormField label={adminText('Имя')} error={identityErrors.first_name?.[0]}>
                <input
                  type="text"
                  value={identityForm.first_name}
                  onChange={(event) => setIdentityForm((current) => ({ ...current, first_name: event.target.value }))}
                  className={`${inputClass} notranslate`}
                  translate="no"
                  disabled={actionLoading === 'identity'}
                />
              </FormField>
              <FormField label={adminText('Фамилия')} error={identityErrors.last_name?.[0]}>
                <input
                  type="text"
                  value={identityForm.last_name}
                  onChange={(event) => setIdentityForm((current) => ({ ...current, last_name: event.target.value }))}
                  className={`${inputClass} notranslate`}
                  translate="no"
                  disabled={actionLoading === 'identity'}
                />
              </FormField>
            </div>
            <FormField label={adminText('Телефон')} error={identityErrors.phone?.[0]}>
              <input
                type="tel"
                value={identityForm.phone}
                onChange={(event) => setIdentityForm((current) => ({ ...current, phone: event.target.value }))}
                className={`${inputClass} notranslate`}
                translate="no"
                disabled={actionLoading === 'identity'}
                required
              />
            </FormField>
            <FormField label={adminText('Email')} error={identityErrors.email?.[0]}>
              <input
                type="email"
                value={identityForm.email}
                onChange={(event) => setIdentityForm((current) => ({ ...current, email: event.target.value }))}
                className={`${inputClass} notranslate`}
                translate="no"
                disabled={actionLoading === 'identity'}
                required
              />
            </FormField>
            <button
              type="submit"
              disabled={actionLoading === 'identity'}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'identity' ? adminText('Сохранение...') : adminText('Сохранить')}
            </button>
          </form>
        </Modal>
      )}

      {passwordModalOpen && (
        <Modal title={adminText('a_0JjQt9C80LXQ')} onClose={closePasswordModal}>
          <form className="space-y-5" onSubmit={submitPassword}>
            <FormField label={adminText('a_0J3QvtCy0YvQ_2')} error={passwordErrors.password?.[0]}>
              <input
                type="password"
                value={passwordForm.password}
                onChange={(event) => setPasswordForm((current) => ({ ...current, password: event.target.value }))}
                className={inputClass}
                autoComplete="new-password"
                required
              />
            </FormField>
            <FormField label={adminText('a_0J_QvtCy0YLQ')} error={passwordErrors.password_confirmation?.[0]}>
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
                <Shuffle className="h-4 w-4" />{adminText('a_0KHQs9C10L3Q')}</button>
              <button
                type="submit"
                disabled={actionLoading === 'password'}
                className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'password' ? adminText('a_0KHQvtGF0YDQ_2') : adminText('a_0KHQvtGF0YDQ')}
              </button>
            </div>
          </form>

          {credentials && (
            <CredentialsBlock credentials={credentials} onCopy={copyCredentials} />
          )}
        </Modal>
      )}

      {packageModalOpen && (
        <Modal title={adminText('a_0JjQt9C80LXQ_3')} onClose={() => setPackageModalOpen(false)}>
          <form className="space-y-5" onSubmit={submitPackage}>
            <FormField label={adminText('a_0J_QsNC60LXR_4')}>
              <select value={selectedPackageId} onChange={(event) => setSelectedPackageId(event.target.value)} className={inputClass} required>
                <option value="">{adminText('a_0JLRi9Cx0LXR')}</option>
                {packages.map((item) => (
                  <option key={item.id} value={item.id}>{item.label || item.name}</option>
                ))}
              </select>
            </FormField>
            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm text-safi-green">
              <input
                type="checkbox"
                checked={applyPackageBusinessEffects}
                onChange={(event) => setApplyPackageBusinessEffects(event.target.checked)}
                className="mt-1 h-4 w-4 cursor-pointer rounded border-safi-green/30 text-safi-green focus:ring-safi-green"
              />
              <span>
                <span className="block font-bold">{adminText('manual_package_apply_business_effects')}</span>
                <span className="mt-1 block text-xs leading-5 text-safi-muted">{adminText('manual_package_apply_business_effects_hint')}</span>
              </span>
            </label>
            <button
              type="submit"
              disabled={actionLoading === 'package'}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'package' ? adminText('a_0KHQvtGF0YDQ_2') : adminText('a_0KHQvtGF0YDQ')}
            </button>
          </form>
        </Modal>
      )}

      {balanceModalOpen && (
        <Modal title={adminText('Изменить баланс')} onClose={closeBalanceModal}>
          <form className="space-y-5" onSubmit={submitBalance}>
            <div className="rounded-2xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm font-bold text-safi-green">
              Текущий баланс: {partner.availableBalance.toLocaleString('ru-RU')} ₸
            </div>
            <FormField label="Режим" error={balanceErrors.mode?.[0]}>
              <select
                value={balanceForm.mode}
                onChange={(event) => setBalanceForm((current) => ({ ...current, mode: event.target.value === 'adjust' ? 'adjust' : 'set' }))}
                className={inputClass}
                disabled={actionLoading === 'balance'}
              >
                <option value="set">Установить баланс</option>
                <option value="adjust">Скорректировать на сумму</option>
              </select>
            </FormField>
            <FormField label={balanceForm.mode === 'set' ? 'Новый баланс' : 'Сумма корректировки'} error={balanceErrors.amount?.[0]}>
              <input
                type="number"
                step="0.01"
                min={balanceForm.mode === 'set' ? 0 : undefined}
                value={balanceForm.amount}
                onChange={(event) => setBalanceForm((current) => ({ ...current, amount: event.target.value }))}
                className={inputClass}
                disabled={actionLoading === 'balance'}
                required
              />
            </FormField>
            <FormField label="Комментарий" error={balanceErrors.comment?.[0]}>
              <textarea
                value={balanceForm.comment}
                onChange={(event) => setBalanceForm((current) => ({ ...current, comment: event.target.value }))}
                className={`${inputClass} min-h-[96px] resize-none`}
                maxLength={1000}
                disabled={actionLoading === 'balance'}
                placeholder="Manual correction"
              />
            </FormField>
            <button
              type="submit"
              disabled={actionLoading === 'balance' || !balanceForm.amount}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'balance' ? 'Сохранение...' : 'Сохранить баланс'}
            </button>
          </form>
        </Modal>
      )}

      {statusModalOpen && (
        <Modal title={adminText('a_0JjQt9C80LXQ_4')} onClose={() => setStatusModalOpen(false)}>
          <form className="space-y-5" onSubmit={submitStatus}>
            <FormField label={adminText('a_0KHRgtCw0YLR')}>
              <select value={selectedStatus} onChange={(event) => setSelectedStatus(event.target.value)} className={inputClass} required>
                {statusOptions.map((status) => (
                  <option key={status} value={status}>{mlmStatusLabel(status)}</option>
                ))}
              </select>
            </FormField>
            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm text-safi-green">
              <input
                type="checkbox"
                checked={applyStatusBonusEffects}
                onChange={(event) => setApplyStatusBonusEffects(event.target.checked)}
                className="mt-1 h-4 w-4 cursor-pointer rounded border-safi-green/30 text-safi-green focus:ring-safi-green"
              />
              <span>
                <span className="block font-bold">Применить бонусные начисления</span>
                <span className="mt-1 block text-xs leading-5 text-safi-muted">Для ELITE партнёра при повышении до Директора или выше статусный бонус начисляется автоматически. Чекбокс нужен для ручного применения бонусов к другим статусам.</span>
              </span>
            </label>
            <button
              type="submit"
              disabled={actionLoading === 'status'}
              className="w-full cursor-pointer rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
            >
              {actionLoading === 'status' ? adminText('a_0KHQvtGF0YDQ_2') : adminText('a_0KHQvtGF0YDQ')}
            </button>
          </form>
        </Modal>
      )}

      {binaryRecalculateModalOpen && (
        <Modal title="Перерассчитать бинар" onClose={() => actionLoading !== 'binary' && setBinaryRecalculateModalOpen(false)}>
          <div className="space-y-5">
            <div className="rounded-2xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm leading-6 text-safi-green">
              <p className="font-bold">Перерассчитать бинар для партнёра?</p>
              <p className="mt-2 text-safi-muted">Будет пересчитан текущий период. Повторное нажатие не должно дублировать выплаты.</p>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => setBinaryRecalculateModalOpen(false)}
                disabled={actionLoading === 'binary'}
                className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
              >
                Отмена
              </button>
              <button
                type="button"
                onClick={recalculateBinaryBonus}
                disabled={actionLoading === 'binary'}
                className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl bg-safi-green px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'binary' ? 'Расчёт...' : 'Перерассчитать'}
              </button>
            </div>
          </div>
        </Modal>
      )}

      {deleteModalOpen && (
        <Modal title={adminText('Удалить партнёра')} onClose={() => actionLoading !== 'delete' && setDeleteModalOpen(false)}>
          <form className="space-y-5" onSubmit={submitDeletePartner}>
            <div className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-700">
              Пользователь будет архивирован через soft delete. Логин, email и телефон будут освобождены для повторной регистрации. Пользователь будет исключён из статистики и расчётов, история транзакций сохранится, бонусы будут reversed/voided, структура и PV пересчитаны по активным пользователям.
            </div>

            {deletePreview && (
              <div className="grid gap-3 rounded-2xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm">
                <PreviewRow label="ID" value={deletePreview.user?.id || partner.id} />
                <PreviewRow label={adminText('Имя')} value={deletePreview.user?.name || partner.fullName} />
                <PreviewRow label={adminText('Email')} value={deletePreview.user?.email || partner.email} />
                <PreviewRow label="Есть дети" value={deletePreview.has_children ? 'Да' : 'Нет'} />
                <PreviewRow label="Descendants" value={deletePreview.descendants_count ?? 0} />
                <PreviewRow label="Affected uplines" value={deletePreview.affected_uplines_count ?? 0} />
                <PreviewRow label="Транзакции" value={deletePreview.transactions_count ?? 0} />
                <PreviewRow label="Заказы" value={deletePreview.orders_count ?? 0} />
                <PreviewRow label="Заявки на вывод" value={deletePreview.withdrawals_count ?? 0} />
                <PreviewRow label="Баланс кошелька" value={`${Number(deletePreview.wallet_balance ?? 0).toLocaleString('ru-RU')} ₸`} />
                <PreviewRow label="PV к пересчёту" value={formatPv(deletePreview.pv_to_recalculate ?? 0)} />
              </div>
            )}

            {deletePreview?.warning && (
              <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold leading-6 text-amber-800">
                {deletePreview.warning}
              </div>
            )}

            <FormField label="Причина удаления">
              <textarea
                value={deleteReason}
                onChange={(event) => setDeleteReason(event.target.value)}
                className={`${inputClass} min-h-[96px] resize-none`}
                placeholder="Например: тестовый пользователь"
                required
              />
            </FormField>

            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm text-safi-green">
              <input
                type="checkbox"
                checked={deleteUnderstood}
                onChange={(event) => setDeleteUnderstood(event.target.checked)}
                className="mt-1 h-4 w-4 cursor-pointer rounded border-safi-green/30 text-safi-green focus:ring-safi-green"
              />
              <span className="font-bold">Я понимаю, что будет выполнен перерасчёт</span>
            </label>

            {deletePreview?.has_children && (
              <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <input
                  type="checkbox"
                  checked={deleteSubtree}
                  onChange={(event) => {
                    const nextValue = event.target.checked;
                    setDeleteSubtree(nextValue);
                    void refreshDeletePreview(nextValue);
                  }}
                  className="mt-1 h-4 w-4 cursor-pointer rounded border-red-300 text-red-600 focus:ring-red-600"
                />
                <span>
                  <span className="block font-bold">Удалить вместе с поддеревом</span>
                  <span className="mt-1 block text-xs leading-5">Будут архивированы выбранный партнёр и все descendants. Перепривязка детей не выполняется.</span>
                </span>
              </label>
            )}

            {deleteError && (
              <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {deleteError}
              </div>
            )}

            <div className="flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => setDeleteModalOpen(false)}
                disabled={actionLoading === 'delete'}
                className="inline-flex cursor-pointer items-center justify-center rounded-xl border border-safi-border bg-[#F5F5F0] px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:cursor-not-allowed disabled:opacity-60"
              >
                Отмена
              </button>
              <button
                type="submit"
                disabled={actionLoading === 'delete' || actionLoading === 'delete-preview' || !deleteReason.trim() || !deleteUnderstood || Boolean(deletePreview?.has_children && !deleteSubtree)}
                className="inline-flex flex-1 cursor-pointer items-center justify-center rounded-xl bg-red-600 px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actionLoading === 'delete' ? adminText('Удаление...') : adminText('Удалить партнёра')}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-3 py-3 backdrop-blur-sm sm:px-4 sm:py-6">
      <div className="safi-responsive-modal w-full max-w-xl overflow-y-auto rounded-[28px] border border-safi-border bg-white p-5 shadow-[0_24px_70px_rgba(11,23,18,0.2)] sm:p-6">
        <div className="mb-6 flex items-center justify-between gap-4">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-white"
            aria-label={adminText('a_0JfQsNC60YDR')}
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

function PreviewRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{label}</span>
      <NoTranslate className="text-right font-bold text-safi-green">{value}</NoTranslate>
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
        <CredentialLine label={adminText('a_0JvQvtCz0LjQ')} value={credentials.login} />
        <CredentialLine label="Email" value={credentials.email} />
        <CredentialLine label={adminText('a_0J3QvtCy0YvQ_2')} value={credentials.password} />
      </div>
      <button
        type="button"
        onClick={onCopy}
        className="mt-4 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-safi-green bg-white px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
      >
        <Copy className="h-4 w-4" />{adminText('a_0KHQutC-0L_Q')}</button>
    </div>
  );
}

function CredentialLine({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{label}</div>
      <NoTranslate as="div" className="mt-1 break-all font-mono font-bold text-safi-green">{value}</NoTranslate>
    </div>
  );
}

function PartnerAvatar({ name, avatarUrl }: { name: string; avatarUrl: string }) {
  if (avatarUrl) {
    return (
      <img
        src={avatarUrl}
        alt={name}
        className="h-16 w-16 shrink-0 rounded-2xl border border-safi-green/5 object-cover shadow-sm"
      />
    );
  }

  return (
    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-safi-green/5 bg-safi-green font-serif text-2xl font-semibold text-safi-gold shadow-sm">
      {initials(name)}
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
        <NoTranslate as="div" className="text-sm font-bold text-safi-text">{value}</NoTranslate>
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

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'S';
}

function splitName(name: string) {
  const parts = name === '-' ? [] : name.split(/\s+/).filter(Boolean);

  return {
    firstName: parts[0] || '',
    lastName: parts.slice(1).join(' '),
  };
}

function normalizePartner(response: unknown, fallbackId: string): PartnerDetail {
  const user = unwrapRecord(response, ['user']);
  const profile = isRecord(user.profile) ? user.profile : {};
  const sponsor = isRecord(user.sponsor) ? user.sponsor : {};
  const pkg = isRecord(user.current_package) ? user.current_package : isRecord(user.package) ? user.package : {};
  const wallets = Array.isArray(user.wallets) ? user.wallets.filter(isRecord) : [];
  const accountStatus = normalizeAccountStatus(getString(user, ['account_status']));
  const walletBalance = getNumber(user, ['wallet_balance', 'walletBalance', 'balance', 'main_balance'])
    ?? getWalletBalance(wallets, 'main');
  const packageActivityPV = getNumber(user, ['package_pv', 'packagePv', 'package_activity_pv', 'packageActivityPv'])
    ?? getNumber(pkg, ['activity_pv', 'activityPv', 'pv'])
    ?? 0;
  const packageActivityAmount = getNumber(user, ['package_activity_amount', 'packageActivityAmount'])
    ?? 0;
  const bonusBalance = getNumber(user, ['bonus_balance', 'bonusBalance'])
    ?? getWalletBalance(wallets, 'bonus');
  const depositBalance = getNumber(user, ['deposit_balance', 'depositBalance'])
    ?? getWalletBalance(wallets, 'deposit');
  const totalWalletBalance = getNumber(user, ['total_wallet_balance', 'totalWalletBalance', 'wallet_total_balance', 'walletTotalBalance'])
    ?? walletBalance + bonusBalance + depositBalance;
  const totalWalletEarned = getNumber(user, ['total_wallet_earned', 'totalWalletEarned', 'wallet_total_earned', 'walletTotalEarned'])
    ?? totalWalletBalance;
  const apiAvailableBalance = getNumber(user, ['available_balance', 'availableBalance', 'wallet_balance', 'walletBalance']);
  const apiTotalEarned = getNumber(user, ['total_earned', 'totalEarned', 'total_balance', 'totalBalance', 'total_wallet_balance', 'totalWalletBalance']);
  const availableBalance = apiAvailableBalance ?? walletBalance;
  const totalEarned = apiTotalEarned ?? totalWalletEarned;
  const rawPackageCode = getString(user, ['package_code', 'packageCode'])
    || getString(pkg, ['code', 'slug', 'id'])
    || getString(user, ['package'])
    || '';
  const packageStatus = getPartnerPackageStatus(user, rawPackageCode);
  const packageCode = packageStatus === 'active' ? rawPackageCode : '';
  const statusCode = getString(user, ['status']) || 'user';
  const fullName = getString(user, ['name']) || '-';
  const firstName = getString(user, ['first_name', 'firstName'])
    || getString(profile, ['first_name', 'firstName'])
    || splitName(fullName).firstName;
  const lastName = getString(user, ['last_name', 'lastName'])
    || getString(profile, ['last_name', 'lastName'])
    || splitName(fullName).lastName;

  return {
    id: getString(user, ['id']) || fallbackId,
    login: getString(user, ['login']) || fallbackId,
    fullName,
    firstName,
    lastName,
    phone: getString(user, ['phone']) || getString(profile, ['phone']) || '-',
    email: getString(user, ['email']) || '-',
    avatarUrl: getString(user, ['avatar_url', 'avatarUrl']) || getString(profile, ['avatar_url', 'avatarUrl']) || '',
    city: getString(user, ['city']) || getString(profile, ['city']) || '-',
    sponsorId: getString(user, ['sponsor_id']) || '',
    sponsor: getString(sponsor, ['name', 'login', 'id']) || getString(user, ['sponsor_id']) || '-',
    invitedCount: getNumber(user, ['invited_count', 'invited_users_count', 'referrals_count']) ?? 0,
    packageId: getString(user, ['current_package_id']) || '',
    packageCode,
    package: packageStatus === 'active'
      ? packageLabel(packageCode, getString(user, ['package_name', 'packageName']) || getString(pkg, ['code_label', 'codeLabel', 'label', 'name']) || '-')
      : '-',
    packageStatus,
    packageStatusLabel: getString(user, ['package_status_label', 'packageStatusLabel']) || partnerPackageStatusLabel(packageStatus),
    statusCode,
    status: packageStatus === 'active'
      ? mlmStatusLabel(statusCode, getString(user, ['status_label', 'statusLabel']) || statusCode)
      : 'Неактивен',
    personalPV: packageActivityPV,
    teamPV: (getNumber(user, ['left_pv']) ?? 0) + (getNumber(user, ['right_pv']) ?? 0),
    leftPV: getNumber(user, ['left_pv']) ?? 0,
    rightPV: getNumber(user, ['right_pv']) ?? 0,
    weakLegPV: getNumber(user, ['weak_leg_pv', 'weakLegPv'])
      ?? Math.min(getNumber(user, ['left_pv']) ?? 0, getNumber(user, ['right_pv']) ?? 0),
    totalIncome: totalEarned,
    availableBalance,
    packageActivityPV,
    packageActivityAmount,
    registrationDate: getString(user, ['created_at']) || '-',
    accountStatus,
    accountStatusLabel: accountStatusLabel(accountStatus),
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
      type: transactionTypeLabel(getString(record, ['type']), getString(record, ['type_label', 'typeLabel']) || '-'),
      amount: formatTransactionAmount(direction, amount),
      status: transactionStatusLabel(getString(record, ['status']), getString(record, ['status_label', 'statusLabel']) || '-'),
      comment: getString(record, ['description']) || '-',
    };
  });
}

function formatTransactionAmount(direction: string, amount: number) {
  if (direction === 'credit') {
    return `+${amount.toLocaleString('ru-RU')} ₸`;
  }

  if (direction === 'debit') {
    return `-${amount.toLocaleString('ru-RU')} ₸`;
  }

  return `${amount.toLocaleString('ru-RU')} ₸`;
}

function formatBranchPv(branch: 'л' | 'п', value: number) {
  return `${branch}:${value.toLocaleString('ru-RU')}PV`;
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
    `${adminText('a_0JvQvtCz0LjQ')}: ${credentials.login}`,
    `Email: ${credentials.email}`,
    `${adminText('a_0J_QsNGA0L7Q_2')}: ${credentials.password}`,
    `${adminText('login_link_label')}: ${credentials.login_url}`,
  ].join('\n');
}

function generatePassword() {
  return `Safi${Math.random().toString(36).slice(2, 8)}${Math.floor(10 + Math.random() * 90)}`;
}

function getWalletBalance(wallets: Record<string, unknown>[], type: string) {
  return wallets
    .filter((wallet) => getString(wallet, ['type']) === type)
    .reduce((sum, wallet) => sum + (getNumber(wallet, ['balance']) ?? 0), 0);
}

function normalizeAccountStatus(status?: string) {
  return status === 'blocked' ? 'blocked' : 'active';
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
