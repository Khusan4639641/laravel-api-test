import { FormEvent, ReactNode, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Copy, Eye, Filter, Network, Plus, Search, X } from 'lucide-react';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { NoTranslate } from '../../components/ui/NoTranslate';
import { ApiError, createAdminPartner, getAdminUsers, getApiErrorState, getRegistrationPackages, Package, searchAdminSponsors } from '../../lib/api';
import { formatPv } from '../../lib/format';
import { adminText } from '../../i18n/adminText';
import { features } from '../../config/features';
import { mlmStatusLabel, packageLabel } from '../../lib/systemLabels';
import { getPartnerPackageStatus, partnerPackageStatusLabel, type PartnerPackageStatus } from '../../lib/partnerStatus';

interface AdminPartnerRow {
  id: string;
  role: string;
  login: string;
  fullName: string;
  phone: string;
  email: string;
  city: string;
  sponsor: string;
  invitedCount: number;
  packageCode: string;
  package: string;
  packageStatus: PartnerPackageStatus;
  packageStatusLabel: string;
  statusCode: string;
  status: string;
  personalPV: number;
  teamPV: number;
  totalIncome: number;
  availableBalance: number;
  registrationDate: string;
}

interface SponsorOption {
  id: string;
  label: string;
  login: string;
  email: string;
  phone: string;
  fullName: string;
}

interface CreatedCredentials {
  login: string;
  email: string;
  password: string;
  login_url: string;
}

interface AdminPartnersSummary {
  totalPartners: number;
  activePartners: number;
  vipElitePartners: number;
  totalBalance: number;
}

interface AdminPartnersPagination {
  total: number;
  filteredTotal: number;
  limit: number;
  offset: number;
  hasNext: boolean;
  hasPrev: boolean;
}

type FieldErrors = Record<string, string[]>;

const initialCreateForm = {
  name: '',
  login: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  sponsor_id: '',
  branch: '',
  package_id: '',
  pay_referral_bonus: false,
  role: 'user',
};

const modalInputClass = 'w-full rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-sm font-bold text-safi-green outline-none transition-colors focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60';
const pageSizeOptions = [10, 20, 50, 100];
const defaultSummary: AdminPartnersSummary = {
  totalPartners: 0,
  activePartners: 0,
  vipElitePartners: 0,
  totalBalance: 0,
};
const defaultPagination: AdminPartnersPagination = {
  total: 0,
  filteredTotal: 0,
  limit: 20,
  offset: 0,
  hasNext: false,
  hasPrev: false,
};

