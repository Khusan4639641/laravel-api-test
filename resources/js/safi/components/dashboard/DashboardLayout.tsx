import { useCallback, useEffect, useState } from 'react';
import { Outlet, useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { Bell, Menu } from 'lucide-react';
import { Sidebar } from './Sidebar';
import { LanguageSwitcher } from '../ui/LanguageSwitcher';
import { ApiError, clearAuthToken, getAuthToken, getDashboardNotifications, getMyPermissions, me } from '../../lib/api';
import { getCurrentLanguage } from '../../lib/language';
import { canAccessPath, normalizePermissions, RolePermissions } from '../../lib/permissions';
import { mlmStatusLabel, packageLabel } from '../../lib/systemLabels';

export interface DashboardCurrentUser {
  id?: string | number;
  name: string;
  login?: string;
  email?: string;
  avatarUrl?: string;
  role: string;
  partnerId: string;
  referralCode: string;
  packageCode?: string;
  packageName: string;
  statusCode?: string;
  status: string;
  sponsor: string;
  registrationDate: string;
  walletAvailable: number;
  totalEarned: number;
  personalPV: number;
  teamPV: number;
  bonusesTotal: number;
  referralsCount: number;
  raw?: unknown;
}

export interface DashboardContextValue {
  currentUser: DashboardCurrentUser;
  permissions: RolePermissions;
  refreshCurrentUser: () => Promise<void>;
}

interface DashboardNotificationRow {
  id: string;
  type: string;
  title: string;
  message: string;
  createdAt?: string;
  readAt?: string;
}

const userDefaults: DashboardCurrentUser = {
  name: 'Safi Partner',
  role: 'user',
  partnerId: 'SAFI',
  referralCode: 'SAFI',
  packageCode: '',
  packageName: '-',
  statusCode: 'user',
  status: 'user',
  sponsor: '-',
  registrationDate: '-',
  walletAvailable: 0,
  totalEarned: 0,
  personalPV: 0,
  teamPV: 0,
  bonusesTotal: 0,
  referralsCount: 0,
};

export function useDashboardContext() {
  return useOutletContext<DashboardContextValue>();
}

export function DashboardLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState<DashboardNotificationRow[]>([]);
  const [unreadNotificationsCount, setUnreadNotificationsCount] = useState(0);
  const [currentUser, setCurrentUser] = useState<DashboardCurrentUser | null>(null);
  const [permissions, setPermissions] = useState<RolePermissions | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [authError, setAuthError] = useState<string | null>(null);
  const navigate = useNavigate();
  const location = useLocation();

  const loadNotifications = useCallback(async () => {
    try {
      const response = await getDashboardNotifications(6);
      const normalized = normalizeNotificationsResponse(response);

      setNotifications(normalized.notifications);
      setUnreadNotificationsCount(normalized.unreadCount);
    } catch {
      setNotifications([]);
      setUnreadNotificationsCount(0);
    }
  }, []);

  const loadCurrentUser = useCallback(async () => {
    const token = getAuthToken();

    if (!token) {
      setIsLoading(false);
      navigate('/login', { replace: true });
      return;
    }

    setIsLoading(true);
    setAuthError(null);

    try {
      const [response, permissionsResponse] = await Promise.all([me(), getMyPermissions()]);
      const user = normalizeCurrentUser(response);
      const rolePermissions = normalizePermissions(permissionsResponse);

      if (rolePermissions.role !== 'user' || user.role.toLowerCase() !== 'user') {
        navigate(rolePermissions.redirect_after_login, { replace: true });
        return;
      }

      if (!canAccessPath(location.pathname, rolePermissions)) {
        navigate(rolePermissions.redirect_after_login, { replace: true });
        return;
      }

      setCurrentUser(user);
      setPermissions(rolePermissions);
      void loadNotifications();
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearAuthToken();
        setIsLoading(false);
        navigate('/login', { replace: true });
        return;
      }

      setCurrentUser(null);
      setPermissions(null);
      setAuthError('Не удалось получить данные пользователя. Попробуйте обновить страницу.');
    } finally {
      setIsLoading(false);
    }
  }, [loadNotifications, location.pathname, navigate]);

  useEffect(() => {
    void loadCurrentUser();
  }, [loadCurrentUser]);

  useEffect(() => {
    setIsNotificationsOpen(false);
  }, [location.pathname]);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-safi-bg px-5 text-center text-safi-green">
        <div>
          <div className="mx-auto mb-4 h-12 w-12 rounded-full border-4 border-safi-border border-t-safi-gold animate-spin" />
          <div className="font-serif text-2xl font-semibold">Загрузка кабинета</div>
        </div>
      </div>
    );
  }

  if (authError || !currentUser || !permissions) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-safi-bg px-5 text-center text-safi-green">
        <div className="max-w-md rounded-[32px] border border-safi-border bg-white p-8 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
          <div className="font-serif text-3xl font-semibold">Кабинет недоступен</div>
          <p className="mt-3 text-sm leading-7 text-safi-muted">
            {authError || 'Не удалось подтвердить сессию пользователя.'}
          </p>
          <button
            type="button"
            onClick={() => void loadCurrentUser()}
            className="mt-6 rounded-full border border-safi-green bg-safi-green px-6 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-white"
          >
            Повторить
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen bg-safi-bg text-safi-green">
      <Sidebar
        isOpen={isSidebarOpen}
        onClose={() => setIsSidebarOpen(false)}
        currentUser={currentUser}
        permissions={permissions}
      />

      <div className="relative flex min-h-screen max-w-full flex-1 flex-col overflow-hidden lg:ml-[280px]">
        <header className="sticky top-0 z-30 flex h-20 shrink-0 items-center justify-between border-b border-safi-border/80 bg-safi-bg/90 px-4 backdrop-blur-xl md:px-8">
          <div className="flex items-center gap-4">
            <button
              type="button"
              className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:bg-safi-cream lg:hidden"
              onClick={() => setIsSidebarOpen(true)}
              aria-label="Открыть меню"
            >
              <Menu className="h-5 w-5" />
            </button>
            <div className="hidden lg:block">
              <LanguageSwitcher />
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className="lg:hidden">
              <LanguageSwitcher />
            </div>
            <div className="relative hidden sm:block">
              <button
                type="button"
                className="relative flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                aria-label="Уведомления"
                aria-expanded={isNotificationsOpen}
                onClick={() => setIsNotificationsOpen((isOpen) => !isOpen)}
              >
                <Bell className="h-5 w-5" />
                {unreadNotificationsCount > 0 ? (
                  <span className="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-safi-gold px-1.5 text-[10px] font-extrabold text-safi-green">
                    {unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount}
                  </span>
                ) : null}
              </button>

              {isNotificationsOpen ? (
                <div className="absolute right-0 mt-3 w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-3xl border border-safi-border bg-white shadow-[0_24px_70px_rgba(11,23,18,0.12)]">
                  <div className="flex items-center justify-between border-b border-safi-border/70 px-5 py-4">
                    <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Уведомления</div>
                    <div className="rounded-full bg-safi-cream px-3 py-1 text-[10px] font-extrabold text-safi-green">
                      {unreadNotificationsCount.toLocaleString('ru-RU')} новых
                    </div>
                  </div>

                  <div className="max-h-96 overflow-y-auto">
                    {notifications.length > 0 ? (
                      notifications.map((notification) => (
                        <div
                          key={notification.id}
                          className="border-b border-safi-border/60 px-5 py-4 last:border-b-0"
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div className="text-sm font-extrabold text-safi-green">{notification.title}</div>
                            {!notification.readAt ? (
                              <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-safi-gold" aria-label="Новое уведомление" />
                            ) : null}
                          </div>
                          <div className="mt-1 text-sm leading-6 text-safi-muted">{notification.message}</div>
                          {notification.createdAt ? (
                            <div className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-muted/70">
                              {formatNotificationDate(notification.createdAt)}
                            </div>
                          ) : null}
                        </div>
                      ))
                    ) : (
                      <div className="px-5 py-8 text-center text-sm text-safi-muted">
                        Пока нет уведомлений.
                      </div>
                    )}
                  </div>
                </div>
              ) : null}
            </div>
            <div className="hidden items-center gap-3 border-l border-safi-border pl-4 sm:flex">
              <div className="text-right">
                <div className="text-sm font-extrabold text-safi-green">{currentUser.name}</div>
                <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">{currentUser.status}</div>
              </div>
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-green font-serif text-lg font-semibold text-safi-gold">
                {currentUser.name.charAt(0)}
              </div>
            </div>
          </div>
        </header>

        <main className="relative flex-1 overflow-x-hidden overflow-y-auto p-4 md:p-8">
          <div className="relative mx-auto w-full max-w-7xl pb-20">
            <Outlet context={{ currentUser, permissions, refreshCurrentUser: loadCurrentUser } satisfies DashboardContextValue} />
          </div>
        </main>
      </div>
    </div>
  );
}

