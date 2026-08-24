import { useCallback, useEffect, useState } from 'react';
import { Filter, Network, Search, Users } from 'lucide-react';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext, type DashboardCurrentUser } from '../../components/dashboard/DashboardLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { MobileDataCard, MobileDataHeader, MobileDataList, MobileDataRow } from '../../components/ui/MobileData';
import { NoTranslate } from '../../components/ui/NoTranslate';
import { getApiErrorState, getArray, getDashboardStructure, getNumber, getString } from '../../lib/api';
import { mlmStatusLabel, packageLabel } from '../../lib/systemLabels';
import { getPartnerPackageStatus, partnerPackageStatusLabel, type PartnerPackageStatus } from '../../lib/partnerStatus';
import { StructureTreeCanvas } from '../../components/structure/StructureTreeCanvas';
import { useUiText } from '../../i18n/useUiText';

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
  packageStatus: PartnerPackageStatus;
  packageStatusLabel: string;
  status: string;
  personalPV: number;
  leftPV: number;
  rightPV: number;
  teamPV: number;
  activity: string;
  createdAt: string;
}

interface StructureTreeNode {
  id: string;
  userId: string;
  name: string;
  login: string;
  line: number;
  branch: string | null;
  packageCode: string;
  packageName: string;
  packageStatus: PartnerPackageStatus;
  packageStatusLabel: string;
  status: string;
  personalPV: number;
  leftPV: number;
  rightPV: number;
  leftBranchPV: number;
  rightBranchPV: number;
  teamPV: number;
  children: {
    left: StructureTreeNode | null;
    right: StructureTreeNode | null;
  };
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
  const ui = useUiText();
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
  const [treeRoot, setTreeRoot] = useState<StructureTreeNode | null>(null);
  const [partnersMeta, setPartnersMeta] = useState<PartnersMeta>(defaultPartnersMeta);
  const [isTreeVisible, setIsTreeVisible] = useState(false);

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
        const personalPV = getNumber(user, ['package_pv', 'packagePv', 'personal_pv', 'personalPV', 'package_activity_pv', 'packageActivityPv'])
          ?? getNumber(packageRecord, ['activity_pv', 'activityPv', 'pv'])
          ?? 0;
        const leftPV = getNumber(user, ['left_branch_pv', 'leftBranchPv', 'left_pv', 'leftPV', 'left_volume', 'leftVolume'])
          ?? getNumber(node, ['left_branch_pv', 'leftBranchPv', 'left_pv', 'leftPV', 'left_volume', 'leftVolume'])
          ?? 0;
        const rightPV = getNumber(user, ['right_branch_pv', 'rightBranchPv', 'right_pv', 'rightPV', 'right_volume', 'rightVolume'])
          ?? getNumber(node, ['right_branch_pv', 'rightBranchPv', 'right_pv', 'rightPV', 'right_volume', 'rightVolume'])
          ?? 0;
        const id = getString(user, ['id']) || getString(node, ['user_id', 'userId']) || String(index + 1);
        const rawPackageCode = getString(user, ['package_code', 'packageCode'])
          || getString(packageRecord, ['code', 'slug', 'id'])
          || getString(user, ['package'])
          || '';
        const packageStatus = getPartnerPackageStatus(user, rawPackageCode);
        const packageStatusLabel = getString(user, ['package_status_label', 'packageStatusLabel'])
          || partnerPackageStatusLabel(packageStatus);
        const packageCode = packageStatus === 'active' ? rawPackageCode : '';

