import { useState } from 'react';
import { Search, Info } from 'lucide-react';
import { cn } from '../../lib/utils';
import { AdminBadge } from '../../components/admin/ui';

export default function AdminStructure() {
  const [view, setView] = useState<'tree' | 'list'>('tree');

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-6xl mx-auto">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Структура дерева</h1>
          <p className="text-sm text-safi-text/70">Визуализация бинарной структуры партнёров</p>
        </div>
        
        <div className="flex bg-[#F5F5F0] p-1 rounded-xl">
          <button 
            onClick={() => setView('tree')}
            className={cn("px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg transition-colors", view === 'tree' ? "bg-white text-safi-green shadow-sm" : "text-safi-text/50 hover:text-safi-green")}
          >
            Дерево
          </button>
          <button 
             onClick={() => setView('list')}
            className={cn("px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg transition-colors", view === 'list' ? "bg-white text-safi-green shadow-sm" : "text-safi-text/50 hover:text-safi-green")}
          >
            Список ветвей
          </button>
        </div>
      </div>

      <div className="bg-white p-4 rounded-[24px] border border-safi-green/5 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div className="flex-1 relative w-full">
          <Search className="w-5 h-5 text-safi-text/40 absolute left-4 top-1/2 -translate-y-1/2" />
          <input 
            type="text" 
            placeholder="Введите ID партнёра (например, SAFI-10000) чтобы открыть его ветку..." 
            className="w-full pl-12 pr-4 py-3 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
          />
        </div>
        <button className="px-8 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shrink-0">
          Показать
        </button>
      </div>

      <div className="bg-white rounded-[32px] border border-safi-green/5 shadow-sm p-8 min-h-[600px] flex items-center justify-center overflow-x-auto relative hidden-scrollbar">
         
         <div className="absolute top-4 right-4 flex items-center gap-2 text-xs text-safi-text/50">
           <Info className="w-4 h-4" />
           Демонстрационный вид дерева
         </div>

         {view === 'tree' ? (
           <div className="flex flex-col items-center gap-8 min-w-[800px] py-10">
              
              {/* Root */}
              <TreeNode name="Айдар Алиханов" id="SAFI-10000" pkg="ELITE" pv={2500} isRoot />

              <div className="flex gap-16 relative">
                 {/* Connection Lines (Simulated with div borders for mockup) */}
                 <div className="absolute -top-8 left-1/4 right-1/4 h-8 border-t-2 border-l-2 border-r-2 border-safi-green/20 rounded-t-xl" />
                 <div className="absolute -top-8 left-1/2 bottom-full border-l-2 border-safi-green/20" />
                 
                 {/* Left Branch */}
                 <div className="flex flex-col items-center gap-8 relative">
                   <div className="absolute -top-4 w-12 text-center text-[10px] font-bold text-safi-text/40 bg-white left-1/2 -ml-6">Левая</div>
                   <TreeNode name="Елена Волкова" id="SAFI-10001" pkg="VIP" pv={1000} />
                   
                   <div className="flex gap-8 relative mt-8">
                     <div className="absolute -top-8 left-1/4 right-1/4 h-8 border-t-2 border-l-2 border-r-2 border-safi-green/20 rounded-t-xl" />
                     <TreeNode name="Кайрат Исаев" id="SAFI-10003" pkg="BUSINESS" pv={250} />
                     <TreeNode name="Empty" id="-" pkg="BUSINESS" pv={0} empty />
                   </div>
                 </div>

                 {/* Right Branch */}
                 <div className="flex flex-col items-center gap-8 relative">
                   <div className="absolute -top-4 w-12 text-center text-[10px] font-bold text-safi-text/40 bg-white left-1/2 -ml-6">Правая</div>
                   <TreeNode name="Асель Муканова" id="SAFI-10002" pkg="BUSINESS" pv={50} />
                   
                   <div className="flex gap-8 relative mt-8">
                     <div className="absolute -top-8 left-1/4 right-1/4 h-8 border-t-2 border-l-2 border-r-2 border-safi-green/20 rounded-t-xl" />
                     <TreeNode name="Тимур Бекмансуров" id="SAFI-10004" pkg="VIP" pv={1500} />
                     <TreeNode name="User 5" id="SAFI-10005" pkg="BUSINESS" pv={100} />
                   </div>
                 </div>

              </div>

           </div>
         ) : (
           <div className="w-full h-full flex items-center justify-center text-safi-text/40 font-medium">
             Отображение списком в разработке
           </div>
         )}
      </div>

    </div>
  );
}

function TreeNode({ name, id, pkg, pv, isRoot, empty }: { name: string, id: string, pkg: string, pv: number, isRoot?: boolean, empty?: boolean }) {
  if (empty) {
    return (
      <div className="w-48 p-4 bg-[#F5F5F0]/50 border-2 border-dashed border-safi-green/20 rounded-2xl flex flex-col items-center text-center justify-center opacity-70 group hover:opacity-100 cursor-pointer">
         <div className="w-10 h-10 rounded-full bg-safi-green/5 text-safi-green/40 flex items-center justify-center text-xl pb-1 mb-2">+</div>
         <div className="text-xs font-bold text-safi-text/50 group-hover:text-safi-green">Добавить партнёра</div>
      </div>
    );
  }

  return (
    <div className={cn(
      "w-48 p-4 bg-white rounded-2xl flex flex-col items-center text-center shadow-sm cursor-pointer hover:-translate-y-1 transition-transform",
      isRoot ? "border-2 border-safi-gold shadow-md" : "border border-safi-green/10"
    )}>
       <div className={cn(
         "w-12 h-12 rounded-full flex items-center justify-center text-lg font-serif text-white font-bold mb-3",
         pkg === 'BUSINESS' ? "bg-blue-400" : pkg === 'VIP' ? "bg-purple-500" : "bg-safi-gold"
       )}>
         {name.charAt(0)}
       </div>
       <div className="font-bold text-sm text-safi-green mb-1 line-clamp-1 truncate w-full" title={name}>{name}</div>
       <div className="text-[10px] font-mono text-safi-text/50 bg-[#F5F5F0] px-2 py-0.5 rounded mb-2">{id}</div>
       <div className="w-full flex justify-between items-center text-[10px] border-t border-safi-green/5 pt-2 mt-1">
         <AdminBadge variant={pkg === 'ELITE' || pkg === 'VIP' ? 'gold' : 'default'} className="px-1.5 py-0.5">{pkg}</AdminBadge>
         <span className="font-bold text-safi-green">{pv} PV</span>
       </div>
    </div>
  )
}
