import { AdminTable, AdminBadge } from '../../components/admin/ui';

export default function AdminStatuses() {
  const statuses = [
    { name: "Менеджер", pv: "1 000", volume: "500 000 ₸", reward: "2 продукции", count: 450 },
    { name: "Лидер", pv: "2 500", volume: "1 250 000 ₸", reward: "косметика", count: 280 },
    { name: "Директор", pv: "5 000", volume: "2 500 000 ₸", reward: "250 000 ₸", count: 120 },
    { name: "Бронзовый директор", pv: "10 000", volume: "5 000 000 ₸", reward: "санаторий / 400к", count: 45 },
    { name: "Серебряный директор", pv: "25 000", volume: "12 500 000 ₸", reward: "путёвка / 750к", count: 15 },
    { name: "Золотой директор", pv: "50 000", volume: "25 000 000 ₸", reward: "5 000 000 ₸", count: 5 },
    { name: "Платиновый директор", pv: "100 000", volume: "50 000 000 ₸", reward: "6 000 000 ₸", count: 2 },
    { name: "Изумрудный директор", pv: "250 000", volume: "125 000 000 ₸", reward: "Автобонус 10 млн", count: 0 },
    { name: "Бриллиантовый директор", pv: "500 000", volume: "250 000 000 ₸", reward: "Квартира 20 млн", count: 0 },
  ];

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Статусы</h1>
          <p className="text-sm text-safi-text/70">Лестница статусов и условия достижения</p>
        </div>
      </div>

      <AdminTable headers={['Статус', 'Условия (PV)', 'Премия / Подарок', 'Партнёров на статусе', 'Действия']}>
        {statuses.map((s, i) => (
          <tr key={i} className="hover:bg-safi-green/5 transition-colors group">
             <td className="px-6 py-4 font-bold text-safi-green text-sm">{s.name}</td>
             <td className="px-6 py-4">
               <div className="text-sm font-bold">{s.pv} PV</div>
             </td>
             <td className="px-6 py-4 font-bold max-w-[200px] truncate">{s.reward}</td>
             <td className="px-6 py-4"><AdminBadge variant="default">{s.count}</AdminBadge></td>
             <td className="px-6 py-4 text-right">
                <button className="text-xs font-bold uppercase tracking-widest text-safi-gold hover:underline">Изменить</button>
             </td>
          </tr>
        ))}
      </AdminTable>
    </div>
  );
}
