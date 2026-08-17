import { type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Crosshair, Maximize2, Minus, Plus, RotateCcw } from 'lucide-react';
import { AdminBadge } from '../admin/ui';
import { EmptyState } from '../ui/AsyncState';
import { adminText } from '../../i18n/adminText';
import { cn } from '../../lib/utils';
import { NoTranslate } from '../ui/NoTranslate';

export interface StructureTreeCanvasNode {
  id: string;
  userId?: string;
  name: string;
  login: string;
  packageCode: string;
  packageName: string;
  status: string;
  personalPV: number;
  teamPV: number;
  leftBranchPV?: number;
  rightBranchPV?: number;
  leftPV?: number;
  rightPV?: number;
  children: {
    left: StructureTreeCanvasNode | null;
    right: StructureTreeCanvasNode | null;
  };
}

export interface StructureTreeCanvasDepthInfo {
  hasDeeperNodes: boolean;
  hiddenNodesCount: number;
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
  node: StructureTreeCanvasNode | null;
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

const minTreeZoom = 0.35;
const maxTreeZoom = 1.2;
const defaultTreeViewSettings: TreeViewSettings = {
  zoom: 0.75,
  nodeSize: 'small',
  density: 'compact',
  showEmptySlots: true,
};
const defaultStorageKey = 'safi_structure_tree_view_settings';
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

export function StructureTreeCanvas({
  rootNode,
  storageKey = defaultStorageKey,
  framed = true,
  infoText = adminText('a_0JjRgdC_0L7Q'),
  depthLabel,
  depthInfo,
  emptyMessage = adminText('a_0KMg0L_QsNGA'),
  onOpenNode,
  className,
}: {
  rootNode: StructureTreeCanvasNode | null;
  storageKey?: string;
  framed?: boolean;
  infoText?: ReactNode;
  depthLabel?: ReactNode;
  depthInfo?: StructureTreeCanvasDepthInfo;
  emptyMessage?: string;
  onOpenNode?: (userId: string) => void;
  className?: string;
}) {
  const treeScrollRef = useRef<HTMLDivElement | null>(null);
  const treeProgrammaticScrollRef = useRef(false);
  const treeUserScrolledRef = useRef(false);
  const [treeSettings, setTreeSettings] = useState<TreeViewSettings>(() => readTreeViewSettings(storageKey));
  const [treeNodeMeasurements, setTreeNodeMeasurements] = useState<Record<string, TreeNodeMeasurement>>({});
  const nodeSizeConfig = treeNodeSizeConfigs[treeSettings.nodeSize];
  const densityConfig = treeDensityConfigs[treeSettings.density];
  const hasChildren = Boolean(rootNode?.children.left || rootNode?.children.right);
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

    window.localStorage.setItem(storageKey, JSON.stringify(nextSettings));
  }, [storageKey]);

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
      window.localStorage.removeItem(storageKey);
    }

    treeUserScrolledRef.current = false;
    setTreeSettings(defaultTreeViewSettings);
    window.setTimeout(() => centerTree(defaultTreeViewSettings.zoom), 0);
  }, [centerTree, storageKey]);

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

  if (!rootNode) {
    return (
      <div className={cn(framed && 'rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6', className)}>
        <EmptyState title={adminText('Дерево недоступно')} description={adminText('Не удалось получить корневой узел структуры.')} />
      </div>
    );
  }

  return (
    <div className={cn(framed && 'rounded-[32px] border border-safi-green/5 bg-white p-4 shadow-sm md:p-6', className)}>
      <div className="mb-4 flex flex-col gap-3">
        <div className="flex flex-col gap-2 text-xs font-bold text-safi-text/50 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-2">
            {infoText}
          </div>
          {depthLabel && <span>{depthLabel}</span>}
        </div>
        <StructureTreeToolbar
          settings={treeSettings}
          onChange={updateTreeSettings}
          onFit={fitTreeToScreen}
          onCenter={() => centerTree()}
          onReset={resetTreeView}
        />
      </div>

      {depthInfo?.hasDeeperNodes && (
        <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800">
          {adminText('Есть ещё партнёры глубже текущей глубины дерева')}: {depthInfo.hiddenNodesCount.toLocaleString('ru-RU')}. {adminText('Увеличьте depth в URL до 50 или используйте список выше.')}
        </div>
      )}

      <div
        ref={treeScrollRef}
        onScroll={() => {
          if (!treeProgrammaticScrollRef.current) {
            treeUserScrolledRef.current = true;
          }
        }}
        className="relative max-h-[calc(100vh-260px)] min-h-[540px] overflow-x-auto overflow-y-auto rounded-[24px] border border-safi-border bg-white"
      >
        {!hasChildren && (
          <div className="sticky bottom-5 left-5 z-10 mx-5 mt-5 rounded-2xl bg-[#F5F5F0] px-4 py-3 text-center text-xs font-bold text-safi-text/60">{emptyMessage}</div>
        )}

        {treeLayout && treeBounds && (
          <div
            className="relative"
            style={{
              width: `${treeBounds.width * treeSettings.zoom}px`,
              height: `${treeBounds.height * treeSettings.zoom}px`,
            }}
          >
            <div
              className="absolute left-0 top-0 bg-[radial-gradient(circle_at_1px_1px,rgba(35,74,58,0.08)_1px,transparent_0)] [background-size:28px_28px]"
              style={{
                width: `${treeBounds.width}px`,
                height: `${treeBounds.height}px`,
                transform: `scale(${treeSettings.zoom})`,
                transformOrigin: 'top left',
              }}
            >
              <svg
                className="absolute inset-0"
                width={treeBounds.width}
                height={treeBounds.height}
                viewBox={`0 0 ${treeBounds.width} ${treeBounds.height}`}
                style={{ pointerEvents: 'none' }}
              >
                {treeLayout.connectors.map((connector) => {
                  const connectorPath = getTreeConnectorPath(connector, treeItemsById, treeNodeMeasurements);

                  return (
                    <path
                      key={connector.id}
                      d={connectorPath}
                      fill="none"
                      stroke={connector.isEmptyTarget ? 'rgba(35,74,58,0.18)' : 'rgba(35,74,58,0.32)'}
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeDasharray={connector.isEmptyTarget ? '6 7' : undefined}
                    />
                  );
                })}
              </svg>
              {treeLayout.items.map((item) => (
                <MeasuredTreeItem
                  key={item.id}
                  item={item}
                  onMeasure={updateTreeNodeMeasurement}
                >
                  {item.kind === 'node' && item.node ? (
                    <TreeNodeCard node={item.node} isRoot={item.isRoot} onOpen={onOpenNode} sizeConfig={nodeSizeConfig} />
                  ) : (
                    <EmptyTreeSlotCard sizeConfig={nodeSizeConfig} branch={item.branch} />
                  )}
                </MeasuredTreeItem>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
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
        <IconButton label={adminText('Уменьшить')} onClick={() => onChange({ zoom: settings.zoom - 0.05 })}>
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
        <IconButton label={adminText('Увеличить')} onClick={() => onChange({ zoom: settings.zoom + 0.05 })}>
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
        label={adminText('Карточки')}
        value={settings.nodeSize}
        options={[
          ['small', 'small'],
          ['normal', 'normal'],
          ['large', 'large'],
        ]}
        onChange={(value) => onChange({ nodeSize: value as TreeNodeSize })}
      />
      <SegmentedTreeControl
        label={adminText('Плотность')}
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
        {adminText('Свободные места')}
      </label>
      <div className="ml-auto flex flex-wrap items-center gap-2">
        <ToolbarButton label={adminText('Вместить')} onClick={onFit} icon={<Maximize2 className="h-4 w-4" />} />
        <ToolbarButton label={adminText('Центрировать')} onClick={onCenter} icon={<Crosshair className="h-4 w-4" />} />
        <ToolbarButton label={adminText('Сбросить')} onClick={onReset} icon={<RotateCcw className="h-4 w-4" />} />
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
  node: StructureTreeCanvasNode;
  isRoot?: boolean;
  onOpen?: (userId: string) => void;
  sizeConfig: TreeSizeConfig;
}) {
  const userId = getTreeNodeUserId(node);
  const leftBranchPV = node.leftBranchPV ?? node.leftPV ?? 0;
  const rightBranchPV = node.rightBranchPV ?? node.rightPV ?? 0;
  const clickable = typeof onOpen === 'function';

  return (
    <button
      type="button"
      onClick={() => onOpen?.(userId)}
      disabled={!clickable}
      className={cn(
        'flex h-auto w-max max-w-none flex-col overflow-visible rounded-2xl bg-white text-center shadow-sm transition-transform focus:outline-none focus:ring-2 focus:ring-safi-green/20',
        clickable && 'cursor-pointer hover:-translate-y-1',
        !clickable && 'cursor-default',
        sizeConfig.padding,
        isRoot ? 'border-2 border-safi-gold shadow-md' : 'border border-safi-green/10',
      )}
      style={{ minWidth: `${sizeConfig.width}px` }}
      title={clickable ? adminText('a_0J7RgtC60YDR_5') : undefined}
    >
      <div className={cn(
        'mx-auto mb-2 flex shrink-0 items-center justify-center rounded-full font-serif font-bold text-white',
        sizeConfig.avatar,
        node.packageCode === 'START' ? 'bg-blue-400' : node.packageCode === 'VIP' ? 'bg-purple-500' : 'bg-safi-gold',
      )}>
        {(node.name || node.login || userId || '?').charAt(0)}
      </div>
      <NoTranslate as="div" className={cn('mb-1 w-full truncate font-bold leading-tight text-safi-green', sizeConfig.nameText)} style={{ maxWidth: `${sizeConfig.width}px` }} title={node.name}>{node.name}</NoTranslate>
      <NoTranslate as="div" className={cn('mb-2 truncate rounded bg-[#F5F5F0] px-2 py-0.5 font-mono text-safi-text/50', sizeConfig.metaText)} style={{ maxWidth: `${sizeConfig.width}px` }}>{node.login || userId}</NoTranslate>
      <div className={cn('space-y-1 overflow-visible border-t border-safi-green/5 pt-2 text-left font-bold text-safi-text/70', sizeConfig.detailText)}>
        <div className="grid grid-cols-[auto_max-content] items-center justify-between gap-2 whitespace-nowrap">
          <span>{adminText('Пакет')}:</span>
          <AdminBadge variant={node.packageCode === 'ELITE' || node.packageCode === 'VIP' ? 'gold' : 'default'} className="whitespace-nowrap px-1.5 py-0.5"><NoTranslate>{node.packageCode || node.packageName || '-'}</NoTranslate></AdminBadge>
        </div>
        <div className="grid grid-cols-[auto_max-content] items-center justify-between gap-2 whitespace-nowrap">
          <span>{adminText('Статус')}:</span>
          <span className="whitespace-nowrap text-safi-green">{node.status}</span>
        </div>
      </div>
      <div className={cn('mt-1 shrink-0 text-center font-extrabold leading-none', sizeConfig.pvText)}>
        <div className="whitespace-nowrap text-safi-gold" title={adminText('Личный PV')} aria-label={adminText('Личный PV')}>
          {adminText('Личный PV')}: {node.personalPV.toLocaleString('ru-RU')}
        </div>
        <div className="mt-1 whitespace-nowrap text-safi-muted" title={adminText('Командный PV')} aria-label={adminText('Командный PV')}>
          {adminText('Командный PV')}: {node.teamPV.toLocaleString('ru-RU')}
        </div>
        <div className="mt-1 grid grid-cols-[max-content_max-content] justify-center gap-1 text-safi-green">
          <span
            className="min-w-max whitespace-nowrap rounded-full bg-[#F5F5F0] px-1 py-1 text-center leading-none"
            title={adminText('Левая ветка PV')}
            aria-label={adminText('Левая ветка PV')}
          >
            {formatBranchPv('л', leftBranchPV)}
          </span>
          <span
            className="min-w-max whitespace-nowrap rounded-full bg-[#F5F5F0] px-1 py-1 text-center leading-none"
            title={adminText('Правая ветка PV')}
            aria-label={adminText('Правая ветка PV')}
          >
            {formatBranchPv('п', rightBranchPV)}
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

function getTreeNodeUserId(node: StructureTreeCanvasNode) {
  return node.userId || node.id;
}

function getNodeItemId(node: StructureTreeCanvasNode) {
  return `node-${getTreeNodeUserId(node)}-${node.id}`;
}

function getEmptyItemId(parent: StructureTreeCanvasNode, branch: 'L' | 'R') {
  return `empty-${getTreeNodeUserId(parent)}-${branch}`;
}

function computeTreeLayout(
  root: StructureTreeCanvasNode,
  settings: TreeViewSettings,
  sizeConfig: TreeSizeConfig,
  densityConfig: TreeDensityConfig,
): TreeLayout {
  const widthCache = new Map<StructureTreeCanvasNode, number>();
  const items: TreeLayoutItem[] = [];
  const connectors: TreeConnector[] = [];
  const paddingX = Math.max(32, densityConfig.horizontalGap);
  const paddingTop = 32;
  const paddingBottom = 48;
  let maxBottom = paddingTop + sizeConfig.height;
  let rootCenterX = paddingX + sizeConfig.width / 2;

  const emptySlotWidth = (node: StructureTreeCanvasNode) => (
    settings.showEmptySlots && Boolean(node.children.left || node.children.right) ? sizeConfig.width : 0
  );

  const getNodeWidth = (node: StructureTreeCanvasNode): number => {
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
    parent: StructureTreeCanvasNode,
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

  const layoutNode = (node: StructureTreeCanvasNode, x: number, y: number, isRoot = false) => {
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

function readTreeViewSettings(storageKey: string): TreeViewSettings {
  if (typeof window === 'undefined') {
    return defaultTreeViewSettings;
  }

  try {
    const savedSettings = window.localStorage.getItem(storageKey);

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

function formatBranchPv(branch: 'л' | 'п', value: number) {
  return `${branch}:${value.toLocaleString('ru-RU')}PV`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}
