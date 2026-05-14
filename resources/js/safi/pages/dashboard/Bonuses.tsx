import { FormEvent, useEffect, useState } from 'react';
import { ArrowUpCircle, Calculator, CheckCircle2, Info, Wallet } from 'lucide-react';
import { balance, bonuses, structure, withdrawals as mockWithdrawals } from '../../data/dashboardMock';
import { Badge, ProgressBar, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { ApiError, calculateBinaryBonus, createWithdrawal, getWithdrawals } from '../../lib/api';
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
  const [withdrawals, setWithdrawals] = useState<WithdrawalItem[]>(mockWithdrawals);
  const [withdrawalAmount, setWithdrawalAmount] = useState(50000);
  const [withdrawalMethod, setWithdrawalMethod] = useState('card');
  const [binaryResult, setBinaryResult] = useState<number | null>(null);
  const [isSubmittingWithdrawal, setIsSubmittingWithdrawal] = useState(false);
  const [isCalculating, setIsCalculating] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let isMounted = true;

    async function loadWithdrawals() {
      try {
        const response = await getWithdrawals();
        const apiWithdrawals = normalizeWithdrawals(response);

        if (isMounted && apiWithdrawals.length > 0) {
          setWithdrawals(apiWithdrawals);
        }
      } catch {
        if (isMounted) {
          setWithdrawals(mockWithdrawals);
        }
      }
    }

    void loadWithdrawals();

    return () => {
      isMounted = false;
    };
  }, []);

  const submitWithdrawal = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmittingWithdrawal(true);
    setMessage('');
    setError('');

    try {
      await createWithdrawal({
        amount: withdrawalAmount,
        method: withdrawalMethod,
      });
      setMessage('Заявка на вывод отправлена.');
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

  const calculateBinary = async () => {
    setIsCalculating(true);
    setMessage('');
    setError('');

    try {
      const response = await calculateBinaryBonus({
        left_volume: structure.leftPV,
        right_volume: structure.rightPV,
        package_id: currentUser.packageName,
      });
      setBinaryResult(extractAmount(response) ?? bonuses.binary);
      setMessage('Бинарный расчет обновлен.');
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось пересчитать бинарный бонус.');
      }
      setBinaryResult(bonuses.binary);
    } finally {
      setIsCalculating(false);
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

      {activeTab === 'bonuses' && (
        <div className="space-y-8">
          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard title="Доступно" value={`${currentUser.walletAvailable.toLocaleString('ru-RU')} ₸`} icon={<Wallet className="h-5 w-5" />} variant="primary" />
            <StatCard title="Всего заработано" value={`${currentUser.totalEarned.toLocaleString('ru-RU')} ₸`} />
            <StatCard title="Ожидает" value={`${balance.pending.toLocaleString('ru-RU')} ₸`} />
            <StatCard title="Выведено" value={`${balance.withdrawn.toLocaleString('ru-RU')} ₸`} />
          </section>

          <section className="grid gap-5 md:grid-cols-2 xl:grid-cols-6">
            <BonusMiniCard title="Реферальные" amount={bonuses.referral} />
            <BonusMiniCard title="Бинарные" amount={binaryResult ?? bonuses.binary} />
            <BonusMiniCard title="Статусные" amount={bonuses.status} />
            <BonusMiniCard title="Кэшбэк" amount={bonuses.cashback} />
            <BonusMiniCard title="Депозит" amount={bonuses.deposit} />
            <BonusMiniCard title="Bonus X2" amount={bonuses.bonusX2} />
          </section>

          <section className="grid gap-8 lg:grid-cols-2">
            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Реферальный бонус</h2>
              <div className="mt-6 space-y-4">
                <DetailRow label="Пакет" value={currentUser.packageName} badge />
                <DetailRow label="Текущий процент" value="10%" highlight />
                <DetailRow label="Приглашено лично" value="14 партнеров" />
                <DetailRow label="Активных партнеров" value="11" />
              </div>
            </article>

            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
              <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                  <h2 className="font-serif text-3xl font-semibold text-safi-green">Бинарный бонус</h2>
                  <p className="mt-2 text-sm leading-7 text-safi-muted">Расчет по меньшей ветке структуры.</p>
                </div>
                <button
                  type="button"
                  onClick={calculateBinary}
                  disabled={isCalculating}
                  className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white disabled:opacity-60"
                >
                  <Calculator className="h-4 w-4" />
                  {isCalculating ? 'Считаем...' : 'Пересчитать'}
                </button>
              </div>
              <div className="mt-6 space-y-4">
                <DetailRow label="Левая ветка" value={`${structure.leftPV.toLocaleString('ru-RU')} PV`} />
                <DetailRow label="Правая ветка" value={`${structure.rightPV.toLocaleString('ru-RU')} PV`} />
                <DetailRow label="Расчетная ветка" value={structure.weakLeg} badge />
                <DetailRow label="Начислено" value={`${(binaryResult ?? bonuses.binary).toLocaleString('ru-RU')} ₸`} highlight />
              </div>
            </article>
          </section>

          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Статусный бонус</h2>
            <div className="mt-6 grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
              <div className="space-y-4">
                <DetailRow label="Текущий статус" value={currentUser.status} badge />
                <DetailRow label="Следующий статус" value="Директор" />
                <DetailRow label="Ваш PV" value={`${currentUser.personalPV.toLocaleString('ru-RU')} PV`} highlight />
              </div>
              <div className="rounded-3xl border border-safi-border bg-safi-cream p-6">
                <ProgressBar label={`${currentUser.status} -> Директор`} current={currentUser.personalPV} total={5000} />
              </div>
            </div>
          </article>
        </div>
      )}

      {activeTab === 'withdrawal' && (
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
                    <option value="card">Карта партнера</option>
                    <option value="bank_account">Счет ИП</option>
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
                <p>Заявки проверяются администратором перед выплатой. История ниже показывает последние операции.</p>
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
      method: getString(record, ['method', 'payment_method', 'paymentMethod']) || 'Карта партнера',
      status: getString(record, ['status']) || 'В обработке',
      paymentDate: getString(record, ['payment_date', 'paymentDate', 'paid_at']) || '-',
      comment: getString(record, ['comment']),
    };
  });
}

function extractAmount(response: unknown) {
  if (!isRecord(response)) {
    return undefined;
  }

  const record = isRecord(response.data) ? response.data : response;
  const value = record.amount ?? record.total ?? record.binary_bonus ?? record.binaryBonus;

  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/\s/g, ''));
    return Number.isFinite(parsed) ? parsed : undefined;
  }

  return undefined;
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