export default function AdminPartners() {
  const { currentUser } = useAdminContext();
  const [partners, setPartners] = useState<AdminPartnerRow[]>([]);
  const [query, setQuery] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [limit, setLimit] = useState(defaultPagination.limit);
  const [offset, setOffset] = useState(defaultPagination.offset);
  const [pagination, setPagination] = useState<AdminPartnersPagination>(defaultPagination);
  const [summary, setSummary] = useState<AdminPartnersSummary>(defaultSummary);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState(initialCreateForm);
  const [isCreating, setIsCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [createdCredentials, setCreatedCredentials] = useState<CreatedCredentials | null>(null);
  const [copyStatus, setCopyStatus] = useState('');
  const [registrationPackages, setRegistrationPackages] = useState<Package[]>([]);
  const [sponsorOptions, setSponsorOptions] = useState<SponsorOption[]>([]);
  const [sponsorQuery, setSponsorQuery] = useState('');
  const [isSponsorsLoading, setIsSponsorsLoading] = useState(false);
  const [sponsorSearchError, setSponsorSearchError] = useState<string | null>(null);

  const loadUsers = async (searchQuery = searchTerm, pageLimit = limit, pageOffset = offset) => {
    setIsLoading(true);
    setError(null);

    try {
      const normalizedQuery = searchQuery.trim();
      const response = await getAdminUsers({
        ...(normalizedQuery ? { search: normalizedQuery } : {}),
        limit: pageLimit,
        offset: pageOffset,
        sort_by: 'created_at',
        sort_dir: 'desc',
      });
      const body = unwrapAdminPartnersPayload(response);
      const normalizedPartners = normalizePartners(body);

      setPartners(normalizedPartners);
      setSummary(normalizeSummary(body));
      setPagination(normalizePagination(body, pageLimit, pageOffset, normalizedPartners.length));
    } catch (caughtError) {
      setPartners([]);
      setSummary(defaultSummary);
      setPagination({ ...defaultPagination, limit: pageLimit, offset: pageOffset });
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_15'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setOffset(0);
      setSearchTerm(query.trim());
    }, query.trim() ? 400 : 0);

    return () => window.clearTimeout(timeout);
  }, [query]);

  useEffect(() => {
    void loadUsers(searchTerm, limit, offset);
  }, [searchTerm, limit, offset]);

  useEffect(() => {
    void getRegistrationPackages()
      .then((packages) => setRegistrationPackages(packages.filter((pkg) => isStarterPackage(pkg))))
      .catch(() => setRegistrationPackages([]));
  }, []);

  useEffect(() => {
    if (!isCreateOpen) {
      return undefined;
    }

    let isCurrent = true;
    const timeout = window.setTimeout(() => {
      setIsSponsorsLoading(true);
      setSponsorSearchError(null);

      void searchAdminSponsors(sponsorQuery.trim(), 30, createForm.sponsor_id || undefined)
        .then((response) => {
          if (!isCurrent) {
            return;
          }

          const normalizedSponsors = normalizeSponsorOptions(response);
          setSponsorOptions((current) => mergeSponsorOptions(normalizedSponsors, current, createForm.sponsor_id));
        })
        .catch(() => {
          if (!isCurrent) {
            return;
          }

          setSponsorSearchError('Не удалось загрузить список спонсоров');
        })
        .finally(() => {
          if (isCurrent) {
            setIsSponsorsLoading(false);
          }
        });
    }, sponsorQuery.trim() ? 300 : 0);

    return () => {
      isCurrent = false;
      window.clearTimeout(timeout);
    };
  }, [isCreateOpen, sponsorQuery, createForm.sponsor_id]);

  const openCreateModal = () => {
    setCreateForm(initialCreateForm);
    setCreateError(null);
    setFieldErrors({});
    setCreatedCredentials(null);
    setCopyStatus('');
    setSponsorOptions([]);
    setSponsorQuery('');
    setSponsorSearchError(null);
    setIsCreateOpen(true);
  };

  const closeCreateModal = () => {
    setIsCreateOpen(false);
    setCreatedCredentials(null);
    setCopyStatus('');
  };

  const submitCreatePartner = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsCreating(true);
    setCreateError(null);
    setFieldErrors({});
    setCreatedCredentials(null);
    setCopyStatus('');

    try {
      const response = await createAdminPartner(createForm);
      const credentials = normalizeCredentials(response);

      setCreatedCredentials(credentials);
      setCreateForm((current) => ({
        ...initialCreateForm,
        sponsor_id: current.sponsor_id,
        branch: current.branch,
      }));
      await loadUsers(searchTerm, limit, offset);
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setCreateError(caughtError.message);
        setFieldErrors(caughtError.errors || {});
      } else {
        setCreateError(adminText('a_0J3QtSDRg9C0_16'));
      }
    } finally {
      setIsCreating(false);
    }
  };

  const copyCredentials = async () => {
    if (!createdCredentials) {
      return;
    }

    const text = [
      `${adminText('a_0JvQvtCz0LjQ')}: ${createdCredentials.login}`,
      `Email: ${createdCredentials.email}`,
      `${adminText('a_0J_QsNGA0L7Q_2')}: ${createdCredentials.password}`,
      `${adminText('login_link_label')}: ${createdCredentials.login_url}`,
    ].join('\n');

    try {
      await navigator.clipboard.writeText(text);
      setCopyStatus(adminText('a_0JTQvtGB0YLR'));
    } catch {
      setCopyStatus(adminText('a_0J3QtSDRg9C0_17'));
    }
  };

  const visiblePartners = partners;
  const effectiveLimit = Math.max(pagination.limit || limit, 1);
  const effectiveOffset = Math.max(pagination.offset || offset, 0);
  const totalItems = pagination.filteredTotal ?? pagination.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / effectiveLimit));
  const currentPage = Math.min(Math.floor(effectiveOffset / effectiveLimit) + 1, totalPages);
  const paginationStart = totalItems === 0 ? 0 : effectiveOffset + 1;
  const paginationEnd = totalItems === 0
    ? 0
    : Math.min(effectiveOffset + effectiveLimit, totalItems);
  const paginationMeta = {
    current_page: currentPage,
    last_page: totalPages,
    per_page: effectiveLimit,
    total: totalItems,
    from: paginationStart,
    to: paginationEnd,
  };
  const canCreatePartners = currentUser.role === 'super_admin';
  const changePage = (page: number) => {
    if (page < 1 || page > totalPages || page === currentPage || isLoading) {
      return;
    }

    setOffset((page - 1) * limit);
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Admin users</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">{adminText('a_0J_QsNGA0YLQ_5')}</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">{adminText('a_0J_QvtC70YzQ_2')}</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={openCreateModal}
              disabled={!canCreatePartners}
              title={canCreatePartners ? adminText('a_0KHQvtC30LTQ_5') : adminText('a_0KHQvtC30LTQ_6')}
              className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Plus className="h-4 w-4 text-safi-gold" />{adminText('a_0JTQvtCx0LDQ_4')}</button>
            {canCreatePartners ? (
              <Link
                to="/admin/partners/bulk-create"
                className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green/10"
              >
                <Plus className="h-4 w-4" />{adminText('a_0JzQsNGB0YHQ')}</Link>
            ) : (
              <button
                type="button"
                disabled
                title={adminText('a_0JzQsNGB0YHQ_2')}
                className="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green opacity-60"
              >
                <Plus className="h-4 w-4" />{adminText('a_0JzQsNGB0YHQ')}</button>
            )}
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
            {adminText('a_0JLRgdC10LPQ')}
          </div>
          <div className="mt-3 font-serif text-3xl font-semibold text-safi-green">
            {summary.totalPartners.toLocaleString('ru-RU')}
          </div>
        </article>

        <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
            {adminText('a_0JDQutGC0LjQ_3')}
          </div>
          <div className="mt-3 font-serif text-3xl font-semibold text-safi-green">
            {summary.activePartners.toLocaleString('ru-RU')}
          </div>
        </article>

        <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">VIP / ELITE</div>
          <div className="mt-3 font-serif text-3xl font-semibold text-safi-green">
            {summary.vipElitePartners.toLocaleString('ru-RU')}
          </div>
        </article>

        <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
            {adminText('a_0JHQsNC70LDQ')}
          </div>
          <div className="mt-3 font-serif text-3xl font-semibold text-safi-green">
            {formatMoney(summary.totalBalance)}
          </div>
        </article>
      </section>

      <section className="rounded-[28px] border border-safi-border bg-white p-4 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="flex flex-col gap-4 md:flex-row">
          <label className="relative flex-1">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-muted" />
            <input
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Поиск по ID, ФИО, login, email или телефону"
              className="w-full rounded-full border border-safi-border bg-safi-cream py-3 pl-12 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
            />
          </label>
          <button
            type="button"
            disabled
            title={adminText('a_0KTQuNC70YzR')}
            className="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green opacity-60"
          >
            <Filter className="h-4 w-4" />{adminText('a_0KTQuNC70YzR_2')}</button>
        </div>
      </section>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={() => void loadUsers(searchTerm, limit, offset)} />}
      {!isLoading && !error && visiblePartners.length === 0 && (
        <EmptyState title={query ? 'Партнёры не найдены' : adminText('a_0J_QsNGA0YLQ_6')} description={query ? 'Попробуйте другой ID, ФИО, login, email или телефон.' : adminText('a_0KHQv9C40YHQ')} />
      )}

      {!isLoading && !error && visiblePartners.length > 0 && (
        <section className="space-y-4">
          <AdminTable headers={[adminText('a_0J_QsNGA0YLQ_7'), adminText('a_0JrQvtC90YLQ'), adminText('a_0KHQv9C-0L3R_2'), adminText('a_0J_QsNC60LXR_5'), 'PV', adminText('a_0KTQuNC90LDQ'), adminText('a_0JTQtdC50YHR')]}>
            {visiblePartners.map((partner) => (
              <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
                <td className="px-6 py-4">
                  <Link to={`/admin/partners/${partner.id}`} className="block cursor-pointer hover:opacity-80">
                    <NoTranslate as="div" className="font-bold text-safi-green">{partner.fullName}</NoTranslate>
                    <NoTranslate as="div" className="mt-1 font-mono text-[10px] text-safi-muted">{partner.id}</NoTranslate>
                    <div className="mt-1 text-[10px] text-safi-muted">{adminText('a_0KDQtdCzOg')}{partner.registrationDate}</div>
                  </Link>
                </td>
                <td className="px-6 py-4">
                  <NoTranslate as="div" className="text-sm text-safi-green">{partner.phone}</NoTranslate>
                  <NoTranslate as="div" className="mt-1 text-xs text-safi-muted">{partner.email}</NoTranslate>
                  <NoTranslate as="div" className="mt-1 text-[10px] text-safi-muted">{partner.city}</NoTranslate>
                </td>
                <td className="px-6 py-4">
                  <NoTranslate as="div" className="inline-block rounded-full bg-safi-cream px-3 py-1 font-mono text-xs font-bold text-safi-green">{partner.sponsor}</NoTranslate>
                  <div className="mt-1 text-[10px] text-safi-muted">{adminText('a_0J_RgNC40LPQ')}{partner.invitedCount}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="mb-2"><AdminBadge variant="gold"><NoTranslate>{partner.package}</NoTranslate></AdminBadge></div>
                  <div className="mb-2">
                    <AdminBadge variant={partner.packageStatus === 'active' ? 'success' : 'warning'}>
                      Пакет: {partner.packageStatusLabel}
                    </AdminBadge>
                  </div>
                  <AdminBadge variant="default">{partner.status}</AdminBadge>
                </td>
                <td className="px-6 py-4">
                  <div className="text-sm">{adminText('a_0Js6')}<span className="font-bold text-safi-green">{formatPv(partner.personalPV)}</span></div>
                  <div className="mt-1 text-xs text-safi-muted">{adminText('a_0Jo6')}{formatPv(partner.teamPV)}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="text-sm font-bold text-safi-green">{adminText('a_0JHQsNC70LDQ_2')}{formatMoney(partner.availableBalance)}</div>
                  <div className="mt-1 text-[10px] text-safi-muted">{adminText('a_0JLRgdC10LPQ_3')}{formatMoney(partner.totalIncome)}</div>
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-2">
                    <Link to={`/admin/partners/${partner.id}`} className="cursor-pointer rounded-xl p-2 text-safi-muted transition-colors hover:bg-safi-cream hover:text-safi-green" title={adminText('a_0J7RgtC60YDR_2')}>
                      <Eye className="h-4 w-4" />
                    </Link>
                    <Link to={`/admin/structure?root_id=${encodeURIComponent(partner.id)}`} className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-safi-border bg-safi-cream px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white" title={adminText('a_0KHRgtGA0YPQ')}>
                      <Network className="h-4 w-4" />Показать дерево
                    </Link>
                  </div>
                </td>
              </tr>
            ))}
          </AdminTable>

          <AdminPagination
            meta={paginationMeta}
            perPageOptions={pageSizeOptions}
            onPageChange={changePage}
            onPerPageChange={(nextLimit) => {
              setLimit(nextLimit);
              setOffset(0);
            }}
            totalSuffix={pagination.total !== pagination.filteredTotal && (
              <span className="ml-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                всего {pagination.total.toLocaleString('ru-RU')}
              </span>
            )}
          />
        </section>
      )}

      {isCreateOpen && (
        <CreatePartnerModal
          form={createForm}
          sponsors={sponsorOptions}
          sponsorQuery={sponsorQuery}
          isSponsorsLoading={isSponsorsLoading}
          sponsorSearchError={sponsorSearchError}
          packages={registrationPackages}
          fieldErrors={fieldErrors}
          error={createError}
          isCreating={isCreating}
          credentials={createdCredentials}
          copyStatus={copyStatus}
          onClose={closeCreateModal}
          onCopy={copyCredentials}
          onSubmit={submitCreatePartner}
          onSponsorQueryChange={setSponsorQuery}
          onChange={(field, value) => setCreateForm((current) => ({
            ...current,
            [field]: value,
            ...(field === 'sponsor_id' && !value ? { branch: '' } : {}),
          }))}
        />
      )}
    </div>
  );
}