        return {
          name: getString(user, ['name']) || `Partner ${index + 1}`,
          id,
          login: getString(user, ['login']) || '',
          email: getString(user, ['email']) || '',
          phone: getString(user, ['phone']) || '',
          line: getNumber(node, ['line', 'level', 'depth']) ?? getNumber(user, ['line', 'level', 'depth']) ?? 0,
          branch,
          package: packageStatus === 'active'
            ? packageLabel(packageCode, getString(user, ['package_name', 'packageName']) || getString(packageRecord, ['code_label', 'codeLabel', 'label', 'name']) || '-')
            : '-',
          packageStatus,
          packageStatusLabel,
          status: packageStatus === 'active'
            ? mlmStatusLabel(getString(user, ['status']), getString(user, ['status_label', 'statusLabel']) || '-')
            : 'Неактивен',
          personalPV,
          leftPV,
          rightPV,
          teamPV: getNumber(user, ['team_pv', 'teamPV']) ?? leftPV + rightPV,
          activity: packageStatusLabel,
          createdAt: formatDate(getString(user, ['registered_at', 'registeredAt', 'created_at', 'createdAt']) || getString(node, ['registered_at', 'registeredAt', 'created_at', 'createdAt'])),
        };
      });
      setPartners(list);
      setPartnersMeta(getPartnersMeta(record));
      const leftPV = getNumber(structureRecord, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
      const rightPV = getNumber(structureRecord, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;
      const nextStructure = {
        totalPartners: getNumber(structureRecord, ['total_partners']) ?? list.length,
        leftPartners: getNumber(structureRecord, ['left_count', 'left_partners']) ?? list.filter((partner) => partner.branch === 'Левая ветка').length,
        rightPartners: getNumber(structureRecord, ['right_count', 'right_partners']) ?? list.filter((partner) => partner.branch === 'Правая ветка').length,
        leftPV,
        rightPV,
        weakLegPV: getNumber(structureRecord, ['weak_leg_pv', 'weakLegPv', 'weak_leg_branch_pv', 'weakLegBranchPv'])
          ?? Math.min(leftPV, rightPV),
        weakLeg: getString(structureRecord, ['weak_leg']) || 'left',
      };
      setStructure(nextStructure);
      setTreeRoot(normalizeTreeNode(record.tree) ?? currentUserTreeRoot(currentUser, nextStructure));
    } catch (caughtError) {
      setPartners([]);
      setTreeRoot(null);
      setPartnersMeta(defaultPartnersMeta);
      setStructure({ totalPartners: 0, leftPartners: 0, rightPartners: 0, leftPV: 0, rightPV: 0, weakLegPV: 0, weakLeg: 'left' });
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
      setIsPartnersLoading(false);
    }
  }, [
    branchFilter,
    currentUser.id,
    currentUser.login,
    currentUser.name,
    currentUser.packageCode,
    currentUser.packageName,
    currentUser.packageStatus,
    currentUser.packageStatusLabel,
    currentUser.partnerId,
    currentUser.personalPV,
    currentUser.status,
    page,
    perPage,
    searchTerm,
  ]);

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
            <span className="safi-kicker">{ui('Structure')}</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">{ui('Моя структура')}</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              {ui('Бинарная структура и список партнеров.')}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">{ui('Пакет')}: <NoTranslate>{currentUser.packageCode || currentUser.packageName}</NoTranslate></Badge>
            <Badge variant={currentUser.packageStatus === 'active' ? 'success' : 'default'}>{ui('Пакет')}: {currentUser.packageStatusLabel}</Badge>
            <Badge variant="default">{ui('Статус')}: {currentUser.status}</Badge>
          </div>
        </div>
      </section>

      {isLoading && (
        <LoadingState title={ui('Загружаем структуру')} description={ui('Получаем бинарное дерево и партнеров из API.')} />
      )}

      {!isLoading && error && (
        <ErrorState description={error} onRetry={loadStructure} />
      )}

      {!isLoading && !error && (
        <>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard title={ui('Всего партнеров')} value={structure.totalPartners} icon={<Users className="h-5 w-5" />} />
        <StatCard title={ui('Малая ветка PV')} value={`${structure.weakLegPV.toLocaleString('ru-RU')} PV`} />
        <BranchCard title={ui('Левая ветка')} partners={structure.leftPartners} pv={structure.leftPV} branch="л" weak={structure.weakLeg === 'left'} />
        <BranchCard title={ui('Правая ветка')} partners={structure.rightPartners} pv={structure.rightPV} branch="п" weak={structure.weakLeg === 'right'} />
      </section>

      <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="border-b border-safi-border p-6 md:p-7">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">{ui('Список партнеров')}</h2>
            <div className="flex flex-col gap-3 md:flex-row">
              <label className="relative min-w-0 flex-1 md:w-80 md:flex-none">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
                <input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder={ui('Поиск по ID, имени, login, email или телефону')}
                  translate="no"
                  data-notranslate="true"
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
                  aria-label={ui('Фильтр ветки')}
                >
                  <option value="all">{ui('Все ветки')}</option>
                  <option value="left">{ui('Левая ветка')}</option>
                  <option value="right">{ui('Правая ветка')}</option>
                </select>
              </label>
            </div>
          </div>
        </div>

        <div className="relative">
          {isPartnersLoading && !isLoading && (
            <div className="absolute inset-x-0 top-0 z-10 h-1 overflow-hidden bg-safi-cream">
              <div className="h-full w-1/3 animate-pulse rounded-full bg-safi-gold" />
            </div>
          )}
          <MobileDataList className="p-5">
            {visiblePartners.length === 0 && !hasStructureListMismatch && (
              <EmptyState
                title={ui(structure.totalPartners > 0 ? 'По вашему запросу партнёры не найдены' : 'Партнёров пока нет')}
                description={ui(structure.totalPartners > 0 ? 'Попробуйте изменить поиск или фильтр ветки.' : 'Партнёры появятся в списке после добавления в бинарную структуру.')}
                className="min-h-[180px] shadow-none"
              />
            )}
            {hasStructureListMismatch && (
              <div className="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm font-bold text-amber-800">
                {ui('Не удалось загрузить список структуры')}
              </div>
            )}
            {visiblePartners.map((partner) => (
              <MobileDataCard key={partner.id}>
                <MobileDataHeader
                  title={<NoTranslate>{partner.name}</NoTranslate>}
                  meta={<NoTranslate>ID: {partner.id}{partner.login ? ` · login: ${partner.login}` : ''}</NoTranslate>}
                  action={<Badge variant={partner.packageStatus === 'active' ? 'success' : 'default'}>{partner.activity}</Badge>}
                />
                <MobileDataRow label={ui('Контакты')}>
                  {partner.email && <NoTranslate as="div">{partner.email}</NoTranslate>}
                  {partner.phone && <NoTranslate as="div" className="mt-1 text-xs text-safi-muted">{partner.phone}</NoTranslate>}
                </MobileDataRow>
                <MobileDataRow label={ui('Ветка / линия')}>
                  <div>{ui(partner.branch)}</div>
                  <div className="mt-1 text-xs text-safi-muted">{ui('Линия')}: {partner.line}</div>
                </MobileDataRow>
                <MobileDataRow label={ui('Пакет / статус')}>
                  <NoTranslate as="div">{partner.package}</NoTranslate>
                  <div className="mt-1 text-xs text-safi-muted">{partner.status !== '-' ? partner.status : ui('Участник')}</div>
                </MobileDataRow>
                <MobileDataRow label="PV">
                  <div>{ui('Личный PV')}: {partner.personalPV.toLocaleString('ru-RU')}</div>
                  <div className="mt-1 text-xs text-safi-muted">{formatBranchPv('л', partner.leftPV)} · {formatBranchPv('п', partner.rightPV)}</div>
                  <div className="mt-1 text-xs text-safi-muted">{ui('Командный PV')}: {partner.teamPV.toLocaleString('ru-RU')}</div>
                </MobileDataRow>
              </MobileDataCard>
            ))}
          </MobileDataList>

          <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[860px] text-left">
              <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                <tr>
                  <th className="px-7 py-4">{ui('Партнер')}</th>
                  <th className="px-7 py-4">{ui('Ветка / линия')}</th>
                  <th className="px-7 py-4">{ui('Пакет / статус')}</th>
                  <th className="px-7 py-4 text-right">PV</th>
                  <th className="px-7 py-4 text-center">{ui('Активность')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-safi-border text-sm">
                {visiblePartners.length === 0 && !hasStructureListMismatch && (
                  <tr>
                    <td colSpan={5} className="px-7 py-8">
                      <EmptyState
                        title={ui(structure.totalPartners > 0 ? 'По вашему запросу партнёры не найдены' : 'Партнёров пока нет')}
                        description={ui(structure.totalPartners > 0 ? 'Попробуйте изменить поиск или фильтр ветки.' : 'Партнёры появятся в списке после добавления в бинарную структуру.')}
                        className="min-h-[180px] shadow-none"
                      />
                    </td>
                  </tr>
                )}

                {hasStructureListMismatch && (
                  <tr>
                    <td colSpan={5} className="px-7 py-8">
                      <div className="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm font-bold text-amber-800">
                        {ui('Не удалось загрузить список структуры')}
                      </div>
                    </td>
                  </tr>
                )}

                {visiblePartners.map((partner) => (
                  <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
                    <td className="px-7 py-5">
                      <NoTranslate as="div" className="font-extrabold text-safi-green">{partner.name}</NoTranslate>
                      <NoTranslate as="div" className="mt-1 font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">ID: {partner.id}</NoTranslate>
                      {partner.login && <NoTranslate as="div" className="mt-1 text-xs font-bold text-safi-muted">login: {partner.login}</NoTranslate>}
                      {partner.email && <NoTranslate as="div" className="mt-1 text-xs text-safi-muted">{partner.email}</NoTranslate>}
                      {partner.phone && <NoTranslate as="div" className="mt-1 text-xs text-safi-muted">{partner.phone}</NoTranslate>}
                    </td>
                    <td className="px-7 py-5">
                      <div className="font-bold text-safi-green">{ui(partner.branch)}</div>
                      <div className="mt-1 text-xs text-safi-muted">{ui('Линия')}: {partner.line}</div>
                    </td>
                    <td className="px-7 py-5">
                      <NoTranslate as="div" className="font-bold text-safi-green">{partner.package}</NoTranslate>
                      <div className="mt-1 text-xs text-safi-muted">{partner.status !== '-' ? partner.status : ui('Участник')}</div>
                    </td>
                    <td className="px-7 py-5 text-right">
                      <div className="font-extrabold text-safi-gold">{ui('Личный PV')}: {partner.personalPV.toLocaleString('ru-RU')}</div>
                      <div className="mt-2 flex flex-wrap justify-end gap-1 text-[10px] font-extrabold text-safi-green">
                        <span className="whitespace-nowrap rounded-full bg-safi-cream px-2 py-1" title={ui('Левая ветка PV')} aria-label={ui('Левая ветка PV')}>{formatBranchPv('л', partner.leftPV)}</span>
                        <span className="whitespace-nowrap rounded-full bg-safi-cream px-2 py-1" title={ui('Правая ветка PV')} aria-label={ui('Правая ветка PV')}>{formatBranchPv('п', partner.rightPV)}</span>
                      </div>
                      <div className="mt-1 text-xs text-safi-muted">{ui('Командный PV')}: {partner.teamPV.toLocaleString('ru-RU')}</div>
                    </td>
                    <td className="px-7 py-5 text-center">
                      <Badge variant={partner.packageStatus === 'active' ? 'success' : 'default'}>{partner.activity}</Badge>
                      {partner.createdAt && <div className="mt-2 text-xs text-safi-muted">{partner.createdAt}</div>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="flex flex-col gap-4 border-t border-safi-border bg-white px-5 py-4 md:px-7">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="text-sm font-bold text-safi-muted">
              {ui('Найдено')}: <span className="text-safi-green">{partnersMeta.total.toLocaleString('ru-RU')}</span>
              {partnersMeta.total > 0 && (
                <span className="ml-2">
                  {partnersMeta.from}–{partnersMeta.to}
                </span>
              )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <label className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                {ui('Показывать по')}
                <select
                  value={perPage}
                  onChange={(event) => {
                    setPerPage(Number(event.target.value));
                    setPage(1);
                  }}
                  disabled={isPartnersLoading}
                  className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60"
                  aria-label={ui('Показывать по')}
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
                  {ui('Назад')}
                </button>
                <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                  {ui('Страница')} {partnersMeta.current_page} {ui('из')} {partnersMeta.last_page}
                </div>
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page + 1)}
                  disabled={!canGoNext}
                  className="rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {ui('Вперёд')}
                </button>
              </div>

              <div className="hidden flex-wrap items-center gap-1 lg:flex">
                <button
                  type="button"
                  onClick={() => changePage(partnersMeta.current_page - 1)}
                  disabled={!canGoPrev}
                  className="flex h-9 min-w-9 items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-sm font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label={ui('Назад')}
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
                  aria-label={ui('Вперёд')}
                >
                  ›
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="min-w-0 rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">{ui('Бинарное дерево')}</h2>
          <button
            type="button"
            onClick={() => setIsTreeVisible((visible) => !visible)}
            className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-5 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-white transition-colors hover:bg-white hover:text-safi-green"
            aria-expanded={isTreeVisible}
          >
            <Network className="h-4 w-4" />
            {ui(isTreeVisible ? 'Скрыть дерево' : 'Показать дерево')}
          </button>
        </div>
        {isTreeVisible && <DashboardStructureTree root={treeRoot} totalPartners={structure.totalPartners} />}
      </section>
        </>
      )}
    </div>
  );
}

function DashboardStructureTree({ root, totalPartners }: { root: StructureTreeNode | null; totalPartners: number }) {
  return (
    <div className="mt-6 space-y-4">
      <StructureTreeCanvas
        rootNode={root}
        framed={false}
        storageKey="safi_dashboard_structure_view_settings"
        emptyMessage={ui('В вашей структуре пока нет нижестоящих партнёров.')}
      />
      <div className="text-xs font-bold text-safi-muted">
        {ui('В дереве показан текущий пользователь и нижестоящие партнёры')}: {totalPartners.toLocaleString('ru-RU')}.
      </div>
    </div>
  );
}

function normalizeTreeNode(value: unknown): StructureTreeNode | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null;
  }

  const record = value as Record<string, unknown>;
  const packageRecord = record.package && typeof record.package === 'object' && !Array.isArray(record.package)
    ? record.package as Record<string, unknown>
    : {};
  const childrenRecord = record.children && typeof record.children === 'object' && !Array.isArray(record.children)
    ? record.children as Record<string, unknown>
    : {};
  const rawPackageCode = getString(record, ['package_code', 'packageCode'])
    || (typeof record.package === 'string' ? record.package : '')
    || getString(packageRecord, ['code', 'slug', 'id'])
    || '';
  const packageStatus = getPartnerPackageStatus(record, rawPackageCode);
  const leftPV = getNumber(record, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
  const rightPV = getNumber(record, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;

  return {
    id: getString(record, ['id', 'user_id', 'userId']) || '-',
    userId: getString(record, ['user_id', 'userId', 'id']) || '-',
    name: getString(record, ['name']) || 'Партнёр',
    login: getString(record, ['login']) || '',
    line: getNumber(record, ['line', 'level', 'depth']) ?? 0,
    branch: getString(record, ['branch', 'position']) || null,
    packageCode: rawPackageCode,
    packageName: packageStatus === 'active'
      ? packageLabel(rawPackageCode, getString(record, ['package_label', 'packageLabel', 'package_name', 'packageName']) || getString(packageRecord, ['name', 'label']) || '-')
      : '-',
    packageStatus,
    packageStatusLabel: getString(record, ['package_status_label', 'packageStatusLabel']) || partnerPackageStatusLabel(packageStatus),
    status: packageStatus === 'active'
      ? mlmStatusLabel(getString(record, ['status']), getString(record, ['status_label', 'statusLabel']) || '-')
      : 'Неактивен',
    personalPV: getNumber(record, ['personal_pv', 'personalPV', 'package_pv', 'packagePv', 'package_activity_pv', 'packageActivityPv']) ?? 0,
    leftPV,
    rightPV,
    leftBranchPV: leftPV,
    rightBranchPV: rightPV,
    teamPV: getNumber(record, ['team_pv', 'teamPV']) ?? leftPV + rightPV,
    children: {
      left: normalizeTreeNode(childrenRecord.left),
      right: normalizeTreeNode(childrenRecord.right),
    },
  };
}

function currentUserTreeRoot(
  currentUser: DashboardCurrentUser,
  structure: { leftPV: number; rightPV: number },
): StructureTreeNode {
  return {
    id: String(currentUser.id || currentUser.partnerId || '-'),
    userId: String(currentUser.id || currentUser.partnerId || '-'),
    name: currentUser.name,
    login: currentUser.login || currentUser.partnerId,
    line: 0,
    branch: null,
    packageCode: currentUser.packageCode || '',
    packageName: currentUser.packageName,
    packageStatus: currentUser.packageStatus,
    packageStatusLabel: currentUser.packageStatusLabel,
    status: currentUser.status,
    personalPV: currentUser.personalPV,
    leftPV: structure.leftPV,
    rightPV: structure.rightPV,
    leftBranchPV: structure.leftPV,
    rightBranchPV: structure.rightPV,
    teamPV: structure.leftPV + structure.rightPV,
    children: {
      left: null,
      right: null,
    },
  };
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

function BranchCard({ title, partners, pv, branch, weak }: { title: string; partners: number; pv: number; branch: 'л' | 'п'; weak: boolean }) {
  const ui = useUiText();
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</div>
        {weak && <Badge variant="warning">{ui('Малая')}</Badge>}
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-green">{partners}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">{ui('Партнеров')}</div>
        </div>
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-gold">{formatBranchPv(branch, pv)}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">PV</div>
        </div>
      </div>
    </article>
  );
}

function formatBranchPv(branch: 'л' | 'п', value: number) {
  return `${branch}:${value.toLocaleString('ru-RU')}PV`;
}
