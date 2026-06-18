import { useCallback, useEffect, useState } from 'react';
import { Copy, Filter, Search, Users } from 'lucide-react';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getArray, getDashboardStructure, getNumber, getString } from '../../lib/api';
import { accountStatusLabel, mlmStatusLabel, packageLabel } from '../../lib/systemLabels';
import { buildReferralBranchUrl, type ReferralBranch } from '../../lib/referrals';

type BranchFilter = 'all' | 'left' | 'right';
type PaginationItem = number | 'ellipsis';

interface StructurePartnerRow {
  name: string;
  id: string;
  login: string;
  email: string;
  phone: string;
  line: number;
  branch: string;
  package: string;
  status: string;
  personalPV: number;
  teamPV: number;
  activity: string;
  createdAt: string;
}

interface PartnersMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

const defaultPartnersMeta: PartnersMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
};
const perPageOptions = [10, 25, 50];

export default function Structure() {
  const { currentUser } = useDashboardContext();
  const [query, setQuery] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [branchFilter, setBranchFilter] = useState<BranchFilter>('all');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [isLoading, setIsLoading] = useState(true);
  const [isPartnersLoading, setIsPartnersLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [structure, setStructure] = useState({ totalPartners: 0, leftPartners: 0, rightPartners: 0, leftPV: 0, rightPV: 0, weakLegPV: 0, weakLeg: 'left' });
  const [partners, setPartners] = useState<StructurePartnerRow[]>([]);
  const [partnersMeta, setPartnersMeta] = useState<PartnersMeta>(defaultPartnersMeta);
  const [canInvite, setCanInvite] = useState(currentUser.canInvite);
  const [referralLinks, setReferralLinks] = useState<Record<ReferralBranch, string>>({
    left: currentUser.canInvite ? buildReferralBranchUrl(currentUser.referralCode, 'left') : '',
    right: currentUser.canInvite ? buildReferralBranchUrl(currentUser.referralCode, 'right') : '',
  });

  const loadStructure = useCallback(async () => {
    setIsPartnersLoading(true);
    setError(null);

    try {
      const response = await getDashboardStructure({
        ...(searchTerm ? { search: searchTerm } : {}),
        ...(branchFilter !== 'all' ? { branch: branchFilter } : {}),
        page,
        per_page: perPage,
      });
      const record = response && typeof response === 'object' ? response as Record<string, unknown> : {};
      const summaryRecord = record.summary && typeof record.summary === 'object' ? record.summary as Record<string, unknown> : {};
      const structureRecord = record.structure && typeof record.structure === 'object' ? record.structure as Record<string, unknown> : summaryRecord;
      const referralLinksRecord = record.referral_links && typeof record.referral_links === 'object' ? record.referral_links as Record<string, unknown> : {};
      const inviteAvailable = typeof record.can_invite === 'boolean'
        ? record.can_invite
        : typeof structureRecord.can_invite === 'boolean'
          ? structureRecord.can_invite
          : currentUser.canInvite;
      const list = getStructurePartners(record).map((item, index) => {
        const node = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const nestedUser = node.user && typeof node.user === 'object' ? node.user as Record<string, unknown> : null;
        const user = nestedUser || node;
        const packageRecord = user.package && typeof user.package === 'object'
          ? user.package as Record<string, unknown>
          : user.current_package && typeof user.current_package === 'object'
            ? user.current_package as Record<string, unknown>
            : node.package && typeof node.package === 'object'
              ? node.package as Record<string, unknown>
              : {};
        const branch = branchLabel(getString(node, ['branch', 'position']) || getString(user, ['branch', 'position']));
        const personalPV = getNumber(user, ['personal_pv', 'personalPV', 'package_activity_pv', 'packageActivityPv'])
          ?? getNumber(packageRecord, ['activity_pv', 'activityPv', 'pv'])
          ?? 0;
        const leftPV = getNumber(user, ['left_pv']) ?? 0;
        const rightPV = getNumber(user, ['right_pv']) ?? 0;
        const id = getString(user, ['id']) || getString(node, ['user_id', 'userId']) || String(index + 1);

        return {
          name: getString(user, ['name']) || `Partner ${index + 1}`,
          id,
          login: getString(user, ['login']) || '',
          email: getString(user, ['email']) || '',
          phone: getString(user, ['phone']) || '',
          line: getNumber(node, ['line', 'level', 'depth']) ?? getNumber(user, ['line', 'level', 'depth']) ?? 0,
          branch,
          package: packageLabel(getString(packageRecord, ['code', 'slug', 'id']), getString(packageRecord, ['code_label', 'codeLabel', 'label', 'name']) || '-'),
          status: mlmStatusLabel(getString(user, ['status']), getString(user, ['status_label', 'statusLabel']) || '-'),
          personalPV,
          teamPV: getNumber(user, ['team_pv', 'teamPV']) ?? leftPV + rightPV,
          activity: accountStatusLabel(getString(user, ['account_status', 'accountStatus']), getString(user, ['account_status_label', 'accountStatusLabel']) || accountStatusLabel('active')),
          createdAt: formatDate(getString(user, ['registered_at', 'registeredAt', 'created_at', 'createdAt']) || getString(node, ['registered_at', 'registeredAt', 'created_at', 'createdAt'])),
        };
      });
      setPartners(list);
      setPartnersMeta(getPartnersMeta(record));
      setCanInvite(inviteAvailable);
      setReferralLinks({
        left: inviteAvailable ? (getString(referralLinksRecord, ['left']) || buildReferralBranchUrl(currentUser.referralCode, 'left')) : '',
        right: inviteAvailable ? (getString(referralLinksRecord, ['right']) || buildReferralBranchUrl(currentUser.referralCode, 'right')) : '',
      });
      const leftPV = getNumber(structureRecord, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
      const rightPV = getNumber(structureRecord, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;
      setStructure({
        totalPartners: getNumber(structureRecord, ['total_partners']) ?? list.length,
        leftPartners: getNumber(structureRecord, ['left_count', 'left_partners']) ?? list.filter((partner) => partner.branch === 'Левая ветка').length,
        rightPartners: getNumber(structureRecord, ['right_count', 'right_partners']) ?? list.filter((partner) => partner.branch === 'Правая ветка').length,
        leftPV,
        rightPV,
        weakLegPV: getNumber(structureRecord, ['weak_leg_pv', 'weakLegPv', 'weak_leg_branch_pv', 'weakLegBranchPv'])
          ?? Math.min(leftPV, rightPV),
        weakLeg: getString(structureRecord, ['weak_leg']) || 'left',
      });
    } catch (caughtError) {
      setPartners([]);
      setPartnersMeta(defaultPartnersMeta);
      setStructure({ totalPartners: 0, leftPartners: 0, rightPartners: 0, leftPV: 0, rightPV: 0, weakLegPV: 0, weakLeg: 'left' });
      setCanInvite(false);
      setReferralLinks({ left: '', right: '' });
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
      setIsPartnersLoading(false);
    }
  }, [branchFilter, currentUser.canInvite, currentUser.referralCode, page, perPage, searchTerm]);

  useEffect(() => {
    void loadStructure();
  }, [loadStructure]);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setPage(1);
      setSearchTerm(query.trim());
    }, query.trim() ? 300 : 0);

    return () => window.clearTimeout(timeout);
  }, [query]);

  const visiblePartners = partners;
  const hasActiveListFilter = searchTerm !== '' || query.trim() !== '' || branchFilter !== 'all';
  const hasStructureListMismatch = structure.totalPartners > 0 && partners.length === 0 && !hasActiveListFilter;
  const paginationItems = getPaginationItems(partnersMeta.current_page, partnersMeta.last_page);
  const canGoPrev = partnersMeta.current_page > 1 && !isPartnersLoading;
  const canGoNext = partnersMeta.current_page < partnersMeta.last_page && !isPartnersLoading;
  const changePage = (nextPage: number) => {
    if (nextPage < 1 || nextPage > partnersMeta.last_page || nextPage === partnersMeta.current_page || isPartnersLoading) {
      return;
    }

    setPage(nextPage);
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Structure</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Моя структура</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Бинарная структура, реферальный код и список партнеров.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">Пакет: {currentUser.packageName}</Badge>
            <Badge variant="default">Статус: {currentUser.status}</Badge>
          </div>
        </div>
      </section>

      {isLoading && (
        <LoadingState title="Загружаем структуру" description="Получаем бинарное дерево и партнеров из API." />
      )}

      {!isLoading && error && (
        <ErrorState description={error} onRetry={loadStructure} />
      )}

      {!isLoading && !error && (
        <>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard title="Всего партнеров" value={structure.totalPartners} icon={<Users className="h-5 w-5" />} />
        <StatCard title="Малая ветка PV" value={`${structure.weakLegPV.toLocaleString('ru-RU')} PV`} />
        <BranchCard title="Левая ветка" partners={structure.leftPartners} pv={structure.leftPV} weak={structure.weakLeg === 'left'} />
        <BranchCard title="Правая ветка" partners={structure.rightPartners} pv={structure.rightPV} weak={structure.weakLeg === 'right'} />
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">Реферальные ссылки</h2>
          <div className="mt-6 space-y-4">
            <ReferralBox label="Левая ветка" link={canInvite ? referralLinks.left : ''} />
            <ReferralBox label="Правая ветка" link={canInvite ? referralLinks.right : ''} />
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">Бинарное дерево</h2>
          <div className="mt-6 rounded-[28px] border border-dashed border-safi-border bg-safi-cream p-6">
            <div className="mx-auto max-w-lg">
              <Node name={currentUser.name} label={currentUser.partnerId} root />
              <div className="mx-auto h-8 w-px bg-safi-border" />
              <div className="grid grid-cols-2 gap-5">
                <Node name="Левая ветка" label={`${structure.leftPartners} партнеров`} />
                <Node name="Правая ветка" label={`${structure.rightPartners} партнеров`} />
              </div>
            </div>
          </div>
        </article>
      </section>

      <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="border-b border-safi-border p-6 md:p-7">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Список партнеров</h2>
            <div className="flex flex-col gap-3 md:flex-row">
              <label className="relative min-w-0 flex-1 md:w-80 md:flex-none">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
                <input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Поиск по ID, имени, login, email или телефону"
                  className="w-full rounded-full border border-safi-border bg-safi-cream py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
                />
              </label>
              <label className="relative md:w-48">
                <Filter className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
                <select
                  value={branchFilter}
                  onChange={(event) => {
                    setBranchFilter(event.target.value as BranchFilter);
                    setPage(1);
                  }}
                  className="h-12 w-full cursor-pointer rounded-full border border-safi-border bg-safi-cream py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
                  aria-label="Фильтр ветки"
                >
                  <option value="all">Все ветки</option>
                  <option value="left">Левая ветка</option>
                  <option value="right">Правая ветка</option>
                </select>
              </label>
            </div>
          </div>
        </div>

        <div className="relative overflow-x-auto">
          {isPartnersLoading && !isLoading && (
            <div className="absolute inset-x-0 top-0 z-10 h-1 overflow-hidden bg-safi-cream">
              <div className="h-full w-1/3 animate-pulse rounded-full bg-safi-gold" />
            </div>
          )}
          <table className="w-full min-w-[860px] text-left">
            <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
              <tr>
                <th className="px-7 py-4">Партнер</th>
                <th className="px-7 py-4">Ветка / линия</th>
                <th className="px-7 py-4">Пакет / статус</th>
                <th className="px-7 py-4 text-right">PV</th>
                <th className="px-7 py-4 text-center">Активность</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-safi-border text-sm">
              {visiblePartners.length === 0 && !hasStructureListMismatch && (
                <tr>
                  <td colSpan={5} className="px-7 py-8">
                    <EmptyState
                      title={structure.totalPartners > 0 ? 'По вашему запросу партнёры не найдены' : 'Партнёров пока нет'}
                      description={structure.totalPartners > 0 ? 'Попробуйте изменить поиск или фильтр ветки.' : 'Партнёры появятся в списке после добавления в бинарную структуру.'}
                      className="min-h-[180px] shadow-none"
                    />
                  </td>
                </tr>
              )}

              {hasStructureListMismatch && (
                <tr>
                  <td colSpan={5} className="px-7 py-8">
                    <div className="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm font-bold text-amber-800">
                      Не удалось загрузить список структуры
                    </div>
                  </td>
                </tr>
              )}

              {visiblePartners.map((partner) => (
                <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
                  <td className="px-7 py-5">
                    <div className="font-extrabold text-safi-green">{partner.name}</div>
                    <div className="mt-1 font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">ID: {partner.id}</div>
                    {partner.login && <div className="mt-1 text-xs font-bold text-safi-muted">login: {partner.login}</div>}
                    {partner.email && <div className="mt-1 text-xs text-safi-muted">{partner.email}</div>}
                    {partner.phone && <div className="mt-1 text-xs text-safi-muted">{partner.phone}</div>}
                  </td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{partner.branch}</div>
                    <div className="mt-1 text-xs text-safi-muted">Линия: {partner.line}</div>
                  </td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{partner.package}</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.status !== '-' ? partner.status : 'Участник'}</div>
                  </td>
                  <td className="px-7 py-5 text-right">
                    <div className="font-extrabold text-safi-gold">Личный PV: {partner.personalPV.toLocaleString('ru-RU')}</div>
                    <div className="mt-1 text-xs text-safi-muted">Командный PV: {partner.teamPV.toLocaleString('ru-RU')}</div>
                  </td>
                  <td className="px-7 py-5 text-center">
                    <Badge variant={partner.activity === 'Активен' ? 'success' : 'default'}>{partner.activity}</Badge>
                    {partner.createdAt && <div className="mt-2 text-xs text-safi-muted">{partner.createdAt}</div>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col gap-4 border-t border-safi-border bg-white px-5 py-4 md:px-7">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="text-sm font-bold text-safi-muted">
              Найдено: <span className="text-safi-green">{partnersMeta.total.toLocaleString('ru-RU')}</span>
              {partnersMeta.total > 0 && (
                <span className="ml-2">
                  {partnersMeta.from}–{partnersMeta.to}
                </span>
              )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <label className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                Показывать по
                <select
                  value={perPage}
                  onChange={(event) => {
                    setPerPage(Number(event.target.value));
                    setPage(1);
                  }}
                  disabled={isPartnersLoading}
                  className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60"
                  aria-label="Показывать по"
                >
                  {perPageOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>

              <div className="flex w-full items-center justify-between gap-2 sm:w-auto lg:hidden">
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page - 1)}
                  disabled={!canGoPrev}
                  className="rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Назад
                </button>
                <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                  Страница {partnersMeta.current_page} из {partnersMeta.last_page}
                </div>
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page + 1)}
                  disabled={!canGoNext}
                  className="rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Вперёд
                </button>
              </div>

              <div className="hidden flex-wrap items-center gap-1 lg:flex">
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page - 1)}
                  disabled={!canGoPrev}
                  className="flex h-9 min-w-9 items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-sm font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Назад"
                >
                  ‹
                </button>
                {paginationItems.map((item, index) => item === 'ellipsis' ? (
                  <span
                    key={`ellipsis-${index}`}
                    className="flex h-9 min-w-9 items-center justify-center px-2 text-sm font-extrabold text-safi-muted"
                    aria-hidden="true"
                  >
                    …
                  </span>
                ) : (
                  <button
                    key={item}
                    type="button"
                    onClick={() => changePage(item)}
                    disabled={item === partnersMeta.current_page || isPartnersLoading}
                    aria-current={item === partnersMeta.current_page ? 'page' : undefined}
                    className={[
                      'flex h-9 min-w-9 items-center justify-center rounded-full border px-3 text-xs font-extrabold transition-colors disabled:cursor-default',
                      item === partnersMeta.current_page
                        ? 'border-safi-green bg-safi-green text-white shadow-[0_8px_22px_rgba(29,78,54,0.18)]'
                        : 'border-safi-border bg-safi-cream text-safi-green hover:border-safi-green hover:bg-white disabled:opacity-60',
                    ].join(' ')}
                  >
                    {item}
                  </button>
                ))}
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page + 1)}
                  disabled={!canGoNext}
                  className="flex h-9 min-w-9 items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-sm font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Вперёд"
                >
                  ›
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
        </>
      )}
    </div>
  );
}

