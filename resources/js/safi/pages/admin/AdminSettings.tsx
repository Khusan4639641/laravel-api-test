import { AdminTable } from '../../components/admin/ui';
import { Settings, Save } from 'lucide-react';

export default function AdminSettings() {
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-4xl">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Настройки</h1>
          <p className="text-sm text-safi-text/70">Глобальные параметры системы</p>
        </div>
        <button className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg">
          <Save className="w-4 h-4 ml-[-4px]" /> Сохранить настройки
        </button>
      </div>

      <div className="grid gap-8">
        <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6 flex items-center gap-3">
             <Settings className="w-5 h-5 text-safi-gold" /> Основные настройки
          </h3>
          <div className="space-y-4">
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Название компании</label>
                <input type="text" defaultValue="Safi Life" className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" />
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Минимальная сумма вывода</label>
                <input type="text" defaultValue="10 000 ₸" className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" />
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Доступные способы вывода</label>
                <div className="flex flex-col gap-2 p-3 bg-[#F5F5F0] rounded-xl">
                   <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" defaultChecked className="rounded text-safi-green focus:ring-safi-green" /> Банковская карта (KZT)</label>
                   <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" defaultChecked className="rounded text-safi-green focus:ring-safi-green" /> Счёт ИП</label>
                   <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" className="rounded text-safi-green focus:ring-safi-green" /> USDT ERC-20 / TRC-20</label>
                </div>
             </div>
          </div>
        </div>
      </div>
    </div>
  );
}
