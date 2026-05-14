import { AdminBadge } from '../../components/admin/ui';
import { Package } from 'lucide-react';

export default function AdminPackages() {
  const packages = [
    { name: 'BUSINESS', price: '60 000 ₸', ref: '10%', bin: '7%', count: 520, status: 'Активен' },
    { name: 'VIP', price: '180 000 ₸', ref: '10%', bin: '8%', count: 280, status: 'Активен' },
    { name: 'ELITE', price: '300 000 ₸', ref: '10%', bin: '10%', count: 150, status: 'Активен' },
  ];

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Пакеты</h1>
          <p className="text-sm text-safi-text/70">Управление вступительными пакетами</p>
        </div>
      </div>

      <div className="grid md:grid-cols-3 gap-6 max-w-5xl">
         {packages.map((pkg, i) => (
           <div key={i} className="bg-white rounded-[32px] border border-safi-green/5 shadow-sm p-6 relative overflow-hidden group">
             <div className="mb-4">
                <AdminBadge variant="gold">{pkg.status}</AdminBadge>
             </div>
             <Package className="w-10 h-10 text-safi-green/20 absolute top-6 right-6" />
             <h3 className="text-2xl font-serif font-bold text-safi-green mb-1">{pkg.name}</h3>
             <div className="text-sm text-safi-text/60 mb-6">{pkg.price}</div>
             
             <div className="space-y-3 mb-6 flex-1">
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span className="text-safi-text/60">Реферальный</span>
                 <span className="font-bold text-safi-green">{pkg.ref}</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span className="text-safi-text/60">Бинарный</span>
                 <span className="font-bold text-safi-green">{pkg.bin}</span>
               </div>
               <div className="flex justify-between items-center text-sm pb-2">
                 <span className="text-safi-text/60">Пользователей</span>
                 <span className="font-bold">{pkg.count}</span>
               </div>
             </div>

             <button className="w-full py-3 bg-[#F5F5F0] group-hover:bg-safi-green group-hover:text-white rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green transition-colors">
               Редактировать
             </button>
           </div>
         ))}
      </div>
    </div>
  );
}