function branchLabel(branch?: string) {
  const normalized = String(branch || '').toLowerCase();

  if (['r', 'right', 'правая'].includes(normalized)) {
    return 'Правая ветка';
  }

  if (['l', 'left', 'левая'].includes(normalized)) {
    return 'Левая ветка';
  }

  return 'Не указана';
}

function getStructurePartners(record: Record<string, unknown>) {
  const partners = record.partners;

  if (Array.isArray(partners)) {
    return partners;
  }

  if (partners && typeof partners === 'object') {
    return getArray(partners);
  }

  return getArray(record, ['partners', 'downline', 'structure_partners']);
}

function getPartnersMeta(record: Record<string, unknown>): PartnersMeta {
  const partners = record.partners && typeof record.partners === 'object'
    ? record.partners as Record<string, unknown>
    : {};
  const meta = partners.meta && typeof partners.meta === 'object'
    ? partners.meta as Record<string, unknown>
    : record.meta && typeof record.meta === 'object'
      ? record.meta as Record<string, unknown>
      : {};
  const total = getNumber(meta, ['total']) ?? 0;
  const perPage = getNumber(meta, ['per_page', 'perPage']) ?? 10;
  const lastPage = getNumber(meta, ['last_page', 'lastPage']) ?? Math.max(1, Math.ceil(total / perPage));

  return {
    current_page: getNumber(meta, ['current_page', 'currentPage']) ?? 1,
    last_page: Math.max(1, lastPage),
    per_page: perPage,
    total,
    from: getNumber(meta, ['from']) ?? 0,
    to: getNumber(meta, ['to']) ?? 0,
  };
}

