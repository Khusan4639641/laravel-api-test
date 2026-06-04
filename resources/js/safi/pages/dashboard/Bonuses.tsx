import { FormEvent, useCallback, useEffect, useState } from 'react';
import { ArrowUpCircle, Info, Wallet } from 'lucide-react';
import { Badge, ProgressBar, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, createDashboardWithdrawal, getApiErrorState, getDashboardBonuses, getDashboardOverview, getDashboardWithdrawals, getNumber, getPublicStatuses, getString, Status } from '../../lib/api';
import { cn } from '../../lib/utils';

interface WithdrawalItem {
  id: string;
  date: string;
  amount: string;
  method: string;
  status: string;
  paymentDate: string;
  comment?: string;
}

export default function Bonuses() {
  const { currentUser } = useDashboardContext();
  const [activeTab, setActiveTab] = useState<'bonuses' | 'withdrawal'>('bonuses');
  const [withdrawals, setWithdrawals] = useState<WithdrawalItem[]>([]);
  const [balance, setBalance] = useState({
    available: currentUser.walletAvailable,
    totalEarned: currentUser.totalEarned,
    pending: 0,
    withdrawn: 0,
  });
  const [bonuses, setBonuses] = useState({ referral: 0, binary: 0, status: 0, cashback: 0, deposit: 0, bonusX2: 0 });
  const [structure, setStructure] = useState({ leftPV: 0, rightPV: 0, weakLeg: 'left' });
  const [statuses, setStatuses] = useState<Status[]>([]);
  const [withdrawalAmount, setWithdrawalAmount] = useState(50000);
  const [withdrawalMethod, setWithdrawalMethod] = useState('card_account');
  const [isSubmittingWithdrawal, setIsSubmittingWithdrawal] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const loadBonusData = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const [withdrawalsResponse, bonusesResponse, overviewResponse, statusItems] = await Promise.all([
        getDashboardWithdrawals(),
        getDashboardBonuses(),
        getDashboardOverview(),
        getPublicStatuses(),
      ]);

      setWithdrawals(normalizeWithdrawals(withdrawalsResponse));
      setStatuses(statusItems);

      const response = bonusesResponse;
      const record = response && typeof response === 'object' ? response as Record<string, unknown> : {};
      const summary = record.summary && typeof record.summary === 'object' ? record.summary as Record<string, unknown> : {};
      const byType = summary.by_type && typeof summary.by_type === 'object' ? summary.by_type as Record<string, unknown> : {};
      const overview = overviewResponse;
      const overviewRecord = overview && typeof overview === 'object' ? overview as Record<string, unknown> : {};
      const wallets = Array.isArray(overviewRecord.wallets) ? overviewRecord.wallets : [];
      const depositWallet = wallets.find((wallet) => isRecord(wallet) && getString(wallet, ['type']) === 'deposit');

      setBonuses({
        referral: getNumber(byType, ['referral']) ?? 0,
        binary: getNumber(byType, ['binary']) ?? 0,
        status: getNumber(byType, ['status']) ?? 0,
        cashback: getNumber(byType, ['cashback']) ?? 0,
        deposit: isRecord(depositWallet) ? getNumber(depositWallet, ['balance', 'available']) ?? 0 : 0,
        bonusX2: getNumber(byType, ['bonus_x2', 'x2']) ?? 0,
      });

      const balances = overviewRecord.balances && typeof overviewRecord.balances === 'object' ? overviewRecord.balances as Record<string, unknown> : {};
      const structureRecord = overviewRecord.structure && typeof overviewRecord.structure === 'object' ? overviewRecord.structure as Record<string, unknown> : {};
      setBalance({
        available: getNumber(balances, ['available']) ?? currentUser.walletAvailable,
        totalEarned: getNumber(balances, ['total_earned']) ?? currentUser.totalEarned,
        pending: getNumber(balances, ['pending_withdrawals']) ?? 0,
        withdrawn: getNumber(balances, ['withdrawn']) ?? 0,
      });
      setStructure({
        leftPV: getNumber(structureRecord, ['left_pv']) ?? 0,
        rightPV: getNumber(structureRecord, ['right_pv']) ?? 0,
        weakLeg: getString(structureRecord, ['weak_leg']) || 'left',
      });
    } catch (caughtError) {
      setWithdrawals([]);
      setStatuses([]);
      setBonuses({ referral: 0, binary: 0, status: 0, cashback: 0, deposit: 0, bonusX2: 0 });
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadBonusData();
  }, [loadBonusData]);

  const nextStatus = statuses.find((status) => status.pv > currentUser.personalPV);
  const statusProgressTotal = nextStatus?.pv || Math.max(currentUser.personalPV, 1);

  const submitWithdrawal = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmittingWithdrawal(true);
    setMessage('');
    setError('');

    try {
      await createDashboardWithdrawal({
        amount: withdrawalAmount,
        method: withdrawalMethod,
      });
      setMessage('Заявка на вывод отправлена.');
      await loadBonusData();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось отправить заявку на вывод.');
      }
    } finally {
      setIsSubmittingWithdrawal(false);
    }
  };

  return (
    <div className="space-y-8">
      <section className="flex flex-col gap-6 rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:flex-row md:items-end md:justify-between md:p-8">
        <div>
          <span className="safi-kicker">Finance</span>
          <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Бонусы и вывод</h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
            Кошелек, бонусы, бинарный расчет и заявки на вывод средств.
          </p>
        </div>
        <div className="flex rounded-full border border-safi-border bg-safi-cream p-1">
          <TabButton active={activeTab === 'bonuses'} onClick={() => setActiveTab('bonuses')}>Бонусы</TabButton>
          <TabButton active={activeTab === 'withdrawal'} onClick={() => setActiveTab('withdrawal')}>Вывод</TabButton>
        </div>
      </section>

      {(message || error) && (
        <div className={`rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {error || message}
        </div>
      )}

      {isLoading && (
        <LoadingState title="Загружаем бонусы" description="Получаем кошелек, бонусы и заявки на вывод из API." />
      )}

      {!isLoading && loadError && (
        <ErrorState description={loadError} onRetry={loadBonusData} />
      )}

      {!isLoading && !loadError && activeTab === 'bonuses' && (
        <div className="space-y-8">
          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard title="Доступно" value={`${balance.available.toLocaleString('ru-RU')} ₸`} icon={<Wallet className="h-5 w-5" />} variant="primary" />
            <StatCard title="Всего заработано" value={`${balance.totalEarned.toLocaleString('ru-RU')} ₸`} />
            <StatCard title="Ожидает" value={`${balance.pending.toLocaleString('ru-RU')} ₸`} />
            <StatCard title="Выведено" value={`${balance.withdrawn.toLocaleString('ru-RU')} ₸`} />
          </section>

          <section className="grid gap-5 md:grid-cols-2 xl:grid-cols-6">
            <BonusMiniCard title="Реферальные" amount={bonuses.referral} />
            <BonusMiniCard title="Бинарные" amount={bonuses.binary} />
            <BonusMiniCard title="Статусные" amount={bonuses.status} />
            <BonusMiniCard title="Кэшбэк" amount={bonuses.cashback} />
            <BonusMiniCard title="Депозит" amount={bonuses.deposit} />
            <BonusMiniCard title="Bonus X2" amount={bonuses.bonusX2} />
          </section>

          {Object.values(bonuses).every((amount) => amount === 0) && (
            <EmptyState
              title="Бонусов пока нет"
              description="Начисления появятся после продаж, структуры или других бонусных операций."
            />
          )}

          <section className="grid gap-8 lg:grid-cols-2">
            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Реферальный бонус</h2>
              <div className="mt-6 space-y-4">
                <DetailRow label="Пакет" value={currentUser.packageName} badge />
                <DetailRow label="Текущий процент" value="10%" highlight />
                <DetailRow label="Приглашено лично" value={`${currentUser.referralsCount.toLocaleString('ru-RU')} партнеров`} />
              </div>
            </article>

            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Бинарный бонус</h2>
              <p className="mt-2 text-sm leading-7 text-safi-muted">
                Расчет по меньшей ветке выполняет администратор раз в 15 дней.
              </p>
              <div className="mt-6 space-y-4">
                <DetailRow label="Левая ветка" value={`${structure.leftPV.toLocaleString('ru-RU')} PV`} />
                <DetailRow label="Правая ветка" value={`${structure.rightPV.toLocaleString('ru-RU')} PV`} />
                <DetailRow label="Расчетная ветка" value={structure.weakLeg} badge />
                <DetailRow label="Начислено" value={`${bonuses.binary.toLocaleString('ru-RU')} ₸`} highlight />
              </div>
            </article>
          </section>

          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Статусный бонус</h2>
            <div className="mt-6 grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
              <div className="space-y-4">
                <DetailRow label="Текущий статус" value={currentUser.status} badge />
                <DetailRow label="Следующий статус" value={nextStatus?.name || currentUser.status} />
                <DetailRow label="Ваш PV" value={`${currentUser.personalPV.toLocaleString('ru-RU')} PV`} highlight />
              </div>
              <div className="rounded-3xl border border-safi-border bg-safi-cream p-6">
                <ProgressBar label={`${currentUser.status} -> ${nextStatus?.name || currentUser.status}`} current={currentUser.personalPV} total={statusProgressTotal} />
              </div>
            </div>
          </article>
        </div>
      )}

      {!isLoading && !loadError && activeTab === 'withdrawal' && (
        <div className="space-y-8">
          <section className="grid gap-8 lg:grid-cols-[1.25fr_0.75fr]">
            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
              <h2 className="mb-7 flex items-center gap-3 font-serif text-3xl font-semibold text-safi-green">
                <Wallet className="h-6 w-6 text-safi-gold" />
                Заявка на вывод
              </h2>

              <form className="space-y-6" onSubmit={submitWithdrawal}>
                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Сумма вывода</span>
                  <input
                    type="number"
                    min="10000"
                    value={withdrawalAmount}
                    onChange={(event) => setWithdrawalAmount(Number(event.target.value))}
                    className="w-full rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-xl font-extrabold text-safi-green outline-none focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25"
                  />
                  <span className="mt-2 block text-xs font-bold text-safi-muted">Доступно: {currentUser.walletAvailable.toLocaleString('ru-RU')} ₸</span>
                </label>

                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Способ вывода</span>
                  <select
                    value={withdrawalMethod}
                    onChange={(event) => setWithdrawalMethod(event.target.value)}
                    className="w-full rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25"
                  >
                    <option value="card_account">Карта партнера</option>
                    <option value="ip_account">Счет ИП</option>
                  </select>
                </label>

                <button
                  type="submit"
                  disabled={isSubmittingWithdrawal}
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-5 py-4 text-xs font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] transition-colors hover:bg-safi-green-hover disabled:opacity-60"
                >
                  <ArrowUpCircle className="h-5 w-5" />
                  {isSubmittingWithdrawal ? 'Отправляем...' : 'Отправить заявку'}
                </button>
              </form>
            </article>

            <aside className="rounded-[32px] border border-safi-green bg-safi-green p-7 text-white shadow-[0_18px_48px_rgba(11,23,18,0.10)]">
              <div className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-white/60">Доступно к выводу</div>
              <div className="mt-3 font-serif text-5xl font-semibold text-safi-gold">{currentUser.walletAvailable.toLocaleString('ru-RU')} ₸</div>
              <div className="mt-8 flex gap-3 rounded-3xl border border-white/10 bg-white/[0.08] p-4 text-sm leading-6 text-white/75">
                <Info className="mt-1 h-5 w-5 shrink-0 text-safi-gold" />
                <p>Заявки проверяются администратором перед выплатой. Плановый период выплат - каждые 14 дней.</p>
              </div>
            </aside>
          </section>

          <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="border-b border-safi-border bg-safi-cream p-6 md:p-7">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">История выводов</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[760px] text-left">
                <thead className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                  <tr>
                    <th className="px-7 py-4">Заявка / дата</th>
                    <th className="px-7 py-4">Сумма</th>
                    <th className="px-7 py-4">Способ</th>
                    <th className="px-7 py-4">Статус</th>
                    <th className="px-7 py-4">Дата выплаты</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-safi-border text-sm">
                  {withdrawals.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-7 py-8">
                        <EmptyState
                          title="Заявок на вывод пока нет"
                          description="История появится после первой заявки на вывод."
                          className="min-h-[180px] shadow-none"
                        />
                      </td>
                    </tr>
                  )}

                  {withdrawals.map((withdrawal) => (
                    <tr key={withdrawal.id} className="transition-colors hover:bg-safi-cream/70">
                      <td className="px-7 py-5">
                        <div className="font-extrabold text-safi-green">#{withdrawal.id}</div>
                        <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{withdrawal.date}</div>
                      </td>
                      <td className="px-7 py-5 font-extrabold text-safi-green">{withdrawal.amount}</td>
                      <td className="px-7 py-5 text-safi-muted">{withdrawal.method}</td>
                      <td className="px-7 py-5">
                        <Badge variant={withdrawal.status === 'Выплачено' ? 'success' : withdrawal.status === 'Отклонено' ? 'danger' : 'warning'}>
                          {withdrawal.status}
                        </Badge>
                      </td>
                      <td className="px-7 py-5 text-safi-muted">{withdrawal.paymentDate}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      )}
    </div>
  );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-colors ${active ? 'bg-white text-safi-green shadow-sm' : 'text-safi-muted hover:text-safi-green'}`}
    >
      {children}
    </button>
  );
}

function BonusMiniCard({ title, amount }: { title: string; amount: number }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-5 text-center shadow-[0_18px_48px_rgba(11,23,18,0.04)]">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</div>
      <div className="mt-3 font-serif text-2xl font-semibold text-safi-green">{amount > 0 ? `${amount.toLocaleString('ru-RU')} ₸` : '-'}</div>
    </article>
  );
}

