import { FormEvent, useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { ImageIcon, Plus, Edit, Trash2, Upload, X } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { adminText } from '../../i18n/adminText';
import {
  createAdminProduct,
  deleteAdminProduct,
  getAdminProducts,
  getApiErrorState,
  normalizeProducts,
  Product,
  productImagePlaceholder,
  updateAdminProduct,
} from '../../lib/api';
import { productStatusLabel } from '../../lib/systemLabels';

interface ProductFormState {
  id?: string;
  name: string;
  category: string;
  description: string;
  price: string;
  stock: string;
  status: string;
  imagePreview: string;
  removeImage: boolean;
}

const emptyForm: ProductFormState = {
  name: '',
  category: 'Safi Life',
  description: '',
  price: '',
  stock: '0',
  status: 'active',
  imagePreview: productImagePlaceholder,
  removeImage: false,
};

export default function AdminProducts() {
  const [products, setProducts] = useState<Product[]>([]);
  const [form, setForm] = useState<ProductFormState>(emptyForm);
  const [showForm, setShowForm] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState('');
  const [imageFile, setImageFile] = useState<File | null>(null);

  const loadProducts = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminProducts();
      setProducts(normalizeProducts(response));
    } catch (caughtError) {
      setProducts([]);
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_19'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadProducts();
  }, []);

  const openCreateForm = () => {
    setForm(emptyForm);
    setImageFile(null);
    setActionError(null);
    setShowForm(true);
  };

  const openEditForm = (product: Product) => {
    setForm({
      id: product.id,
      name: product.name,
      category: product.category || 'Safi Life',
      description: product.description || product.shortDescription || '',
      price: String(product.price || ''),
      stock: String(product.stock ?? 0),
      status: product.status || 'active',
      imagePreview: product.image || productImagePlaceholder,
      removeImage: false,
    });
    setImageFile(null);
    setActionError(null);
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setForm(emptyForm);
    setImageFile(null);
    setActionError(null);
  };

  const handleImageChange = (file: File | null) => {
    setImageFile(file);

    if (!file) {
      return;
    }

    setForm((current) => ({
      ...current,
      imagePreview: URL.createObjectURL(file),
      removeImage: false,
    }));
  };

  const handleRemoveImage = () => {
    setImageFile(null);
    setForm((current) => ({
      ...current,
      imagePreview: productImagePlaceholder,
      removeImage: Boolean(current.id),
    }));
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setActionError(null);

    const payload = new FormData();
    payload.append('name', form.name);
    payload.append('description', form.description);
    payload.append('price', String(Number(form.price)));
    payload.append('stock_quantity', String(Number(form.stock || 0)));
    payload.append('status', form.status);
    payload.append('category', form.category);

    if (imageFile) {
      payload.append('image', imageFile);
    }

    if (form.removeImage) {
      payload.append('remove_image', '1');
    }

    try {
      if (form.id) {
        await updateAdminProduct(form.id, payload);
      } else {
        await createAdminProduct(payload);
      }

      closeForm();
      await loadProducts();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_20'));
    }
  };

  const handleDelete = async (productId: string) => {
    setPendingId(productId);
    setActionError(null);

    try {
      await deleteAdminProduct(productId);
      await loadProducts();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_21'));
    } finally {
      setPendingId('');
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0KLQvtCy0LDR')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0KPQv9GA0LDQ_3')}</p>
        </div>
        <button
          type="button"
          onClick={showForm ? closeForm : openCreateForm}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />}
          {showForm ? adminText('a_0J7RgtC80LXQ') : adminText('a_0JTQvtCx0LDQ_6')}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{form.id ? adminText('a_0KDQtdC00LDQ_4') : adminText('a_0J3QvtCy0YvQ_3')}</h3>
          <div className="grid gap-6 xl:grid-cols-[280px_1fr]">
            <div className="rounded-3xl border border-safi-border bg-safi-cream p-4">
              <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-white">
                <img src={form.imagePreview || productImagePlaceholder} alt={form.name || 'Product'} className="h-full w-full object-cover" />
                {!form.imagePreview && (
                  <div className="absolute inset-0 flex items-center justify-center text-safi-muted">
                    <ImageIcon className="h-8 w-8" />
                  </div>
                )}
              </div>
              <div className="mt-4 grid gap-3">
                <label className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green hover:text-white">
                  <Upload className="h-4 w-4" />
                  Загрузить фото
                  <input
                    key={`${form.id || 'new'}-${form.imagePreview}`}
                    type="file"
                    accept="image/*"
                    className="sr-only"
                    onChange={(event) => handleImageChange(event.target.files?.[0] ?? null)}
                  />
                </label>
                <button
                  type="button"
                  onClick={handleRemoveImage}
                  className="rounded-full border border-safi-border bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                >
                  Удалить фото
                </button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <Field label={adminText('a_0J3QsNC30LLQ')} value={form.name} onChange={(value) => setForm({ ...form, name: value })} required />
              <Field label={adminText('a_0JrQsNGC0LXQ')} value={form.category} onChange={(value) => setForm({ ...form, category: value })} />
              <Field label={adminText('a_0KbQtdC90LA')} type="number" value={form.price} onChange={(value) => setForm({ ...form, price: value })} required />
              <Field label={adminText('a_0J7RgdGC0LDR')} type="number" value={form.stock} onChange={(value) => setForm({ ...form, stock: value })} />
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
              <div className="md:col-span-2">
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0J7Qv9C40YHQ')}</label>
                <textarea
                  rows={4}
                  value={form.description}
                  onChange={(event) => setForm({ ...form, description: event.target.value })}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                />
              </div>
            </div>
          </div>
          <button type="submit" className="mt-6 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
            {form.id ? adminText('a_0KHQvtGF0YDQ') : adminText('a_0KHQvtC30LTQ_3')}
          </button>
        </form>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadProducts} />}
      {!isLoading && !error && products.length === 0 && <EmptyState title={adminText('a_0KLQvtCy0LDR_2')} description={adminText('a_0KHQvtC30LTQ_9')} />}

      {!isLoading && !error && products.length > 0 && (
        <AdminTable headers={[adminText('a_0KLQvtCy0LDR_3'), adminText('a_0JrQsNGC0LXQ'), adminText('a_0KbQtdC90LA'), adminText('a_0J7RgdGC0LDR'), adminText('a_0KHRgtCw0YLR'), adminText('a_0JTQtdC50YHR')]}>
          {products.map((product) => (
            <tr key={product.id} className="hover:bg-safi-green/5 transition-colors group">
              <td className="px-6 py-4">
                <div className="flex items-center gap-4">
                  <div className="h-14 w-14 overflow-hidden rounded-xl border border-safi-green/10 bg-[#F5F5F0] shrink-0">
                    <img src={product.image || productImagePlaceholder} alt={product.name} className="h-full w-full object-cover" />
                  </div>
                  <div>
                    <div className="font-bold text-safi-green">{product.name}</div>
                    <div className="text-[10px] text-safi-text/50 mt-1">{adminText('a_0JTQvtCx0LDQ_7')}{product.createdAt || '-'}</div>
                  </div>
                </div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm">{product.category}</div>
              </td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold">{product.stock || 0}{adminText('a_0YjRgg')}</div>
                {(product.stock || 0) <= 0 && <div className="mt-1 text-[10px] font-bold uppercase tracking-widest text-red-500">нет в наличии</div>}
              </td>
              <td className="px-6 py-4">
                <AdminBadge variant={product.status === 'active' ? 'success' : 'danger'}>{product.statusLabel || productStatusLabel(product.status)}</AdminBadge>
              </td>
              <td className="px-6 py-4 text-right">
                <div className="flex justify-end gap-2">
                  <button type="button" onClick={() => openEditForm(product)} className="p-2 text-safi-text hover:text-safi-green hover:bg-[#F5F5F0] rounded-lg transition-colors">
                    <Edit className="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    disabled={pendingId === product.id}
                    onClick={() => handleDelete(product.id)}
                    className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                  >
                    <Trash2 className="w-4 h-4" />
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
