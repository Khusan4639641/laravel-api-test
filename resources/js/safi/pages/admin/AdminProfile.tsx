import { Mail, Shield, User } from 'lucide-react';
import { useAdminContext } from '../../components/admin/AdminLayout';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function AdminProfile() {
  const { currentUser } = useAdminContext();

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <section className="flex flex-col gap-5 rounded-[28px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:flex-row md:items-end md:justify-between">
        <div>
          <span className="safi-kicker">Profile</span>
          <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green">Профиль</h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">{currentUser.email || currentUser.role}</p>
        </div>
        <div className="inline-flex items-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-green">
          <Shield className="h-4 w-4 text-safi-gold" />
          {roleLabel(currentUser.role)}
        </div>
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.34fr_0.66fr]">
        <article className="rounded-[28px] border border-safi-border bg-white p-7 text-center shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <div className="mx-auto flex h-28 w-28 items-center justify-center rounded-full border-4 border-white bg-safi-green font-serif text-5xl font-semibold text-safi-gold shadow-[0_18px_48px_rgba(11,23,18,0.14)]">
            {currentUser.name.charAt(0)}
          </div>
          <h2 className="mt-6 font-serif text-3xl font-semibold text-safi-green">{currentUser.name}</h2>
          <div className="mt-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-muted">{roleLabel(currentUser.role)}</div>
        </article>

        <article className="rounded-[28px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          <h2 className="mb-6 flex items-center gap-3 font-serif text-2xl font-semibold text-safi-green">
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
              <User className="h-5 w-5" />
            </span>
            Данные аккаунта
          </h2>
          <div className="grid gap-5 md:grid-cols-2">
            <ConfigInput label="Имя" value={currentUser.name} />
            <ConfigInput label="Роль" value={roleLabel(currentUser.role)} />
            <label className="block md:col-span-2">
              <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Email</span>
              <div className="relative">
                <Mail className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
                <input className={`${inputClass} pl-11`} value={currentUser.email || ''} readOnly />
              </div>
            </label>
          </div>
        </article>
      </section>
    </div>
  );
}

function ConfigInput({ label, value }: { label: string; value: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      <input className={inputClass} value={value} readOnly />
    </label>
  );
}

function roleLabel(role: string) {
  if (role === 'super_admin') {
    return 'Super Admin';
  }

  if (role === 'support') {
    return 'Support';
  }

  return 'Пользователь';
}
