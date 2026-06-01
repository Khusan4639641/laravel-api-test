import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Search, Info } from 'lucide-react';
import { cn } from '../../lib/utils';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminStructure, getApiErrorState, getArray, getNumber, getString, unwrapRecord } from '../../lib/api';

interface StructureNode {
  id: string;
  userId: string;
  parentId: string;
  position: string;
  name: string;
  login: string;
  email: string;
  sponsor: string;
  packageName: string;
  status: string;
  pv: number;
  leftPV: number;
  rightPV: number;
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
}

const emptyStats: StructureStats = {
  directInvitedCount: 0,
  totalDownlineCount: 0,
  leftBranchCount: 0,
  rightBranchCount: 0,
};

export default function AdminStructure() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const selectedUserId = searchParams.get('user_id') || '';
  const selectedDepth = searchParams.get('depth') || '5';
  const [view, setView] = useState<'tree' | 'list'>('tree');
  const [query, setQuery] = useState(selectedUserId);
  const [rootNode, setRootNode] = useState<StructureNode | null>(null);
  const [nodes, setNodes] = useState<StructureNode[]>([]);
  const [stats, setStats] = useState<StructureStats>(emptyStats);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadStructure = async () => {
    setIsLoading(true);
    setError(null);
    setRootNode(null);
    setNodes([]);
    setStats(emptyStats);

    try {
      const response = await getAdminStructure({
        ...(selectedUserId ? { user_id: selectedUserId } : {}),
        depth: selectedDepth,
        include_flat: 'true',
      });
      const root = normalizeRoot(response);

      setRootNode(root);
      setStats(normalizeStats(response));
      setNodes(normalizeFlatNodes(response, root));
    } catch (caughtError) {
      setRootNode(null);
      setNodes([]);
      setStats(emptyStats);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить структуру.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    setQuery(selectedUserId);
    void loadStructure();
  }, [selectedUserId, selectedDepth]);

  const visibleNodes = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery || /^\d+$/.test(normalizedQuery)) {
      return nodes;
    }

    return nodes.filter((node) => `${node.name} ${node.login} ${node.email} ${node.userId}`.toLowerCase().includes(normalizedQuery));
  }, [nodes, query]);

  const hasChildren = Boolean(rootNode?.children.left || rootNode?.children.right);
  const treeCanvasWidth = useMemo(() => `${Math.max(1400, (Number(selectedDepth) || 5) * 360)}px`, [selectedDepth]);

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedQuery = query.trim();

    if (/^\d+$/.test(normalizedQuery)) {
      navigate(`/admin/structure?user_id=${encodeURIComponent(normalizedQuery)}`);
    }
  };

  const openNodeTree = (userId: string) => {
    if (!userId || userId === '-') {
      return;
    }

    navigate(`/admin/structure?user_id=${encodeURIComponent(userId)}`);
  };

  return (
    <div className="w-full max-w-none space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Структура дерева</h1>
          <p className="text-sm text-safi-text/70">
            {selectedUserId && rootNode
              ? `Дерево партнёра: ${rootNode.name} / ID ${rootNode.userId}`
              : 'Визуализация бинарной структуры партнёров'}
          </p>
        </div>

        <div className="flex bg-[#F5F5F0] p-1 rounded-xl">
          <button
            type="button"
            onClick={() => setView('tree')}
            className={cn('cursor-pointer px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg transition-colors', view === 'tree' ? 'bg-white text-safi-green shadow-sm' : 'text-safi-text/50 hover:text-safi-green')}
          >
            Дерево
          </button>
          <button
            type="button"
            onClick={() => setView('list')}
            className={cn('cursor-pointer px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg transition-colors', view === 'list' ? 'bg-white text-safi-green shadow-sm' : 'text-safi-text/50 hover:text-safi-green')}
          >
            Список ветвей
          </button>
        </div>
      </div>

      <form onSubmit={submitSearch} className="bg-white p-4 rounded-[24px] border border-safi-green/5 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div className="flex-1 relative w-full">
          <Search className="w-5 h-5 text-safi-text/40 absolute left-4 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Введите ID партнёра, чтобы открыть его ветку..."
            className="w-full pl-12 pr-4 py-3 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
          />
        </div>
        <button
          type="submit"
          disabled={!/^\d+$/.test(query.trim())}
          className="w-full cursor-pointer rounded-xl bg-safi-green px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
        >
          Открыть
        </button>
      </form>

      {!isLoading && !error && rootNode && (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <SummaryCard label="Лично пригласил" value={stats.directInvitedCount.toLocaleString('ru-RU')} />
          <SummaryCard label="Всего в структуре" value={stats.totalDownlineCount.toLocaleString('ru-RU')} />
          <SummaryCard label="Левая ветка" value={stats.leftBranchCount.toLocaleString('ru-RU')} />
          <SummaryCard label="Правая ветка" value={stats.rightBranchCount.toLocaleString('ru-RU')} />
        </section>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadStructure} />}
      {!isLoading && !error && !rootNode && (
        <EmptyState title="Структура не найдена" description="Бинарное дерево появится после размещения партнеров." />
      )}

      {!isLoading && !error && rootNode && view === 'tree' && (
        <div className="rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6">
          <div className="mb-4 flex flex-col gap-2 text-xs font-bold text-safi-text/50 md:flex-row md:items-center md:justify-between">
            <div className="flex items-center gap-2">
              <Info className="h-4 w-4 shrink-0" />
              <span>Используйте горизонтальную прокрутку для просмотра всей структуры</span>
            </div>
            <span>Данные из backend API, depth {selectedDepth}</span>
          </div>

          <div className="relative max-h-[calc(100vh-260px)] min-h-[540px] overflow-x-auto overflow-y-auto rounded-[24px] border border-safi-border bg-white">
            {!hasChildren && (
              <div className="sticky bottom-5 left-5 z-10 mx-5 mt-5 rounded-2xl bg-[#F5F5F0] px-4 py-3 text-center text-xs font-bold text-safi-text/60">
                У партнёра пока нет нижестоящих участников
              </div>
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

      {!isLoading && !error && visibleNodes.length === 0 && rootNode && view === 'list' && (
        <EmptyState title="Нижестоящие партнёры не найдены" description="Список появится после размещения участников в бинарной структуре." />
      )}

      {!isLoading && !error && visibleNodes.length > 0 && view === 'list' && (
        <AdminTable headers={['ID', 'Партнёр', 'Логин', 'Спонсор', 'Ветка', 'Уровень', 'Пакет', 'Статус', 'PV', 'Баланс', 'Действия']}>
          {visibleNodes.map((node) => (
            <tr key={`${node.id}-${node.userId}`} className="hover:bg-safi-green/5 transition-colors">
              <td className="px-6 py-4 font-mono text-[10px] text-safi-text/50">{node.userId}</td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{node.name}</div>
                <div className="text-[10px] text-safi-text/50">{node.email || '-'}</div>
              </td>
              <td className="px-6 py-4 font-mono text-xs text-safi-text/70">{node.login || '-'}</td>
              <td className="px-6 py-4">{node.sponsor || '-'}</td>
              <td className="px-6 py-4">{formatPosition(node.position)}</td>
              <td className="px-6 py-4">{node.depth}</td>
              <td className="px-6 py-4"><AdminBadge variant="gold">{node.packageName}</AdminBadge></td>
              <td className="px-6 py-4"><AdminBadge variant="default">{node.status}</AdminBadge></td>
              <td className="px-6 py-4 font-bold text-safi-green">{node.pv.toLocaleString('ru-RU')} PV</td>
              <td className="px-6 py-4">{node.balance.toLocaleString('ru-RU')}</td>
              <td className="px-6 py-4">
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => openNodeTree(node.userId)}
                    className="cursor-pointer rounded-full border border-safi-green bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                  >
                    Открыть дерево
                  </button>
                  <button
                    type="button"
                    onClick={() => navigate(`/admin/partners/${encodeURIComponent(node.userId)}`)}
                    className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10"
                  >
                    Профиль
                  </button>
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
    <article className="rounded-3xl border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="mt-3 font-serif text-2xl font-semibold text-safi-green">{value}</div>
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
        title="Открыть дерево партнёра"
      >
        <div className={cn(
          'mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full text-lg font-bold font-serif text-white',
          node.packageName === 'START' ? 'bg-blue-400' : node.packageName === 'VIP' ? 'bg-purple-500' : 'bg-safi-gold'
        )}>
          {node.name.charAt(0)}
        </div>
        <div className="mb-1 w-full truncate text-sm font-bold text-safi-green" title={node.name}>{node.name}</div>
        <div className="mb-2 rounded bg-[#F5F5F0] px-2 py-0.5 font-mono text-[10px] text-safi-text/50">{node.login || node.userId}</div>
        <div className="mt-1 flex w-full items-center justify-between border-t border-safi-green/5 pt-2 text-[10px]">
          <AdminBadge variant={node.packageName === 'ELITE' || node.packageName === 'VIP' ? 'gold' : 'default'} className="px-1.5 py-0.5">{node.packageName || '-'}</AdminBadge>
          <span className="font-bold text-safi-green">{node.pv.toLocaleString('ru-RU')} PV</span>
        </div>
      </button>

      {hasChildren && (
        <div className="mt-6 flex flex-col items-center">
          <div className="h-6 border-l-2 border-safi-green/20" />
          <div className="relative grid grid-cols-2 gap-6 lg:gap-8">
            <div className="absolute left-1/4 right-1/4 top-0 border-t-2 border-safi-green/20" />
            <BranchColumn label="Левая">
              {node.children.left ? <TreeNode node={node.children.left} onOpen={onOpen} /> : <EmptyTreeSlot />}
            </BranchColumn>
            <BranchColumn label="Правая">
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
      <div className="text-xs font-bold text-safi-text/50">Свободная позиция</div>
    </div>
  );
}

function normalizeStats(response: unknown): StructureStats {
  const stats = unwrapRecord(response, ['stats']);

  return {
    directInvitedCount: getNumber(stats, ['direct_invited_count', 'directInvitedCount']) ?? 0,
    totalDownlineCount: getNumber(stats, ['total_downline_count', 'totalDownlineCount']) ?? 0,
    leftBranchCount: getNumber(stats, ['left_branch_count', 'leftBranchCount']) ?? 0,
    rightBranchCount: getNumber(stats, ['right_branch_count', 'rightBranchCount']) ?? 0,
  };
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

  return getString(pkg, ['name', 'code']) || getString(record, ['package']) || '-';
}

function normalizeNodeRecord(record: Record<string, unknown>, index = 0): StructureNode {
  const children = record.children && typeof record.children === 'object' ? record.children as Record<string, unknown> : {};
  const left = children.left && typeof children.left === 'object' ? normalizeTreeNode(children.left as Record<string, unknown>) : null;
  const right = children.right && typeof children.right === 'object' ? normalizeTreeNode(children.right as Record<string, unknown>) : null;

  return {
    id: getString(record, ['binary_node_id', 'id']) || String(index + 1),
    userId: getString(record, ['user_id', 'id']) || '-',
    parentId: getString(record, ['parent_id']) || '',
    position: getString(record, ['position', 'branch']) || '',
    name: getString(record, ['name']) || `Partner ${index + 1}`,
    login: getString(record, ['login']) || '',
    email: getString(record, ['email']) || '',
    sponsor: normalizeSponsor(record),
    packageName: normalizePackage(record),
    status: getString(record, ['status']) || '-',
    pv: getNumber(record, ['total_pv']) ?? 0,
    leftPV: getNumber(record, ['left_pv']) ?? 0,
    rightPV: getNumber(record, ['right_pv']) ?? 0,
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

function normalizeFlatNodes(response: unknown, root: StructureNode | null): StructureNode[] {
  const flat = getArray(response, ['flat', 'descendants']);

  if (flat.length > 0) {
    return flat.map((item, index) => {
      const record = item && typeof item === 'object' ? item as Record<string, unknown> : {};

      return normalizeNodeRecord(record, index);
    });
  }

  return root ? flattenTree(root).filter((node) => node.userId !== root.userId) : [];
}

function flattenTree(root: StructureNode): StructureNode[] {
  return [
    root,
    ...(root.children.left ? flattenTree(root.children.left) : []),
    ...(root.children.right ? flattenTree(root.children.right) : []),
  ];
}

function formatPosition(position: string) {
  const normalized = position.toLowerCase();

  if (['l', 'left'].includes(normalized)) {
    return 'Левая';
  }

  if (['r', 'right'].includes(normalized)) {
    return 'Правая';
  }

  return position || '-';
}
