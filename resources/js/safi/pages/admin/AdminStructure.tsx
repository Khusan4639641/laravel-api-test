import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Filter, Info, Network, Search } from 'lucide-react';
import { cn } from '../../lib/utils';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminStructure, getAdminStructureRootOrphans, getApiErrorState, getArray, getNumber, getString, searchAdminPartners, unwrapRecord } from '../../lib/api';
import { adminText } from '../../i18n/adminText';
import { accountStatusLabel, mlmStatusLabel, packageLabel } from '../../lib/systemLabels';

interface StructureNode {
  id: string;
  userId: string;
  parentId: string;
  position: string;
  name: string;
  login: string;
  email: string;
  sponsor: string;
  packageCode: string;
  packageName: string;
  status: string;
  personalPV: number;
  teamPV: number;
  weakLegPV: number;
  leftPV: number;
  rightPV: number;
  leftBranchPV: number;
  rightBranchPV: number;
  balance: number;
  totalBalance: number;
  depth: number;
  children: {
    left: StructureNode | null;
    right: StructureNode | null;
  };
}

interface StructureStats {
  directInvitedCount: number;
  totalDownlineCount: number;
  leftBranchCount: number;
  rightBranchCount: number;
  leftPV: number;
  rightPV: number;
  weakLegPV: number;
}

interface StructureDepthInfo {
  hasDeeperNodes: boolean;
  hiddenNodesCount: number;
}

interface PartnerSearchResult {
  id: string;
  name: string;
  login: string;
  email: string;
  phone: string;
}

interface RootOrphanPartner {
  id: string;
  name: string;
  login: string;
  email: string;
  phone: string;
  city: string;
  sponsor: string;
  childrenCount: number;
  packageCode: string;
  packageName: string;
  statusCode: string;
  status: string;
  accountStatusCode: string;
  accountStatus: string;
  personalPV: number;
  teamPV: number;
  balance: number;
  totalBalance: number;
  createdAt: string;
}

interface RootOrphanPagination {
  total: number;
  filteredTotal: number;
  limit: number;
  offset: number;
  hasNext: boolean;
  hasPrev: boolean;
}

const emptyStats: StructureStats = {
  directInvitedCount: 0,
  totalDownlineCount: 0,
  leftBranchCount: 0,
  rightBranchCount: 0,
  leftPV: 0,
  rightPV: 0,
  weakLegPV: 0,
};

const defaultRootOrphanPageSize = 5;
const pageSizeOptions = [defaultRootOrphanPageSize, 10, 20, 50, 100];
const defaultRootOrphanPagination: RootOrphanPagination = {
  total: 0,
  filteredTotal: 0,
  limit: defaultRootOrphanPageSize,
  offset: 0,
  hasNext: false,
  hasPrev: false,
};
const mlmStatusFilterOptions = [
  { value: '', label: 'Все статусы' },
  { value: 'user', label: mlmStatusLabel('user', 'Партнёр') },
  { value: 'manager', label: mlmStatusLabel('manager', 'Менеджер') },
  { value: 'leader', label: mlmStatusLabel('leader', 'Лидер') },
  { value: 'director', label: mlmStatusLabel('director', 'Директор') },
  { value: 'bronze_director', label: mlmStatusLabel('bronze_director', 'Bronze Director') },
  { value: 'silver_director', label: mlmStatusLabel('silver_director', 'Silver Director') },
  { value: 'gold_director', label: mlmStatusLabel('gold_director', 'Gold Director') },
  { value: 'platinum_director', label: mlmStatusLabel('platinum_director', 'Platinum Director') },
  { value: 'emerald_director', label: mlmStatusLabel('emerald_director', 'Emerald Director') },
  { value: 'diamond_director', label: mlmStatusLabel('diamond_director', 'Diamond Director') },
];

function normalizeRootOrphanPerPage(value: string | null): number {
  const parsedValue = Number(value);

  return pageSizeOptions.includes(parsedValue) ? parsedValue : defaultRootOrphanPageSize;
}

