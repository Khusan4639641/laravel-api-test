import { type FormEvent, type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Crosshair, Filter, Info, Maximize2, Minus, Network, Plus, RotateCcw, Search } from 'lucide-react';
import { cn } from '../../lib/utils';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { StructureTreeCanvas } from '../../components/structure/StructureTreeCanvas';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { MobileCardActions, MobileDataCard, MobileDataHeader, MobileDataList, MobileDataRow } from '../../components/ui/MobileData';
import { getAdminStructure, getAdminStructureRootOrphans, getApiErrorState, getArray, getNumber, getString, searchAdminPartners, unwrapRecord } from '../../lib/api';
import { adminText } from '../../i18n/adminText';
import { accountStatusLabel, mlmStatusLabel, packageLabel } from '../../lib/systemLabels';
import { getPartnerPackageStatus } from '../../lib/partnerStatus';

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

type TreeNodeSize = 'small' | 'normal' | 'large';
type TreeDensity = 'compact' | 'normal' | 'wide';

interface TreeViewSettings {
  zoom: number;
  nodeSize: TreeNodeSize;
  density: TreeDensity;
  showEmptySlots: boolean;
}

interface TreeSizeConfig {
  width: number;
  height: number;
  avatar: string;
  padding: string;
  nameText: string;
  metaText: string;
  detailText: string;
  pvText: string;
}

interface TreeDensityConfig {
  horizontalGap: number;
  verticalGap: number;
}

interface TreeLayoutItem {
  id: string;
  kind: 'node' | 'empty';
  node: StructureNode | null;
  x: number;
  y: number;
  width: number;
  height: number;
  branch?: 'L' | 'R';
  isRoot?: boolean;
}

interface TreeNodeMeasurement {
  width: number;
  height: number;
}

interface TreeConnector {
  id: string;
  fromItemId: string;
  toItemId: string;
  fromX: number;
  fromY: number;
  toX: number;
  toY: number;
  isEmptyTarget: boolean;
}

