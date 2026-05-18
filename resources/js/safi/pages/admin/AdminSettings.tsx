import { useEffect, useState } from 'react';
import { Settings, Save } from 'lucide-react';
import { ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminSettings, getApiErrorState, updateAdminSettings } from '../../lib/api';

interface SettingsState {
  companyName: string;
  minimumWithdrawal: string;
  card: boolean;
  businessAccount: boolean;
  usdt: boolean;
  contacts: string;
  supportEmail: string;
  supportPhone: string;
}

const defaultSettings: SettingsState = {
  companyName: 'Safi Life',
  minimumWithdrawal: '10000',
  card: true,
  businessAccount: true,
  usdt: false,
  contacts: 'Алматы, Казахстан',
  supportEmail: 'support@safilife.test',
  supportPhone: '+7 700 000 00 00',
};

export default function AdminSettings() {
  const [settings, setSettings] = useState<SettingsState>(defaultSettings);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState('');

  const loadSettings = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setSettings(normalizeSettings(await getAdminSettings()));
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить настройки.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadSettings();
  }, []);

  const handleSave = async () => {
    setIsSaving(true);
    setError(null);
    setMessage('');

    try {
      const response = await updateAdminSettings({
        'company.name': settings.companyName,
        'withdrawals.minimum_amount': Number(settings.minimumWithdrawal || 0),
        'withdrawals.methods.card': settings.card,
        'withdrawals.methods.business_account': settings.businessAccount,
        'withdrawals.methods.usdt': settings.usdt,
        'contacts.public': settings.contacts,
        'support.email': settings.supportEmail,
        'support.phone': settings.supportPhone,
      });
      setSettings(normalizeSettings(response));
      setMessage('Настройки сохранены.');
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось сохранить настройки.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-4xl">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Настройки</h1>
          <p className="text-sm text-safi-text/70">Глобальные параметры системы</p>
        </div>
        <button
          onClick={handleSave}
          disabled={isLoading || isSaving}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg disabled:opacity-50"
        >
          <Save className="w-4 h-4 ml-[-4px]" /> {isSaving ? 'Сохранение...' : 'Сохранить настройки'}
        </button>
      </div>

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadSettings} />}

      {message && (
        <div className="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
          {message}
        </div>
      )}

      {!isLoading && !error && (
        <div className="grid gap-8">
          <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
            <h3 className="text-xl font-serif font-bold text-safi-green mb-6 flex items-center gap-3">
              <Settings className="w-5 h-5 text-safi-gold" /> Основные настройки
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Название компании</label>
                <input
                  type="text"
                  value={settings.companyName}
                  onChange={(event) => setSettings({ ...settings, companyName: event.target.value })}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                />
              </div>
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Минимальная сумма вывода</label>
                <input
                  type="number"
                  value={settings.minimumWithdrawal}
                  onChange={(event) => setSettings({ ...settings, minimumWithdrawal: event.target.value })}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                />
              </div>
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Доступные способы вывода</label>
                <div className="flex flex-col gap-2 p-3 bg-[#F5F5F0] rounded-xl">
                  <Toggle label="Банковская карта (KZT)" checked={settings.card} onChange={(checked) => setSettings({ ...settings, card: checked })} />
                  <Toggle label="Счёт ИП" checked={settings.businessAccount} onChange={(checked) => setSettings({ ...settings, businessAccount: checked })} />
                  <Toggle label="USDT ERC-20 / TRC-20" checked={settings.usdt} onChange={(checked) => setSettings({ ...settings, usdt: checked })} />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
            <h3 className="text-xl font-serif font-bold text-safi-green mb-6">Контакты и поддержка</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Contacts</label>
                <textarea
                  rows={4}
                  value={settings.contacts}
                  onChange={(event) => setSettings({ ...settings, contacts: event.target.value })}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                />
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Support email</label>
                  <input
                    type="email"
                    value={settings.supportEmail}
                    onChange={(event) => setSettings({ ...settings, supportEmail: event.target.value })}
                    className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                  />
                </div>
                <div>
                  <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Support phone</label>
                  <input
                    type="text"
                    value={settings.supportPhone}
                    onChange={(event) => setSettings({ ...settings, supportPhone: event.target.value })}
                    className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
  return (
    <label className="flex items-center gap-2 text-sm font-bold">
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="rounded text-safi-green focus:ring-safi-green"
      />
      {label}
    </label>
  );
}

function normalizeSettings(response: unknown): SettingsState {
  const root = isRecord(response) ? response : {};
  const values = isRecord(root.settings) ? root.settings : {};

  return {
    companyName: getString(values['company.name']) || defaultSettings.companyName,
    minimumWithdrawal: String(getNumber(values['withdrawals.minimum_amount']) ?? defaultSettings.minimumWithdrawal),
    card: getBoolean(values['withdrawals.methods.card'], defaultSettings.card),
    businessAccount: getBoolean(values['withdrawals.methods.business_account'], defaultSettings.businessAccount),
    usdt: getBoolean(values['withdrawals.methods.usdt'], defaultSettings.usdt),
    contacts: getString(values['contacts.public']) || defaultSettings.contacts,
    supportEmail: getString(values['support.email']) || defaultSettings.supportEmail,
    supportPhone: getString(values['support.phone']) || defaultSettings.supportPhone,
  };
}

function getString(value: unknown) {
  return typeof value === 'string' && value.trim() !== '' ? value : undefined;
}

function getNumber(value: unknown) {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value.replace(/[^\d.-]/g, ''));
    return Number.isFinite(parsed) ? parsed : undefined;
  }

  return undefined;
}

function getBoolean(value: unknown, fallback: boolean) {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
  }

  return fallback;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