function DetailRow({ label, value, highlight, badge }: { label: string; value: string; highlight?: boolean; badge?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-5 border-b border-safi-border py-3 last:border-b-0">
      <span className="text-sm font-bold text-safi-muted">{label}</span>
      {badge ? (
        <Badge variant={highlight ? 'gold' : 'default'}>{value}</Badge>
      ) : (
        <span className={cn('text-right text-sm font-extrabold', highlight ? 'text-safi-gold' : 'text-safi-green')}>{value}</span>
      )}
    </div>
  );
}

function normalizeWithdrawals(response: unknown): WithdrawalItem[] {
  const list = getArray(response);

  return list.map((item, index) => {
    const record = isRecord(item) ? item : {};

    return {
      id: getString(record, ['id', 'uuid', 'number']) || `W-${index + 1}`,
      date: getString(record, ['date', 'created_at', 'createdAt']) || '-',
      amount: formatAmount(record.amount ?? record.sum),
      method: methodLabel(getString(record, ['method', 'payment_method', 'paymentMethod'])),
      status: getString(record, ['status']) || 'В обработке',
      paymentDate: getString(record, ['payment_date', 'paymentDate', 'paid_at']) || '-',
      comment: getString(record, ['comment']),
    };
  });
}

function getArray(response: unknown) {
  if (Array.isArray(response)) {
    return response;
  }

  if (isRecord(response)) {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (isRecord(response.data) && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    if (Array.isArray(response.withdrawals)) {
      return response.withdrawals;
    }
  }

  return [];
}

function formatAmount(value: unknown) {
  if (typeof value === 'number') {
    return `${value.toLocaleString('ru-RU')} тг`;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    return value.includes('тг') || value.includes('₸') ? value : `${value} тг`;
  }

  return '0 тг';
}

function methodLabel(method?: string) {
  if (method === 'ip_account') {
    return 'Счет ИП';
  }

  if (method === 'card_account') {
    return 'Карта партнера';
  }

  return method || 'Карта партнера';
}

function getString(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