interface TreeLayout {
  width: number;
  height: number;
  rootCenterX: number;
  rootY: number;
  rootItemId: string;
  items: TreeLayoutItem[];
  connectors: TreeConnector[];
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

const treeSettingsStorageKey = 'safi_admin_structure_view_settings';
const minTreeZoom = 0.35;
const maxTreeZoom = 1.2;
const defaultTreeViewSettings: TreeViewSettings = {
  zoom: 0.75,
  nodeSize: 'small',
  density: 'compact',
  showEmptySlots: true,
};
const treeNodeSizeConfigs: Record<TreeNodeSize, TreeSizeConfig> = {
  small: {
    width: 110,
    height: 190,
    avatar: 'h-8 w-8 text-sm',
    padding: 'p-2',
    nameText: 'text-[11px]',
    metaText: 'text-[9px]',
    detailText: 'text-[9px]',
    pvText: 'text-[8px]',
  },
  normal: {
    width: 140,
    height: 215,
    avatar: 'h-10 w-10 text-base',
    padding: 'p-3',
    nameText: 'text-xs',
    metaText: 'text-[10px]',
    detailText: 'text-[10px]',
    pvText: 'text-[9px]',
  },
  large: {
    width: 170,
    height: 245,
    avatar: 'h-12 w-12 text-lg',
    padding: 'p-4',
    nameText: 'text-sm',
    metaText: 'text-[10px]',
    detailText: 'text-[10px]',
    pvText: 'text-[9px]',
  },
};
const treeDensityConfigs: Record<TreeDensity, TreeDensityConfig> = {
  compact: { horizontalGap: 32, verticalGap: 70 },
  normal: { horizontalGap: 64, verticalGap: 90 },
  wide: { horizontalGap: 100, verticalGap: 120 },
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

function normalizeRootOrphanPerPage(value: string | null): number {
  const parsedValue = Number(value);

  return pageSizeOptions.includes(parsedValue) ? parsedValue : defaultRootOrphanPageSize;
}

export default function AdminStructure() {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const treeScrollRef = useRef<HTMLDivElement | null>(null);
  const treeProgrammaticScrollRef = useRef(false);
  const treeUserScrolledRef = useRef(false);
  const rootIdFromUrl = searchParams.get('root_id')?.trim() || '';
  const selectedRootId = /^\d+$/.test(rootIdFromUrl) ? Number(rootIdFromUrl) : null;
  const selectedUserId = selectedRootId === null ? '' : String(selectedRootId);
  const selectedDepth = searchParams.get('depth') || '10';
  const rootOrphanPerPageParam = searchParams.get('per_page');
  const initialRootOrphanLimit = normalizeRootOrphanPerPage(rootOrphanPerPageParam);
  const [query, setQuery] = useState(selectedUserId);
  const [rootOrphanQuery, setRootOrphanQuery] = useState('');
  const [rootOrphanSearchTerm, setRootOrphanSearchTerm] = useState('');
  const [rootOrphanAccountStatus, setRootOrphanAccountStatus] = useState('');
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
  const [isLoading, setIsLoading] = useState(selectedRootId !== null);
  const [error, setError] = useState<string | null>(null);
  const [treeSettings, setTreeSettings] = useState<TreeViewSettings>(() => readTreeViewSettings());
  const [treeNodeMeasurements, setTreeNodeMeasurements] = useState<Record<string, TreeNodeMeasurement>>({});

  const loadStructure = async () => {
    if (selectedRootId === null) {
      setIsLoading(false);
      setError(null);
      setRootNode(null);
      setStats(emptyStats);
      setDepthInfo({ hasDeeperNodes: false, hiddenNodesCount: 0 });
      return;
    }

    setIsLoading(true);
    setError(null);
    setRootNode(null);
    setStats(emptyStats);
    setDepthInfo({ hasDeeperNodes: false, hiddenNodesCount: 0 });

    try {
      const response = await getAdminStructure({
        root_id: selectedUserId,
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
        limit: pageLimit,
        offset: pageOffset,
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

  const nodeSizeConfig = treeNodeSizeConfigs[treeSettings.nodeSize];
  const densityConfig = treeDensityConfigs[treeSettings.density];
  const treeLayout = useMemo(
    () => rootNode ? computeTreeLayout(rootNode, treeSettings, nodeSizeConfig, densityConfig) : null,
    [rootNode, treeSettings.nodeSize, treeSettings.density, treeSettings.showEmptySlots, nodeSizeConfig, densityConfig],
  );
  const treeItemsById = useMemo(() => {
    const itemsById = new Map<string, TreeLayoutItem>();

    treeLayout?.items.forEach((item) => {
      itemsById.set(item.id, item);
    });

    return itemsById;
  }, [treeLayout]);
  const treeBounds = useMemo(
    () => treeLayout ? getMeasuredTreeBounds(treeLayout, treeNodeMeasurements) : null,
    [treeLayout, treeNodeMeasurements],
  );

  const updateTreeNodeMeasurement = useCallback((id: string, measurement: TreeNodeMeasurement) => {
    setTreeNodeMeasurements((currentMeasurements) => {
      const previousMeasurement = currentMeasurements[id];

      if (
        previousMeasurement
        && Math.abs(previousMeasurement.width - measurement.width) < 1
        && Math.abs(previousMeasurement.height - measurement.height) < 1
      ) {
        return currentMeasurements;
      }

      return { ...currentMeasurements, [id]: measurement };
    });
  }, []);

  const persistTreeSettings = useCallback((nextSettings: TreeViewSettings) => {
    if (typeof window === 'undefined') {
      return;
    }

    window.localStorage.setItem(treeSettingsStorageKey, JSON.stringify(nextSettings));
  }, []);

  const updateTreeSettings = useCallback((patch: Partial<TreeViewSettings>) => {
    setTreeSettings((current) => {
      const nextSettings = normalizeTreeViewSettings({ ...current, ...patch });

      persistTreeSettings(nextSettings);

      return nextSettings;
    });
  }, [persistTreeSettings]);

  const centerTree = useCallback((zoomOverride?: number) => {
    const container = treeScrollRef.current;

    if (!container || !treeLayout) {
      return;
    }

    const rootItem = treeItemsById.get(treeLayout.rootItemId);
    const rootMeasurement = rootItem ? treeNodeMeasurements[rootItem.id] : undefined;
    const rootCenterX = rootItem
      ? rootItem.x + (rootMeasurement?.width ?? rootItem.width) / 2
      : treeLayout.rootCenterX;
    const rootY = rootItem?.y ?? treeLayout.rootY;
    const zoom = zoomOverride ?? treeSettings.zoom;
    const nextScrollLeft = Math.max(0, rootCenterX * zoom - container.clientWidth / 2);
    const nextScrollTop = Math.max(0, rootY * zoom - 40);

    treeProgrammaticScrollRef.current = true;
    container.scrollTo({ left: nextScrollLeft, top: nextScrollTop, behavior: 'smooth' });
    window.setTimeout(() => {
      treeProgrammaticScrollRef.current = false;
    }, 450);
  }, [treeItemsById, treeLayout, treeNodeMeasurements, treeSettings.zoom]);

  const fitTreeToScreen = useCallback(() => {
    const container = treeScrollRef.current;

    if (!container || !treeBounds) {
      return;
    }

    const nextZoom = clampNumber(
      Math.min(container.clientWidth / treeBounds.width, container.clientHeight / treeBounds.height, 1),
      minTreeZoom,
      1,
    );

    updateTreeSettings({ zoom: nextZoom });
    window.setTimeout(() => centerTree(nextZoom), 0);
  }, [centerTree, treeBounds, updateTreeSettings]);

  const resetTreeView = useCallback(() => {
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem(treeSettingsStorageKey);
    }

    treeUserScrolledRef.current = false;
    setTreeSettings(defaultTreeViewSettings);
    window.setTimeout(() => centerTree(defaultTreeViewSettings.zoom), 0);
  }, [centerTree]);

  useEffect(() => {
    treeUserScrolledRef.current = false;
  }, [rootNode?.id]);

  useEffect(() => {
    setTreeNodeMeasurements({});
  }, [rootNode?.id, treeSettings.nodeSize, treeSettings.density, treeSettings.showEmptySlots]);

  useEffect(() => {
    if (!treeLayout || treeUserScrolledRef.current) {
      return;
    }

    const timeout = window.setTimeout(() => centerTree(treeSettings.zoom), 80);

    return () => window.clearTimeout(timeout);
  }, [centerTree, treeBounds?.height, treeBounds?.width, rootNode?.id, treeSettings.zoom]);

  useEffect(() => {
    setQuery(selectedUserId);
    void loadStructure();
  }, [selectedRootId, selectedDepth]);

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
      setSearchResults([]);
      setSearchMessage('Введите ID партнёра.');
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
          className="w-full cursor-pointer rounded-xl bg-safi-green px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white md:w-auto"
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
            subValue={formatBranchPv('л', stats.leftPV)}
          />
          <SummaryCard
            label={adminText('Правая ветка')}
            value={`${stats.rightBranchCount.toLocaleString('ru-RU')} ${adminText('Партнёров').toLowerCase()}`}
            subValue={formatBranchPv('п', stats.rightPV)}
          />
          <SummaryCard label={adminText('Малая ветка PV')} value={`${stats.weakLegPV.toLocaleString('ru-RU')} PV`} />
        </section>
      )}

      {selectedRootId === null && (
        <EmptyState title={adminText('a_0KHRgtGA0YPQ_3')} description="Выберите партнёра из списка или введите ID, чтобы открыть дерево." />
      )}
      {selectedRootId !== null && isLoading && <LoadingState />}
      {selectedRootId !== null && !isLoading && error && <ErrorState description={error} onRetry={loadStructure} />}
      {selectedRootId !== null && !isLoading && !error && !rootNode && (
        <EmptyState title={adminText('a_0KHRgtGA0YPQ_3')} description={adminText('a_0JHQuNC90LDR_3')} />
      )}

      <section className="space-y-4 rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6">
        <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <h2 className="font-serif text-2xl font-bold text-safi-green">Партнёры без parent line</h2>
            <p className="mt-1 text-sm text-safi-text/60">
              Root-orphans без Super Admin.
            </p>
          </div>
          <div className="text-xs font-bold uppercase tracking-widest text-safi-text/45">
            {rootOrphanPagination.filteredTotal.toLocaleString('ru-RU')} / {rootOrphanPagination.total.toLocaleString('ru-RU')}
          </div>
        </div>

        <div className="grid gap-3 rounded-[24px] border border-safi-border bg-safi-cream p-4 lg:grid-cols-[minmax(240px,1fr)_minmax(150px,190px)]">
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
            <MobileDataList>
              {rootOrphans.map((partner) => (
                <MobileDataCard key={partner.id}>
                  <MobileDataHeader title={partner.name} meta={`ID ${partner.id} · ${partner.createdAt || '-'}`} />
                  <MobileDataRow label={adminText('a_0JrQvtC90YLQ')}>
                    <div>{partner.phone || '-'}</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.email || '-'}</div>
                    <div className="mt-1 font-mono text-xs text-safi-muted">{partner.login || '-'}</div>
                  </MobileDataRow>
                  <MobileCardActions>
                    <button
                      type="button"
                      onClick={() => navigate(`/admin/partners/${encodeURIComponent(partner.id)}`)}
                      className="inline-flex items-center justify-center rounded-xl border border-safi-border bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green"
                    >
                      Профиль
                    </button>
                    <button
                      type="button"
                      onClick={() => openNodeTree(partner.id)}
                      className="inline-flex items-center justify-center gap-2 rounded-xl border border-safi-border bg-safi-cream px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green"
                    >
                      <Network className="h-4 w-4" />Дерево
                    </button>
                  </MobileCardActions>
                </MobileDataCard>
              ))}
            </MobileDataList>

            <div className="hidden md:block">
              <AdminTable headers={[adminText('a_0J_QsNGA0YLQ'), adminText('a_0JrQvtC90YLQ'), adminText('a_0JTQtdC50YHR')]}>
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
            </div>

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
        <StructureTreeCanvas
          rootNode={rootNode}
          storageKey={treeSettingsStorageKey}
          infoText={(
            <>
              <Info className="h-4 w-4 shrink-0" />
              <span>{adminText('a_0JjRgdC_0L7Q')}</span>
            </>
          )}
          depthLabel={`${adminText('a_0JTQsNC90L3R_4')}${selectedDepth}`}
          depthInfo={depthInfo}
          emptyMessage={adminText('a_0KMg0L_QsNGA')}
          onOpenNode={openNodeTree}
        />
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

function StructureTreeToolbar({
  settings,
  onChange,
  onFit,
  onCenter,
  onReset,
}: {
  settings: TreeViewSettings;
  onChange: (patch: Partial<TreeViewSettings>) => void;
  onFit: () => void;
  onCenter: () => void;
  onReset: () => void;
}) {
  const zoomPercent = Math.round(settings.zoom * 100);

  return (
    <div className="flex flex-wrap items-center gap-2 rounded-[24px] border border-safi-border bg-safi-cream p-3">
      <div className="flex items-center gap-1 rounded-full bg-white p-1">
        <IconButton label="Уменьшить" onClick={() => onChange({ zoom: settings.zoom - 0.05 })}>
          <Minus className="h-4 w-4" />
        </IconButton>
        {[0.5, 0.75, 1].map((zoom) => (
          <button
            key={zoom}
            type="button"
            onClick={() => onChange({ zoom })}
            className={cn(
              'h-8 rounded-full px-3 text-[10px] font-extrabold text-safi-green transition-colors',
              Math.abs(settings.zoom - zoom) < 0.01 ? 'bg-safi-green text-white' : 'hover:bg-safi-green/10',
            )}
          >
            {Math.round(zoom * 100)}%
          </button>
        ))}
        <IconButton label="Увеличить" onClick={() => onChange({ zoom: settings.zoom + 0.05 })}>
          <Plus className="h-4 w-4" />
        </IconButton>
      </div>
      <label className="flex min-w-[180px] items-center gap-2 rounded-full bg-white px-3 py-2 text-[10px] font-extrabold text-safi-green">
        <span className="w-9 tabular-nums">{zoomPercent}%</span>
        <input
          type="range"
          min={40}
          max={120}
          step={5}
          value={Math.round(settings.zoom * 100)}
          onChange={(event) => onChange({ zoom: Number(event.target.value) / 100 })}
          className="w-28 accent-safi-green"
        />
      </label>
      <SegmentedTreeControl
        label="Карточки"
        value={settings.nodeSize}
        options={[
          ['small', 'small'],
          ['normal', 'normal'],
          ['large', 'large'],
        ]}
        onChange={(value) => onChange({ nodeSize: value as TreeNodeSize })}
      />
      <SegmentedTreeControl
        label="Плотность"
        value={settings.density}
        options={[
          ['compact', 'compact'],
          ['normal', 'normal'],
          ['wide', 'wide'],
        ]}
        onChange={(value) => onChange({ density: value as TreeDensity })}
      />
      <label className="flex h-10 cursor-pointer items-center gap-2 rounded-full bg-white px-3 text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-green">
        <input
          type="checkbox"
          checked={settings.showEmptySlots}
          onChange={(event) => onChange({ showEmptySlots: event.target.checked })}
          className="h-4 w-4 accent-safi-green"
        />
        Свободные места
      </label>
      <div className="ml-auto flex flex-wrap items-center gap-2">
        <ToolbarButton label="Вместить" onClick={onFit} icon={<Maximize2 className="h-4 w-4" />} />
        <ToolbarButton label="Центрировать" onClick={onCenter} icon={<Crosshair className="h-4 w-4" />} />
        <ToolbarButton label="Сбросить" onClick={onReset} icon={<RotateCcw className="h-4 w-4" />} />
      </div>
    </div>
  );
}

function SegmentedTreeControl({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: Array<[string, string]>;
  onChange: (value: string) => void;
}) {
  return (
    <div className="flex items-center gap-1 rounded-full bg-white p-1 pl-3">
      <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-muted">{label}</span>
      {options.map(([optionValue, optionLabel]) => (
        <button
          key={optionValue}
          type="button"
          onClick={() => onChange(optionValue)}
          className={cn(
            'h-8 rounded-full px-3 text-[10px] font-extrabold text-safi-green transition-colors',
            value === optionValue ? 'bg-safi-green text-white' : 'hover:bg-safi-green/10',
          )}
        >
          {optionLabel}
        </button>
      ))}
    </div>
  );
}

function ToolbarButton({ label, onClick, icon }: { label: string; onClick: () => void; icon: ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="inline-flex h-10 items-center gap-2 rounded-full border border-safi-border bg-white px-3 text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white"
    >
      {icon}
      {label}
    </button>
  );
}

function IconButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      title={label}
      className="inline-flex h-8 w-8 items-center justify-center rounded-full text-safi-green transition-colors hover:bg-safi-green hover:text-white"
    >
      {children}
    </button>
  );
}

function TreeNodeCard({
  node,
  isRoot,
  onOpen,
  sizeConfig,
}: {
  node: StructureNode;
  isRoot?: boolean;
  onOpen: (userId: string) => void;
  sizeConfig: TreeSizeConfig;
}) {
  return (
    <button
      type="button"
      onClick={() => onOpen(node.userId)}
      className={cn(
        'flex h-auto w-max max-w-none cursor-pointer flex-col overflow-visible rounded-2xl bg-white text-center shadow-sm transition-transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-safi-green/20',
        sizeConfig.padding,
        isRoot ? 'border-2 border-safi-gold shadow-md' : 'border border-safi-green/10',
      )}
      style={{ minWidth: `${sizeConfig.width}px` }}
      title={adminText('a_0J7RgtC60YDR_5')}
    >
      <div className={cn(
        'mx-auto mb-2 flex shrink-0 items-center justify-center rounded-full font-serif font-bold text-white',
        sizeConfig.avatar,
        node.packageCode === 'START' ? 'bg-blue-400' : node.packageCode === 'VIP' ? 'bg-purple-500' : 'bg-safi-gold',
      )}>
        {node.name.charAt(0)}
      </div>
      <div className={cn('mb-1 w-full truncate font-bold leading-tight text-safi-green', sizeConfig.nameText)} style={{ maxWidth: `${sizeConfig.width}px` }} title={node.name}>{node.name}</div>
      <div className={cn('mb-2 truncate rounded bg-[#F5F5F0] px-2 py-0.5 font-mono text-safi-text/50', sizeConfig.metaText)} style={{ maxWidth: `${sizeConfig.width}px` }}>{node.login || node.userId}</div>
      <div className={cn('space-y-1 overflow-visible border-t border-safi-green/5 pt-2 text-left font-bold text-safi-text/70', sizeConfig.detailText)}>
        <div className="grid grid-cols-[auto_max-content] items-center justify-between gap-2 whitespace-nowrap">
          <span>{adminText('Пакет')}:</span>
          <AdminBadge variant={node.packageCode === 'ELITE' || node.packageCode === 'VIP' ? 'gold' : 'default'} className="whitespace-nowrap px-1.5 py-0.5">{node.packageName || '-'}</AdminBadge>
        </div>
        <div className="grid grid-cols-[auto_max-content] items-center justify-between gap-2 whitespace-nowrap">
          <span>{adminText('Статус')}:</span>
          <span className="whitespace-nowrap text-safi-green">{node.status}</span>
        </div>
      </div>
      <div className={cn('mt-1 shrink-0 text-center font-extrabold leading-none', sizeConfig.pvText)}>
        <div className="whitespace-nowrap text-safi-gold" title={adminText('Личный PV')} aria-label={adminText('Личный PV')}>
          Личный PV: {node.personalPV.toLocaleString('ru-RU')}
        </div>
        <div className="mt-1 whitespace-nowrap text-safi-muted" title={adminText('Командный PV')} aria-label={adminText('Командный PV')}>
          Командный PV: {node.teamPV.toLocaleString('ru-RU')}
        </div>
        <div className="mt-1 grid grid-cols-[max-content_max-content] justify-center gap-1 text-safi-green">
          <span
            className="min-w-max whitespace-nowrap rounded-full bg-[#F5F5F0] px-1 py-1 text-center leading-none"
            title={adminText('Левая ветка PV')}
            aria-label={adminText('Левая ветка PV')}
          >
            {formatBranchPv('л', node.leftBranchPV)}
          </span>
          <span
            className="min-w-max whitespace-nowrap rounded-full bg-[#F5F5F0] px-1 py-1 text-center leading-none"
            title={adminText('Правая ветка PV')}
            aria-label={adminText('Правая ветка PV')}
          >
            {formatBranchPv('п', node.rightBranchPV)}
          </span>
        </div>
      </div>
    </button>
  );
}

function EmptyTreeSlotCard({ sizeConfig, branch }: { sizeConfig: TreeSizeConfig; branch?: 'L' | 'R' }) {
  return (
    <div
      className={cn('flex h-auto w-max flex-col items-center justify-center overflow-visible rounded-2xl border-2 border-dashed border-safi-green/20 bg-[#F5F5F0]/60 text-center opacity-75', sizeConfig.padding)}
      style={{ minWidth: `${sizeConfig.width}px`, minHeight: `${Math.round(sizeConfig.height * 0.72)}px` }}
    >
      <div className={cn('mb-2 flex items-center justify-center rounded-full bg-safi-green/5 pb-1 text-safi-green/40', sizeConfig.avatar)}>+</div>
      <div className={cn('font-bold text-safi-text/50', sizeConfig.nameText)}>{adminText('a_0KHQstC-0LHQ')}</div>
      {branch && <div className={cn('mt-1 font-mono text-safi-muted', sizeConfig.metaText)}>{branch === 'L' ? adminText('Л') : adminText('П')}</div>}
    </div>
  );
}

function MeasuredTreeItem({
  item,
  onMeasure,
  children,
}: {
  item: TreeLayoutItem;
  onMeasure: (id: string, measurement: TreeNodeMeasurement) => void;
  children: ReactNode;
}) {
  const itemRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const element = itemRef.current;

    if (!element) {
      return;
    }

    const measure = () => {
      onMeasure(item.id, {
        width: Math.ceil(element.offsetWidth),
        height: Math.ceil(element.offsetHeight),
      });
    };

    measure();

    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    const observer = new ResizeObserver(measure);

    observer.observe(element);

    return () => observer.disconnect();
  }, [item.id, onMeasure]);

  return (
    <div
      ref={itemRef}
      className="absolute overflow-visible"
      style={{
        left: `${item.x}px`,
        top: `${item.y}px`,
        minWidth: `${item.width}px`,
      }}
    >
      {children}
    </div>
  );
}

