import { FormEvent, useEffect, useMemo, useState } from 'react';
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
  packageName: string;
  status: string;
  pv: number;
  leftPV: number;
  rightPV: number;
  depth: number;
  children: {
    left: StructureNode | null;
    right: StructureNode | null;
  };
}

export default function AdminStructure() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const selectedUserId = searchParams.get('user_id') || '';
  const [view, setView] = useState<'tree' | 'list'>('tree');
  const [query, setQuery] = useState(selectedUserId);
  const [rootNode, setRootNode] = useState<StructureNode | null>(null);
  const [nodes, setNodes] = useState<StructureNode[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadStructure = async () => {
    setIsLoading(true);
    setError(null);
    setRootNode(null);
    setNodes([]);

    try {
      const response = await getAdminStructure(selectedUserId ? { user_id: selectedUserId } : {});
      const root = normalizeRoot(response);

      setRootNode(root);
      setNodes(root ? flattenTree(root) : normalizeFlatNodes(response));
    } catch (caughtError) {
      setRootNode(null);
      setNodes([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить структуру.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    setQuery(selectedUserId);
    void loadStructure();
  }, [selectedUserId]);

  const visibleNodes = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery || /^\d+$/.test(normalizedQuery)) {
      return nodes;
    }

    return nodes.filter((node) => `${node.name} ${node.login} ${node.email} ${node.userId}`.toLowerCase().includes(normalizedQuery));
  }, [nodes, query]);

  const hasChildren = Boolean(rootNode?.children.left || rootNode?.children.right);

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedQuery = query.trim();

    if (/^\d+$/.test(normalizedQuery)) {
      navigate(`/admin/structure?user_id=${encodeURIComponent(normalizedQuery)}`);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-6xl mx-auto">
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

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadStructure} />}
      {!isLoading && !error && !rootNode && (
        <EmptyState title="Структура не найдена" description="Бинарное дерево появится после размещения партнеров." />
      )}

      {!isLoading && !error && rootNode && view === 'tree' && (
        <div className="bg-white rounded-[32px] border border-safi-green/5 shadow-sm p-8 min-h-[520px] flex items-center justify-center overflow-x-auto relative hidden-scrollbar">
          <div className="absolute top-4 right-4 flex items-center gap-2 text-xs text-safi-text/50">
            <Info className="w-4 h-4" />
            Данные из backend API
          </div>

          {!hasChildren && (
            <div className="absolute bottom-5 left-5 right-5 rounded-2xl bg-[#F5F5F0] px-4 py-3 text-center text-xs font-bold text-safi-text/60">
              У партнёра пока нет нижестоящих участников
            </div>
          )}

          <div className="flex flex-col items-center gap-8 min-w-[680px] py-10">
            <TreeNode node={rootNode} isRoot />

            <div className="flex gap-16 relative">
              <div className="absolute -top-8 left-1/4 right-1/4 h-8 border-t-2 border-l-2 border-r-2 border-safi-green/20 rounded-t-xl" />
              <div className="absolute -top-8 left-1/2 bottom-full border-l-2 border-safi-green/20" />

              <div className="flex flex-col items-center gap-8 relative">
                <div className="absolute -top-4 w-12 text-center text-[10px] font-bold text-safi-text/40 bg-white left-1/2 -ml-6">Левая</div>
                {rootNode.children.left ? <TreeNode node={rootNode.children.left} /> : <EmptyTreeSlot />}
              </div>

              <div className="flex flex-col items-center gap-8 relative">
                <div className="absolute -top-4 w-12 text-center text-[10px] font-bold text-safi-text/40 bg-white left-1/2 -ml-6">Правая</div>
                {rootNode.children.right ? <TreeNode node={rootNode.children.right} /> : <EmptyTreeSlot />}
              </div>
            </div>
          </div>
        </div>
      )}

      {!isLoading && !error && visibleNodes.length > 0 && view === 'list' && (
        <AdminTable headers={['Партнер', 'Пакет', 'PV', 'Ветка', 'Уровень']}>
          {visibleNodes.map((node) => (
            <tr key={`${node.id}-${node.userId}`} className="hover:bg-safi-green/5 transition-colors">
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{node.name}</div>
                <div className="text-[10px] font-mono text-safi-text/50">{node.login || node.userId}</div>
              </td>
              <td className="px-6 py-4"><AdminBadge variant="gold">{node.packageName}</AdminBadge></td>
              <td className="px-6 py-4 font-bold text-safi-green">{node.pv.toLocaleString('ru-RU')} PV</td>
              <td className="px-6 py-4">{formatPosition(node.position)}</td>
              <td className="px-6 py-4">{node.depth}</td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}

function TreeNode({ node, isRoot }: { node: StructureNode; isRoot?: boolean }) {
  return (
    <div className={cn(
      'w-48 p-4 bg-white rounded-2xl flex flex-col items-center text-center shadow-sm cursor-pointer hover:-translate-y-1 transition-transform',
      isRoot ? 'border-2 border-safi-gold shadow-md' : 'border border-safi-green/10'
    )}>
      <div className={cn(
        'w-12 h-12 rounded-full flex items-center justify-center text-lg font-serif text-white font-bold mb-3',
        node.packageName === 'START' ? 'bg-blue-400' : node.packageName === 'VIP' ? 'bg-purple-500' : 'bg-safi-gold'
      )}>
        {node.name.charAt(0)}
      </div>
      <div className="font-bold text-sm text-safi-green mb-1 line-clamp-1 truncate w-full" title={node.name}>{node.name}</div>
      <div className="text-[10px] font-mono text-safi-text/50 bg-[#F5F5F0] px-2 py-0.5 rounded mb-2">{node.login || node.userId}</div>
      <div className="w-full flex justify-between items-center text-[10px] border-t border-safi-green/5 pt-2 mt-1">
        <AdminBadge variant={node.packageName === 'ELITE' || node.packageName === 'VIP' ? 'gold' : 'default'} className="px-1.5 py-0.5">{node.packageName || '-'}</AdminBadge>
        <span className="font-bold text-safi-green">{node.pv} PV</span>
      </div>
    </div>
  );
}

function EmptyTreeSlot() {
  return (
    <div className="w-48 p-4 bg-[#F5F5F0]/50 border-2 border-dashed border-safi-green/20 rounded-2xl flex flex-col items-center text-center justify-center opacity-70">
      <div className="w-10 h-10 rounded-full bg-safi-green/5 text-safi-green/40 flex items-center justify-center text-xl pb-1 mb-2">+</div>
      <div className="text-xs font-bold text-safi-text/50">Свободная позиция</div>
    </div>
  );
}

function normalizeRoot(response: unknown): StructureNode | null {
  const root = unwrapRecord(response, ['root']);

  if (!root || Object.keys(root).length === 0) {
    return null;
  }

  return normalizeTreeNode(root);
}

function normalizeTreeNode(record: Record<string, unknown>): StructureNode {
  const children = record.children && typeof record.children === 'object' ? record.children as Record<string, unknown> : {};
  const left = children.left && typeof children.left === 'object' ? normalizeTreeNode(children.left as Record<string, unknown>) : null;
  const right = children.right && typeof children.right === 'object' ? normalizeTreeNode(children.right as Record<string, unknown>) : null;

  return {
    id: getString(record, ['binary_node_id', 'id']) || '-',
    userId: getString(record, ['user_id', 'id']) || '-',
    parentId: getString(record, ['parent_id']) || '',
    position: getString(record, ['position', 'branch']) || '',
    name: getString(record, ['name']) || '-',
    login: getString(record, ['login']) || '',
    email: getString(record, ['email']) || '',
    packageName: getString(record, ['package']) || '-',
    status: getString(record, ['status']) || '-',
    pv: getNumber(record, ['total_pv']) ?? 0,
    leftPV: getNumber(record, ['left_pv']) ?? 0,
    rightPV: getNumber(record, ['right_pv']) ?? 0,
    depth: getNumber(record, ['level', 'depth']) ?? 0,
    children: { left, right },
  };
}

function normalizeFlatNodes(response: unknown): StructureNode[] {
  return getArray(response, ['nodes']).map((item, index) => {
    const node = item && typeof item === 'object' ? item as Record<string, unknown> : {};
    const user = node.user && typeof node.user === 'object' ? node.user as Record<string, unknown> : {};
    const pkg = user.current_package && typeof user.current_package === 'object' ? user.current_package as Record<string, unknown> : {};

    return {
      id: getString(node, ['id']) || String(index + 1),
      userId: getString(node, ['user_id']) || getString(user, ['id']) || '-',
      parentId: getString(node, ['parent_id']) || '',
      position: getString(node, ['position', 'branch']) || '',
      name: getString(user, ['name']) || getString(node, ['name']) || `Partner ${index + 1}`,
      login: getString(user, ['login']) || getString(node, ['login']) || '',
      email: getString(user, ['email']) || getString(node, ['email']) || '',
      packageName: getString(pkg, ['name', 'code']) || getString(node, ['package']) || '-',
      status: getString(user, ['status']) || getString(node, ['status']) || '-',
      pv: getNumber(user, ['total_pv']) ?? getNumber(node, ['total_pv']) ?? 0,
      leftPV: getNumber(user, ['left_pv']) ?? getNumber(node, ['left_pv']) ?? 0,
      rightPV: getNumber(user, ['right_pv']) ?? getNumber(node, ['right_pv']) ?? 0,
      depth: getNumber(node, ['depth', 'level']) ?? 0,
      children: { left: null, right: null },
    };
  });
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