function CreatePartnerModal({
  form,
  sponsors,
  sponsorQuery,
  isSponsorsLoading,
  sponsorSearchError,
  packages,
  fieldErrors,
  error,
  isCreating,
  credentials,
  copyStatus,
  onClose,
  onCopy,
  onSubmit,
  onSponsorQueryChange,
  onChange,
}: {
  form: typeof initialCreateForm;
  sponsors: SponsorOption[];
  sponsorQuery: string;
  isSponsorsLoading: boolean;
  sponsorSearchError: string | null;
  packages: Package[];
  fieldErrors: FieldErrors;
  error: string | null;
  isCreating: boolean;
  credentials: CreatedCredentials | null;
  copyStatus: string;
  onClose: () => void;
  onCopy: () => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onSponsorQueryChange: (value: string) => void;
  onChange: (field: keyof typeof initialCreateForm, value: string | boolean) => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-3 py-3 backdrop-blur-sm sm:px-4 sm:py-6">
      <div className="safi-responsive-modal w-full max-w-4xl overflow-y-auto rounded-[28px] border border-safi-border bg-white shadow-[0_24px_70px_rgba(11,23,18,0.2)] [--safi-modal-width:56rem]">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-safi-border bg-white px-6 py-5">
          <div>
            <div className="safi-kicker">Super admin</div>
            <h2 className="mt-2 font-serif text-3xl font-semibold text-safi-green">{adminText('a_0JTQvtCx0LDQ_4')}</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-safi-cream text-safi-green transition-colors hover:bg-safi-green hover:text-white"
            aria-label={adminText('a_0JfQsNC60YDR')}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="grid gap-6 p-4 sm:p-6 lg:grid-cols-[1fr_320px]">
          <form className="space-y-5" onSubmit={onSubmit}>
            {error && (
              <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {error}
              </div>
            )}

            <div className="grid gap-4 md:grid-cols-2">
              <ModalField label={adminText('a_0JjQvNGP')} error={fieldErrors.name?.[0]}>
                <input
                  value={form.name}
                  onChange={(event) => onChange('name', event.target.value)}
                  className={`${modalInputClass} notranslate`}
                  translate="no"
                  autoComplete="name"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0JvQvtCz0LjQ')} error={fieldErrors.login?.[0]}>
                <input
                  value={form.login}
                  onChange={(event) => onChange('login', event.target.value)}
                  className={`${modalInputClass} notranslate`}
                  translate="no"
                  autoComplete="username"
                  required
                />
              </ModalField>
              <ModalField label="Email" error={fieldErrors.email?.[0]}>
                <input
                  type="email"
                  value={form.email}
                  onChange={(event) => onChange('email', event.target.value)}
                  className={`${modalInputClass} notranslate`}
                  translate="no"
                  autoComplete="email"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0KLQtdC70LXR')} error={fieldErrors.phone?.[0]}>
                <input
                  value={form.phone}
                  onChange={(event) => onChange('phone', event.target.value)}
                  className={`${modalInputClass} notranslate`}
                  translate="no"
                  autoComplete="tel"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0J_QsNGA0L7Q_2')} error={fieldErrors.password?.[0]}>
                <input
                  type="password"
                  value={form.password}
                  onChange={(event) => onChange('password', event.target.value)}
                  className={modalInputClass}
                  autoComplete="new-password"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0J_QvtCy0YLQ_2')} error={fieldErrors.password_confirmation?.[0]}>
                <input
                  type="password"
                  value={form.password_confirmation}
                  onChange={(event) => onChange('password_confirmation', event.target.value)}
                  className={modalInputClass}
                  autoComplete="new-password"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0KHQv9C-0L3R_3')} error={fieldErrors.sponsor_id?.[0]}>
                <input
                  type="search"
                  value={sponsorQuery}
                  onChange={(event) => onSponsorQueryChange(event.target.value)}
                  className={`${modalInputClass} mb-2`}
                  placeholder="Поиск по ID, имени, login, email, телефону"
                  autoComplete="off"
                />
                <select
                  value={form.sponsor_id}
                  onChange={(event) => onChange('sponsor_id', event.target.value)}
                  className={modalInputClass}
                >
                  <option value="">{adminText('a_0JHQtdC3INGB')}</option>
                  {sponsors.map((sponsor) => (
                    <option key={sponsor.id} value={sponsor.id}>
                      {sponsor.label}
                    </option>
                  ))}
                </select>
                {isSponsorsLoading && <span className="mt-2 block text-xs font-bold text-safi-muted">Загрузка спонсоров...</span>}
                {sponsorSearchError && <span className="mt-2 block text-xs font-bold text-red-600">{sponsorSearchError}</span>}
              </ModalField>
              <ModalField label={adminText('a_0JLQtdGC0LrQ')} error={fieldErrors.branch?.[0]}>
                <select
                  value={form.branch}
                  onChange={(event) => onChange('branch', event.target.value)}
                  className={modalInputClass}
                  disabled={!form.sponsor_id}
                  required={Boolean(form.sponsor_id)}
                >
                  <option value="">{form.sponsor_id ? 'Выберите ветку' : 'Сначала выберите спонсора'}</option>
                  <option value="left">Левая ветка</option>
                  <option value="right">Правая ветка</option>
                </select>
              </ModalField>
              <ModalField label={adminText('a_0KDQvtC70Yw')} error={fieldErrors.role?.[0]}>
                <select
                  value={form.role}
                  onChange={(event) => onChange('role', event.target.value)}
                  className={modalInputClass}
                >
                  <option value="user">user</option>
                  {features.support && <option value="support">support</option>}
                  <option value="accountant">accountant</option>
                  <option value="admin">admin</option>
                </select>
              </ModalField>
              <ModalField label="Стартовый пакет" error={fieldErrors.package_id?.[0]}>
                <select
                  value={form.package_id}
                  onChange={(event) => onChange('package_id', event.target.value)}
                  className={modalInputClass}
                >
                  <option value="">Без пакета</option>
                  {packages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {String(pkg.code || pkg.name).toUpperCase()} — {pkg.price.toLocaleString('ru-RU')} ₸
                    </option>
                  ))}
                </select>
              </ModalField>
            </div>

            <label className="flex items-start gap-3 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3">
              <input
                type="checkbox"
                checked={form.pay_referral_bonus}
                onChange={(event) => onChange('pay_referral_bonus', event.target.checked)}
                disabled={!form.sponsor_id || !form.package_id}
                className="mt-1 h-4 w-4 cursor-pointer rounded border-safi-border text-safi-green focus:ring-safi-green disabled:cursor-not-allowed disabled:opacity-60"
              />
              <span className="text-sm font-bold leading-6 text-safi-green">
                Начислить реферальный бонус спонсору
                <span className="mt-1 block text-xs font-medium text-safi-muted">
                  По умолчанию ручное создание не начисляет бонус. При включении START даст 5 000 ₸, VIP даст 15 000 ₸.
                </span>
              </span>
            </label>

            <div className="flex flex-col gap-3 border-t border-safi-border pt-5 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={onClose}
                className="cursor-pointer rounded-full border border-safi-border bg-white px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-cream"
              >{adminText('a_0J7RgtC80LXQ')}</button>
              <button
                type="submit"
                disabled={isCreating}
                className="cursor-pointer rounded-full border border-safi-green bg-safi-green px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green/90 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isCreating ? adminText('a_0KHQvtC30LTQ_7') : adminText('a_0KHQvtC30LTQ_5')}
              </button>
            </div>
          </form>

          <aside className="rounded-[24px] border border-safi-border bg-safi-cream p-5">
            <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{adminText('a_0JTQvtGB0YLR_3')}</div>
            {credentials ? (
              <div className="mt-4 space-y-4">
                <CredentialRow label={adminText('a_0JvQvtCz0LjQ')} value={credentials.login} />
                <CredentialRow label="Email" value={credentials.email} />
                <CredentialRow label={adminText('a_0J_QsNGA0L7Q_2')} value={credentials.password} />
                <button
                  type="button"
                  onClick={onCopy}
                  className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                >
                  <Copy className="h-4 w-4" />{adminText('a_0KHQutC-0L_Q')}</button>
                {copyStatus && <div className="text-xs font-bold text-safi-muted">{copyStatus}</div>}
              </div>
            ) : (
              <p className="mt-4 text-sm leading-7 text-safi-muted">{adminText('a_0J_QvtGB0LvQ_2')}</p>
            )}
          </aside>
        </div>
      </div>
    </div>
  );
}

