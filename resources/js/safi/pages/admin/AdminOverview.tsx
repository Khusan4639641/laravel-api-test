import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, ArrowUpCircle, CreditCard, FileText, Package, Search, Settings, TrendingUp, Users } from 'lucide-react';
import { adminStats, partners as mockPartners, withdrawals as mockWithdrawals } from '../../data/adminMock';
import { AdminBadge, AdminStatCard } from '../../components/admin/ui';
import { getAdminUsers, getAdminWithdrawals } from '../../lib/api';

export default function AdminOverview() {
  const [usersCount, setUsersCount] = useState(adminStats.totalPartners);
  const [activeUsersCount, setActiveUsersCount] = useState(adminStats.activePartners);
  const [withdrawalsCount, setWithdrawalsCount] = useState(adminStats.withdrawalRequests);
  const [pendingWithdrawalsAmount, setPendingWithdrawalsAmount] = useState(adminStats.pendingWithdrawals);
  const [isUsingFallback, setIsUsingFallback] = useState(true);

  useEffect(() => {
    let isMounted = true;

    async function loadSummary() {
      try {
        const [usersResponse, withdrawalsResponse] = await Promise.all([
          getAdminUsers(),
          getAdminWithdrawals(),
        ]);
        const users = getArray(usersResponse);
        const withdrawals = getArray(withdrawalsResponse);

        if (!isMounted) {
          return;
        }

        if (users.length > 0) {
          setUsersCount(users.length);
          setActiveUsersCount(users.filter(isActiveUser).length);
        }

        if (withdrawals.length > 0) {
          setWithdrawalsCount(withdrawals.filter(isPendingWithdrawal).length);
          setPendingWithdrawalsAmount(withdrawals.filter(isPendingWithdrawal).reduce((sum, withdrawal) => sum + getAmount(withdrawal), 0));
        }

        setIsUsingFallback(false);
      } catch {
        if (isMounted) {
          setUsersCount(mockPartners.length || adminStats.totalPartners);
          setActiveUsersCount(mockPartners.filter((partner) => partner.activity === 'Активен').length || adminStats.activePartners);
          setWithdrawalsCount(mockWithdrawals.length || adminStats.withdrawalRequests);
          setPendingWithdrawalsAmount(adminStats.pendingWithdrawals);
          setIsUsingFallback(true);
        }
      }
    }

    void loadSummary();

    return () => {
      isMounted = false;
    };
  }, []);

  const cards = useMemo(() => [
    { title: 'Всего партнеров', value: usersCount.toLocaleString('ru-RU'), icon: Users, trend: `Активные: ${activeUsersCount.toLocaleString('ru-RU')}` },
    { title: 'Общий оборот', value: `${adminStats.totalRevenue.toLocaleString('ru-RU')} ₸`, icon: TrendingUp },
    { title: 'Выплачено бонусов', value: `${adminStats.totalBonusesPaid.toLocaleString('ru-RU')} ₸`, icon: CreditCard },
    { title: 'Ожидает вывода', value: `${pendingWithdrawalsAmount.toLocaleString('ru-RU')} ₸`, icon: ArrowUpCircle, trend: `${withdrawalsCount} заявок`, className: 'border-safi-gold/30 bg-safi-gold/5' },
    { title: 'Активные партнеры', value: activeUsersCount.toLocaleString('ru-RU'), icon: Activity },
    { title: 'Неактивные партнеры', value: Math.max(usersCount - activeUsersCount, 0).toLocaleString('ru-RU'), icon: Users },
    { title: 'Продано пакетов', value: adminStats.packagesSold.toLocaleString('ru-RU'), icon: Package },
    { title: 'Общий PV', value: `${adminStats.totalPV.toLocaleString('ru-RU')} PV`, icon: Activity },
  ], [activeUsersCount, pendingWithdrawalsAmount, usersCount, withdrawalsCount]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin dashboard</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Сводка</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Общая статистика платформы Safi Life по партнерам, обороту и заявкам.
            </p>
          </div>
          {isUsingFallback && <AdminBadge variant="warning">Mock fallback</AdminBadge>}
        </div>
      </section>

      <section className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <AdminStatCard
            key={card.title}
            title={card.title}
            value={card.value}
            icon={card.icon}
            trend={card.trend}
            className={card.className}
          />
        ))}
      </section>

      <section className="grid gap-8 lg:grid-cols-[1.25fr_0.75fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-8 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="flex min-h-72 flex-col items-center justify-center rounded-[28px] border border-dashed border-safi-border bg-safi-cream p-8 text-center">
            <TrendingUp className="mb-4 h-12 w-12 text-safi-gold" />
            <h2 className="font-serif text-3xl font-semibold text-safi-green">График роста структуры</h2>
            <p className="mt-3 max-w-md text-sm leading-7 text-safi-muted">
              Здесь будет отображаться динамика партнеров, оборота и PV после подключения аналитического endpoint.
            </p>
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-green bg-safi-green p-8 text-white shadow-[0_18px_48px_rgba(11,23,18,0.08)]">
          <h2 className="font-serif text-3xl font-semibold text-white">Быстрые действия</h2>
          <div className="mt-7 space-y-3">
            <QuickLink to="/admin/partners" icon={<Search className="h-4 w-4" />} label="Найти партнера" />
            <QuickLink to="/admin/withdrawals" icon={<ArrowUpCircle className="h-4 w-4" />} label={`Заявки на вывод: ${withdrawalsCount}`} />
            <QuickLink to="/admin/transactions" icon={<CreditCard className="h-4 w-4" />} label="Транзакции" />
            <QuickLink to="/admin/reports" icon={<FileText className="h-4 w-4" />} label="Отчеты" />
            <QuickLink to="/admin/settings" icon={<Settings className="h-4 w-4" />} label="Настройки системы" />
          </div>
        </article>
      </section>
    </div>
  );
}

function QuickLink({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
  return (
    <Link to={to} className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.08] p-4 text-sm font-bold text-white/90 transition-colors hover:bg-white/15">
      <span className="text-safi-gold">{icon}</span>
      {label}
    </Link>
  );
}

function getArray(response: unknown) {
  if (Array.isArray(response)) {
    return response;
  }

  if (isRecord(response)) {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (isRecord(response.data) && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    if (Array.isArray(response.users)) {
      return response.users;
    }

    if (Array.isArray(response.withdrawals)) {
      return response.withdrawals;
    }
  }

  return [];
}

function isActiveUser(user: unknown) {
  if (!isRecord(user)) {
    return false;
  }

  const value = String(user.activity || user.account_status || user.status || '').toLowerCase();
  return value.includes('active') || value.includes('актив');
}

function isPendingWithdrawal(withdrawal: unknown) {
  if (!isRecord(withdrawal)) {
    return false;
  }

  const status = String(withdrawal.status || '').toLowerCase();
  return ['pending', 'new', 'processing', 'новая заявка', 'в обработке'].includes(status);
}

function getAmount(withdrawal: unknown) {
  if (!isRecord(withdrawal)) {
    return 0;
  }

  const value = withdrawal.amount || withdrawal.sum;

  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  return 0;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
