import { bonuses } from '../../data/adminMock';
import { AdminTable, AdminBadge } from '../../components/admin/ui';

export default function AdminBonuses() {
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Бонусы</h1>
          <p className="text-sm text-safi-text/70">Начисления и статистика</p>
        </div>
      </div>

      <AdminTable headers={['Дата', 'Партнёр', 'Тип бонуса', 'Обоснование', 'Сумма', 'Статус']}>
        {bonuses.map((b, i) => (
          <tr key={i} className="hover:bg-safi-green/5 transition-colors group">
            <td className="px-6 py-4 text-xs">{b.date}</td>
            <td className="px-6 py-4">
              <div className="font-bold text-safi-green">{b.partnerName}</div>
              <div className="text-[10px] font-mono text-safi-text/50">{b.partnerId}</div>
            </td>
            <td className="px-6 py-4 font-bold text-sm">{b.type}</td>
            <td className="px-6 py-4">
              <div className="text-sm">{b.basis}</div>
              <div className="text-xs text-safi-gold font-bold mt-0.5">{b.percentage}</div>
            </td>
            <td className="px-6 py-4 font-bold text-green-600">+{b.amount}</td>
            <td className="px-6 py-4"><AdminBadge variant="success">{b.status}</AdminBadge></td>
          </tr>
        ))}
      </AdminTable>
    </div>
  );
}
