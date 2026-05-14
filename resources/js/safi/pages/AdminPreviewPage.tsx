import React from 'react';
import { Link } from 'react-router-dom';
import { Users, ShoppingCart, DollarSign, Settings, Bell, TrendingUp, Settings2 } from 'lucide-react';

export default function AdminPreviewPage() {
  return (
    <div className="min-h-screen bg-safi-bg font-sans flex flex-col pt-8 pb-20 px-4 md:px-8 relative overflow-hidden">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none z-0"></div>
      
      <div className="max-w-[1400px] mx-auto w-full relative z-10">
        <header className="flex flex-col md:flex-row md:justify-between md:items-center mb-10 gap-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <div className="w-10 h-10 bg-safi-green rounded-xl flex items-center justify-center text-safi-gold font-bold text-xl">S</div>
              <h1 className="text-3xl font-serif font-bold text-safi-green">Safi Life</h1>
            </div>
            <p className="text-[10px] uppercase tracking-widest text-safi-gold font-bold">Панель администратора (Preview)</p>
          </div>
          <div className="flex items-center gap-4">
            <button className="w-10 h-10 rounded-full bg-white border border-safi-green/10 flex items-center justify-center text-safi-green hover:bg-safi-green/5 transition-colors">
              <Bell className="w-5 h-5" />
            </button>
            <Link to="/" className="text-xs uppercase tracking-widest text-safi-green font-bold hover:text-safi-gold transition-colors underline-offset-4 hover:underline">
              Вернуться на сайт
            </Link>
          </div>
        </header>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          {[
            { label: 'Всего партнёров', value: '1,248', icon: <Users className="w-5 h-5 opacity-40" /> },
            { label: 'Новых (30 дней)', value: '+156', icon: <TrendingUp className="w-5 h-5 opacity-40" /> },
            { label: 'Общий объем PV', value: '8.4M', icon: <ShoppingCart className="w-5 h-5 opacity-40" /> },
            { label: 'Заявки на вывод', value: '12', icon: <DollarSign className="w-5 h-5 opacity-40" /> },
          ].map((stat, i) => (
             <div key={i} className="bg-white border border-safi-green/5 p-6 rounded-[24px] shadow-sm flex flex-col hover:-translate-y-1 transition-transform duration-300">
               <div className="flex justify-between items-start mb-4">
                 <div className="text-[10px] uppercase tracking-widest text-safi-text opacity-70 font-bold">{stat.label}</div>
                 <div className="text-safi-green">{stat.icon}</div>
               </div>
               <div className="text-3xl font-serif font-bold text-safi-green mt-auto">{stat.value}</div>
             </div>
          ))}
        </div>

        <div className="grid lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-8">
            {/* Recent Registrations Table */}
            <div className="bg-white border border-safi-green/5 rounded-[32px] overflow-hidden shadow-sm">
               <div className="p-6 md:p-8 border-b border-safi-green/5 flex justify-between items-center bg-[#F5F5F0]">
                 <div className="text-sm uppercase tracking-widest text-safi-green font-bold">Последние регистрации</div>
                 <button className="text-[10px] uppercase tracking-widest text-safi-gold font-bold hover:underline">Смотреть все</button>
               </div>
               <div className="overflow-x-auto">
                 <table className="w-full text-left">
                   <thead className="bg-white text-[10px] text-safi-text opacity-50 uppercase tracking-widest border-b border-safi-green/5">
                     <tr>
                       <th className="px-8 py-4 font-bold">ID</th>
                       <th className="px-8 py-4 font-bold">Партнёр</th>
                       <th className="px-8 py-4 font-bold">Пакет</th>
                       <th className="px-8 py-4 font-bold">Дата</th>
                     </tr>
                   </thead>
                   <tbody className="divide-y divide-safi-green/5 text-sm">
                     {[1, 2, 3].map((item) => (
                       <tr key={item} className="hover:bg-safi-green/5 transition-colors">
                         <td className="px-8 py-5 text-safi-text opacity-70 font-mono">#SL-20{item}4</td>
                         <td className="px-8 py-5 font-bold text-safi-green flex items-center gap-3">
                           <div className="w-8 h-8 rounded-full bg-[#F5F5F0] flex items-center justify-center text-[10px] text-safi-gold font-bold shrink-0">И</div>
                           Иван Иванов
                         </td>
                         <td className="px-8 py-5">
                           <span className="px-3 py-1 bg-safi-gold/10 text-safi-gold rounded-md text-[10px] uppercase tracking-widest font-bold">BUSINESS</span>
                         </td>
                         <td className="px-8 py-5 text-safi-text opacity-70">Сегодня, 14:30</td>
                       </tr>
                     ))}
                   </tbody>
                 </table>
               </div>
            </div>

            {/* Withdrawals Table */}
            <div className="bg-white border border-safi-green/5 rounded-[32px] overflow-hidden shadow-sm">
               <div className="p-6 md:p-8 border-b border-safi-green/5 flex justify-between items-center bg-[#F5F5F0]">
                 <div className="text-sm uppercase tracking-widest text-safi-green font-bold">Заявки на вывод (Ожидают)</div>
               </div>
               <div className="p-6 md:p-8 text-sm text-safi-text opacity-70 text-center py-12">
                 Здесь будет отображаться таблица заявок на выплату с кнопками "Одобрить" и "Отклонить".
               </div>
            </div>
          </div>
          
          <div className="space-y-4">
            <div className="bg-safi-green text-white border border-safi-green/10 rounded-[32px] p-6 md:p-8 shadow-xl relative overflow-hidden">
               <div className="absolute -top-10 -right-10 w-32 h-32 bg-white/5 rounded-full blur-xl z-0"></div>
               <div className="text-sm uppercase tracking-widest font-bold mb-6 text-safi-gold relative z-10 flex items-center gap-2">
                 <Settings2 className="w-4 h-4" /> Модули управления
               </div>
               <div className="space-y-3 relative z-10">
                 {['Управление пользователями', 'Бинарное дерево', 'Каталог продуктов', 'Настройки маркетинга', 'Финансовые отчеты', 'Настройки системы'].map((mod, i) => (
                   <button key={i} className="w-full text-left bg-white/5 hover:bg-white/10 px-5 py-4 rounded-xl text-xs uppercase tracking-wider font-bold transition-colors border border-white/5 flex justify-between items-center group">
                     {mod}
                     <span className="text-safi-gold opacity-0 group-hover:opacity-100 transition-opacity">→</span>
                   </button>
                 ))}
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
