import { products } from '../../data/adminMock';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Plus, Edit, Trash2 } from 'lucide-react';

export default function AdminProducts() {
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Товары</h1>
          <p className="text-sm text-safi-text/70">Управление каталогом продукции</p>
        </div>
        <button className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg">
          <Plus className="w-4 h-4 ml-[-4px]" /> Добавить товар
        </button>
      </div>

      <AdminTable headers={['Товар', 'Категория', 'Цена / PV', 'Остаток', 'Статус', 'Действия']}>
        {products.map((p, i) => (
          <tr key={i} className="hover:bg-safi-green/5 transition-colors group">
            <td className="px-6 py-4">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-[#F5F5F0] flex items-center justify-center text-safi-text/30 shrink-0 border border-safi-green/10">
                  Img
                </div>
                <div>
                  <div className="font-bold text-safi-green">{p.name}</div>
                  <div className="text-[10px] text-safi-text/50 mt-1">Добавлен: {p.added}</div>
                </div>
              </div>
            </td>
            <td className="px-6 py-4">
              <div className="text-sm">{p.category}</div>
            </td>
            <td className="px-6 py-4">
              <div className="font-bold text-safi-green">{p.price.toLocaleString('ru-RU')} ₸</div>
              <div className="text-[10px] uppercase font-bold text-safi-gold mt-1 tracking-widest">{p.pv} PV</div>
            </td>
            <td className="px-6 py-4">
              <div className="text-sm font-bold">{p.stock} шт</div>
            </td>
            <td className="px-6 py-4">
              <AdminBadge variant={p.status === 'Активен' ? 'success' : 'danger'}>{p.status}</AdminBadge>
            </td>
            <td className="px-6 py-4 text-right">
              <div className="flex justify-end gap-2">
                <button className="p-2 text-safi-text hover:text-safi-green hover:bg-[#F5F5F0] rounded-lg transition-colors">
                  <Edit className="w-4 h-4" />
                </button>
                <button className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        ))}
      </AdminTable>
      
    </div>
  );
}
