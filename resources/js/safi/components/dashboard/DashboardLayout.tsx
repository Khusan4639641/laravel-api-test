import { useCallback, useEffect, useState } from 'react';
import { Outlet, useNavigate, useOutletContext } from 'react-router-dom';
import { Bell, Menu } from 'lucide-react';
import { Sidebar } from './Sidebar';
import { LanguageSwitcher } from '../ui/LanguageSwitcher';
import { ApiError, clearAuthToken, getAuthToken, me } from '../../lib/api';

export interface DashboardCurrentUser {
  id?: string | number;
  name: string;
  login?: string;
  email?: string;
  role: string;
  partnerId: string;
  referralCode: string;
  packageName: string;
  status: string;
  sponsor: string;
  registrationDate: string;
  walletAvailable: number;
  totalEarned: number;
  personalPV: number;
  teamPV: number;
  bonusesTotal: number;
  raw?: unknown;
}

export interface DashboardContextValue {
  currentUser: DashboardCurrentUser;
  refreshCurrentUser: () => Promise<void>;
}

const userDefaults: DashboardCurrentUser = {
  name: 'Safi Partner',
  role: 'user',
  partnerId: 'SAFI',
  referralCode: 'SAFI',
  packageName: '-',
  status: 'user',
  sponsor: '-',
  registrationDate: '-',
  walletAvailable: 0,
  totalEarned: 0,
  personalPV: 2500,
  teamPV: 0,
  bonusesTotal: 0,
};

export function useDashboardContext() {
  return useOutletContext<DashboardContextValue>();
}

export function DashboardLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [currentUser, setCurrentUser] = useState<DashboardCurrentUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [authError, setAuthError] = useState<string | null>(null);
  const navigate = useNavigate();

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
      const response = await me();
      const user = normalizeCurrentUser(response);

      if (['super_admin', 'support'].includes(user.role.toLowerCase())) {
        navigate(user.role === 'support' ? '/support' : '/admin', { replace: true });
        return;
      }

      setCurrentUser(user);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearAuthToken();
        setIsLoading(false);
        navigate('/login', { replace: true });
        return;
      }

      setCurrentUser(null);
      setAuthError('Не удалось получить данные пользователя. Попробуйте обновить страницу.');
    } finally {
      setIsLoading(false);
    }
  }, [navigate]);

  useEffect(() => {
    void loadCurrentUser();
  }, [loadCurrentUser]);

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

  if (authError || !currentUser) {
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
            <button
              type="button"
              className="hidden h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:bg-safi-green hover:text-white sm:flex"
              aria-label="Уведомления"
            >
              <Bell className="h-5 w-5" />
            </button>
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
            <Outlet context={{ currentUser, refreshCurrentUser: loadCurrentUser } satisfies DashboardContextValue} />
          </div>
        </main>
      </div>
    </div>
  );
}

function normalizeCurrentUser(response: unknown): DashboardCurrentUser {
  const record = unwrapRecord(response);
  const packageRecord = isRecord(record.package) ? record.package : undefined;
  const statusRecord = isRecord(record.status) ? record.status : undefined;
  const walletRecord = isRecord(record.wallet) ? record.wallet : undefined;
  const sponsorRecord = isRecord(record.sponsor) ? record.sponsor : undefined;

  const packageName = getString(packageRecord, ['name', 'title'])
    || getString(record, ['package_name', 'packageName', 'package_id', 'package'])
    || userDefaults.packageName;
  const statusName = getString(statusRecord, ['name', 'title'])
    || getString(record, ['status_name', 'statusName', 'status'])
    || userDefaults.status;
  const sponsorName = getString(sponsorRecord, ['name', 'full_name'])
    || getString(record, ['sponsor_name', 'sponsorName', 'sponsor'])
    || userDefaults.sponsor;

  return {
    id: getString(record, ['id']) || getNumber(record, ['id']),
    name: getString(record, ['name', 'full_name', 'fullName']) || userDefaults.name,
    login: getString(record, ['login', 'username']),
    email: getString(record, ['email']),
    role: getString(record, ['role', 'user_role', 'role_name']) || userDefaults.role,
    partnerId: getString(record, ['partner_id', 'partnerId', 'member_id', 'code']) || userDefaults.partnerId,
    referralCode: getString(record, ['referral_code', 'referralCode', 'invite_code']) || userDefaults.referralCode,
    packageName,
    status: statusName,
    sponsor: sponsorName,
    registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || userDefaults.registrationDate,
    walletAvailable: getNumber(walletRecord, ['available', 'balance', 'amount']) ?? getNumber(record, ['wallet_available', 'available_balance', 'balance']) ?? userDefaults.walletAvailable,
    totalEarned: getNumber(walletRecord, ['total_earned', 'totalEarned', 'earned']) ?? getNumber(record, ['total_earned', 'totalEarned']) ?? userDefaults.totalEarned,
    personalPV: getNumber(record, ['personal_pv', 'personalPV', 'pv']) ?? userDefaults.personalPV,
    teamPV: getNumber(record, ['team_pv', 'teamPV', 'structure_pv']) ?? userDefaults.teamPV,
    bonusesTotal: getNumber(record, ['bonuses_total', 'bonusesTotal', 'bonus_balance']) ?? userDefaults.bonusesTotal,
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
