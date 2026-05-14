import { Link, useLocation } from 'react-router-dom';
import { cn } from '../../lib/utils';
import { LayoutDashboard, Users, CreditCard, Gift, Star, UserCircle, HelpCircle, LogOut, ShoppingBag, Newspaper } from 'lucide-react';
import { logout } from '../../lib/api';

const menuItems = [
  { path: '/dashboard', name: 'Обзор', icon: LayoutDashboard },
  { path: '/dashboard/structure', name: 'Структура', icon: Users },
  { path: '/dashboard/transactions', name: 'Транзакции', icon: CreditCard },
  { path: '/dashboard/bonuses', name: 'Бонусы', icon: Gift },
  { path: '/dashboard/package-status', name: 'Пакет', icon: Star },
  { path: '/dashboard/products', name: 'Продукты', icon: ShoppingBag },
  { path: '/dashboard/news', name: 'Новости', icon: Newspaper },
  { path: '/dashboard/profile', name: 'Профиль', icon: UserCircle },
  { path: '/dashboard/support', name: 'Поддержка', icon: HelpCircle },
];

interface SidebarUser {
  name: string;
  partnerId: string;
  packageName: string;
  status: string;
}

export function Sidebar({ isOpen, onClose, currentUser }: { isOpen: boolean; onClose: () => void; currentUser: SidebarUser }) {
  const location = useLocation();

  return (
    <>
      {isOpen && (
        <button
          type="button"
          aria-label="Закрыть меню"
          className="fixed inset-0 z-40 bg-safi-green/30 backdrop-blur-sm lg:hidden"
          onClick={onClose}
        />
      )}

      <aside
        className={cn(
          'fixed left-0 top-0 z-50 flex h-full w-[280px] flex-col overflow-y-auto border-r border-safi-border bg-white transition-transform duration-300',
          isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        )}
      >
        <div className="flex h-20 shrink-0 items-center border-b border-safi-border px-6">
          <Link to="/" className="flex items-center gap-3" onClick={onClose}>
            <img
              alt="Safi Life"
              src="https://napaxiong.wordpress.com/wp-content/uploads/2026/04/safi-life.png"
              className="h-10 w-[112px] object-contain"
            />
          </Link>
        </div>

        <div className="border-b border-safi-border px-5 py-6">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-green font-serif text-xl font-semibold text-safi-gold">
              {currentUser.name.charAt(0)}
            </div>
            <div className="min-w-0">
              <div className="truncate text-sm font-extrabold text-safi-green">{currentUser.name}</div>
              <div className="mt-1 truncate text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">{currentUser.partnerId}</div>
            </div>
          </div>
          <div className="mt-5 grid grid-cols-2 gap-2">
            <div className="rounded-2xl border border-safi-border bg-safi-cream px-3 py-2">
              <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">Пакет</div>
              <div className="mt-1 truncate font-serif text-lg font-semibold text-safi-green">{currentUser.packageName}</div>
            </div>
            <div className="rounded-2xl border border-safi-border bg-safi-cream px-3 py-2">
              <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">Статус</div>
              <div className="mt-1 truncate font-serif text-lg font-semibold text-safi-green">{currentUser.status}</div>
            </div>
          </div>
        </div>

        <nav className="flex flex-1 flex-col gap-2 px-4 py-6">
          <div className="mb-2 pl-4 text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-muted">Навигация</div>
          {menuItems.map((item) => {
            const Icon = item.icon;
            const isActive = location.pathname === item.path || (item.path !== '/dashboard' && location.pathname.startsWith(item.path));

            return (
              <Link
                key={item.path}
                to={item.path}
                onClick={onClose}
                className={cn(
                  'flex items-center gap-3 rounded-2xl px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] transition-all',
                  isActive
                    ? 'bg-safi-green text-white shadow-[0_16px_34px_rgba(11,23,18,0.16)]'
                    : 'text-safi-muted hover:bg-safi-cream hover:text-safi-green'
                )}
              >
                <Icon className={cn('h-5 w-5 shrink-0', isActive ? 'text-safi-gold' : 'text-current')} />
                {item.name}
              </Link>
            );
          })}
        </nav>

        <div className="flex shrink-0 flex-col gap-2 border-t border-safi-border p-4">
          <Link
            to="/admin"
            className="flex w-full items-center justify-center rounded-2xl border border-safi-green bg-safi-green px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-gold transition-colors hover:bg-safi-green-hover"
          >
            Админ-панель
          </Link>
          <button
            type="button"
            onClick={() => {
              onClose();
              void logout();
            }}
            className="flex w-full items-center justify-center gap-3 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-red-600 transition-colors hover:border-red-200 hover:bg-red-100"
          >
            <LogOut className="h-5 w-5" />
            Выйти
          </button>
        </div>
      </aside>
    </>
  );
}
