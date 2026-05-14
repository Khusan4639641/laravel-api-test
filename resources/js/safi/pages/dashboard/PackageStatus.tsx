import { useState } from 'react';
import { ArrowUpRight, CheckCircle2, Lock, Trophy } from 'lucide-react';
import { packages } from '../../data/packages';
import { statuses } from '../../data/statuses';
import { Badge, ProgressBar } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { activatePackage, ApiError, upgradePackage } from '../../lib/api';
import { cn } from '../../lib/utils';

export default function PackageStatus() {
  const { currentUser, refreshCurrentUser } = useDashboardContext();
  const [pendingPackage, setPendingPackage] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const currentPackageIndex = packages.findIndex((pkg) => pkg.name.toLowerCase() === currentUser.packageName.toLowerCase());
  const nextStatus = statuses.find((status) => status.pv > currentUser.personalPV) || statuses[statuses.length - 1];

  const handlePackageAction = async (packageId: string, action: 'activate' | 'upgrade') => {
    setPendingPackage(packageId);
    setMessage('');
    setError('');

    try {
      if (action === 'activate') {
        await activatePackage(packageId);
      } else {
        await upgradePackage(packageId);
      }

      setMessage(action === 'activate' ? 'Пакет отправлен на активацию.' : 'Апгрейд пакета отправлен.');
      await refreshCurrentUser();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось выполнить действие. Попробуйте позже.');
      }
    } finally {
      setPendingPackage('');
    }
  };

  return (
    <div className="space-y-10">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Package status</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Пакет и статус</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Управление стартовым пакетом, апгрейдом и прогрессом по PV.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">Пакет: {currentUser.packageName}</Badge>
            <Badge variant="default">Статус: {currentUser.status}</Badge>
          </div>
        </div>

        {(message || error) && (
          <div className={`mt-6 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {error || message}
          </div>
        )}
      </section>

      <section className="grid gap-5 md:grid-cols-3">
        {packages.map((pkg, index) => {
          const isCurrent = index === currentPackageIndex;
          const isLower = currentPackageIndex >= 0 && index < currentPackageIndex;
          const action: 'activate' | 'upgrade' = currentPackageIndex === -1 ? 'activate' : 'upgrade';

          return (
            <article
              key={pkg.id}
              className={cn(
                'relative flex flex-col rounded-[32px] border p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)]',
                isCurrent ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white text-safi-green'
              )}
            >
              {isCurrent && <Trophy className="absolute right-6 top-6 h-6 w-6 text-safi-gold" />}
              <h2 className={`font-serif text-3xl font-semibold ${isCurrent ? 'text-white' : 'text-safi-green'}`}>{pkg.name}</h2>
              <div className={`mt-3 text-4xl font-extrabold ${isCurrent ? 'text-safi-gold' : 'text-safi-green'}`}>
                {pkg.price.toLocaleString('ru-RU')} ₸
              </div>
              <div className="mt-6 grid grid-cols-2 gap-3">
                <PackageMetric label="Реф." value={`${pkg.referralBonus}%`} dark={isCurrent} />
                <PackageMetric label="Бинар" value={pkg.binaryBonus ? `${pkg.binaryBonus}%` : '-'} dark={isCurrent} />
              </div>
              <ul className="mt-7 flex-1 space-y-3">
                {pkg.features.slice(0, 4).map((feature) => (
                  <li key={feature} className={`flex gap-3 text-sm leading-6 ${isCurrent ? 'text-white/75' : 'text-safi-muted'}`}>
                    <CheckCircle2 className="mt-1 h-4 w-4 shrink-0 text-safi-gold" />
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>
              {isCurrent ? (
                <div className="mt-8 rounded-full border border-white/15 bg-white/10 px-4 py-3 text-center text-[10px] font-extrabold uppercase tracking-[0.16em] text-white">
                  Текущий пакет
                </div>
              ) : (
                <button
                  type="button"
                  disabled={isLower || pendingPackage === pkg.id}
                  onClick={() => handlePackageAction(pkg.id, action)}
                  className="mt-8 inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white disabled:cursor-not-allowed disabled:opacity-45"
                >
                  <ArrowUpRight className="h-4 w-4" />
                  {pendingPackage === pkg.id ? 'Отправляем...' : isLower ? 'Пройден' : currentPackageIndex === -1 ? 'Активировать' : 'Апгрейд'}
                </button>
              )}
            </article>
          );
        })}
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
          <span className="safi-kicker">PV progress</span>
          <h2 className="mt-3 font-serif text-3xl font-semibold text-safi-green">Следующий статус: {nextStatus.name}</h2>
          <p className="mt-3 text-sm leading-7 text-safi-muted">
            Ваш текущий PV: {currentUser.personalPV.toLocaleString('ru-RU')} PV.
          </p>
          <div className="mt-8">
            <ProgressBar label={`${currentUser.status} -> ${nextStatus.name}`} current={currentUser.personalPV} total={nextStatus.pv} />
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
          <span className="safi-kicker">Statuses</span>
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            {statuses.slice(0, 6).map((status) => {
              const achieved = currentUser.personalPV >= status.pv;
              const current = currentUser.status.toLowerCase() === status.name.toLowerCase();

              return (
                <div key={status.id} className={`rounded-3xl border p-5 ${achieved ? 'border-safi-gold/40 bg-safi-cream' : 'border-safi-border bg-white'}`}>
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span className={`flex h-10 w-10 items-center justify-center rounded-full ${achieved ? 'bg-safi-gold text-safi-black' : 'bg-safi-cream text-safi-muted'}`}>
                      {achieved ? <CheckCircle2 className="h-5 w-5" /> : <Lock className="h-4 w-4" />}
                    </span>
                    {current && <Badge variant="gold">Текущий</Badge>}
                  </div>
                  <h3 className="font-serif text-xl font-semibold text-safi-green">{status.name}</h3>
                  <div className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{status.pv.toLocaleString('ru-RU')} PV</div>
                  <div className="mt-4 text-sm font-bold text-safi-gold">{status.reward}</div>
                </div>
              );
            })}
          </div>
        </article>
      </section>
    </div>
  );
}

function PackageMetric({ label, value, dark }: { label: string; value: string; dark?: boolean }) {
  return (
    <div className={`rounded-2xl border px-4 py-3 ${dark ? 'border-white/10 bg-white/[0.08]' : 'border-safi-border bg-safi-cream'}`}>
      <div className={`text-[9px] font-extrabold uppercase tracking-[0.16em] ${dark ? 'text-white/60' : 'text-safi-muted'}`}>{label}</div>
      <div className={`mt-1 text-lg font-extrabold ${dark ? 'text-safi-gold' : 'text-safi-green'}`}>{value}</div>
    </div>
  );
}
