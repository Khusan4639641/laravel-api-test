import { Bell, Camera, CreditCard, Save, Shield, User } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function Profile() {
  const { currentUser, isUsingMockUser } = useDashboardContext();

  return (
    <div className="space-y-8">
      <section className="flex flex-col gap-5 rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:flex-row md:items-end md:justify-between md:p-8">
        <div>
          <span className="safi-kicker">Profile</span>
          <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Профиль партнера</h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
            Управляйте личными, платежными и контактными данными партнера.
          </p>
        </div>
        <button
          type="button"
          className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-6 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)]"
        >
          <Save className="h-4 w-4" />
          Сохранить
        </button>
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.36fr_0.64fr]">
        <aside className="space-y-8">
          <article className="rounded-[32px] border border-safi-border bg-white p-7 text-center shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="relative mx-auto mb-6 h-32 w-32">
              <div className="flex h-32 w-32 items-center justify-center rounded-full border-4 border-white bg-safi-green font-serif text-5xl font-semibold text-safi-gold shadow-[0_18px_48px_rgba(11,23,18,0.14)]">
                {currentUser.name.charAt(0)}
              </div>
              <button type="button" className="absolute bottom-0 right-0 flex h-11 w-11 items-center justify-center rounded-full border-4 border-white bg-safi-cream text-safi-green shadow-sm transition-colors hover:bg-safi-green hover:text-white">
                <Camera className="h-4 w-4" />
              </button>
            </div>

            <h2 className="font-serif text-3xl font-semibold text-safi-green">{currentUser.name}</h2>
            <div className="mt-3 font-mono text-xs font-bold uppercase tracking-[0.14em] text-safi-muted">{currentUser.partnerId}</div>
            <div className="mt-6 flex flex-wrap justify-center gap-2">
              <Badge variant="gold">{currentUser.packageName}</Badge>
              <Badge variant="default">{currentUser.status}</Badge>
              {isUsingMockUser && <Badge variant="warning">Mock</Badge>}
            </div>
          </article>

          <article className="rounded-[32px] border border-safi-border bg-safi-cream p-6">
            <ProfileRow label="Спонсор" value={currentUser.sponsor} />
            <ProfileRow label="Регистрация" value={currentUser.registrationDate} />
            <ProfileRow label="Код приглашения" value={currentUser.referralCode} />
          </article>
        </aside>

        <div className="space-y-8">
          <Panel icon={<User className="h-5 w-5" />} title="Личные данные">
            <div className="grid gap-5 md:grid-cols-2">
              <ConfigInput label="ФИО" defaultValue={currentUser.name} />
              <ConfigInput label="Логин" defaultValue={currentUser.login || ''} />
              <ConfigInput label="Email" defaultValue={currentUser.email || ''} type="email" />
              <ConfigInput label="Partner ID" defaultValue={currentUser.partnerId} />
              <ConfigInput label="Пакет" defaultValue={currentUser.packageName} />
              <ConfigInput label="Статус" defaultValue={currentUser.status} />
            </div>
          </Panel>

          <Panel icon={<CreditCard className="h-5 w-5" />} title="Платежные данные">
            <div className="grid gap-5 md:grid-cols-2">
              <div className="md:col-span-2">
                <label className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Способ выплаты</label>
                <select className={inputClass}>
                  <option>Банковская карта (KZT)</option>
                  <option>Счет ИП</option>
                </select>
              </div>
              <ConfigInput label="Номер карты" placeholder="0000 0000 0000 0000" />
              <ConfigInput label="Банк" placeholder="Kaspi Bank" />
              <ConfigInput label="Имя получателя" placeholder="NAME SURNAME" />
              <ConfigInput label="ИИН / БИН" placeholder="000000000000" />
            </div>
          </Panel>

          <div className="grid gap-8 md:grid-cols-2">
            <Panel icon={<Shield className="h-5 w-5" />} title="Безопасность">
              <div className="space-y-4">
                <ConfigInput label="Текущий пароль" type="password" placeholder="********" />
                <ConfigInput label="Новый пароль" type="password" placeholder="********" />
                <button type="button" className="w-full rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white">
                  Изменить пароль
                </button>
              </div>
            </Panel>

            <Panel icon={<Bell className="h-5 w-5" />} title="Уведомления">
              <div className="space-y-3">
                <ToggleRow label="Бонусы" active />
                <ToggleRow label="Новые партнеры" active />
                <ToggleRow label="Статус выплат" active />
                <ToggleRow label="Новости компании" />
              </div>
            </Panel>
          </div>
        </div>
      </section>
    </div>
  );
}

function Panel({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <h2 className="mb-6 flex items-center gap-3 font-serif text-2xl font-semibold text-safi-green">
        <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">{icon}</span>
        {title}
      </h2>
      {children}
    </article>
  );
}

function ConfigInput({ label, defaultValue, type = 'text', placeholder }: { label: string; defaultValue?: string; type?: string; placeholder?: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      <input type={type} defaultValue={defaultValue} placeholder={placeholder} className={inputClass} />
    </label>
  );
}

function ProfileRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-safi-border py-4 last:border-b-0">
      <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      <span className="text-right text-sm font-extrabold text-safi-green">{value}</span>
    </div>
  );
}

function ToggleRow({ label, active = false }: { label: string; active?: boolean }) {
  return (
    <div className="flex items-center justify-between rounded-2xl bg-safi-cream p-3">
      <span className="text-sm font-bold text-safi-green">{label}</span>
      <span className={`relative h-6 w-11 rounded-full transition-colors ${active ? 'bg-safi-green' : 'bg-safi-muted/25'}`}>
        <span className={`absolute top-1 h-4 w-4 rounded-full bg-white transition-transform ${active ? 'translate-x-6' : 'translate-x-1'}`} />
      </span>
    </div>
  );
}