function getTreeConnectorPath(
  connector: TreeConnector,
  itemsById: Map<string, TreeLayoutItem>,
  measurements: Record<string, TreeNodeMeasurement>,
) {
  const fromItem = itemsById.get(connector.fromItemId);
  const toItem = itemsById.get(connector.toItemId);
  const fromMeasurement = fromItem ? measurements[fromItem.id] : undefined;
  const toMeasurement = toItem ? measurements[toItem.id] : undefined;
  const fromX = fromItem ? fromItem.x + (fromMeasurement?.width ?? fromItem.width) / 2 : connector.fromX;
  const fromY = fromItem ? fromItem.y + (fromMeasurement?.height ?? fromItem.height) : connector.fromY;
  const toX = toItem ? toItem.x + (toMeasurement?.width ?? toItem.width) / 2 : connector.toX;
  const toY = toItem ? toItem.y : connector.toY;
  const midY = fromY + Math.max((toY - fromY) / 2, 24);

  return `M ${fromX} ${fromY} V ${midY} H ${toX} V ${toY}`;
}

function getMeasuredTreeBounds(layout: TreeLayout, measurements: Record<string, TreeNodeMeasurement>) {
  let width = layout.width;
  let height = layout.height;
  const padding = 48;

  layout.items.forEach((item) => {
    const measurement = measurements[item.id];

    width = Math.max(width, item.x + (measurement?.width ?? item.width) + padding);
    height = Math.max(height, item.y + (measurement?.height ?? item.height) + padding);
  });

  return {
    width: Math.ceil(width),
    height: Math.ceil(height),
  };
}