function isStarterPackage(pkg: Package) {
  const code = String(pkg.code || pkg.name || '').trim().toUpperCase();

  return code === 'START' || code === 'VIP';
}

function ModalField({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-bold text-red-600">{error}</span>}
    </label>
  );
}

function CredentialRow({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <NoTranslate as="div" className="mt-1 break-all font-mono text-sm font-bold text-safi-green">{value}</NoTranslate>
    </div>
  );
}

function normalizeCredentials(response: unknown): CreatedCredentials {
  const record = isRecord(response) ? response : {};
  const credentials = isRecord(record.credentials) ? record.credentials : {};

  return {
    login: getString(credentials, ['login']) || '-',
    email: getString(credentials, ['email']) || '-',
    password: getString(credentials, ['password']) || '-',
    login_url: getString(credentials, ['login_url', 'loginUrl']) || 'https://safilife.kz/login',
  };
}

function normalizeSponsorOptions(response: unknown): SponsorOption[] {
  return getArray(response)
    .map((item, index) => {
      const record = isRecord(item) ? item : {};
      const profileRecord = isRecord(record.profile) ? record.profile : undefined;
      const id = getString(record, ['id', 'partner_id', 'partnerId', 'code']) || '';
      const fullName = getString(record, ['full_name', 'fullName', 'name']) || `Sponsor ${index + 1}`;
      const login = getString(record, ['login', 'username']) || '';
      const email = getString(record, ['email']) || '';
      const phone = getString(record, ['phone', 'phone_number', 'phoneNumber']) || getString(profileRecord, ['phone']) || '';
      const details = [
        `ID ${id}`,
        login,
        email,
        phone,
      ].filter(Boolean);

      return {
        id,
        label: `${fullName}${details.length ? ` (${details.join(' · ')})` : ''}`,
        login,
        email,
        phone,
        fullName,
      };
    })
    .filter((sponsor) => sponsor.id !== '');
}