export default function AdminStructure() {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const selectedUserId = searchParams.get('root_id') || searchParams.get('user_id') || '';
  const selectedDepth = searchParams.get('depth') || '10';
  const rootOrphanPerPageParam = searchParams.get('per_page');
  const initialRootOrphanLimit = normalizeRootOrphanPerPage(rootOrphanPerPageParam);
  const [query, setQuery] = useState(selectedUserId);
  const [rootOrphanQuery, setRootOrphanQuery] = useState('');
  const [rootOrphanSearchTerm, setRootOrphanSearchTerm] = useState('');
  const [rootOrphanAccountStatus, setRootOrphanAccountStatus] = useState('');
  const [rootOrphanStatus, setRootOrphanStatus] = useState('');
  const [rootOrphanPackageCode, setRootOrphanPackageCode] = useState('');
  const [rootOrphanSortDir, setRootOrphanSortDir] = useState<'asc' | 'desc'>('desc');
  const [rootOrphanLimit, setRootOrphanLimit] = useState(initialRootOrphanLimit);
  const [rootOrphanOffset, setRootOrphanOffset] = useState(defaultRootOrphanPagination.offset);
  const [rootOrphans, setRootOrphans] = useState<RootOrphanPartner[]>([]);
  const [rootOrphanPagination, setRootOrphanPagination] = useState<RootOrphanPagination>(defaultRootOrphanPagination);
  const [isRootOrphansLoading, setIsRootOrphansLoading] = useState(true);
  const [rootOrphansError, setRootOrphansError] = useState<string | null>(null);
  const [rootNode, setRootNode] = useState<StructureNode | null>(null);
  const [stats, setStats] = useState<StructureStats>(emptyStats);
  const [depthInfo, setDepthInfo] = useState<StructureDepthInfo>({ hasDeeperNodes: false, hiddenNodesCount: 0 });
  const [searchResults, setSearchResults] = useState<PartnerSearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchMessage, setSearchMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadStructure = async () => {
    setIsLoading(true);
    setError(null);
    setRootNode(null);
    setStats(emptyStats);
    setDepthInfo({ hasDeeperNodes: false, hiddenNodesCount: 0 });

    try {
      const response = await getAdminStructure({
        ...(selectedUserId ? { root_id: selectedUserId } : {}),
        depth: selectedDepth,
      });
      const root = normalizeRoot(response);

      setRootNode(root);
      setStats(normalizeStats(response));
      setDepthInfo(normalizeDepthInfo(response));
    } catch (caughtError) {
      setRootNode(null);
      setStats(emptyStats);
      setDepthInfo({ hasDeeperNodes: false, hiddenNodesCount: 0 });
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_26'));
    } finally {
      setIsLoading(false);
    }
  };

  const loadRootOrphans = async (
    searchQuery = rootOrphanSearchTerm,
    pageLimit = rootOrphanLimit,
    pageOffset = rootOrphanOffset,
  ) => {
    setIsRootOrphansLoading(true);
    setRootOrphansError(null);

    try {
      const normalizedQuery = searchQuery.trim();
      const response = await getAdminStructureRootOrphans({
        ...(normalizedQuery ? { search: normalizedQuery } : {}),
        ...(rootOrphanAccountStatus ? { account_status: rootOrphanAccountStatus } : {}),
        ...(rootOrphanStatus ? { status: rootOrphanStatus } : {}),
        ...(rootOrphanPackageCode ? { package_code: rootOrphanPackageCode } : {}),
        limit: pageLimit,
        offset: pageOffset,
        sort_by: 'children_count',
        sort_dir: rootOrphanSortDir,
      });
      const normalizedRootOrphans = normalizeRootOrphans(response);

      setRootOrphans(normalizedRootOrphans);
      setRootOrphanPagination(normalizeRootOrphanPagination(response, pageLimit, pageOffset, normalizedRootOrphans.length));
    } catch (caughtError) {
      setRootOrphans([]);
      setRootOrphanPagination({ ...defaultRootOrphanPagination, limit: pageLimit, offset: pageOffset });
      setRootOrphansError(getApiErrorState(caughtError).error || 'Не удалось загрузить партнёров без parent line');
    } finally {
      setIsRootOrphansLoading(false);
    }
  };

  useEffect(() => {
    setQuery(selectedUserId);
    void loadStructure();
  }, [selectedUserId, selectedDepth]);

  useEffect(() => {
    const nextLimit = normalizeRootOrphanPerPage(rootOrphanPerPageParam);

    setRootOrphanLimit((currentLimit) => currentLimit === nextLimit ? currentLimit : nextLimit);
    setRootOrphanOffset(0);
  }, [rootOrphanPerPageParam]);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setRootOrphanOffset(0);
      setRootOrphanSearchTerm(rootOrphanQuery.trim());
    }, rootOrphanQuery.trim() ? 400 : 0);

    return () => window.clearTimeout(timeout);
  }, [rootOrphanQuery]);

  useEffect(() => {
    void loadRootOrphans(rootOrphanSearchTerm, rootOrphanLimit, rootOrphanOffset);
  }, [
    rootOrphanSearchTerm,
    rootOrphanLimit,
    rootOrphanOffset,
    rootOrphanAccountStatus,
    rootOrphanStatus,
    rootOrphanPackageCode,
    rootOrphanSortDir,
  ]);

  useEffect(() => {
    const normalizedQuery = query.trim();

    if (!normalizedQuery || normalizedQuery === selectedUserId) {
      setSearchResults([]);
      setSearchMessage('');
      setIsSearching(false);
      return;
    }

    const timeout = window.setTimeout(async () => {
      setIsSearching(true);
      setSearchMessage('');

      try {
        const response = await searchAdminPartners(normalizedQuery, 8);
        const results = normalizePartnerSearchResults(response);

        setSearchResults(results);
        setSearchMessage(results.length === 0 ? adminText('Партнёр не найден') : '');
      } catch (caughtError) {
        setSearchResults([]);
        setSearchMessage(getApiErrorState(caughtError).error || adminText('Не удалось выполнить поиск партнёра'));
      } finally {
        setIsSearching(false);
      }
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [query, selectedUserId]);

  const hasChildren = Boolean(rootNode?.children.left || rootNode?.children.right);
  const treeCanvasWidth = useMemo(() => `${Math.max(1400, (Number(selectedDepth) || 10) * 360)}px`, [selectedDepth]);
  const rootOrphanTotalItems = rootOrphanPagination.filteredTotal ?? rootOrphanPagination.total ?? 0;
  const rootOrphanEffectiveLimit = Math.max(rootOrphanPagination.limit || rootOrphanLimit, 1);
  const rootOrphanEffectiveOffset = Math.max(rootOrphanPagination.offset || rootOrphanOffset, 0);
  const rootOrphanTotalPages = Math.max(1, Math.ceil(rootOrphanTotalItems / rootOrphanEffectiveLimit));
  const rootOrphanCurrentPage = Math.min(Math.floor(rootOrphanEffectiveOffset / rootOrphanEffectiveLimit) + 1, rootOrphanTotalPages);
  const rootOrphanPaginationMeta = {
    current_page: rootOrphanCurrentPage,
    last_page: rootOrphanTotalPages,
    per_page: rootOrphanEffectiveLimit,
    total: rootOrphanTotalItems,
    from: rootOrphanTotalItems === 0 ? 0 : rootOrphanEffectiveOffset + 1,
    to: rootOrphanTotalItems === 0 ? 0 : Math.min(rootOrphanEffectiveOffset + rootOrphanEffectiveLimit, rootOrphanTotalItems),
  };

  const changeRootOrphanPage = (page: number) => {
    if (page < 1 || page > rootOrphanTotalPages || page === rootOrphanCurrentPage || isRootOrphansLoading) {
      return;
    }

    setRootOrphanOffset((page - 1) * rootOrphanLimit);
  };

  const changeRootOrphanPerPage = (nextLimit: number) => {
    const normalizedLimit = normalizeRootOrphanPerPage(String(nextLimit));
    const nextParams = new URLSearchParams(searchParams);

    setRootOrphanLimit(normalizedLimit);
    setRootOrphanOffset(0);
    nextParams.set('per_page', String(normalizedLimit));
    setSearchParams(nextParams, { replace: true });
  };

  const resetRootOrphanPage = () => setRootOrphanOffset(0);

  const structureUrlWithRoot = (userId: string) => {
    const nextParams = new URLSearchParams(searchParams);

    nextParams.set('root_id', userId);
    nextParams.delete('user_id');

    return `/admin/structure?${nextParams.toString()}`;
  };

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedQuery = query.trim();

    if (!normalizedQuery) {
      return;
    }

    if (/^\d+$/.test(normalizedQuery)) {
      navigate(structureUrlWithRoot(normalizedQuery));
      return;
    }

    if (searchResults.length === 1) {
      openNodeTree(searchResults[0].id);
      return;
    }

    setSearchMessage(searchResults.length > 1 ? adminText('Выберите партнёра из списка ниже') : adminText('Партнёр не найден'));
  };

  const openNodeTree = (userId: string) => {
    if (!userId || userId === '-') {
      return;
    }

    navigate(structureUrlWithRoot(userId));
  };

  return (
    <div className="w-full max-w-none space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0KHRgtGA0YPQ_2')}</h1>
          <p className="text-sm text-safi-text/70">
            {selectedUserId && rootNode
              ? `${adminText('partner_tree_label')}: ${rootNode.name} / ID ${rootNode.userId}`
              : adminText('a_0JLQuNC30YPQ')}
          </p>
        </div>

      </div>

      <form onSubmit={submitSearch} className="bg-white p-4 rounded-[24px] border border-safi-green/5 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div className="flex-1 relative w-full">
          <Search className="w-5 h-5 text-safi-text/40 absolute left-4 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Поиск по ID, ФИО, email, login или телефону"
            className="w-full pl-12 pr-4 py-3 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
          />
        </div>
        <button
          type="submit"
          disabled={!query.trim()}
          className="w-full cursor-pointer rounded-xl bg-safi-green px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
        >{adminText('a_0J7RgtC60YDR_3')}</button>
      </form>

      {(isSearching || searchMessage || searchResults.length > 0) && (
        <section className="rounded-[24px] border border-safi-green/5 bg-white p-4 shadow-sm">
          {isSearching && <div className="text-sm font-bold text-safi-muted">{adminText('Ищем партнёра...')}</div>}
          {!isSearching && searchMessage && <div className="text-sm font-bold text-safi-muted">{searchMessage}</div>}
          {!isSearching && searchResults.length > 0 && (
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              {searchResults.map((partner) => (
                <article key={partner.id} className="rounded-2xl border border-safi-border bg-safi-cream p-4">
                  <div className="font-bold text-safi-green">{partner.name}</div>
                  <div className="mt-1 font-mono text-[10px] text-safi-text/50">ID {partner.id}</div>
                  <div className="mt-2 text-xs text-safi-text/60">{partner.login || partner.email || partner.phone || '-'}</div>
                  <button
                    type="button"
                    onClick={() => openNodeTree(partner.id)}
                    className="mt-4 inline-flex cursor-pointer items-center justify-center rounded-full border border-safi-green bg-white px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                  >
                    {adminText('Открыть дерево')}
                  </button>
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {!isLoading && !error && rootNode && (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <SummaryCard label={adminText('Лично пригласил')} value={stats.directInvitedCount.toLocaleString('ru-RU')} />
          <SummaryCard label={adminText('Всего в структуре')} value={stats.totalDownlineCount.toLocaleString('ru-RU')} />
          <SummaryCard
            label={adminText('Левая ветка')}
            value={`${stats.leftBranchCount.toLocaleString('ru-RU')} ${adminText('Партнёров').toLowerCase()}`}
            subValue={`${stats.leftPV.toLocaleString('ru-RU')} PV`}
          />
          <SummaryCard
            label={adminText('Правая ветка')}
            value={`${stats.rightBranchCount.toLocaleString('ru-RU')} ${adminText('Партнёров').toLowerCase()}`}
            subValue={`${stats.rightPV.toLocaleString('ru-RU')} PV`}
          />
          <SummaryCard label={adminText('Малая ветка PV')} value={`${stats.weakLegPV.toLocaleString('ru-RU')} PV`} />
        </section>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadStructure} />}
      {!isLoading && !error && !rootNode && (
        <EmptyState title={adminText('a_0KHRgtGA0YPQ_3')} description={adminText('a_0JHQuNC90LDR_3')} />
      )}

      <section className="space-y-4 rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6">
        <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <h2 className="font-serif text-2xl font-bold text-safi-green">Партнёры без parent line</h2>
            <p className="mt-1 text-sm text-safi-text/60">
              Root-orphans без Super Admin. Children count считает только партнёров.
            </p>
          </div>
          <div className="text-xs font-bold uppercase tracking-widest text-safi-text/45">
            {rootOrphanPagination.filteredTotal.toLocaleString('ru-RU')} / {rootOrphanPagination.total.toLocaleString('ru-RU')}
          </div>
        </div>

        <div className="grid gap-3 rounded-[24px] border border-safi-border bg-safi-cream p-4 lg:grid-cols-[minmax(240px,1fr)_repeat(4,minmax(150px,190px))]">
          <label className="relative">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-muted" />
            <input
              type="text"
              value={rootOrphanQuery}
              onChange={(event) => setRootOrphanQuery(event.target.value)}
              placeholder="Поиск по ID, ФИО, login, email, телефону"
              className="w-full rounded-full border border-safi-border bg-white py-3 pl-12 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
            />
          </label>
          <label className="relative">
            <Filter className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
            <select
              value={rootOrphanAccountStatus}
              onChange={(event) => {
                setRootOrphanAccountStatus(event.target.value);
                resetRootOrphanPage();
              }}
              className="w-full cursor-pointer rounded-full border border-safi-border bg-white py-3 pl-10 pr-4 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green"
            >
              <option value="">Все аккаунты</option>
              <option value="active">{accountStatusLabel('active')}</option>
              <option value="inactive">{accountStatusLabel('inactive')}</option>
              <option value="blocked">{accountStatusLabel('blocked')}</option>
            </select>
          </label>
          <select
            value={rootOrphanStatus}
            onChange={(event) => {
              setRootOrphanStatus(event.target.value);
              resetRootOrphanPage();
            }}
            className="w-full cursor-pointer rounded-full border border-safi-border bg-white px-4 py-3 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green"
          >
            {mlmStatusFilterOptions.map((option) => (
              <option key={option.value || 'all'} value={option.value}>{option.label}</option>
            ))}
          </select>
          <select
            value={rootOrphanPackageCode}
            onChange={(event) => {
              setRootOrphanPackageCode(event.target.value);
              resetRootOrphanPage();
            }}
            className="w-full cursor-pointer rounded-full border border-safi-border bg-white px-4 py-3 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green"
          >
            <option value="">Все пакеты</option>
            <option value="START">{packageLabel('START', 'START')}</option>
            <option value="VIP">{packageLabel('VIP', 'VIP')}</option>
            <option value="ELITE">{packageLabel('ELITE', 'ELITE')}</option>
          </select>
          <select
            value={rootOrphanSortDir}
            onChange={(event) => {
              setRootOrphanSortDir(event.target.value === 'asc' ? 'asc' : 'desc');
              resetRootOrphanPage();
            }}
            className="w-full cursor-pointer rounded-full border border-safi-border bg-white px-4 py-3 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green"
          >
            <option value="desc">children_count ↓</option>
            <option value="asc">children_count ↑</option>
          </select>
        </div>

        {isRootOrphansLoading && <LoadingState />}
        {!isRootOrphansLoading && rootOrphansError && (
          <ErrorState description={rootOrphansError} onRetry={() => void loadRootOrphans(rootOrphanSearchTerm, rootOrphanLimit, rootOrphanOffset)} />
        )}
        {!isRootOrphansLoading && !rootOrphansError && rootOrphans.length === 0 && (
          <EmptyState title="Root-orphans не найдены" description="Попробуйте изменить поиск или фильтры." />
        )}

        {!isRootOrphansLoading && !rootOrphansError && rootOrphans.length > 0 && (
          <>
            <AdminTable headers={[adminText('a_0J_QsNGA0YLQ'), adminText('a_0JrQvtC90YLQ'), 'Parent line', 'children_count', adminText('a_0J_QsNC60LXR_4'), adminText('a_0KHRgtCw0YLR'), 'PV', adminText('a_0JHQsNC70LDQ'), adminText('a_0JTQtdC50YHR')]}>
              {rootOrphans.map((partner) => (
                <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
                  <td className="px-6 py-4">
                    <button
                      type="button"
                      onClick={() => navigate(`/admin/partners/${encodeURIComponent(partner.id)}`)}
                      className="block cursor-pointer text-left hover:opacity-80"
                    >
                      <div className="font-bold text-safi-green">{partner.name}</div>
                      <div className="mt-1 font-mono text-[10px] text-safi-muted">ID {partner.id}</div>
                      <div className="mt-1 text-[10px] text-safi-muted">{partner.createdAt || '-'}</div>
                    </button>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-safi-green">{partner.phone}</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.email}</div>
                    <div className="mt-1 font-mono text-[10px] text-safi-muted">{partner.login || '-'}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="inline-flex rounded-full bg-safi-cream px-3 py-1 text-xs font-bold text-safi-green">{partner.sponsor}</div>
                    <div className="mt-1 text-[10px] text-safi-muted">{partner.city || '-'}</div>
                  </td>
                  <td className="px-6 py-4">
                    <button
                      type="button"
                      onClick={() => openNodeTree(partner.id)}
                      className="cursor-pointer rounded-full border border-safi-green bg-white px-3 py-2 text-xs font-extrabold text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                    >
                      {partner.childrenCount.toLocaleString('ru-RU')}
                    </button>
                  </td>
                  <td className="px-6 py-4"><AdminBadge variant="gold">{partner.packageName}</AdminBadge></td>
                  <td className="px-6 py-4">
                    <div className="mb-2"><AdminBadge variant="default">{partner.status}</AdminBadge></div>
                    <AdminBadge variant={partner.accountStatusCode === 'active' ? 'success' : 'danger'}>{partner.accountStatus}</AdminBadge>
                  </td>
                  <td className="px-6 py-4">
                    <div className="font-bold text-safi-green">{adminText('Личный PV')}: {partner.personalPV.toLocaleString('ru-RU')}</div>
                    <div className="mt-1 text-xs text-safi-muted">{adminText('Командный PV')}: {partner.teamPV.toLocaleString('ru-RU')}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm font-bold text-safi-green">{formatMoney(partner.balance)}</div>
                    <div className="mt-1 text-[10px] text-safi-muted">{formatMoney(partner.totalBalance)}</div>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex justify-end">
                      <button
                        type="button"
                        onClick={() => openNodeTree(partner.id)}
                        className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-safi-border bg-safi-cream px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white"
                      >
                        <Network className="h-4 w-4" />Показать дерево
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </AdminTable>

            <AdminPagination
              meta={rootOrphanPaginationMeta}
              perPageOptions={pageSizeOptions}
              onPageChange={changeRootOrphanPage}
              onPerPageChange={changeRootOrphanPerPage}
              totalSuffix={rootOrphanPagination.total !== rootOrphanPagination.filteredTotal && (
                <span className="ml-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                  всего {rootOrphanPagination.total.toLocaleString('ru-RU')}
                </span>
              )}
            />
          </>
        )}
      </section>

      {!isLoading && !error && rootNode && (
        <div className="rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6">
          <div className="mb-4 flex flex-col gap-2 text-xs font-bold text-safi-text/50 md:flex-row md:items-center md:justify-between">
            <div className="flex items-center gap-2">
              <Info className="h-4 w-4 shrink-0" />
              <span>{adminText('a_0JjRgdC_0L7Q')}</span>
            </div>
            <span>{adminText('a_0JTQsNC90L3R_4')}{selectedDepth}</span>
          </div>
          {depthInfo.hasDeeperNodes && (
            <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800">
              Есть ещё партнёры глубже текущей глубины дерева: {depthInfo.hiddenNodesCount.toLocaleString('ru-RU')}. Увеличьте depth в URL до 10 или используйте список выше.
            </div>
          )}

          <div className="relative max-h-[calc(100vh-260px)] min-h-[540px] overflow-x-auto overflow-y-auto rounded-[24px] border border-safi-border bg-white">
            {!hasChildren && (
              <div className="sticky bottom-5 left-5 z-10 mx-5 mt-5 rounded-2xl bg-[#F5F5F0] px-4 py-3 text-center text-xs font-bold text-safi-text/60">{adminText('a_0KMg0L_QsNGA')}</div>
            )}

            <div
              className="flex min-h-[700px] w-max items-start justify-center px-10 py-12"
              style={{ minWidth: treeCanvasWidth }}
            >
              <TreeNode node={rootNode} isRoot onOpen={openNodeTree} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function SummaryCard({ label, value, subValue }: { label: string; value: string; subValue?: string }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="mt-3 font-serif text-2xl font-semibold text-safi-green">{value}</div>
      {subValue && <div className="mt-2 text-sm font-extrabold text-safi-gold">{subValue}</div>}
    </article>
  );
}

function TreeNode({ node, isRoot, onOpen }: { node: StructureNode; isRoot?: boolean; onOpen: (userId: string) => void }) {
  const hasChildren = Boolean(node.children.left || node.children.right);

  return (
    <div className="flex flex-col items-center">
      <button
        type="button"
        onClick={() => onOpen(node.userId)}
        className={cn(
          'w-48 shrink-0 cursor-pointer rounded-2xl bg-white p-4 text-center shadow-sm transition-transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-safi-green/20',
          isRoot ? 'border-2 border-safi-gold shadow-md' : 'border border-safi-green/10'
        )}
        title={adminText('a_0J7RgtC60YDR_5')}
      >
        <div className={cn(
          'mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full text-lg font-bold font-serif text-white',
          node.packageCode === 'START' ? 'bg-blue-400' : node.packageCode === 'VIP' ? 'bg-purple-500' : 'bg-safi-gold'
        )}>
          {node.name.charAt(0)}
        </div>
        <div className="mb-1 w-full truncate text-sm font-bold text-safi-green" title={node.name}>{node.name}</div>
        <div className="mb-2 rounded bg-[#F5F5F0] px-2 py-0.5 font-mono text-[10px] text-safi-text/50">{node.login || node.userId}</div>
        <div className="mt-3 space-y-1 border-t border-safi-green/5 pt-3 text-left text-[10px] font-bold text-safi-text/70">
          <div className="flex items-center justify-between gap-2">
            <span>{adminText('Пакет')}:</span>
            <AdminBadge variant={node.packageCode === 'ELITE' || node.packageCode === 'VIP' ? 'gold' : 'default'} className="px-1.5 py-0.5">{node.packageName || '-'}</AdminBadge>
          </div>
          <div className="flex items-center justify-between gap-2">
            <span>{adminText('Статус')}:</span>
            <span className="truncate text-safi-green">{node.status}</span>
          </div>
          <div className="flex items-center justify-between gap-2">
            <span>{adminText('Личный PV')}:</span>
            <span className="text-safi-gold">{node.personalPV.toLocaleString('ru-RU')} PV</span>
          </div>
          <div className="grid grid-cols-2 gap-1 pt-1 text-center text-[9px] font-extrabold text-safi-green">
            <div className="rounded-lg bg-[#F5F5F0] px-1 py-1" title={adminText('Левая ветка PV')}>
              {adminText('Л')}: {formatCompactPv(node.leftBranchPV)}
            </div>
            <div className="rounded-lg bg-[#F5F5F0] px-1 py-1" title={adminText('Правая ветка PV')}>
              {adminText('П')}: {formatCompactPv(node.rightBranchPV)}
            </div>
          </div>
        </div>
      </button>

      {hasChildren && (
        <div className="mt-6 flex flex-col items-center">
          <div className="h-6 border-l-2 border-safi-green/20" />
          <div className="relative grid grid-cols-2 gap-6 lg:gap-8">
            <div className="absolute left-1/4 right-1/4 top-0 border-t-2 border-safi-green/20" />
            <BranchColumn label={adminText('a_0JvQtdCy0LDR')}>
              {node.children.left ? <TreeNode node={node.children.left} onOpen={onOpen} /> : <EmptyTreeSlot />}
            </BranchColumn>
            <BranchColumn label={adminText('a_0J_RgNCw0LLQ')}>
              {node.children.right ? <TreeNode node={node.children.right} onOpen={onOpen} /> : <EmptyTreeSlot />}
            </BranchColumn>
          </div>
        </div>
      )}
    </div>
  );
}

function BranchColumn({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="relative flex min-w-[210px] shrink-0 flex-col items-center pt-6">
      <div className="absolute top-0 h-6 border-l-2 border-safi-green/20" />
      <div className="mb-2 rounded-full bg-white px-2 text-center text-[10px] font-bold text-safi-text/40">{label}</div>
      {children}
    </div>
  );
}

function EmptyTreeSlot() {
  return (
    <div className="flex w-48 shrink-0 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-safi-green/20 bg-[#F5F5F0]/50 p-4 text-center opacity-70">
      <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-safi-green/5 pb-1 text-xl text-safi-green/40">+</div>
      <div className="text-xs font-bold text-safi-text/50">{adminText('a_0KHQstC-0LHQ')}</div>
    </div>
  );
}

function normalizeStats(response: unknown): StructureStats {
  const summary = unwrapRecord(response, ['summary']);
  const stats = Object.keys(summary).length > 0 ? summary : unwrapRecord(response, ['stats']);
  const leftPV = getNumber(stats, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
  const rightPV = getNumber(stats, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;

  return {
    directInvitedCount: getNumber(stats, ['direct_invited_count', 'directInvitedCount']) ?? 0,
    totalDownlineCount: getNumber(stats, ['total_structure_count', 'totalStructureCount', 'total_downline_count', 'totalDownlineCount']) ?? 0,
    leftBranchCount: getNumber(stats, ['left_count', 'leftCount', 'left_branch_count', 'leftBranchCount']) ?? 0,
    rightBranchCount: getNumber(stats, ['right_count', 'rightCount', 'right_branch_count', 'rightBranchCount']) ?? 0,
    leftPV,
    rightPV,
    weakLegPV: getNumber(stats, ['weak_leg_pv', 'weakLegPv', 'weak_leg_branch_pv', 'weakLegBranchPv']) ?? Math.min(leftPV, rightPV),
  };
}

function normalizeDepthInfo(response: unknown): StructureDepthInfo {
  const record = response && typeof response === 'object' ? response as Record<string, unknown> : {};

  return {
    hasDeeperNodes: Boolean(record.has_deeper_nodes ?? record.hasDeeperNodes),
    hiddenNodesCount: getNumber(record, ['hidden_nodes_count', 'hiddenNodesCount']) ?? 0,
  };
}

function normalizePartnerSearchResults(response: unknown): PartnerSearchResult[] {
  return getArray(response, ['partners', 'users']).map((item, index) => {
    const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};
    const profile = record.profile && typeof record.profile === 'object' ? record.profile as Record<string, unknown> : {};

    return {
      id: getString(record, ['id', 'user_id', 'partner_id']) || String(index + 1),
      name: getString(record, ['name', 'full_name', 'fullName']) || `Partner ${index + 1}`,
      login: getString(record, ['login']) || '',
      email: getString(record, ['email']) || '',
      phone: getString(record, ['phone']) || getString(profile, ['phone']) || '',
    };
  });
}

function normalizeRootOrphans(response: unknown): RootOrphanPartner[] {
  return getArray(response, ['root_orphans', 'partners', 'data']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const profile = isRecord(record.profile) ? record.profile : {};
    const packageRecord = isRecord(record.package) ? record.package : isRecord(record.current_package) ? record.current_package : {};
    const mlmStatus = isRecord(record.mlm_status) ? record.mlm_status : {};
    const sponsor = isRecord(record.sponsor) ? record.sponsor : undefined;
    const packageCode = String(getString(packageRecord, ['code', 'slug', 'id']) || getString(record, ['package_code', 'packageCode']) || '').toUpperCase();
    const statusCode = getString(mlmStatus, ['code']) || getString(record, ['status']) || 'user';
    const accountStatusCode = getString(record, ['account_status', 'accountStatus']) || 'active';
    const leftPV = getNumber(record, ['left_pv', 'leftPV']) ?? 0;
    const rightPV = getNumber(record, ['right_pv', 'rightPV']) ?? 0;
    const personalPV = getNumber(record, ['personal_pv', 'personalPv', 'package_activity_pv', 'packageActivityPv'])
      ?? getNumber(packageRecord, ['activity_pv', 'activityPv'])
      ?? 0;

    return {
      id: getString(record, ['user_id', 'id']) || String(index + 1),
      name: getString(record, ['name', 'full_name', 'fullName']) || `Partner ${index + 1}`,
      login: getString(record, ['login']) || '',
      email: getString(record, ['email']) || '-',
      phone: getString(record, ['phone']) || getString(profile, ['phone']) || '-',
      city: getString(record, ['city']) || getString(profile, ['city']) || '-',
      sponsor: sponsor ? (getString(sponsor, ['name', 'login', 'id']) || '-') : 'root-orphan',
      childrenCount: getNumber(record, ['children_count', 'childrenCount', 'direct_children_count', 'directChildrenCount']) ?? 0,
      packageCode,
      packageName: packageLabel(packageCode, getString(packageRecord, ['label', 'name']) || getString(record, ['package_label', 'packageLabel']) || '-'),
      statusCode,
      status: mlmStatusLabel(statusCode, getString(mlmStatus, ['label']) || getString(record, ['status_label', 'statusLabel']) || '-'),
      accountStatusCode,
      accountStatus: accountStatusLabel(accountStatusCode),
      personalPV,
      teamPV: getNumber(record, ['team_pv', 'teamPv', 'total_pv', 'totalPv']) ?? leftPV + rightPV,
      balance: getNumber(record, ['balance', 'wallet_balance', 'walletBalance', 'available_balance', 'availableBalance']) ?? 0,
      totalBalance: getNumber(record, ['total_balance', 'totalBalance', 'total_wallet_balance', 'totalWalletBalance']) ?? 0,
      createdAt: getString(record, ['created_at', 'createdAt']) || '-',
    };
  });
}

function normalizeRootOrphanPagination(response: unknown, fallbackLimit: number, fallbackOffset: number, rowCount: number): RootOrphanPagination {
  const record = isRecord(response) ? response : {};
  const pagination = isRecord(record.pagination) ? record.pagination : {};
  const meta = isRecord(record.meta) ? record.meta : {};
  const total = getNumber(pagination, ['total']) ?? getNumber(meta, ['total']) ?? rowCount;
  const filteredTotal = getNumber(pagination, ['filtered_total', 'filteredTotal']) ?? getNumber(meta, ['total']) ?? total;
  const limit = getNumber(pagination, ['limit']) ?? getNumber(meta, ['per_page', 'perPage']) ?? fallbackLimit;
  const offset = getNumber(pagination, ['offset']) ?? fallbackOffset;
  const hasNextValue = pagination.has_next ?? pagination.hasNext;
  const hasPrevValue = pagination.has_prev ?? pagination.hasPrev;

  return {
    total,
    filteredTotal,
    limit,
    offset,
    hasNext: typeof hasNextValue === 'boolean' ? hasNextValue : offset + limit < filteredTotal,
    hasPrev: typeof hasPrevValue === 'boolean' ? hasPrevValue : offset > 0,
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

function normalizeSponsor(record: Record<string, unknown>) {
  const sponsor = record.sponsor && typeof record.sponsor === 'object' ? record.sponsor as Record<string, unknown> : undefined;

  if (!sponsor) {
    return '-';
  }

  return getString(sponsor, ['name', 'login', 'id']) || '-';
}

function normalizePackage(record: Record<string, unknown>) {
  const pkg = record.package && typeof record.package === 'object' ? record.package as Record<string, unknown> : undefined;
  const code = getString(pkg, ['code', 'slug', 'id']) || getString(record, ['package_code', 'packageCode', 'package']) || '';

  return packageLabel(code, getString(pkg, ['code_label', 'codeLabel', 'label', 'name']) || getString(record, ['package_label', 'packageLabel', 'package_name', 'packageName', 'package']) || '-');
}

function normalizePackageCode(record: Record<string, unknown>) {
  const pkg = record.package && typeof record.package === 'object' ? record.package as Record<string, unknown> : undefined;

  return String(getString(pkg, ['code', 'slug', 'id']) || getString(record, ['package_code', 'packageCode', 'package']) || '').toUpperCase();
}

function normalizeMlmStatus(record: Record<string, unknown>) {
  const mlmStatus = record.mlm_status && typeof record.mlm_status === 'object' ? record.mlm_status as Record<string, unknown> : undefined;
  const code = getString(mlmStatus, ['code']) || getString(record, ['status']);
  const label = getString(mlmStatus, ['label']) || getString(record, ['status_label', 'statusLabel']) || '-';

  return mlmStatusLabel(code, label);
}

function normalizeNodeRecord(record: Record<string, unknown>, index = 0): StructureNode {
  const children = record.children && typeof record.children === 'object' ? record.children as Record<string, unknown> : {};
  const left = children.left && typeof children.left === 'object' ? normalizeTreeNode(children.left as Record<string, unknown>) : null;
  const right = children.right && typeof children.right === 'object' ? normalizeTreeNode(children.right as Record<string, unknown>) : null;
  const leftBranchPV = getNumber(record, ['left_branch_pv', 'leftBranchPv', 'left_pv', 'leftPV']) ?? 0;
  const rightBranchPV = getNumber(record, ['right_branch_pv', 'rightBranchPv', 'right_pv', 'rightPV']) ?? 0;

  return {
    id: getString(record, ['binary_node_id', 'id']) || String(index + 1),
    userId: getString(record, ['user_id', 'id']) || '-',
    parentId: getString(record, ['parent_id']) || '',
    position: getString(record, ['position', 'branch']) || '',
    name: getString(record, ['name']) || `Partner ${index + 1}`,
    login: getString(record, ['login']) || '',
    email: getString(record, ['email']) || '',
    sponsor: normalizeSponsor(record),
    packageCode: normalizePackageCode(record),
    packageName: normalizePackage(record),
    status: normalizeMlmStatus(record),
    personalPV: getNumber(record, ['personal_pv', 'personalPv', 'package_activity_pv', 'packageActivityPv']) ?? 0,
    teamPV: getNumber(record, ['team_pv', 'teamPv'])
      ?? (leftBranchPV + rightBranchPV),
    weakLegPV: getNumber(record, ['weak_leg_pv', 'weakLegPv'])
      ?? Math.min(leftBranchPV, rightBranchPV),
    leftPV: leftBranchPV,
    rightPV: rightBranchPV,
    leftBranchPV,
    rightBranchPV,
    balance: getNumber(record, ['balance']) ?? 0,
    totalBalance: getNumber(record, ['total_balance', 'totalBalance']) ?? 0,
    depth: getNumber(record, ['level', 'depth']) ?? 0,
    children: { left, right },
  };
}

function normalizeRoot(response: unknown): StructureNode | null {
  const root = unwrapRecord(response, ['root']);

  if (!root || Object.keys(root).length === 0) {
    return null;
  }

  return normalizeTreeNode(root);
}

function normalizeTreeNode(record: Record<string, unknown>): StructureNode {
  return normalizeNodeRecord(record);
}

function formatMoney(value: number) {
  return `${value.toLocaleString('ru-RU')} ₸`;
}

function formatCompactPv(value: number) {
  return `${value.toLocaleString('ru-RU')} PV`;
}