function getNodeItemId(node: StructureNode) {
  return `node-${node.userId}-${node.id}`;
}

function getEmptyItemId(parent: StructureNode, branch: 'L' | 'R') {
  return `empty-${parent.userId}-${branch}`;
}

function computeTreeLayout(
  root: StructureNode,
  settings: TreeViewSettings,
  sizeConfig: TreeSizeConfig,
  densityConfig: TreeDensityConfig,
): TreeLayout {
  const widthCache = new Map<StructureNode, number>();
  const items: TreeLayoutItem[] = [];
  const connectors: TreeConnector[] = [];
  const paddingX = Math.max(32, densityConfig.horizontalGap);
  const paddingTop = 32;
  const paddingBottom = 48;
  let maxBottom = paddingTop + sizeConfig.height;
  let rootCenterX = paddingX + sizeConfig.width / 2;

  const emptySlotWidth = (node: StructureNode) => (
    settings.showEmptySlots && Boolean(node.children.left || node.children.right) ? sizeConfig.width : 0
  );

  const getNodeWidth = (node: StructureNode): number => {
    const cachedWidth = widthCache.get(node);

    if (typeof cachedWidth === 'number') {
      return cachedWidth;
    }

    const leftWidth = node.children.left ? getNodeWidth(node.children.left) : emptySlotWidth(node);
    const rightWidth = node.children.right ? getNodeWidth(node.children.right) : emptySlotWidth(node);
    const childrenWidth = leftWidth + rightWidth + (leftWidth > 0 && rightWidth > 0 ? densityConfig.horizontalGap : 0);
    const width = Math.max(sizeConfig.width, childrenWidth);

    widthCache.set(node, width);

    return width;
  };

  const addConnector = (
    parentItemId: string,
    targetItemId: string,
    parentX: number,
    parentY: number,
    childCenterX: number,
    childY: number,
    branch: 'L' | 'R',
    isEmptyTarget: boolean,
  ) => {
    connectors.push({
      id: `${parentItemId}-${branch}-${targetItemId}`,
      fromItemId: parentItemId,
      toItemId: targetItemId,
      fromX: parentX + sizeConfig.width / 2,
      fromY: parentY + sizeConfig.height,
      toX: childCenterX,
      toY: childY,
      isEmptyTarget,
    });
  };

  const addEmptySlot = (
    parent: StructureNode,
    branch: 'L' | 'R',
    x: number,
    y: number,
    slotWidth: number,
    parentX: number,
    parentY: number,
  ) => {
    if (slotWidth <= 0) {
      return;
    }

    const emptyX = x + slotWidth / 2 - sizeConfig.width / 2;
    const emptyItemId = getEmptyItemId(parent, branch);

    items.push({
      id: emptyItemId,
      kind: 'empty',
      node: null,
      x: emptyX,
      y,
      width: sizeConfig.width,
      height: sizeConfig.height,
      branch,
    });
    addConnector(getNodeItemId(parent), emptyItemId, parentX, parentY, emptyX + sizeConfig.width / 2, y, branch, true);
    maxBottom = Math.max(maxBottom, y + sizeConfig.height);
  };

  const layoutNode = (node: StructureNode, x: number, y: number, isRoot = false) => {
    const subtreeWidth = getNodeWidth(node);
    const nodeX = x + subtreeWidth / 2 - sizeConfig.width / 2;
    const itemId = getNodeItemId(node);

    if (isRoot) {
      rootCenterX = nodeX + sizeConfig.width / 2;
    }

    items.push({
      id: itemId,
      kind: 'node',
      node,
      x: nodeX,
      y,
      width: sizeConfig.width,
      height: sizeConfig.height,
      isRoot,
    });
    maxBottom = Math.max(maxBottom, y + sizeConfig.height);

    const hasAnyChild = Boolean(node.children.left || node.children.right);

    if (!hasAnyChild) {
      return;
    }

    const childY = y + sizeConfig.height + densityConfig.verticalGap;
    const leftWidth = node.children.left ? getNodeWidth(node.children.left) : emptySlotWidth(node);
    const rightWidth = node.children.right ? getNodeWidth(node.children.right) : emptySlotWidth(node);
    const hasBothSlots = leftWidth > 0 && rightWidth > 0;
    let childX = x;

    if (leftWidth > 0) {
      if (node.children.left) {
        const childItemId = getNodeItemId(node.children.left);

        layoutNode(node.children.left, childX, childY);
        addConnector(
          itemId,
          childItemId,
          nodeX,
          y,
          childX + leftWidth / 2,
          childY,
          'L',
          false,
        );
      } else {
        addEmptySlot(node, 'L', childX, childY, leftWidth, nodeX, y);
      }

      childX += leftWidth + (hasBothSlots ? densityConfig.horizontalGap : 0);
    }

    if (rightWidth > 0) {
      if (node.children.right) {
        const childItemId = getNodeItemId(node.children.right);

        layoutNode(node.children.right, childX, childY);
        addConnector(
          itemId,
          childItemId,
          nodeX,
          y,
          childX + rightWidth / 2,
          childY,
          'R',
          false,
        );
      } else {
        addEmptySlot(node, 'R', childX, childY, rightWidth, nodeX, y);
      }
    }
  };

  const rootWidth = getNodeWidth(root);

  layoutNode(root, paddingX, paddingTop, true);

  return {
    width: Math.ceil(rootWidth + paddingX * 2),
    height: Math.ceil(maxBottom + paddingBottom),
    rootCenterX,
    rootY: paddingTop,
    rootItemId: getNodeItemId(root),
    items,
    connectors,
  };
}

