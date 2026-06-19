import { ChangeEvent, ReactNode, useEffect, useRef, useState } from 'react';
import { Bell, Camera, CreditCard, Save, Shield, User } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { useDashboardContext } from '../../components/dashboard/DashboardLayout';
import { ApiError, uploadDashboardAvatar } from '../../lib/api';
import { ToastItem, ToastStack, ToastType } from '../../components/ui/Toast';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function Profile() {
  const { currentUser, refreshCurrentUser } = useDashboardContext();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [avatarPreview, setAvatarPreview] = useState(currentUser.avatarUrl || '');
  const [selectedAvatarFile, setSelectedAvatarFile] = useState<File | null>(null);
  const [previewObjectUrl, setPreviewObjectUrl] = useState('');
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  useEffect(() => {
    if (!selectedAvatarFile) {
      setAvatarPreview(currentUser.avatarUrl || '');
    }
  }, [currentUser.avatarUrl, selectedAvatarFile]);

  useEffect(() => () => {
    if (previewObjectUrl) {
      URL.revokeObjectURL(previewObjectUrl);
    }
  }, [previewObjectUrl]);

  const showToast = (message: string, type: ToastType = 'success') => {
    const toast = { id: Date.now() + Math.floor(Math.random() * 1000), message, type };
    setToasts((current) => [...current, toast]);
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== toast.id)), 3500);
  };

  const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    if (previewObjectUrl) {
      URL.revokeObjectURL(previewObjectUrl);
    }

    const localPreview = URL.createObjectURL(file);
    setSelectedAvatarFile(file);
    setPreviewObjectUrl(localPreview);
    setAvatarPreview(localPreview);
    event.target.value = '';
  };

  const handleSaveProfile = async () => {
    setIsSavingProfile(true);

    try {
      if (selectedAvatarFile) {
        const response = await uploadDashboardAvatar(selectedAvatarFile);
        const uploadedAvatarUrl = getAvatarUrlFromResponse(response);

        if (uploadedAvatarUrl) {
          setAvatarPreview(uploadedAvatarUrl);
        }
      }

      await refreshCurrentUser();
      setSelectedAvatarFile(null);

      if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        setPreviewObjectUrl('');
      }

      showToast(selectedAvatarFile ? 'Фото профиля обновлено' : 'Профиль сохранён');
    } catch (caughtError) {
      setAvatarPreview(currentUser.avatarUrl || '');
      const message = caughtError instanceof ApiError
        ? caughtError.message
        : 'Не удалось сохранить профиль.';
      showToast(message, 'error');
    } finally {
      setIsSavingProfile(false);
    }
  };

  return (
    <div className="space-y-8">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />

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
          disabled={isSavingProfile}
          onClick={() => void handleSaveProfile()}
          className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-6 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] transition-colors hover:bg-safi-green-hover disabled:cursor-not-allowed disabled:opacity-60"
        >
          <Save className="h-4 w-4" />
          {isSavingProfile ? 'Сохраняем...' : 'Сохранить'}
        </button>
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.36fr_0.64fr]">
        <aside className="space-y-8">
          <article className="rounded-[32px] border border-safi-border bg-white p-7 text-center shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="relative mx-auto mb-6 h-32 w-32">
              {avatarPreview ? (
                <img
                  src={avatarPreview}
                  alt={currentUser.name}
                  className="h-32 w-32 rounded-full border-4 border-white object-cover shadow-[0_18px_48px_rgba(11,23,18,0.14)]"
                />
              ) : (
                <div className="flex h-32 w-32 items-center justify-center rounded-full border-4 border-white bg-safi-green font-serif text-5xl font-semibold text-safi-gold shadow-[0_18px_48px_rgba(11,23,18,0.14)]">
                  {initials(currentUser.name)}
                </div>
              )}
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={handleAvatarChange}
              />
              <button
                type="button"
                disabled={isSavingProfile}
                onClick={() => fileInputRef.current?.click()}
                className="absolute bottom-0 right-0 flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border-4 border-white bg-safi-cream text-safi-green shadow-sm transition-colors hover:bg-safi-green hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                aria-label="Загрузить фото профиля"
              >
                <Camera className="h-4 w-4" />
              </button>
            </div>

            <h2 className="font-serif text-3xl font-semibold text-safi-green">{currentUser.name}</h2>
            <div className="mt-3 font-mono text-xs font-bold uppercase tracking-[0.14em] text-safi-muted">{currentUser.partnerId}</div>
            <div className="mt-6 flex flex-wrap justify-center gap-2">
              <Badge variant="gold">{currentUser.packageName}</Badge>
              <Badge variant={currentUser.packageStatus === 'active' ? 'success' : 'default'}>Пакет: {currentUser.packageStatusLabel}</Badge>
              <Badge variant="default">{currentUser.status}</Badge>
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

function Panel({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
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

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'S';
}

function getAvatarUrlFromResponse(response: unknown) {
  if (!response || typeof response !== 'object' || Array.isArray(response)) {
    return '';
  }

  const record = response as Record<string, unknown>;

  if (typeof record.avatar_url === 'string') {
    return record.avatar_url;
  }

  const user = record.user;

  if (user && typeof user === 'object' && !Array.isArray(user)) {
    const userRecord = user as Record<string, unknown>;

    if (typeof userRecord.avatar_url === 'string') {
      return userRecord.avatar_url;
    }
  }

  return '';
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
