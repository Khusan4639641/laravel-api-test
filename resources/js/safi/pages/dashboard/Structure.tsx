import { useMemo, useState } from 'react';
import { Copy, Filter, Search, Users } from 'lucide-react';
import { partners, structure } from '../../data/dashboardMock';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';

export default function Structure() {
  const { currentUser } = useDashboardContext();
  const [query, setQuery] = useState('');

  const visiblePartners = useMemo(() => {
    const normalizedQuery = query.toLowerCase().trim();

    if (!normalizedQuery) {
      return partners;
    }

    return partners.filter((partner) => `${partner.name} ${partner.id} ${partner.branch}`.toLowerCase().includes(normalizedQuery));
  }, [query]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Structure</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Моя структура</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Бинарная структура, реферальный код и список партнеров.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="gold">Пакет: {currentUser.packageName}</Badge>
            <Badge variant="default">Статус: {currentUser.status}</Badge>
          </div>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <StatCard title="Всего партнеров" value={structure.totalPartners} icon={<Users className="h-5 w-5" />} />
        <BranchCard title="Левая ветка" partners={structure.leftPartners} pv={structure.leftPV} weak={structure.weakLeg === 'Левая'} />
        <BranchCard title="Правая ветка" partners={structure.rightPartners} pv={structure.rightPV} weak={structure.weakLeg === 'Правая'} />
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">Реферальные ссылки</h2>
          <div className="mt-6 space-y-4">
            <ReferralBox label="Левая ветка" code={currentUser.referralCode} branch="left" />
            <ReferralBox label="Правая ветка" code={currentUser.referralCode} branch="right" />
          </div>
        </article>

        <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <h2 className="font-serif text-3xl font-semibold text-safi-green">Бинарное дерево</h2>
          <div className="mt-6 rounded-[28px] border border-dashed border-safi-border bg-safi-cream p-6">
            <div className="mx-auto max-w-lg">
              <Node name={currentUser.name} label={currentUser.partnerId} root />
              <div className="mx-auto h-8 w-px bg-safi-border" />
              <div className="grid grid-cols-2 gap-5">
                <Node name="Левая ветка" label={`${structure.leftPartners} партнеров`} />
                <Node name="Правая ветка" label={`${structure.rightPartners} партнеров`} />
              </div>
            </div>
          </div>
        </article>
      </section>

      <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="border-b border-safi-border p-6 md:p-7">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Список партнеров</h2>
            <div className="flex gap-3">
              <label className="relative min-w-0 flex-1 md:w-80 md:flex-none">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
                <input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Поиск по имени или ID"
                  className="w-full rounded-full border border-safi-border bg-safi-cream py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
                />
              </label>
              <button type="button" className="flex h-12 w-12 items-center justify-center rounded-full border border-safi-border bg-safi-cream text-safi-green">
                <Filter className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[860px] text-left">
            <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
              <tr>
                <th className="px-7 py-4">Партнер</th>
                <th className="px-7 py-4">Ветка / линия</th>
                <th className="px-7 py-4">Пакет / статус</th>
                <th className="px-7 py-4 text-right">PV</th>
                <th className="px-7 py-4 text-center">Активность</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-safi-border text-sm">
              {visiblePartners.map((partner) => (
                <tr key={partner.id} className="transition-colors hover:bg-safi-cream/70">
                  <td className="px-7 py-5">
                    <div className="font-extrabold text-safi-green">{partner.name}</div>
                    <div className="mt-1 font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{partner.id}</div>
                  </td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{partner.branch}</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.line} линия</div>
                  </td>
                  <td className="px-7 py-5">
                    <div className="font-bold text-safi-green">{partner.package}</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.status !== '-' ? partner.status : 'Участник'}</div>
                  </td>
                  <td className="px-7 py-5 text-right">
                    <div className="font-extrabold text-safi-gold">{partner.personalPV} л.PV</div>
                    <div className="mt-1 text-xs text-safi-muted">{partner.teamPV} к.PV</div>
                  </td>
                  <td className="px-7 py-5 text-center">
                    <Badge variant={partner.activity === 'Активен' ? 'success' : 'default'}>{partner.activity}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function BranchCard({ title, partners, pv, weak }: { title: string; partners: number; pv: number; weak: boolean }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</div>
        {weak && <Badge variant="warning">Слабая</Badge>}
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-green">{partners}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">Партнеров</div>
        </div>
        <div>
          <div className="font-serif text-3xl font-semibold text-safi-gold">{pv.toLocaleString('ru-RU')}</div>
          <div className="mt-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">PV</div>
        </div>
      </div>
    </article>
  );
}

function ReferralBox({ label, code, branch }: { label: string; code: string; branch: 'left' | 'right' }) {
  const link = `${window.location.origin}/register?ref=${code}&branch=${branch}`;

  return (
    <div className="rounded-3xl border border-safi-border bg-safi-cream p-5">
      <div className="mb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="truncate font-mono text-xs text-safi-green">{link}</div>
      <button
        type="button"
        onClick={() => navigator.clipboard.writeText(link)}
        className="mt-4 inline-flex items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold transition-colors hover:text-safi-green"
      >
        <Copy className="h-4 w-4" />
        Копировать
      </button>
    </div>
  );
}

function Node({ name, label, root = false }: { name: string; label: string; root?: boolean }) {
  return (
    <div className={`rounded-3xl border p-5 text-center ${root ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white text-safi-green'}`}>
      <div className={`font-serif text-xl font-semibold ${root ? 'text-white' : 'text-safi-green'}`}>{name}</div>
      <div className={`mt-2 text-[10px] font-extrabold uppercase tracking-[0.14em] ${root ? 'text-white/70' : 'text-safi-muted'}`}>{label}</div>
    </div>
  );
}