function readTreeViewSettings(): TreeViewSettings {
  if (typeof window === 'undefined') {
    return defaultTreeViewSettings;
  }

  try {
    const savedSettings = window.localStorage.getItem(treeSettingsStorageKey);

    if (!savedSettings) {
      return defaultTreeViewSettings;
    }

    const parsedSettings = JSON.parse(savedSettings);

    return normalizeTreeViewSettings(isRecord(parsedSettings) ? parsedSettings as Partial<TreeViewSettings> : {});
  } catch {
    return defaultTreeViewSettings;
  }
}

function normalizeTreeViewSettings(settings: Partial<TreeViewSettings>): TreeViewSettings {
  return {
    zoom: clampNumber(
      Number.isFinite(Number(settings.zoom)) ? Number(settings.zoom) : defaultTreeViewSettings.zoom,
      minTreeZoom,
      maxTreeZoom,
    ),
    nodeSize: isTreeNodeSize(settings.nodeSize) ? settings.nodeSize : defaultTreeViewSettings.nodeSize,
    density: isTreeDensity(settings.density) ? settings.density : defaultTreeViewSettings.density,
    showEmptySlots: typeof settings.showEmptySlots === 'boolean' ? settings.showEmptySlots : defaultTreeViewSettings.showEmptySlots,
  };
}

