import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Eye, Filter, Network, Search } from 'lucide-react';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminUsers, getApiErrorState } from '../../lib/api';

interface AdminPartnerRow {
  id: string;
  fullName: string;
  phone: string;
  email: string;
  city: string;
  sponsor: string;
  invitedCount: number;
  package: string;
  status: string;
  personalPV: number;
  teamPV: number;
  totalIncome: number;
  availableBalance: number;
  registrationDate: string;
  activity: string;
  accountStatus: string;
}

export default function AdminPartners() {
  const [partners, setPartners] = useState<AdminPartnerRow[]>([]);
  const [query, setQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadUsers = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminUsers();
      setPartners(normalizePartners(response));
    } catch (caughtError) {
      setPartners([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить партнеров.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadUsers();
  }, []);

  const visiblePartners = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery) {
      return partners;
    }

    return partners.filter((partner) =>
      `${partner.id} ${partner.fullName} ${partner.phone} ${partner.email}`.toLowerCase().includes(normalizedQuery)
    );
  }, [partners, query]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin users</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Партнеры</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Пользователи, пакеты, PV, балансы и статус аккаунта.
            </p>
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard label="Всего партнеров" value={partners.length.toLocaleString('ru-RU')} />
        <SummaryCard label="Активные" value={partners.filter((partner) => partner.activity === 'Активен').length.toLocaleString('ru-RU')} />
        <SummaryCard label="VIP / ELITE" value={partners.filter((partner) => ['VIP', 'ELITE'].includes(partner.package)).length.toLocaleString('ru-RU')} />
        <SummaryCard label="Баланс" value={`${partners.reduce((sum, partner) => sum + partner.availableBalance, 0).toLocaleString('ru-RU')} ₸`} />
      </section>

      <section className="rounded-[28px] border border-safi-border bg-white p-4 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="flex flex-col gap-4 md:flex-row">
          <label className="relative flex-1">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-muted" />
            <input
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Поиск по ID, ФИО, телефону или email"
              className="w-full rounded-full border border-safi-border bg-safi-cream py-3 pl-12 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
            />
          </label>
          <button className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green">
            <Filter className="h-4 w-4" />
            Фильтры
          </button>
        </div>
      </section>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadUsers} />}
      {!isLoading && !error && visiblePartners.length === 0 && (
        <EmptyState title="Партнеры не найдены" description={query ? 'Попробуйте изменить поисковый запрос.' : 'Список появится после регистрации пользователей.'} />
      )}

      {!isLoading && !error && visiblePartners.length > 0 && (
        <AdminTable headers={['Партнер', 'Контакты', 'Спонсор', 'Пакет / статус', 'PV', 'Финансы', 'Аккаунт', 'Действия']}>
          {visiblePartners.map((partner) => (
            <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
              <td className="px-6 py-4">
                <Link to={`/admin/partners/${partner.id}`} className="block hover:opacity-80">
                  <div className="font-bold text-safi-green">{partner.fullName}</div>
                  <div className="mt-1 font-mono text-[10px] text-safi-muted">{partner.id}</div>
                  <div className="mt-1 text-[10px] text-safi-muted">Рег: {partner.registrationDate}</div>
                </Link>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm text-safi-green">{partner.phone}</div>
                <div className="mt-1 text-xs text-safi-muted">{partner.email}</div>
                <div className="mt-1 text-[10px] text-safi-muted">{partner.city}</div>
              </td>
              <td className="px-6 py-4">
                <div className="inline-block rounded-full bg-safi-cream px-3 py-1 font-mono text-xs font-bold text-safi-green">{partner.sponsor}</div>
                <div className="mt-1 text-[10px] text-safi-muted">Пригласил: {partner.invitedCount}</div>
              </td>
              <td className="px-6 py-4">
                <div className="mb-2"><AdminBadge variant="gold">{partner.package}</AdminBadge></div>
                <AdminBadge variant="default">{partner.status}</AdminBadge>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm">Л: <span className="font-bold text-safi-green">{partner.personalPV}</span></div>
                <div className="mt-1 text-xs text-safi-muted">К: {partner.teamPV}</div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold text-safi-green">Баланс: {partner.availableBalance.toLocaleString('ru-RU')}</div>
                <div className="mt-1 text-[10px] text-safi-muted">Всего: {partner.totalIncome.toLocaleString('ru-RU')}</div>
              </td>
              <td className="px-6 py-4">
                <div className="mb-2">
                  <AdminBadge variant={partner.accountStatus === 'Активен' ? 'success' : 'danger'}>{partner.accountStatus}</AdminBadge>
                </div>
                <AdminBadge variant={partner.activity === 'Активен' ? 'success' : 'warning'}>{partner.activity}</AdminBadge>
              </td>
              <td className="px-6 py-4 text-right">
                <div className="flex items-center justify-end gap-2">
                  <Link to={`/admin/partners/${partner.id}`} className="rounded-xl p-2 text-safi-muted transition-colors hover:bg-safi-cream hover:text-safi-green" title="Открыть профиль">
                    <Eye className="h-4 w-4" />
                  </Link>
                  <Link to="/admin/structure" className="rounded-xl p-2 text-safi-muted transition-colors hover:bg-safi-cream hover:text-safi-green" title="Структура">
                    <Network className="h-4 w-4" />
                  </Link>
                </div>
              </td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="mt-3 font-serif text-3xl font-semibold text-safi-green">{value}</div>
    </article>
  );
}

function normalizePartners(response: unknown): AdminPartnerRow[] {
  return getArray(response).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const packageRecord = isRecord(record.package) ? record.package : undefined;
    const sponsorRecord = isRecord(record.sponsor) ? record.sponsor : undefined;

    return {
      id: getString(record, ['partner_id', 'partnerId', 'code', 'id']) || `USER-${index + 1}`,
      fullName: getString(record, ['full_name', 'fullName', 'name']) || `Partner ${index + 1}`,
      phone: getString(record, ['phone', 'phone_number', 'phoneNumber']) || '-',
      email: getString(record, ['email']) || '-',
      city: getString(record, ['city']) || '-',
      sponsor: getString(sponsorRecord, ['partner_id', 'name', 'id']) || getString(record, ['sponsor_id', 'sponsorId']) || '-',
      invitedCount: getNumber(record, ['invited_count', 'invitedCount', 'children_count']) ?? 0,
      package: getString(packageRecord, ['name', 'title']) || getString(record, ['package_name', 'packageName', 'package']) || '-',
      status: getString(record, ['status_name', 'statusName', 'status']) || 'Участник',
      personalPV: getNumber(record, ['personal_pv', 'personalPV', 'pv']) ?? 0,
      teamPV: getNumber(record, ['team_pv', 'teamPV']) ?? 0,
      totalIncome: getNumber(record, ['total_income', 'totalIncome', 'total_earned']) ?? 0,
      availableBalance: getNumber(record, ['available_balance', 'availableBalance', 'balance']) ?? 0,
      registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || '-',
      activity: getString(record, ['activity', 'activity_status', 'activityStatus']) || 'Активен',
      accountStatus: getString(record, ['account_status', 'accountStatus', 'state']) || 'Активен',
    };
  });
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
  }

  return [];
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

function getNumber(record: Record<string, unknown>, keys: string[]) {
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
