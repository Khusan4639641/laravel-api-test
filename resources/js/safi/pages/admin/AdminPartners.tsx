import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Copy, Eye, Filter, Network, Plus, Search, X } from 'lucide-react';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { useAdminContext } from '../../components/admin/AdminLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, createAdminPartner, getAdminUsers, getApiErrorState } from '../../lib/api';
import { formatPv } from '../../lib/format';
import { adminText } from '../../i18n/adminText';
import { features } from '../../config/features';

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
  package: string;
  status: string;
  personalPV: number;
  teamPV: number;
  totalIncome: number;
  availableBalance: number;
  registrationDate: string;
  accountStatus: string;
}

interface AdminPartnersSummary {
  total_partners: number;
  active_partners: number;
  vip_elite_partners: number;
  total_balance: number;
}

interface CreatedCredentials {
  login: string;
  email: string;
  password: string;
  login_url: string;
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
  role: 'user',
};

const modalInputClass = 'w-full rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-sm font-bold text-safi-green outline-none transition-colors focus:border-safi-green disabled:cursor-not-allowed disabled:opacity-60';

const emptySummary: AdminPartnersSummary = {
  total_partners: 0,
  active_partners: 0,
  vip_elite_partners: 0,
  total_balance: 0,
};

export default function AdminPartners() {
  const { currentUser } = useAdminContext();
  const [partners, setPartners] = useState<AdminPartnerRow[]>([]);
  const [summary, setSummary] = useState<AdminPartnersSummary>(() => ({ ...emptySummary }));
  const [query, setQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState(initialCreateForm);
  const [isCreating, setIsCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [createdCredentials, setCreatedCredentials] = useState<CreatedCredentials | null>(null);
  const [copyStatus, setCopyStatus] = useState('');

  const loadUsers = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminUsers();
      const body = unwrapAdminPartnersPayload(response);
      const bodyRecord = isRecord(body) ? body : {};
      const bodySummary = isRecord(bodyRecord.summary) ? bodyRecord.summary : {};
      const normalizedPartners = normalizePartners(body);
      const nextSummary: AdminPartnersSummary = {
        total_partners: Number(bodySummary.total_partners ?? 0),
        active_partners: Number(bodySummary.active_partners ?? 0),
        vip_elite_partners: Number(bodySummary.vip_elite_partners ?? 0),
        total_balance: Number(bodySummary.total_balance ?? 0),
      };

      setPartners(normalizedPartners);
      setSummary({ ...nextSummary });
    } catch (caughtError) {
      setPartners([]);
      setSummary({ ...emptySummary });
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_15'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadUsers();
  }, []);

  const openCreateModal = () => {
    setCreateForm(initialCreateForm);
    setCreateError(null);
    setFieldErrors({});
    setCreatedCredentials(null);
    setCopyStatus('');
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
      await loadUsers();
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

  const visiblePartners = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery) {
      return partners;
    }

    return partners.filter((partner) =>
      `${partner.id} ${partner.fullName} ${partner.phone} ${partner.email}`.toLowerCase().includes(normalizedQuery)
    );
  }, [partners, query]);
  const canCreatePartners = currentUser.role === 'super_admin';

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
        <SummaryCard label={adminText('a_0JLRgdC10LPQ')} value={summary.total_partners.toLocaleString('ru-RU')} />
        <SummaryCard label={adminText('a_0JDQutGC0LjQ_3')} value={summary.active_partners.toLocaleString('ru-RU')} />
        <SummaryCard label="VIP / ELITE" value={summary.vip_elite_partners.toLocaleString('ru-RU')} />
        <SummaryCard label={adminText('a_0JHQsNC70LDQ')} value={formatMoney(summary.total_balance)} />
      </section>

      <section className="rounded-[28px] border border-safi-border bg-white p-4 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="flex flex-col gap-4 md:flex-row">
          <label className="relative flex-1">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-muted" />
            <input
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={adminText('a_0J_QvtC40YHQ')}
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
      {!isLoading && error && <ErrorState description={error} onRetry={loadUsers} />}
      {!isLoading && !error && visiblePartners.length === 0 && (
        <EmptyState title={adminText('a_0J_QsNGA0YLQ_6')} description={query ? adminText('a_0J_QvtC_0YDQ') : adminText('a_0KHQv9C40YHQ')} />
      )}

      {!isLoading && !error && visiblePartners.length > 0 && (
        <AdminTable headers={[adminText('a_0J_QsNGA0YLQ_7'), adminText('a_0JrQvtC90YLQ'), adminText('a_0KHQv9C-0L3R_2'), adminText('a_0J_QsNC60LXR_5'), 'PV', adminText('a_0KTQuNC90LDQ'), adminText('a_0JDQutC60LDR'), adminText('a_0JTQtdC50YHR')]}>
          {visiblePartners.map((partner) => (
            <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
              <td className="px-6 py-4">
                <Link to={`/admin/partners/${partner.id}`} className="block cursor-pointer hover:opacity-80">
                  <div className="font-bold text-safi-green">{partner.fullName}</div>
                  <div className="mt-1 font-mono text-[10px] text-safi-muted">{partner.id}</div>
                  <div className="mt-1 text-[10px] text-safi-muted">{adminText('a_0KDQtdCzOg')}{partner.registrationDate}</div>
                </Link>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm text-safi-green">{partner.phone}</div>
                <div className="mt-1 text-xs text-safi-muted">{partner.email}</div>
                <div className="mt-1 text-[10px] text-safi-muted">{partner.city}</div>
              </td>
              <td className="px-6 py-4">
                <div className="inline-block rounded-full bg-safi-cream px-3 py-1 font-mono text-xs font-bold text-safi-green">{partner.sponsor}</div>
                <div className="mt-1 text-[10px] text-safi-muted">{adminText('a_0J_RgNC40LPQ')}{partner.invitedCount}</div>
              </td>
              <td className="px-6 py-4">
                <div className="mb-2"><AdminBadge variant="gold">{partner.package}</AdminBadge></div>
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
              <td className="px-6 py-4">
                <AdminBadge variant={partner.accountStatus === adminText('a_0JDQutGC0LjQ_2') ? 'success' : 'danger'}>{partner.accountStatus}</AdminBadge>
              </td>
              <td className="px-6 py-4 text-right">
                <div className="flex items-center justify-end gap-2">
                  <Link to={`/admin/partners/${partner.id}`} className="cursor-pointer rounded-xl p-2 text-safi-muted transition-colors hover:bg-safi-cream hover:text-safi-green" title={adminText('a_0J7RgtC60YDR_2')}>
                    <Eye className="h-4 w-4" />
                  </Link>
                  <Link to={`/admin/structure?user_id=${encodeURIComponent(partner.id)}`} className="cursor-pointer rounded-xl p-2 text-safi-muted transition-colors hover:bg-safi-cream hover:text-safi-green" title={adminText('a_0KHRgtGA0YPQ')}>
                    <Network className="h-4 w-4" />
                  </Link>
                </div>
              </td>
            </tr>
          ))}
        </AdminTable>
      )}

      {isCreateOpen && (
        <CreatePartnerModal
          form={createForm}
          partners={partners}
          fieldErrors={fieldErrors}
          error={createError}
          isCreating={isCreating}
          credentials={createdCredentials}
          copyStatus={copyStatus}
          onClose={closeCreateModal}
          onCopy={copyCredentials}
          onSubmit={submitCreatePartner}
          onChange={(field, value) => setCreateForm((current) => ({ ...current, [field]: value }))}
        />
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

function CreatePartnerModal({
  form,
  partners,
  fieldErrors,
  error,
  isCreating,
  credentials,
  copyStatus,
  onClose,
  onCopy,
  onSubmit,
  onChange,
}: {
  form: typeof initialCreateForm;
  partners: AdminPartnerRow[];
  fieldErrors: FieldErrors;
  error: string | null;
  isCreating: boolean;
  credentials: CreatedCredentials | null;
  copyStatus: string;
  onClose: () => void;
  onCopy: () => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onChange: (field: keyof typeof initialCreateForm, value: string) => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-4 py-6 backdrop-blur-sm">
      <div className="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-[28px] border border-safi-border bg-white shadow-[0_24px_70px_rgba(11,23,18,0.2)]">
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

        <div className="grid gap-6 p-6 lg:grid-cols-[1fr_320px]">
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
                  className={modalInputClass}
                  autoComplete="name"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0JvQvtCz0LjQ')} error={fieldErrors.login?.[0]}>
                <input
                  value={form.login}
                  onChange={(event) => onChange('login', event.target.value)}
                  className={modalInputClass}
                  autoComplete="username"
                  required
                />
              </ModalField>
              <ModalField label="Email" error={fieldErrors.email?.[0]}>
                <input
                  type="email"
                  value={form.email}
                  onChange={(event) => onChange('email', event.target.value)}
                  className={modalInputClass}
                  autoComplete="email"
                  required
                />
              </ModalField>
              <ModalField label={adminText('a_0KLQtdC70LXR')} error={fieldErrors.phone?.[0]}>
                <input
                  value={form.phone}
                  onChange={(event) => onChange('phone', event.target.value)}
                  className={modalInputClass}
                  autoComplete="tel"
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
                <select
                  value={form.sponsor_id}
                  onChange={(event) => onChange('sponsor_id', event.target.value)}
                  className={modalInputClass}
                >
                  <option value="">{adminText('a_0JHQtdC3INGB')}</option>
                  {partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.fullName} ({partner.login || partner.email})
                    </option>
                  ))}
                </select>
              </ModalField>
              <ModalField label={adminText('a_0JLQtdGC0LrQ')} error={fieldErrors.branch?.[0]}>
                <select
                  value={form.branch}
                  onChange={(event) => onChange('branch', event.target.value)}
                  className={modalInputClass}
                  disabled={!form.sponsor_id}
                >
                  <option value="">{adminText('a_0JDQstGC0L7Q')}</option>
                  <option value="left">left</option>
                  <option value="right">right</option>
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
            </div>

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
      <div className="mt-1 break-all font-mono text-sm font-bold text-safi-green">{value}</div>
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

function normalizePartners(response: unknown): AdminPartnerRow[] {
  return getArray(response).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const packageRecord = isRecord(record.current_package) ? record.current_package : isRecord(record.package) ? record.package : undefined;
    const sponsorRecord = isRecord(record.sponsor) ? record.sponsor : undefined;
    const profileRecord = isRecord(record.profile) ? record.profile : undefined;
    const wallets = Array.isArray(record.wallets) ? record.wallets.filter(isRecord) : [];
    const personalPV = getNumber(record, ['total_pv', 'totalPv', 'personal_pv', 'personalPV', 'pv']) ?? 0;
    const leftPV = getNumber(record, ['left_pv', 'leftPV']) ?? 0;
    const rightPV = getNumber(record, ['right_pv', 'rightPV']) ?? 0;
    const packageActivityPV = getNumber(record, ['package_activity_pv', 'packageActivityPv'])
      ?? getNumber(packageRecord, ['activity_pv', 'activityPv', 'pv'])
      ?? 0;
    const packageActivityAmount = getNumber(record, ['package_activity_amount', 'packageActivityAmount'])
      ?? packageActivityPV * 500;
    const walletBalance = getNumber(record, ['wallet_balance', 'walletBalance', 'main_balance', 'mainBalance'])
      ?? getWalletBalance(wallets, 'main');
    const bonusBalance = getNumber(record, ['bonus_balance', 'bonusBalance'])
      ?? getWalletBalance(wallets, 'bonus');
    const depositBalance = getNumber(record, ['deposit_balance', 'depositBalance'])
      ?? getWalletBalance(wallets, 'deposit');
    const totalWalletBalance = getNumber(record, ['total_wallet_balance', 'totalWalletBalance', 'wallet_total_balance', 'walletTotalBalance'])
      ?? walletBalance + bonusBalance + depositBalance;
    const computedBalance = walletBalance + packageActivityAmount;
    const computedTotalBalance = totalWalletBalance + packageActivityAmount;
    const apiBalance = getNumber(record, ['available_balance', 'availableBalance', 'balance']);
    const apiTotalBalance = getNumber(record, ['total_balance', 'totalBalance', 'total_earned', 'totalEarned']);
    const displayBalance = Math.max(apiBalance ?? computedBalance, computedBalance);
    const displayTotalBalance = Math.max(apiTotalBalance ?? computedTotalBalance, computedTotalBalance);

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
      package: getString(packageRecord, ['name', 'title', 'code']) || getString(record, ['package_name', 'packageName', 'package']) || '-',
      status: getString(record, ['status_name', 'statusName', 'status']) || adminText('a_0KPRh9Cw0YHR'),
      personalPV,
      teamPV: leftPV + rightPV,
      totalIncome: displayTotalBalance,
      availableBalance: displayBalance,
      registrationDate: getString(record, ['registration_date', 'registrationDate', 'created_at', 'createdAt']) || '-',
      accountStatus: getAccountStatusLabel(getString(record, ['account_status', 'accountStatus', 'state'])),
    };
  });
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

function getAccountStatusLabel(status?: string) {
  return status === 'blocked' ? adminText('a_0JfQsNCx0LvQ_2') : adminText('a_0JDQutGC0LjQ_2');
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
