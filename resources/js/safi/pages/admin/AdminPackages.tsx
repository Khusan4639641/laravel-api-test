import { FormEvent, useEffect, useState } from 'react';
import { AdminBadge } from '../../components/admin/ui';
import { Package, Plus, X } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { createAdminPackage, getAdminPackages, getApiErrorState, Package as ApiPackage, updateAdminPackage } from '../../lib/api';
import { adminText } from '../../i18n/adminText';
import { productStatusLabel } from '../../lib/systemLabels';

interface PackageFormState {
  id?: string;
  code: string;
  slug: string;
  name: string;
  price: string;
  pv: string;
  activityPv: string;
  turnoverPv: string;
  referralPercent: string;
  binaryPercent: string;
  sortOrder: string;
  status: string;
  isActive: boolean;
}

const emptyForm: PackageFormState = {
  code: '',
  slug: '',
  name: '',
  price: '',
  pv: '',
  activityPv: '',
  turnoverPv: '',
  referralPercent: '0',
  binaryPercent: '0',
  sortOrder: '0',
  status: 'active',
  isActive: true,
};

export default function AdminPackages() {
  const [packages, setPackages] = useState<ApiPackage[]>([]);
  const [form, setForm] = useState<PackageFormState>(emptyForm);
  const [showForm, setShowForm] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const loadPackages = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setPackages(await getAdminPackages());
    } catch (caughtError) {
      setPackages([]);
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_6'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadPackages();
  }, []);

  const openCreateForm = () => {
    setForm(emptyForm);
    setActionError(null);
    setShowForm(true);
  };

  const openEditForm = (pkg: ApiPackage) => {
    setForm({
      id: pkg.id,
      code: pkg.code || '',
      slug: '',
      name: pkg.name,
      price: String(pkg.price || ''),
      pv: String(pkg.pv || ''),
      activityPv: String(pkg.activityPv || pkg.pv || ''),
      turnoverPv: String(pkg.turnoverPv || pkg.pv || ''),
      referralPercent: String(pkg.referralBonus || 0),
      binaryPercent: String(pkg.binaryBonus || 0),
      sortOrder: String(pkg.sortOrder || 0),
      status: pkg.status || 'active',
      isActive: pkg.isActive ?? true,
    });
    setActionError(null);
    setShowForm(true);
  };

  const closeForm = () => {
    setForm(emptyForm);
    setActionError(null);
    setShowForm(false);
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setActionError(null);

    const payload: Record<string, unknown> = {
      name: form.name,
      price: Number(form.price),
      pv: Number(form.pv || 0),
      activity_pv: Number(form.activityPv || form.pv || 0),
      turnover_pv: Number(form.turnoverPv || form.activityPv || form.pv || 0),
      referral_percent: Number(form.referralPercent || 0),
      binary_percent: Number(form.binaryPercent || 0),
      sort_order: Number(form.sortOrder || 0),
      status: form.status,
      is_active: form.isActive,
      is_upgradeable: true,
    };

    if (!form.id || form.code) {
      payload.code = form.code;
    }

    if (!form.id || form.slug) {
      payload.slug = form.slug;
    }

    try {
      if (form.id) {
        await updateAdminPackage(form.id, payload);
      } else {
        await createAdminPackage(payload);
      }

      closeForm();
      await loadPackages();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_7'));
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0J_QsNC60LXR')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0KPQv9GA0LDQ_2')}</p>
        </div>
        <button
          type="button"
          onClick={showForm ? closeForm : openCreateForm}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />}
          {showForm ? adminText('a_0J7RgtC80LXQ') : adminText('a_0JTQvtCx0LDQ_3')}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm max-w-5xl">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{form.id ? adminText('a_0KDQtdC00LDQ_2') : adminText('a_0J3QvtCy0YvQ')}</h3>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={adminText('a_0JrQvtC0')} value={form.code} onChange={(value) => setForm({ ...form, code: value })} required={!form.id} />
            <Field label="Slug" value={form.slug} onChange={(value) => setForm({ ...form, slug: value })} required={!form.id} />
            <Field label={adminText('a_0J3QsNC30LLQ')} value={form.name} onChange={(value) => setForm({ ...form, name: value })} required />
            <Field label={adminText('a_0KbQtdC90LA')} type="number" value={form.price} onChange={(value) => setForm({ ...form, price: value })} required />
            <Field label="PV" type="number" value={form.pv} onChange={(value) => setForm({ ...form, pv: value })} />
            <Field label="Activity PV" type="number" value={form.activityPv} onChange={(value) => setForm({ ...form, activityPv: value })} />
            <Field label="Turnover PV" type="number" value={form.turnoverPv} onChange={(value) => setForm({ ...form, turnoverPv: value })} />
            <Field label={adminText('a_0KDQtdGE0LXR')} type="number" value={form.referralPercent} onChange={(value) => setForm({ ...form, referralPercent: value })} />
            <Field label={adminText('a_0JHQuNC90LDR')} type="number" value={form.binaryPercent} onChange={(value) => setForm({ ...form, binaryPercent: value })} />
            <Field label={adminText('a_0KHQvtGA0YLQ')} type="number" value={form.sortOrder} onChange={(value) => setForm({ ...form, sortOrder: value })} />
            <div>
              <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0KHRgtCw0YLR')}</label>
              <select
                value={form.status}
                onChange={(event) => setForm({ ...form, status: event.target.value })}
                className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
              >
                <option value="active">{productStatusLabel('active')}</option>
                <option value="inactive">{productStatusLabel('inactive')}</option>
              </select>
            </div>
          </div>
          <label className="mt-4 flex items-center gap-2 text-sm font-bold text-safi-green">
            <input
              type="checkbox"
              checked={form.isActive}
              onChange={(event) => setForm({ ...form, isActive: event.target.checked })}
              className="rounded text-safi-green focus:ring-safi-green"
            />{adminText('a_0JDQutGC0LjQ_2')}</label>
          <button type="submit" className="mt-6 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
            {form.id ? adminText('a_0KHQvtGF0YDQ') : adminText('a_0KHQvtC30LTQ_3')}
          </button>
        </form>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadPackages} />}
      {!isLoading && !error && packages.length === 0 && <EmptyState title={adminText('a_0J_QsNC60LXR_2')} description={adminText('a_0KHQvtC30LTQ_4')} />}

      {!isLoading && !error && packages.length > 0 && (
        <div className="grid md:grid-cols-3 gap-6 max-w-5xl">
          {packages.map((pkg) => (
            <div key={pkg.id} className="bg-white rounded-[32px] border border-safi-green/5 shadow-sm p-6 relative overflow-hidden group">
              <div className="mb-4">
                <AdminBadge variant={pkg.isActive === false || pkg.status === 'inactive' ? 'danger' : 'gold'}>
                  {pkg.isActive === false || pkg.status === 'inactive' ? productStatusLabel('inactive') : productStatusLabel('active')}
                </AdminBadge>
              </div>
              <Package className="w-10 h-10 text-safi-green/20 absolute top-6 right-6" />
              <h3 className="text-2xl font-serif font-bold text-safi-green mb-1">{pkg.label || pkg.name}</h3>
              <div className="text-sm text-safi-text/60 mb-6">{pkg.price.toLocaleString('ru-RU')} ₸</div>

              <div className="space-y-3 mb-6 flex-1">
                <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                  <span className="text-safi-text/60">{adminText('a_0KDQtdGE0LXR_2')}</span>
                  <span className="font-bold text-safi-green">{pkg.referralBonus}%</span>
                </div>
                <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                  <span className="text-safi-text/60">{adminText('a_0JHQuNC90LDR_2')}</span>
                  <span className="font-bold text-safi-green">{pkg.binaryBonus || 0}%</span>
                </div>
                <div className="flex justify-between items-center text-sm pb-2">
                  <span className="text-safi-text/60">{adminText('a_0J_QvtC70YzQ')}</span>
                  <span className="font-bold">-</span>
                </div>
              </div>

              <button
                type="button"
                onClick={() => openEditForm(pkg)}
                className="w-full py-3 bg-[#F5F5F0] group-hover:bg-safi-green group-hover:text-white rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green transition-colors"
              >{adminText('a_0KDQtdC00LDQ_3')}</button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  type = 'text',
  required = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  required?: boolean;
}) {
  return (
    <div>
      <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{label}</label>
      <input
        type={type}
        value={value}
        required={required}
        onChange={(event) => onChange(event.target.value)}
        className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
      />
    </div>
  );
}
