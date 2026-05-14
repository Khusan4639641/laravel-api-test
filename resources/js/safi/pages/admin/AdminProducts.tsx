import { FormEvent, useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Plus, Edit, Trash2, X } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import {
  createAdminProduct,
  deleteAdminProduct,
  getAdminProducts,
  getApiErrorState,
  normalizeProducts,
  Product,
  updateAdminProduct,
} from '../../lib/api';

interface ProductFormState {
  id?: string;
  name: string;
  category: string;
  description: string;
  price: string;
  pv: string;
  stock: string;
  status: string;
}

const emptyForm: ProductFormState = {
  name: '',
  category: 'Safi Life',
  description: '',
  price: '',
  pv: '',
  stock: '0',
  status: 'active',
};

export default function AdminProducts() {
  const [products, setProducts] = useState<Product[]>([]);
  const [form, setForm] = useState<ProductFormState>(emptyForm);
  const [showForm, setShowForm] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState('');

  const loadProducts = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminProducts();
      setProducts(normalizeProducts(response));
    } catch (caughtError) {
      setProducts([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить товары.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadProducts();
  }, []);

  const openCreateForm = () => {
    setForm(emptyForm);
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
      pv: String(product.pv || ''),
      stock: String(product.stock ?? 0),
      status: product.status || 'active',
    });
    setActionError(null);
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setForm(emptyForm);
    setActionError(null);
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setActionError(null);

    const payload = {
      name: form.name,
      description: form.description,
      price: Number(form.price),
      pv: Number(form.pv),
      stock_quantity: Number(form.stock || 0),
      status: form.status,
      metadata: {
        category: form.category,
      },
    };

    try {
      if (form.id) {
        await updateAdminProduct(form.id, payload);
      } else {
        await createAdminProduct(payload);
      }

      closeForm();
      await loadProducts();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось сохранить товар.');
    }
  };

  const handleDelete = async (productId: string) => {
    setPendingId(productId);
    setActionError(null);

    try {
      await deleteAdminProduct(productId);
      await loadProducts();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось удалить товар.');
    } finally {
      setPendingId('');
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Товары</h1>
          <p className="text-sm text-safi-text/70">Управление каталогом продукции</p>
        </div>
        <button
          type="button"
          onClick={showForm ? closeForm : openCreateForm}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />}
          {showForm ? 'Отмена' : 'Добавить товар'}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{form.id ? 'Редактирование товара' : 'Новый товар'}</h3>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Название" value={form.name} onChange={(value) => setForm({ ...form, name: value })} required />
            <Field label="Категория" value={form.category} onChange={(value) => setForm({ ...form, category: value })} />
            <Field label="Цена" type="number" value={form.price} onChange={(value) => setForm({ ...form, price: value })} required />
            <Field label="PV" type="number" value={form.pv} onChange={(value) => setForm({ ...form, pv: value })} required />
            <Field label="Остаток" type="number" value={form.stock} onChange={(value) => setForm({ ...form, stock: value })} />
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
            <div className="md:col-span-2">
              <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Описание</label>
              <textarea
                rows={4}
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
                className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
              />
            </div>
          </div>
          <button type="submit" className="mt-6 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
            {form.id ? 'Сохранить' : 'Создать'}
          </button>
        </form>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadProducts} />}
      {!isLoading && !error && products.length === 0 && <EmptyState title="Товары не добавлены" description="Создайте первый товар через форму выше." />}

      {!isLoading && !error && products.length > 0 && (
        <AdminTable headers={['Товар', 'Категория', 'Цена / PV', 'Остаток', 'Статус', 'Действия']}>
          {products.map((product) => (
            <tr key={product.id} className="hover:bg-safi-green/5 transition-colors group">
              <td className="px-6 py-4">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-xl bg-[#F5F5F0] flex items-center justify-center text-safi-text/30 shrink-0 border border-safi-green/10">
                    Img
                  </div>
                  <div>
                    <div className="font-bold text-safi-green">{product.name}</div>
                    <div className="text-[10px] text-safi-text/50 mt-1">Добавлен: {product.createdAt || '-'}</div>
                  </div>
                </div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm">{product.category}</div>
              </td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</div>
                <div className="text-[10px] uppercase font-bold text-safi-gold mt-1 tracking-widest">{product.pv} PV</div>
              </td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold">{product.stock || 0} шт</div>
              </td>
              <td className="px-6 py-4">
                <AdminBadge variant={product.status === 'active' ? 'success' : 'danger'}>{product.status || 'active'}</AdminBadge>
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
