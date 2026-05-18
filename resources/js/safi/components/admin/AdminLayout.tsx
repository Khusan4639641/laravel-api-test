import { useCallback, useEffect, useState } from 'react';
import { Outlet, useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { Bell, Menu, Search, User } from 'lucide-react';
import { AdminSidebar } from './AdminSidebar';
import { ApiError, clearAuthToken, getAuthToken, me } from '../../lib/api';

export interface AdminCurrentUser {
  id?: string | number;
  name: string;
  email?: string;
  role: string;
  raw?: unknown;
}

export interface AdminContextValue {
  currentUser: AdminCurrentUser;
  refreshCurrentUser: () => Promise<void>;
}

export function useAdminContext() {
  return useOutletContext<AdminContextValue>();
}

const adminDefaults: AdminCurrentUser = {
  name: 'Super Admin',
  role: 'super_admin',
};

export function AdminLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [currentUser, setCurrentUser] = useState<AdminCurrentUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();
  const location = useLocation();

  const loadCurrentUser = useCallback(async () => {
    const token = getAuthToken();

    if (!token) {
      setIsLoading(false);
      navigate('/login', { replace: true });
      return;
    }

    setIsLoading(true);

    try {
      const response = await me();
      const adminUser = normalizeAdminUser(response);

      if (!isBackoffice(adminUser)) {
        setIsLoading(false);
        navigate('/dashboard', { replace: true });
        return;
      }

      setCurrentUser(adminUser);
      if (isSupportOnly(adminUser) && !isSupportArea(location.pathname)) {
        navigate('/support', { replace: true });
      }
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearAuthToken();
        setIsLoading(false);
        navigate('/login', { replace: true });
        return;
      }

      setIsLoading(false);
      navigate('/dashboard', { replace: true });
      return;
    } finally {
      setIsLoading(false);
    }
  }, [location.pathname, navigate]);

  useEffect(() => {
    void loadCurrentUser();
  }, [loadCurrentUser]);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-safi-bg px-5 text-center text-safi-green">
        <div>
          <div className="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-safi-border border-t-safi-gold" />
          <div className="font-serif text-2xl font-semibold">Проверка доступа</div>
        </div>
      </div>
    );
  }

  if (!currentUser) {
    return null;
  }

  if (isSupportOnly(currentUser) && !isSupportArea(location.pathname)) {
    return null;
  }

  return (
    <div className="flex min-h-screen bg-safi-bg text-safi-green">
      <AdminSidebar isOpen={isSidebarOpen} onClose={() => setIsSidebarOpen(false)} currentUser={currentUser} />

      <div className="relative flex min-h-screen max-w-full flex-1 flex-col overflow-hidden xl:ml-[280px]">
        <header className="sticky top-0 z-30 flex h-20 shrink-0 items-center justify-between border-b border-safi-border/80 bg-safi-bg/90 px-4 backdrop-blur-xl md:px-8">
          <div className="flex items-center gap-4">
            <button
              type="button"
              className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:bg-safi-cream xl:hidden"
              onClick={() => setIsSidebarOpen(true)}
              aria-label="Открыть меню"
            >
              <Menu className="h-5 w-5" />
            </button>
            <label className="relative hidden md:flex">
              <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
              <input
                type="text"
                placeholder="Глобальный поиск"
                className="w-80 rounded-full border border-safi-border bg-white py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none transition-colors placeholder:text-safi-muted/60 focus:border-safi-green"
              />
            </label>
          </div>

          <div className="flex items-center gap-4">
            <button
              type="button"
              className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green transition-colors hover:bg-safi-green hover:text-white"
              aria-label="Уведомления"
            >
              <Bell className="h-5 w-5" />
            </button>
            <div className="hidden items-center gap-3 border-l border-safi-border pl-4 sm:flex">
              <div className="text-right">
                <div className="text-sm font-extrabold text-safi-green">{currentUser.name}</div>
                <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">{roleLabel(currentUser.role)}</div>
              </div>
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-green text-safi-gold">
                <User className="h-5 w-5" />
              </div>
            </div>
          </div>
        </header>

        <main className="relative flex-1 overflow-x-hidden overflow-y-auto p-4 md:p-8">
          <div className="relative mx-auto w-full max-w-[1600px] pb-20">
            <Outlet context={{ currentUser, refreshCurrentUser: loadCurrentUser } satisfies AdminContextValue} />
          </div>
        </main>
      </div>
    </div>
  );
}

function normalizeAdminUser(response: unknown): AdminCurrentUser {
  const record = unwrapRecord(response);
  const role = extractRole(record);

  return {
    id: getValue(record, ['id']) as string | number | undefined,
    name: getString(record, ['name', 'full_name', 'fullName']) || adminDefaults.name,
    email: getString(record, ['email']),
    role,
    raw: response,
  };
}

function isBackoffice(user: AdminCurrentUser) {
  return ['super_admin', 'support'].includes(user.role.toLowerCase());
}

function isSupportOnly(user: AdminCurrentUser) {
  return user.role.toLowerCase() === 'support';
}

function isSupportArea(pathname: string) {
  return pathname === '/support'
    || pathname.startsWith('/support/')
    || pathname === '/admin/support'
    || pathname.startsWith('/admin/support/');
}

function roleLabel(role: string) {
  if (role === 'super_admin') {
    return 'Super Admin';
  }

  if (role === 'support') {
    return 'Support';
  }

  return 'Пользователь';
}

function extractRole(record: Record<string, unknown>) {
  const directRole = getString(record, ['role', 'user_role', 'role_name', 'type']);

  if (directRole) {
    return directRole;
  }

  const roleRecord = getValue(record, ['role']);

  if (isRecord(roleRecord)) {
    return getString(roleRecord, ['name', 'slug']) || 'user';
  }

  const roles = getValue(record, ['roles']);

  if (Array.isArray(roles)) {
    const adminRole = roles.find((role) => {
      if (typeof role === 'string') {
        return isBackoffice({ name: '', role });
      }

      if (isRecord(role)) {
        return isBackoffice({ name: '', role: getString(role, ['name', 'slug']) || '' });
      }

      return false;
    });

    if (typeof adminRole === 'string') {
      return adminRole;
    }

    if (isRecord(adminRole)) {
      return getString(adminRole, ['name', 'slug']) || 'user';
    }
  }

  if (getValue(record, ['is_admin', 'isAdmin']) === true) {
    return 'admin';
  }

  return 'user';
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

function getString(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = getValue(record, [key]);

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

function getValue(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    if (key in record) {
      return record[key];
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
