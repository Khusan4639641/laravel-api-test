import { AdminStatCard } from '../../components/admin/ui';
import { adminStats } from '../../data/adminMock';
import { PieChart, Download, DollarSign, Users, Package } from 'lucide-react';

export default function AdminReports() {
  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Отчёты</h1>
          <p className="text-sm text-safi-text/70">Аналитика и статистика платформы</p>
        </div>
      </div>

      <div className="grid md:grid-cols-3 gap-6">
         <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm space-y-6">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
              <Users className="w-5 h-5 text-safi-gold" /> Рост партнёров
            </h3>
            <div className="space-y-4">
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Всего</span><span className="font-bold">{adminStats.totalPartners}</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Новых за 14 дн</span><span className="font-bold text-green-600">+{adminStats.newPartners14Days}</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Активных</span><span className="font-bold">{adminStats.activePartners}</span>
               </div>
               <div className="flex justify-between items-center text-sm">
                 <span>Неактивных</span><span className="font-bold text-red-500">{adminStats.inactivePartners}</span>
               </div>
            </div>
            <button className="w-full flex justify-center items-center gap-2 py-3 bg-[#F5F5F0] hover:bg-safi-green hover:text-white rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green transition-colors mt-4">
               <Download className="w-4 h-4" /> Скачать отчёт
            </button>
         </div>

         <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm space-y-6">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
              <DollarSign className="w-5 h-5 text-safi-gold" /> Финансы
            </h3>
            <div className="space-y-4">
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Оборот</span><span className="font-bold text-safi-green">{adminStats.totalRevenue.toLocaleString()} ₸</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Выплачено</span><span className="font-bold text-red-500">{adminStats.totalBonusesPaid.toLocaleString()} ₸</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>В ожидании</span><span className="font-bold">{adminStats.pendingWithdrawals.toLocaleString()} ₸</span>
               </div>
            </div>
            <button className="w-full flex justify-center items-center gap-2 py-3 bg-[#F5F5F0] hover:bg-safi-green hover:text-white rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green transition-colors mt-4">
               <Download className="w-4 h-4" /> Скачать отчёт
            </button>
         </div>
         
         <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm space-y-6">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
              <Package className="w-5 h-5 text-safi-gold" /> Пакеты
            </h3>
            <div className="space-y-4">
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Всего продано</span><span className="font-bold text-safi-green">{adminStats.packagesSold}</span>
               </div>
               <div className="text-center py-8 text-safi-text/40 font-bold uppercase tracking-widest text-[10px]">
                  Здесь будет круговая<br/>диаграмма
               </div>
            </div>
         </div>
      </div>
    </div>
  );
}
