import { useEffect, useState } from 'react';
import { Download, DollarSign, Users, Package } from 'lucide-react';
import { ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminReportsSummary, getApiErrorState } from '../../lib/api';

export default function AdminReports() {
  const [stats, setStats] = useState({
    totalPartners: 0,
    newPartners14Days: 0,
    activePartners: 0,
    inactivePartners: 0,
    totalRevenue: 0,
    totalBonusesPaid: 0,
    pendingWithdrawals: 0,
    packagesSold: 0,
  });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadReports = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setStats(normalizeReports(await getAdminReportsSummary()));
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить отчеты.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadReports();
  }, []);

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Отчёты</h1>
          <p className="text-sm text-safi-text/70">Аналитика и статистика платформы</p>
        </div>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadReports} />}

      {!isLoading && !error && (
      <div className="grid md:grid-cols-3 gap-6">
         <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm space-y-6">
            <h3 className="text-xl font-serif font-bold text-safi-green flex items-center gap-3">
              <Users className="w-5 h-5 text-safi-gold" /> Рост партнёров
            </h3>
            <div className="space-y-4">
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Всего</span><span className="font-bold">{stats.totalPartners}</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Новых за 14 дн</span><span className="font-bold text-green-600">+{stats.newPartners14Days}</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Активных</span><span className="font-bold">{stats.activePartners}</span>
               </div>
               <div className="flex justify-between items-center text-sm">
                 <span>Неактивных</span><span className="font-bold text-red-500">{stats.inactivePartners}</span>
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
                 <span>Оборот</span><span className="font-bold text-safi-green">{stats.totalRevenue.toLocaleString()} ₸</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>Выплачено</span><span className="font-bold text-red-500">{stats.totalBonusesPaid.toLocaleString()} ₸</span>
               </div>
               <div className="flex justify-between items-center text-sm border-b border-safi-green/5 pb-2">
                 <span>В ожидании</span><span className="font-bold">{stats.pendingWithdrawals.toLocaleString()} ₸</span>
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
                 <span>Всего продано</span><span className="font-bold text-safi-green">{stats.packagesSold}</span>
               </div>
               <div className="text-center py-8 text-safi-text/40 font-bold uppercase tracking-widest text-[10px]">
                  Здесь будет круговая<br/>диаграмма
               </div>
            </div>
         </div>
      </div>
      )}
    </div>
  );
}

function normalizeReports(response: unknown) {
  const record = isRecord(response) ? response : {};
  const partners = getRecord(record, 'partners');
  const finance = getRecord(record, 'finance');
  const packages = getRecord(record, 'packages');

  return {
    totalPartners: getNumber(partners.total),
    newPartners14Days: getNumber(partners.new_14_days),
    activePartners: getNumber(partners.active),
    inactivePartners: getNumber(partners.inactive),
    totalRevenue: getNumber(finance.revenue),
    totalBonusesPaid: getNumber(finance.bonuses_paid),
    pendingWithdrawals: getNumber(finance.pending_withdrawals),
    packagesSold: getNumber(packages.sold),
  };
}

function getRecord(record: Record<string, unknown>, key: string) {
  return isRecord(record[key]) ? record[key] as Record<string, unknown> : {};
}

function getNumber(value: unknown) {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  return 0;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