function mergeSponsorOptions(next: SponsorOption[], current: SponsorOption[], selectedId: string): SponsorOption[] {
  const selected = selectedId
    ? next.find((sponsor) => sponsor.id === selectedId) || current.find((sponsor) => sponsor.id === selectedId)
    : undefined;
  const merged = new Map<string, SponsorOption>();

  if (selected) {
    merged.set(selected.id, selected);
  }

  next.forEach((sponsor) => merged.set(sponsor.id, sponsor));

  return Array.from(merged.values());
}

function normalizePartners(response: unknown): AdminPartnerRow[] {
  return getArray(response).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const packageRecord = isRecord(record.current_package) ? record.current_package : isRecord(record.package) ? record.package : undefined;
    const sponsorRecord = isRecord(record.sponsor) ? record.sponsor : undefined;
    const profileRecord = isRecord(record.profile) ? record.profile : undefined;
    const wallets = Array.isArray(record.wallets) ? record.wallets.filter(isRecord) : [];
    const personalPV = getNumber(record, ['package_activity_pv', 'packageActivityPv', 'personal_pv', 'personalPV'])
      ?? getNumber(packageRecord, ['activity_pv', 'activityPv', 'pv'])
      ?? 0;
    const leftPV = getNumber(record, ['left_pv', 'leftPV']) ?? 0;
    const rightPV = getNumber(record, ['right_pv', 'rightPV']) ?? 0;
    const walletBalance = getNumber(record, ['wallet_balance', 'walletBalance', 'main_balance', 'mainBalance'])
      ?? getWalletBalance(wallets, 'main');
    const bonusBalance = getNumber(record, ['bonus_balance', 'bonusBalance'])
      ?? getWalletBalance(wallets, 'bonus');
    const depositBalance = getNumber(record, ['deposit_balance', 'depositBalance'])
      ?? getWalletBalance(wallets, 'deposit');
    const totalWalletBalance = getNumber(record, ['total_wallet_balance', 'totalWalletBalance', 'wallet_total_balance', 'walletTotalBalance'])
      ?? walletBalance + bonusBalance + depositBalance;
    const apiBalance = getNumber(record, ['available_balance', 'availableBalance', 'wallet_balance', 'walletBalance', 'balance']);
    const apiTotalBalance = getNumber(record, ['total_balance', 'totalBalance', 'total_earned', 'totalEarned', 'total_wallet_balance', 'totalWalletBalance']);
    const displayBalance = apiBalance ?? walletBalance;
    const displayTotalBalance = apiTotalBalance ?? totalWalletBalance;
    const rawPackageCode = getString(record, ['package_code', 'packageCode'])
      || getString(packageRecord, ['code', 'slug', 'id'])
      || getString(record, ['package'])
      || '';
    const packageStatus = getPartnerPackageStatus(record, rawPackageCode);
    const packageCode = packageStatus === 'active' ? rawPackageCode : '';
    const statusCode = getString(record, ['status']) || getString(record, ['status_code', 'statusCode']) || 'user';

    return {
      id: getString(record, ['partner_id', 'partnerId', 'code', 'id']) || `USER-${index + 1}`,
      role: getString(record, ['role']) || 'user',
      login: getString(record, ['login', 'username']) || '',
      fullName: getString(record, ['full_name', 'fullName', 'name']) || `Partner ${index + 1}`,
      phone: getString(record, ['phone', 'phone_number', 'phoneNumber']) || getString(profileRecord, ['phone']) || '-',
      email: getString(record, ['email']) || '-',
      city: getString(record, ['city']) || getString(profileRecord, ['city']) || '-',
      sponsor: getString(sponsorRecord, ['name', 'login', 'partner_id', 'id']) || getString(record, ['sponsor_id', 'sponsorId']) || '-',
      invitedCount: getNumber(record, ['invited_count', 'invitedCount', 'invited_users_count', 'referrals_count', 'children_count']) ?? 0,
      packageCode,
      package: packageStatus === 'active'
        ? packageLabel(packageCode, getString(record, ['package_name', 'packageName'])
          || getString(packageRecord, ['code_label', 'codeLabel', 'label', 'name', 'title'])
          || '-')
        : '-',
      packageStatus,
      packageStatusLabel: getString(record, ['package_status_label', 'packageStatusLabel']) || partnerPackageStatusLabel(packageStatus),
      statusCode,
      status: packageStatus === 'active'
        ? mlmStatusLabel(statusCode, getString(record, ['status_label', 'statusLabel', 'status_name', 'statusName']) || adminText('a_0KPRh9Cw0YHR'))
        : 'Неактивен',
      personalPV,
      teamPV: leftPV + rightPV,
      totalIncome: displayTotalBalance,
      availableBalance: displayBalance,
      registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || '-',
    };
  });
}

