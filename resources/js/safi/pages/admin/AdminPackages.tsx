import { FormEvent, useEffect, useState } from 'react';
import { AdminBadge } from '../../components/admin/ui';
import { Package, Plus, X } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { createAdminPackage, getAdminPackages, getApiErrorState, Package as ApiPackage, updateAdminPackage } from '../../lib/api';

interface PackageFormState {
  id?: string;
  code: string;
  slug: string;
  name: string;
  price: string;
  pv: string;
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
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить пакеты.');
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
      code: '',
      slug: '',
      name: pkg.name,
      price: String(pkg.price || ''),
      pv: '',
      referralPercent: String(pkg.referralBonus || 0),
      binaryPercent: String(pkg.binaryBonus || 0),
      sortOrder: String(pkg.sortOrder || 0),
      status: 'active',
      isActive: true,
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
      setActionError(getApiErrorState(caughtError).error || 'Не удалось сохранить пакет.');
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Пакеты</h1>
          <p className="text-sm text-safi-text/70">Управление вступительными пакетами</p>
        </div>
        <button
          type="button"
          onClick={showForm ? closeForm : openCreateForm}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />}
          {showForm ? 'Отмена' : 'Добавить пакет'}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm max-w-5xl">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{form.id ? 'Редактирование пакета' : 'Новый пакет'}</h3>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label="Код" value={form.code} onChange={(value) => setForm({ ...form, code: value })} required={!form.id} />
            <Field label="Slug" value={form.slug} onChange={(value) => setForm({ ...form, slug: value })} required={!form.id} />
            <Field label="Название" value={form.name} onChange={(value) => setForm({ ...form, name: value })} required />
            <Field label="Цена" type="number" value={form.price} onChange={(value) => setForm({ ...form, price: value })} required />
            <Field label="PV" type="number" value={form.pv} onChange={(value) => setForm({ ...form, pv: value })} />
            <Field label="Реферальный %" type="number" value={form.referralPercent} onChange={(value) => setForm({ ...form, referralPercent: value })} />
            <Field label="Бинарный %" type="number" value={form.binaryPercent} onChange={(value) => setForm({ ...form, binaryPercent: value })} />
            <Field label="Сортировка" type="number" value={form.sortOrder} onChange={(value) => setForm({ ...form, sortOrder: value })} />
            <div>
              <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Статус</label>
              <select
                value={form.status}
                onChange={(event) => setForm({ ...form, status: event.target.value })}
                className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
              >
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
          </div>
          <label className="mt-4 flex items-center gap-2 text-sm font-bold text-safi-green">
            <input
              type="checkbox"
              checked={form.isActive}
              onChange={(event) => setForm({ ...form, isActive: event.target.checked })}
              className="rounded text-safi-green focus:ring-safi-green"
            />
            Активен
          </label>
          <button type="submit" className="mt-6 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
            {form.id ? 'Сохранить' : 'Создать'}
          </button>
        </form>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadPackages} />}
      {!isLoading && !error && packages.length === 0 && <EmptyState title="Пакеты не добавлены" description="Создайте первый пакет через форму выше." />}

      {!isLoading && !error && packages.length > 0 && (
        <div className="grid md:grid-cols-3 gap-6 max-w-5xl">
          {packages.map((pkg) => (
            <div key={pkg.id} className="bg-white rounded-[32px] border border-safi-green/5 shadow-sm p-6 relative overflow-hidden group">
              <div className="mb-4">
                <AdminBadge variant="gold">Активен</AdminBadge>
              </div>
              <Package className="w-10 h-10 text-safi-green/20 absolute top-6 right-6" />
              <h3 className="text-2xl font-serif font-bold text-safi-green mb-1">{pkg.name}</h3>
              <div className="text-sm text-safi-text/60 mb-6">{pkg.price.toLocaleString('ru-RU')} ₸</div>

              <div className="space-y-3 mb-6 flex-1">
                <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                  <span className="text-safi-text/60">Реферальный</span>
                  <span className="font-bold text-safi-green">{pkg.referralBonus}%</span>
                </div>
                <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                  <span className="text-safi-text/60">Бинарный</span>
                  <span className="font-bold text-safi-green">{pkg.binaryBonus || 0}%</span>
                </div>
                <div className="flex justify-between items-center text-sm pb-2">
                  <span className="text-safi-text/60">Пользователей</span>
                  <span className="font-bold">-</span>
                </div>
              </div>

              <button
                type="button"
                onClick={() => openEditForm(pkg)}
                className="w-full py-3 bg-[#F5F5F0] group-hover:bg-safi-green group-hover:text-white rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green transition-colors"
              >
                Редактировать
              </button>
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