function normalizeNotificationsResponse(response: unknown) {
  const record = isRecord(response) ? response : {};
  const rawNotifications = Array.isArray(record.notifications)
    ? record.notifications
    : Array.isArray(record.data)
      ? record.data
      : [];
  const notifications = rawNotifications
    .filter(isRecord)
    .map(normalizeNotificationRow);

  return {
    notifications,
    unreadCount: getNumber(record, ['unread_count', 'unreadCount']) ?? notifications.filter((notification) => !notification.readAt).length,
  };
}

function normalizeNotificationRow(record: Record<string, unknown>): DashboardNotificationRow {
  const data = isRecord(record.data) ? record.data : {};
  const type = getString(record, ['type']) || getString(data, ['type']) || 'notification';

  return {
    id: getString(record, ['id']) || `${type}-${getString(record, ['created_at', 'createdAt']) || Math.random()}`,
    type,
    title: getLocalizedNotificationText(record.title ?? data.title, defaultNotificationTitle(type)),
    message: getLocalizedNotificationText(record.message ?? data.message, defaultNotificationMessage(type)),
    createdAt: getString(record, ['created_at', 'createdAt']),
    readAt: getString(record, ['read_at', 'readAt']),
  };
}

function getLocalizedNotificationText(value: unknown, fallback: string) {
  if (typeof value === 'string' && value.trim() !== '') {
    return value;
  }

  if (isRecord(value)) {
    const language = getCurrentLanguage();

    return getString(value, [language, 'ru', 'en', 'kz', 'kg', 'mn']) || fallback;
  }

  return fallback;
}

