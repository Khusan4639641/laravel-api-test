import { useCallback, useEffect, useState } from 'react';
import { Outlet, useNavigate, useOutletContext } from 'react-router-dom';
import { Bell, Menu } from 'lucide-react';
import { Sidebar } from './Sidebar';
import { LanguageSwitcher } from '../ui/LanguageSwitcher';
import { ApiError, clearAuthToken, getAuthToken, me } from '../../lib/api';
import { balance, bonuses, structure, user as mockUser } from '../../data/dashboardMock';

export interface DashboardCurrentUser {
  id?: string | number;
  name: string;
  login?: string;
  email?: string;
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
  isUsingMockUser: boolean;
  refreshCurrentUser: () => Promise<void>;
}

const fallbackUser: DashboardCurrentUser = {
  name: mockUser.name,
  partnerId: mockUser.partnerId,
  referralCode: mockUser.referralCode,
  packageName: mockUser.package,
  status: mockUser.status,
  sponsor: mockUser.sponsor,
  registrationDate: mockUser.registrationDate,
  walletAvailable: balance.available,
  totalEarned: balance.totalEarned,
  personalPV: 2500,
  teamPV: structure.leftPV + structure.rightPV,
  bonusesTotal: bonuses.referral + bonuses.binary + bonuses.status + bonuses.cashback,
};

export function useDashboardContext() {
  return useOutletContext<DashboardContextValue>();
}

export function DashboardLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [currentUser, setCurrentUser] = useState<DashboardCurrentUser>(fallbackUser);
  const [isUsingMockUser, setIsUsingMockUser] = useState(true);
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();

  const loadCurrentUser = useCallback(async () => {
    const token = getAuthToken();

    if (!token) {
      navigate('/login', { replace: true });
      return;
    }

    try {
      const response = await me();
      setCurrentUser(normalizeCurrentUser(response));
      setIsUsingMockUser(false);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearAuthToken();
        navigate('/login', { replace: true });
        return;
      }

      setCurrentUser(fallbackUser);
      setIsUsingMockUser(true);
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
            {isUsingMockUser && (
              <span className="hidden rounded-full border border-safi-border bg-white px-3 py-1 text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted sm:inline-flex">
                Mock fallback
              </span>
            )}
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
            <Outlet context={{ currentUser, isUsingMockUser, refreshCurrentUser: loadCurrentUser } satisfies DashboardContextValue} />
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
    || fallbackUser.packageName;
  const statusName = getString(statusRecord, ['name', 'title'])
    || getString(record, ['status_name', 'statusName', 'status'])
    || fallbackUser.status;
  const sponsorName = getString(sponsorRecord, ['name', 'full_name'])
    || getString(record, ['sponsor_name', 'sponsorName', 'sponsor'])
    || fallbackUser.sponsor;

  return {
    id: getString(record, ['id']) || getNumber(record, ['id']),
    name: getString(record, ['name', 'full_name', 'fullName']) || fallbackUser.name,
    login: getString(record, ['login', 'username']),
    email: getString(record, ['email']),
    partnerId: getString(record, ['partner_id', 'partnerId', 'member_id', 'code']) || fallbackUser.partnerId,
    referralCode: getString(record, ['referral_code', 'referralCode', 'invite_code']) || fallbackUser.referralCode,
    packageName,
    status: statusName,
    sponsor: sponsorName,
    registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || fallbackUser.registrationDate,
    walletAvailable: getNumber(walletRecord, ['available', 'balance', 'amount']) ?? getNumber(record, ['wallet_available', 'available_balance', 'balance']) ?? fallbackUser.walletAvailable,
    totalEarned: getNumber(walletRecord, ['total_earned', 'totalEarned', 'earned']) ?? getNumber(record, ['total_earned', 'totalEarned']) ?? fallbackUser.totalEarned,
    personalPV: getNumber(record, ['personal_pv', 'personalPV', 'pv']) ?? fallbackUser.personalPV,
    teamPV: getNumber(record, ['team_pv', 'teamPV', 'structure_pv']) ?? fallbackUser.teamPV,
    bonusesTotal: getNumber(record, ['bonuses_total', 'bonusesTotal', 'bonus_balance']) ?? fallbackUser.bonusesTotal,
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
