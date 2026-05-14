import { supportTickets } from '../../data/adminMock';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Filter } from 'lucide-react';

export default function AdminSupport() {
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Обращения</h1>
          <p className="text-sm text-safi-text/70">Техподдержка и вопросы от партнёров</p>
        </div>
        <button className="flex items-center justify-center gap-2 px-6 py-3 bg-[#F5F5F0] hover:bg-safi-green/10 text-safi-green rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors md:w-auto w-full shrink-0">
          <Filter className="w-4 h-4" /> Фильтры
        </button>
      </div>

      <AdminTable headers={['Тикет / Даты', 'Отправитель', 'Тематика', 'Статус', 'Действие']}>
        {supportTickets.map((t, i) => (
          <tr key={i} className="hover:bg-safi-green/5 transition-colors group cursor-pointer">
            <td className="px-6 py-4">
              <div className="font-bold font-mono text-safi-green">{t.id}</div>
              <div className="text-[10px] text-safi-text/50 mt-1">Создан: {t.date}</div>
              {t.lastReply !== '-' && <div className="text-[10px] text-safi-gold font-bold mt-1">Ждёт ответа: {t.lastReply}</div>}
            </td>
            <td className="px-6 py-4">
              <div className="text-sm font-bold text-safi-green">{t.partner}</div>
            </td>
            <td className="px-6 py-4">
              <div className="font-bold text-safi-text mb-1">{t.subject}</div>
              <div className="text-xs text-safi-text/60">{t.category}</div>
            </td>
            <td className="px-6 py-4">
               <AdminBadge variant={t.status === 'Закрыто' ? 'default' : t.status === 'В работе' ? 'warning' : t.status === 'Новое' ? 'danger' : 'success'}>
                 {t.status}
               </AdminBadge>
            </td>
            <td className="px-6 py-4 text-right">
               <button className="px-4 py-2 bg-[#F5F5F0] hover:bg-safi-green hover:text-white text-safi-green text-[10px] uppercase tracking-widest font-bold rounded-lg transition-colors">
                 Открыть
               </button>
            </td>
          </tr>
        ))}
      </AdminTable>
      
    </div>
  );
}