function defaultNotificationTitle(type: string) {
  return {
    status_achieved: 'Новый статус',
    referral_bonus: 'Реферальный бонус',
    binary_bonus: 'Бинарный бонус',
    status_bonus: 'Статусный бонус',
    x2_bonus: 'X2 бонус',
    cashback: 'Кэшбэк',
  }[type] || 'Уведомление';
}

function defaultNotificationMessage(type: string) {
  return {
    status_achieved: 'Поздравляем! Вы достигли нового статуса.',
    referral_bonus: 'Начислен реферальный бонус.',
    binary_bonus: 'Начислен бинарный бонус.',
    status_bonus: 'Начислен статусный бонус.',
    x2_bonus: 'Начислен X2 бонус.',
    cashback: 'Начислен кэшбэк.',
  }[type] || 'Новое уведомление.';
}

function formatNotificationDate(value: string) {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function normalizeCurrentUser(response: unknown): DashboardCurrentUser {
  const record = unwrapRecord(response);
  const packageRecord = isRecord(record.package) ? record.package : undefined;
  const statusRecord = isRecord(record.status) ? record.status : undefined;
  const wallets = Array.isArray(record.wallets) ? record.wallets.filter(isRecord) : [];
  const mainWalletRecord = wallets.find((wallet) => getString(wallet, ['type']) === 'main');
  const walletRecord = mainWalletRecord || (isRecord(record.wallet) ? record.wallet : undefined);
  const sponsorRecord = isRecord(record.sponsor) ? record.sponsor : undefined;

  const packageCode = getString(packageRecord, ['code', 'slug', 'id'])
    || getString(record, ['package_code', 'packageCode', 'package_id', 'package'])
    || '';
  const packageName = packageLabel(packageCode, getString(packageRecord, ['code_label', 'codeLabel', 'label', 'name', 'title'])
    || getString(record, ['package_name', 'packageName', 'package_id', 'package'])
    || userDefaults.packageName);
  const statusCode = getString(record, ['status'])
    || getString(statusRecord, ['code', 'id'])
    || userDefaults.statusCode;
  const statusName = mlmStatusLabel(statusCode, getString(record, ['status_label', 'statusLabel'])
    || getString(statusRecord, ['name_label', 'label', 'name', 'title'])
    || getString(record, ['status_name', 'statusName', 'status'])
    || userDefaults.status);
  const sponsorName = getString(sponsorRecord, ['name', 'full_name'])
    || getString(record, ['sponsor_name', 'sponsorName', 'sponsor'])
    || userDefaults.sponsor;

  return {
    id: getString(record, ['id']) || getNumber(record, ['id']),
    name: getString(record, ['name', 'full_name', 'fullName']) || userDefaults.name,
    login: getString(record, ['login', 'username']),
    email: getString(record, ['email']),
    avatarUrl: getString(record, ['avatar_url', 'avatarUrl'])
      || getString(isRecord(record.profile) ? record.profile : undefined, ['avatar_url', 'avatarUrl']),
    role: getString(record, ['role', 'user_role', 'role_name']) || userDefaults.role,
    partnerId: getString(record, ['partner_id', 'partnerId', 'member_id', 'code']) || userDefaults.partnerId,
    referralCode: getString(record, ['referral_code', 'referralCode', 'invite_code']) || userDefaults.referralCode,
    packageCode,
    packageName,
    statusCode,
    status: statusName,
    sponsor: sponsorName,
    registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || userDefaults.registrationDate,
    walletAvailable: getNumber(record, ['wallet_balance', 'walletBalance', 'available_balance', 'availableBalance', 'withdrawable_balance', 'withdrawableBalance'])
      ?? getNumber(walletRecord, ['available', 'balance', 'amount'])
      ?? userDefaults.walletAvailable,
    totalEarned: getNumber(walletRecord, ['total_earned', 'totalEarned', 'earned']) ?? getNumber(record, ['total_earned', 'totalEarned']) ?? userDefaults.totalEarned,
    personalPV: getNumber(record, ['package_activity_pv', 'packageActivityPv', 'personal_pv', 'personalPV'])
      ?? getNumber(packageRecord, ['activity_pv', 'activityPv', 'pv'])
      ?? userDefaults.personalPV,
    teamPV: getNumber(record, ['team_pv', 'teamPV', 'structure_pv'])
      ?? ((getNumber(record, ['left_pv']) ?? 0) + (getNumber(record, ['right_pv']) ?? 0))
      ?? userDefaults.teamPV,
    bonusesTotal: getNumber(record, ['bonuses_total', 'bonusesTotal', 'bonus_balance']) ?? userDefaults.bonusesTotal,
    referralsCount: getNumber(record, ['referrals_count', 'referralsCount']) ?? userDefaults.referralsCount,
    raw: response,
  };
}

function unwrapRecord(response: unknown): Record<string, unknown> {
  if (!isRecord(response)) {
    return {};
  }

  if (isRecord(response.user)) {
    return response.user;
  }

  if (isRecord(response.data)) {
    if (isRecord(response.data.user)) {
      return response.data.user;
    }

    return response.data;
  }

  return response;
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

function getNumber(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const normalized = Number(value.replace(/\s/g, ''));

      if (Number.isFinite(normalized)) {
        return normalized;
      }
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