function getPaginationItems(currentPage: number, totalPages: number): PaginationItem[] {
  const safeTotalPages = Math.max(1, Math.floor(totalPages));
  const safeCurrentPage = Math.min(Math.max(Math.floor(currentPage), 1), safeTotalPages);

  if (safeTotalPages <= 7) {
    return pageRange(1, safeTotalPages);
  }

  if (safeCurrentPage <= 4) {
    return [...pageRange(1, 5), 'ellipsis', safeTotalPages];
  }

  if (safeCurrentPage >= safeTotalPages - 3) {
    return [1, 'ellipsis', ...pageRange(safeTotalPages - 4, safeTotalPages)];
  }

  return [1, 'ellipsis', safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1, 'ellipsis', safeTotalPages];
}

function pageRange(start: number, end: number): number[] {
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function formatDate(value?: string) {
  if (!value) {
    return '';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString('ru-RU');
}

function BranchCard({ title, partners, pv, weak }: { title: string; partners: number; pv: number; weak: boolean }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</div>
        {weak && <Badge variant="warning">Слабая</Badge>}
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-green">{partners}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">Партнеров</div>
        </div>
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-gold">{pv.toLocaleString('ru-RU')}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">PV</div>
        </div>
      </div>
    </article>
  );
}

function ReferralBox({ label, link }: { label: string; link: string }) {
  return (
    <div className="rounded-3xl border border-safi-border bg-safi-cream p-5">
      <div className="mb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      {link ? (
        <>
          <div className="truncate font-mono text-xs text-safi-green">{link}</div>
          <button
            type="button"
            onClick={() => navigator.clipboard.writeText(link)}
            className="mt-4 inline-flex items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold transition-colors hover:text-safi-green"
          >
            <Copy className="h-4 w-4" />
            Копировать
          </button>
        </>
      ) : (
        <div className="text-xs leading-5 text-safi-muted">Реферальные ссылки станут доступны после активации пакета.</div>
      )}
    </div>
  );
}

function Node({ name, label, root = false }: { name: string; label: string; root?: boolean }) {
  return (
    <div className={`rounded-3xl border p-5 text-center ${root ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white text-safi-green'}`}>
      <div className={`font-serif text-xl font-semibold ${root ? 'text-white' : 'text-safi-green'}`}>{name}</div>
      <div className={`mt-2 text-[10px] font-extrabold uppercase tracking-[0.14em] ${root ? 'text-white/70' : 'text-safi-muted'}`}>{label}</div>
    </div>
  );
}