function normalizeSummary(response: unknown): AdminPartnersSummary {
  const record = isRecord(response) ? response : {};
  const summary = isRecord(record.summary) ? record.summary : {};

  return {
    totalPartners: getNumber(summary, ['total_partners', 'totalPartners']) ?? 0,
    activePartners: getNumber(summary, ['active_partners', 'activePartners']) ?? 0,
    vipElitePartners: getNumber(summary, ['vip_elite_partners', 'vipElitePartners']) ?? 0,
    totalBalance: getNumber(summary, ['total_balance', 'totalBalance']) ?? 0,
  };
}

function normalizePagination(response: unknown, fallbackLimit: number, fallbackOffset: number, rowCount: number): AdminPartnersPagination {
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

function unwrapAdminPartnersPayload(response: unknown) {
  const record = isRecord(response) ? response : {};

  if (isRecord(record.data) && isRecord(record.data.summary)) {
    return record.data;
  }

  return response;
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

    if (Array.isArray(response.sponsors)) {
      return response.sponsors;
    }

    if (Array.isArray(response.partners)) {
      return response.partners;
    }
  }

  return [];
}

function formatMoney(value: number) {
  return `${value.toLocaleString('ru-RU')} ₸`;
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

function getNumber(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const normalized = Number(value.replace(/[^\d.-]/g, ''));

      if (Number.isFinite(normalized)) {
        return normalized;
      }
    }
  }

  return undefined;
}

function getWalletBalance(wallets: Record<string, unknown>[], type: string) {
  return wallets
    .filter((wallet) => getString(wallet, ['type']) === type)
    .reduce((sum, wallet) => sum + (getNumber(wallet, ['balance']) ?? 0), 0);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