function isTreeNodeSize(value: unknown): value is TreeNodeSize {
  return value === 'small' || value === 'normal' || value === 'large';
}

function isTreeDensity(value: unknown): value is TreeDensity {
  return value === 'compact' || value === 'normal' || value === 'wide';
}

function clampNumber(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max);
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

    return {
      id: getString(record, ['user_id', 'id']) || String(index + 1),
      name: getString(record, ['name', 'full_name', 'fullName']) || `Partner ${index + 1}`,
      login: getString(record, ['login']) || '',
      email: getString(record, ['email']) || '-',
      phone: getString(record, ['phone']) || getString(profile, ['phone']) || '-',
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
  const rawCode = getString(record, ['package_code', 'packageCode'])
    || getString(pkg, ['code', 'slug', 'id'])
    || getString(record, ['package'])
    || '';
  const packageStatus = getPartnerPackageStatus(record, rawCode);

  if (packageStatus !== 'active') {
    return '-';
  }

  return packageLabel(rawCode, getString(record, ['package_label', 'packageLabel', 'package_name', 'packageName'])
    || getString(pkg, ['code_label', 'codeLabel', 'label', 'name'])
    || '-');
}

function normalizePackageCode(record: Record<string, unknown>) {
  const pkg = record.package && typeof record.package === 'object' ? record.package as Record<string, unknown> : undefined;
  const rawCode = getString(record, ['package_code', 'packageCode'])
    || getString(pkg, ['code', 'slug', 'id'])
    || getString(record, ['package'])
    || '';

  return getPartnerPackageStatus(record, rawCode) === 'active' ? String(rawCode).toUpperCase() : '';
}

function normalizeMlmStatus(record: Record<string, unknown>) {
  const mlmStatus = record.mlm_status && typeof record.mlm_status === 'object' ? record.mlm_status as Record<string, unknown> : undefined;
  const code = getString(mlmStatus, ['code']) || getString(record, ['status']);
  const label = getString(mlmStatus, ['label']) || getString(record, ['status_label', 'statusLabel']) || '-';

  return getPartnerPackageStatus(record, normalizePackageCode(record)) === 'active' ? mlmStatusLabel(code, label) : 'Неактивен';
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
    personalPV: getNumber(record, ['package_pv', 'packagePv', 'personal_pv', 'personalPv', 'package_activity_pv', 'packageActivityPv']) ?? 0,
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

function formatBranchPv(branch: 'л' | 'п', value: number) {
  return `${branch}:${value.toLocaleString('ru-RU')}PV`;
}
