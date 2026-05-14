import { useParams, Link } from 'react-router-dom';
import { partners } from '../../data/adminMock';
import { AdminStatCard, AdminBadge, AdminTable } from '../../components/admin/ui';
import { ArrowLeft, User, Phone, Mail, MapPin, Calendar, Network, CreditCard, Lock, Unlock, MessageSquare, Edit } from 'lucide-react';

export default function AdminPartnerDetail() {
  const { id } = useParams();
  // Using the first partner as a mock if id not found (in reality, fetch by ID)
  const partnerMatch = partners.find(p => p.id === id);
  const partner = partnerMatch || partners[0];

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div className="flex items-center gap-4">
          <Link to="/admin/partners" className="p-3 bg-white rounded-xl border border-safi-green/5 shadow-sm text-safi-text/60 hover:text-safi-green hover:bg-[#F5F5F0] transition-colors">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{partner.fullName}</h1>
            <div className="flex items-center gap-3">
              <span className="text-sm font-mono text-safi-text/70 bg-[#F5F5F0] px-2 py-0.5 rounded">{partner.id}</span>
              <AdminBadge variant={partner.accountStatus === 'Активен' ? 'success' : 'danger'}>{partner.accountStatus}</AdminBadge>
            </div>
          </div>
        </div>
        
        <div className="flex gap-2">
          {partner.accountStatus === 'Активен' ? (
            <button className="flex items-center gap-2 px-4 py-2 bg-red-500/10 text-red-600 hover:bg-red-500/20 rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
              <Lock className="w-4 h-4" /> Заблокировать
            </button>
          ) : (
            <button className="flex items-center gap-2 px-4 py-2 bg-green-500/10 text-green-600 hover:bg-green-500/20 rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
              <Unlock className="w-4 h-4" /> Разблокировать
            </button>
          )}
        </div>
      </div>

      <div className="grid lg:grid-cols-3 gap-8">
        
        {/* Left Col - Overview */}
        <div className="space-y-8">
          {/* Profile Card */}
          <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-6">
              <User className="w-5 h-5 text-safi-gold" /> Основная информация
            </h3>
            
            <div className="space-y-4">
              <InfoRow icon={Phone} label="Телефон" value={partner.phone} />
              <InfoRow icon={Mail} label="Email" value={partner.email} />
              <InfoRow icon={MapPin} label="Город" value={partner.city} />
              <InfoRow icon={Calendar} label="Регистрация" value={partner.registrationDate} />
              <div className="flex items-center justify-between p-3 bg-[#F5F5F0] rounded-xl text-sm mt-4">
                <span className="text-safi-text/60">Спонсор:</span>
                <Link to={`/admin/partners/${partner.sponsor}`} className="font-bold font-mono text-safi-green hover:underline">{partner.sponsor}</Link>
              </div>
            </div>

            <div className="mt-6 pt-6 border-t border-safi-green/5 grid grid-cols-2 gap-4">
              <div>
                <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Текущий пакет</div>
                <AdminBadge variant="gold">{partner.package}</AdminBadge>
                <button className="text-[10px] text-safi-gold mt-2 hover:underline flex items-center gap-1"><Edit className="w-3 h-3" /> Изменить</button>
              </div>
              <div>
                <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Статус</div>
                <AdminBadge variant="default">{partner.status}</AdminBadge>
                <button className="text-[10px] text-safi-gold mt-2 hover:underline flex items-center gap-1"><Edit className="w-3 h-3" /> Изменить</button>
              </div>
            </div>
          </div>

          {/* Admin Comment Card */}
          <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-gold/30">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3 mb-4">
              <MessageSquare className="w-5 h-5 text-safi-gold" /> Заметка администратора
            </h3>
            <textarea 
              className="w-full h-32 bg-[#F5F5F0] rounded-xl p-4 text-sm font-medium border-none focus:ring-2 focus:ring-safi-gold/50 outline-none resize-none"
              placeholder="Оставьте внутренний комментарий о партнёре (виден только администраторам)..."
              defaultValue="Активный партнёр, развивает левую ветку. Проверить реквизиты перед следующим выводом."
            />
            <button className="w-full mt-3 bg-safi-green text-safi-gold py-3 rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors hover:text-white">
              Сохранить заметку
            </button>
          </div>
        </div>

        {/* Right Col - Details */}
        <div className="lg:col-span-2 space-y-8">
          
          {/* Finance Overview */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <MiniStat title="Доступно" value={`${partner.availableBalance.toLocaleString('ru-RU')} ₸`} />
            <MiniStat title="Всего заработал" value={`${partner.totalIncome.toLocaleString('ru-RU')} ₸`} />
            <MiniStat title="Личный PV" value={partner.personalPV} />
            <MiniStat title="Командный PV" value={partner.teamPV} />
          </div>

          {/* Structure Summary */}
          <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                <Network className="w-5 h-5 text-safi-gold" /> Обзор структуры
              </h3>
              <Link to="/admin/structure" className="text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">
                Открыть полное дерево →
              </Link>
            </div>
            
            <div className="flex flex-col md:flex-row gap-6 items-center">
               <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Левая ветка</div>
                  <div className="text-2xl font-bold text-safi-green">{partner.leftPV.toLocaleString('ru-RU')} PV</div>
               </div>
               
               <div className="w-12 h-12 rounded-full border border-safi-green/10 flex items-center justify-center shrink-0">VS</div>
               
               <div className="w-full flex-1 p-6 bg-[#F5F5F0] rounded-2xl flex flex-col items-center justify-center text-center">
                  <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-2">Правая ветка</div>
                  <div className="text-2xl font-bold text-safi-green">{partner.rightPV.toLocaleString('ru-RU')} PV</div>
               </div>
            </div>
            
            <div className="mt-6 flex justify-between items-center px-4">
               <div className="text-sm"><span className="text-safi-text/60">Лично пригласил:</span> <span className="font-bold">{partner.invitedCount}</span></div>
               <div className="text-sm"><span className="text-safi-text/60">Слабая ветка:</span> <span className="font-bold text-safi-gold">{partner.leftPV < partner.rightPV ? 'Левая' : 'Правая'}</span></div>
            </div>
          </div>

          {/* Recent Transactions Preview */}
          <div className="bg-white p-6 md:p-8 rounded-[32px] shadow-sm border border-safi-green/5">
             <div className="flex justify-between items-center mb-6">
              <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
                <CreditCard className="w-5 h-5 text-safi-gold" /> Последние транзакции
              </h3>
              <Link to="/admin/transactions" className="text-[10px] uppercase font-bold tracking-widest text-safi-gold hover:underline">
                Смотреть все →
              </Link>
            </div>
            
            {/* Mock short table */}
            <AdminTable headers={['Дата', 'Тип', 'Сумма', 'Статус', 'Источник']}>
               {[1,2,3].map(i => (
                 <tr key={i} className="hover:bg-safi-green/5 transition-colors">
                    <td className="px-6 py-4 text-xs">25.04.2026</td>
                    <td className="px-6 py-4 text-xs font-bold">Бинарный бонус</td>
                    <td className="px-6 py-4 text-xs font-bold text-green-600">+15 000 ₸</td>
                    <td className="px-6 py-4"><AdminBadge variant="success">Начислено</AdminBadge></td>
                    <td className="px-6 py-4 text-xs text-safi-text/60">SAFI-10045</td>
                 </tr>
               ))}
            </AdminTable>
          </div>

        </div>

      </div>
    </div>
  );
}

function InfoRow({ icon: Icon, label, value }: { icon: any, label: string, value: string }) {
  return (
    <div className="flex items-center gap-4 group">
      <div className="w-10 h-10 rounded-xl bg-[#F5F5F0] flex items-center justify-center text-safi-green/50 group-hover:text-safi-green group-hover:bg-safi-green/10 transition-colors">
        <Icon className="w-4 h-4" />
      </div>
      <div>
        <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/40">{label}</div>
        <div className="text-sm font-bold text-safi-text">{value}</div>
      </div>
    </div>
  )
}

function MiniStat({ title, value }: { title: string, value: string | number }) {
  return (
    <div className="bg-white p-4 rounded-2xl border border-safi-green/5 shadow-sm text-center">
       <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50 mb-1">{title}</div>
       <div className="text-lg font-bold text-safi-green">{value}</div>
    </div>
  )
}
