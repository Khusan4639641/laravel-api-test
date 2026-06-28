import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, ArrowUpCircle, ArrowUpRight, Copy, HelpCircle, ShoppingBag, Users, Wallet } from 'lucide-react';
import { Badge, ProgressBar, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { cn } from '../../lib/utils';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getArray, getDashboardOverview, getNumber, getPublicStatuses, getString, Status } from '../../lib/api';
import { features } from '../../config/features';
import { transactionStatusLabel, transactionTypeLabel } from '../../lib/systemLabels';
import { buildReferralBranchUrl, type ReferralBranch } from '../../lib/referrals';

interface TransactionItem {
  id: string;
  date: string;
  type: string;
  amount: string;
  status: string;
  source: string;
  comment: string;
}

export default function Overview() {
  const { currentUser } = useDashboardContext();
  const [copiedLink, setCopiedLink] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [structure, setStructure] = useState({ totalPartners: 0, leftPV: 0, rightPV: 0, weakLegPV: 0, weakLeg: 'left' });
  const [balances, setBalances] = useState({ available: currentUser.walletAvailable, totalEarned: currentUser.totalEarned, pendingBinary: 0 });
  const [bonusesSummary, setBonusesSummary] = useState({ total: currentUser.bonusesTotal, referral: 0, binary: 0, cashback: 0 });
  const [ordersSummary, setOrdersSummary] = useState({ total: 0, pending: 0, totalAmount: 0, totalPV: 0 });
  const [withdrawalsSummary, setWithdrawalsSummary] = useState({ total: 0, pending: 0, approved: 0, pendingAmount: 0 });
  const [transactions, setTransactions] = useState<TransactionItem[]>([]);
  const [statuses, setStatuses] = useState<Status[]>([]);
  const [canInvite, setCanInvite] = useState(currentUser.canInvite);
  const [referralLinks, setReferralLinks] = useState<Record<ReferralBranch, string>>({
    left: currentUser.canInvite ? buildReferralBranchUrl(currentUser.referralCode, 'left') : '',
    right: currentUser.canInvite ? buildReferralBranchUrl(currentUser.referralCode, 'right') : '',
  });
  const weakLegPV = structure.weakLegPV || Math.min(structure.leftPV, structure.rightPV);
  const nextStatus = statuses.find((status) => status.pv > weakLegPV);
  const statusTargetPV = nextStatus?.pv || statuses[statuses.length - 1]?.pv || Math.max(weakLegPV, 1);
  const statusProgressPercent = statusTargetPV > 0 ? Math.min(100, Math.max(0, (weakLegPV / statusTargetPV) * 100)) : 0;

  const loadOverview = React.useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const [response, statusItems] = await Promise.all([
        getDashboardOverview(),
        getPublicStatuses(),
      ]);
      const record = response && typeof response === 'object' ? response as Record<string, unknown> : {};
      const structureRecord = record.structure && typeof record.structure === 'object' ? record.structure as Record<string, unknown> : {};
      const balancesRecord = record.balances && typeof record.balances === 'object' ? record.balances as Record<string, unknown> : {};
      const bonusesRecord = record.bonuses_summary && typeof record.bonuses_summary === 'object' ? record.bonuses_summary as Record<string, unknown> : {};
      const bonusTypes = bonusesRecord.by_type && typeof bonusesRecord.by_type === 'object' ? bonusesRecord.by_type as Record<string, unknown> : {};
      const ordersRecord = record.orders_summary && typeof record.orders_summary === 'object' ? record.orders_summary as Record<string, unknown> : {};
      const withdrawalsRecord = record.withdrawals_summary && typeof record.withdrawals_summary === 'object' ? record.withdrawals_summary as Record<string, unknown> : {};
      const referralLinksRecord = record.referral_links && typeof record.referral_links === 'object' ? record.referral_links as Record<string, unknown> : {};
      const inviteAvailable = typeof record.can_invite === 'boolean' ? record.can_invite : currentUser.canInvite;
      const leftPV = getNumber(structureRecord, ['left_pv', 'leftPV', 'left_branch_pv', 'leftBranchPv']) ?? 0;
      const rightPV = getNumber(structureRecord, ['right_pv', 'rightPV', 'right_branch_pv', 'rightBranchPv']) ?? 0;

      setStatuses(statusItems);
      setCanInvite(inviteAvailable);
      setReferralLinks({
        left: inviteAvailable ? (getString(referralLinksRecord, ['left']) || buildReferralBranchUrl(currentUser.referralCode, 'left')) : '',
        right: inviteAvailable ? (getString(referralLinksRecord, ['right']) || buildReferralBranchUrl(currentUser.referralCode, 'right')) : '',
      });
      setStructure({
        totalPartners: getNumber(structureRecord, ['total_partners']) ?? 0,
        leftPV,
        rightPV,
        weakLegPV: getNumber(structureRecord, ['weak_leg_pv', 'weakLegPv', 'weak_leg_branch_pv', 'weakLegBranchPv']) ?? Math.min(leftPV, rightPV),
        weakLeg: getString(structureRecord, ['weak_leg']) || 'left',
      });
      setBalances({
        available: getNumber(balancesRecord, ['available']) ?? currentUser.walletAvailable,
        totalEarned: getNumber(balancesRecord, ['total_earned']) ?? currentUser.totalEarned,
        pendingBinary: getNumber(balancesRecord, ['pending_binary', 'pendingBinary']) ?? 0,
      });
      setBonusesSummary({
        total: getNumber(bonusesRecord, ['total']) ?? currentUser.bonusesTotal,
        referral: getNumber(bonusTypes, ['referral']) ?? 0,
        binary: getNumber(bonusTypes, ['binary']) ?? 0,
        cashback: getNumber(bonusTypes, ['cashback']) ?? 0,
      });
      setOrdersSummary({
        total: getNumber(ordersRecord, ['total']) ?? 0,
        pending: getNumber(ordersRecord, ['pending']) ?? 0,
        totalAmount: getNumber(ordersRecord, ['total_amount']) ?? 0,
        totalPV: getNumber(ordersRecord, ['total_pv']) ?? 0,
      });
      setWithdrawalsSummary({
        total: getNumber(withdrawalsRecord, ['total']) ?? 0,
        pending: getNumber(withdrawalsRecord, ['pending']) ?? 0,
        approved: getNumber(withdrawalsRecord, ['approved']) ?? 0,
        pendingAmount: getNumber(withdrawalsRecord, ['pending_amount']) ?? 0,
      });
      setTransactions(getArray(record.recent_transactions).map((item, index) => {
        const trx = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const amount = getNumber(trx, ['amount']) ?? 0;
        const direction = getString(trx, ['direction']) || 'credit';
        const typeCode = getString(trx, ['type']) || 'operation';
        const statusCode = getString(trx, ['status']) || '';
        return {
          id: getString(trx, ['id']) || String(index + 1),
          date: getString(trx, ['created_at']) || '',
          type: transactionTypeLabel(typeCode, getString(trx, ['type_label', 'typeLabel']) || typeCode || 'Операция'),
          amount: `${direction === 'credit' ? '+' : '-'}${amount.toLocaleString('ru-RU')} ₸`,
          status: transactionStatusLabel(statusCode, getString(trx, ['status_label', 'statusLabel']) || statusCode),
          source: getString(trx, ['description']) || 'Система',
          comment: getString(trx, ['description']) || '',
        };
      }));
    } catch (caughtError) {
      setStructure({ totalPartners: 0, leftPV: 0, rightPV: 0, weakLegPV: 0, weakLeg: 'left' });
      setCanInvite(false);
      setReferralLinks({ left: '', right: '' });
      setStatuses([]);
      setTransactions([]);
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, [currentUser.bonusesTotal, currentUser.canInvite, currentUser.referralCode, currentUser.totalEarned, currentUser.walletAvailable]);

  useEffect(() => {
    void loadOverview();
  }, [loadOverview]);

  const copyLink = async (branch: ReferralBranch) => {
    const link = referralLinks[branch];

    if (!link) {
      setCopiedLink('');
      return;
    }

    try {
      await navigator.clipboard.writeText(link);
      setCopiedLink(branch);
      window.setTimeout(() => setCopiedLink(''), 1600);
    } catch {
      setCopiedLink('');
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Dashboard</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">
              Добро пожаловать, <span className="italic text-safi-gold">{currentUser.name.split(' ')[0]}</span>
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted md:text-base">
              Основные показатели бизнеса, пакета, кошелька, PV и структуры.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">Пакет: {currentUser.packageName}</Badge>
            <Badge variant={currentUser.packageStatus === 'active' ? 'success' : 'default'}>Пакет: {currentUser.packageStatusLabel}</Badge>
            <Badge variant="default">Статус: {currentUser.status}</Badge>
          </div>
        </div>
      </section>

      {isLoading && (
        <LoadingState title="Загружаем сводку" description="Получаем данные кабинета из dashboard API." />
      )}

      {!isLoading && error && (
        <ErrorState description={error} onRetry={loadOverview} />
      )}

      {!isLoading && !error && (
        <>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard
          title="Доступно к выводу"
          value={`${balances.available.toLocaleString('ru-RU')} ₸`}
          icon={<Wallet className="h-5 w-5" />}
          variant="primary"
        />
        <StatCard
          title="Малая ветка PV"
          value={`${weakLegPV.toLocaleString('ru-RU')} PV`}
          icon={<Activity className="h-5 w-5" />}
        />
        <StatCard
          title="Левая ветка"
          value={`${structure.leftPV.toLocaleString('ru-RU')} PV`}
          trend={{ value: structure.weakLeg === 'left' ? 'малая' : 'активная', isPositive: structure.weakLeg !== 'left' }}
        />
        <StatCard
          title="Правая ветка"
          value={`${structure.rightPV.toLocaleString('ru-RU')} PV`}
          trend={{ value: structure.weakLeg === 'right' ? 'малая' : 'активная', isPositive: structure.weakLeg !== 'right' }}
        />
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard title="Бонусы всего" value={`${bonusesSummary.total.toLocaleString('ru-RU')} ₸`} icon={<Wallet className="h-5 w-5" />} variant="dark" />
        <StatCard title="Бинар в ожидании" value={`${balances.pendingBinary.toLocaleString('ru-RU')} ₸`} />
        <StatCard title="Заказы" value={ordersSummary.total} icon={<ShoppingBag className="h-5 w-5" />} trend={{ value: `${ordersSummary.totalPV.toLocaleString('ru-RU')} PV`, isPositive: true }} />
        <StatCard title="Выводы" value={withdrawalsSummary.total} icon={<ArrowUpCircle className="h-5 w-5" />} trend={{ value: `${withdrawalsSummary.pending} ожидает`, isPositive: withdrawalsSummary.pending === 0 }} />
        <StatCard title="Команда" value={structure.totalPartners} icon={<Users className="h-5 w-5" />} />
      </section>

      <section className="grid gap-8 lg:grid-cols-[1.45fr_0.95fr]">
        <div className="space-y-8">
          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
            <div className="mb-7 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="font-serif text-3xl font-semibold text-safi-green">Прогресс статуса</h2>
                <p className="mt-2 text-sm leading-7 text-safi-muted">Статус считается по малой ветке: min(левая, правая).</p>
              </div>
              <Link to="/dashboard/package-status" className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold hover:text-safi-green">
                Подробнее
              </Link>
            </div>

            <div className="mb-7 grid gap-4 md:grid-cols-3">
              <MiniMetric label="Малая ветка PV" value={`${weakLegPV.toLocaleString('ru-RU')} PV`} />
              <MiniMetric label="Следующий порог" value={`${statusTargetPV.toLocaleString('ru-RU')} PV`} />
              <MiniMetric label="Прогресс" value={`${statusProgressPercent.toFixed(0)}%`} />
            </div>

            <ProgressBar
              label={`${currentUser.status} -> ${nextStatus?.name || currentUser.status}`}
              current={weakLegPV}
              total={statusTargetPV}
              percentageOverride={statusProgressPercent}
            />
          </article>

          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
            <div className="mb-7 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="font-serif text-3xl font-semibold text-safi-green">Быстрые действия</h2>
                <p className="mt-2 text-sm leading-7 text-safi-muted">
                  {features.support ? 'Частые операции по кошельку и обращениям.' : 'Частые операции по кошельку.'}
                </p>
              </div>
            </div>

            <div className={cn('grid gap-3', features.support ? 'sm:grid-cols-2' : 'sm:grid-cols-1')}>
              <ActionButton icon={<ArrowUpCircle />} label="Вывод" to="/dashboard/bonuses" />
              {features.support && <ActionButton icon={<HelpCircle />} label="Поддержка" to="/dashboard/support" />}
            </div>

            <div className="mt-7 grid gap-4 xl:grid-cols-2">
              <ReferralLink
                label="Левая ветка"
                link={canInvite ? referralLinks.left : ''}
                copied={copiedLink === 'left'}
                onCopy={() => copyLink('left')}
              />
              <ReferralLink
                label="Правая ветка"
                link={canInvite ? referralLinks.right : ''}
                copied={copiedLink === 'right'}
                onCopy={() => copyLink('right')}
              />
            </div>
          </article>
        </div>

        <article className="rounded-[32px] border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-7">
          <div className="mb-6 flex items-center justify-between gap-4">
            <h2 className="font-serif text-2xl font-semibold text-safi-green">Последние транзакции</h2>
            <Link to="/dashboard/transactions" className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold hover:text-safi-green">
              Все
            </Link>
          </div>

          <div className="space-y-3">
            {transactions.length === 0 && (
              <EmptyState
                title="Транзакций пока нет"
                description="Начисления и списания появятся после первых операций."
                className="min-h-[180px] shadow-none"
              />
            )}

            {transactions.slice(0, 6).map((transaction) => {
              const isIncome = transaction.amount.startsWith('+');

              return (
                <div key={transaction.id} className="flex items-center justify-between gap-4 rounded-2xl bg-safi-cream p-4">
                  <div className="flex min-w-0 items-center gap-3">
                    <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', isIncome ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')}>
                      {isIncome ? <ArrowUpRight className="h-5 w-5" /> : <ArrowUpCircle className="h-5 w-5 rotate-180" />}
                    </div>
                    <div className="min-w-0">
                      <div className="truncate text-sm font-extrabold text-safi-green">{transaction.type}</div>
                      <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{transaction.date}</div>
                    </div>
                  </div>
                  <div className={cn('shrink-0 text-sm font-extrabold', isIncome ? 'text-green-700' : 'text-safi-muted')}>
                    {transaction.amount}
                  </div>
                </div>
              );
            })}
          </div>
        </article>
      </section>
        </>
      )}
    </div>
  );
}

function MiniMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-3xl border border-safi-border bg-safi-cream p-5">
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="mt-2 font-serif text-2xl font-semibold text-safi-green">{value}</div>
    </div>
  );
}

function ActionButton({ icon, label, onClick, to }: { icon: React.ReactNode; label: string; onClick?: () => void; to?: string }) {
  const content = (
    <>
      <div className="text-safi-green transition-colors group-hover:text-safi-gold">{icon}</div>
      <span className="mt-3 text-center text-[10px] font-extrabold uppercase tracking-[0.14em]">{label}</span>
    </>
  );

  if (to) {
    return (
      <Link to={to} className="group flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-3xl border border-safi-border bg-safi-cream p-4 text-safi-green transition-all hover:border-safi-green hover:bg-white">
        {content}
      </Link>
    );
  }

  return (
    <button type="button" onClick={onClick} className="group flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-3xl border border-safi-border bg-safi-cream p-4 text-safi-green transition-all hover:border-safi-green hover:bg-white">
      {content}
    </button>
  );
}

function ReferralLink({ label, link, copied, onCopy }: { label: string; link: string; copied: boolean; onCopy: () => void }) {
  return (
    <div className="rounded-3xl border border-safi-border bg-safi-cream p-5">
      <div className="mb-2 flex items-center justify-between gap-3">
        <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
        {copied && <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-green-700">Скопировано</span>}
      </div>
      {link ? (
        <>
          <div className="truncate font-mono text-xs text-safi-green">{link}</div>
          <button type="button" onClick={onCopy} className="mt-4 inline-flex cursor-pointer items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold transition-colors hover:text-safi-green">
            <Copy className="h-4 w-4" />
            Копировать
          </button>
        </>
      ) : (
        <div className="text-xs leading-5 text-safi-muted">Реферальные ссылки станут доступны после активации пакета.</div>
      )}
    </div>
  );
}
