import { useEffect, useState } from 'react';
import { FileText, Settings, Save } from 'lucide-react';
import { ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { fallbackLegalSettings, getAdminSettings, getApiErrorState, updateAdminSettings } from '../../lib/api';
import { adminText } from '../../i18n/adminText';

interface SettingsState {
  companyName: string;
  minimumWithdrawal: string;
  card: boolean;
  businessAccount: boolean;
  usdt: boolean;
  contacts: string;
  supportEmail: string;
  supportPhone: string;
  companyLegalName: string;
  companyBin: string;
  legalAddress: string;
  actualAddress: string;
  bankName: string;
  iban: string;
  bik: string;
  kbe: string;
  legalSupportPhone: string;
  legalSupportEmail: string;
  disputeEmail: string;
  websiteUrl: string;
  directorName: string;
  privacyEmail: string;
}

const defaultSettings: SettingsState = {
  companyName: 'Safi Life',
  minimumWithdrawal: '10000',
  card: true,
  businessAccount: true,
  usdt: false,
  contacts: adminText('a_0JDQu9C80LDR'),
  supportEmail: 'support@safilife.kz',
  supportPhone: '+7 700 000 00 00',
  companyLegalName: fallbackLegalSettings.company_legal_name,
  companyBin: fallbackLegalSettings.company_bin,
  legalAddress: fallbackLegalSettings.legal_address,
  actualAddress: fallbackLegalSettings.actual_address,
  bankName: fallbackLegalSettings.bank_name,
  iban: fallbackLegalSettings.iban,
  bik: fallbackLegalSettings.bik,
  kbe: fallbackLegalSettings.kbe,
  legalSupportPhone: fallbackLegalSettings.support_phone,
  legalSupportEmail: fallbackLegalSettings.support_email,
  disputeEmail: fallbackLegalSettings.dispute_email,
  websiteUrl: fallbackLegalSettings.website_url,
  directorName: fallbackLegalSettings.director_name,
  privacyEmail: fallbackLegalSettings.privacy_email,
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
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_23'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadSettings();
  }, []);

  const updateField = <Key extends keyof SettingsState>(key: Key, value: SettingsState[Key]) => {
    setSettings((current) => ({ ...current, [key]: value }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    setError(null);
    setMessage('');

    try {
      const response = await updateAdminSettings({
        'company.name': settings.companyName,
        'withdrawals.minimum_amount': Number(settings.minimumWithdrawal || 0),
        'withdrawals.methods.card_account': settings.card,
        'withdrawals.methods.ip_account': settings.businessAccount,
        'withdrawals.methods.usdt': settings.usdt,
        'contacts.public': settings.contacts,
        'support.email': settings.supportEmail,
        'support.phone': settings.supportPhone,
        company_legal_name: settings.companyLegalName,
        company_bin: settings.companyBin,
        legal_address: settings.legalAddress,
        actual_address: settings.actualAddress,
        bank_name: settings.bankName,
        iban: settings.iban,
        bik: settings.bik,
        kbe: settings.kbe,
        support_phone: settings.legalSupportPhone,
        support_email: settings.legalSupportEmail,
        dispute_email: settings.disputeEmail,
        website_url: settings.websiteUrl,
        director_name: settings.directorName,
        privacy_email: settings.privacyEmail,
      });
      setSettings(normalizeSettings(response));
      setMessage(adminText('a_0J3QsNGB0YLR_2'));
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_24'));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-5xl">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0J3QsNGB0YLR_3')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0JPQu9C-0LHQ')}</p>
        </div>
        <button
          onClick={handleSave}
          disabled={isLoading || isSaving}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg disabled:opacity-50"
        >
          <Save className="w-4 h-4 ml-[-4px]" /> {isSaving ? adminText('a_0KHQvtGF0YDQ_2') : adminText('a_0KHQvtGF0YDQ_4')}
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
              <Settings className="w-5 h-5 text-safi-gold" />{adminText('a_0J7RgdC90L7Q_2')}
            </h3>
            <div className="space-y-4">
              <Field label={adminText('a_0J3QsNC30LLQ_2')} value={settings.companyName} onChange={(value) => updateField('companyName', value)} />
              <Field label={adminText('a_0JzQuNC90LjQ')} type="number" value={settings.minimumWithdrawal} onChange={(value) => updateField('minimumWithdrawal', value)} />
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0JTQvtGB0YLR_4')}</label>
                <div className="flex flex-col gap-2 p-3 bg-[#F5F5F0] rounded-xl">
                  <Toggle label={adminText('a_0JHQsNC90LrQ')} checked={settings.card} onChange={(checked) => updateField('card', checked)} />
                  <Toggle label={adminText('a_0KHRh9GR0YIg')} checked={settings.businessAccount} onChange={(checked) => updateField('businessAccount', checked)} />
                  <Toggle label="USDT ERC-20 / TRC-20" checked={settings.usdt} onChange={(checked) => updateField('usdt', checked)} />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
            <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{adminText('a_0JrQvtC90YLQ_2')}</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Contacts</label>
                <textarea
                  rows={4}
                  value={settings.contacts}
                  onChange={(event) => updateField('contacts', event.target.value)}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                />
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Support email" type="email" value={settings.supportEmail} onChange={(value) => updateField('supportEmail', value)} />
                <Field label="Support phone" value={settings.supportPhone} onChange={(value) => updateField('supportPhone', value)} />
              </div>
            </div>
          </div>

          <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
            <h3 className="text-xl font-serif font-bold text-safi-green mb-2 flex items-center gap-3">
              <FileText className="w-5 h-5 text-safi-gold" /> Юридическая информация
            </h3>
            <p className="mb-6 text-sm leading-7 text-safi-text/60">
              Эти данные используются на публичных страницах оплаты, оферты, политики конфиденциальности, доставки, возврата, реквизитов и контактов.
            </p>
            <div className="grid gap-4 md:grid-cols-2">
              <Field label="Наименование юридического лица" value={settings.companyLegalName} onChange={(value) => updateField('companyLegalName', value)} />
              <Field label="БИН" value={settings.companyBin} onChange={(value) => updateField('companyBin', value)} />
              <Field label="Юридический адрес" value={settings.legalAddress} onChange={(value) => updateField('legalAddress', value)} textarea />
              <Field label="Фактический адрес" value={settings.actualAddress} onChange={(value) => updateField('actualAddress', value)} textarea />
              <Field label="Банк" value={settings.bankName} onChange={(value) => updateField('bankName', value)} />
              <Field label="ИИК" value={settings.iban} onChange={(value) => updateField('iban', value)} />
              <Field label="БИК" value={settings.bik} onChange={(value) => updateField('bik', value)} />
              <Field label="КБе" value={settings.kbe} onChange={(value) => updateField('kbe', value)} />
              <Field label="Телефон поддержки" value={settings.legalSupportPhone} onChange={(value) => updateField('legalSupportPhone', value)} />
              <Field label="Email поддержки" type="email" value={settings.legalSupportEmail} onChange={(value) => updateField('legalSupportEmail', value)} />
              <Field label="Email для споров" type="email" value={settings.disputeEmail} onChange={(value) => updateField('disputeEmail', value)} />
              <Field label="Сайт" value={settings.websiteUrl} onChange={(value) => updateField('websiteUrl', value)} />
              <Field label="Руководитель" value={settings.directorName} onChange={(value) => updateField('directorName', value)} />
              <Field label="Email по персональным данным" type="email" value={settings.privacyEmail} onChange={(value) => updateField('privacyEmail', value)} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  type = 'text',
  textarea = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  textarea?: boolean;
}) {
  return (
    <div>
      <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{label}</label>
      {textarea ? (
        <textarea
          rows={3}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
        />
      ) : (
        <input
          type={type}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
        />
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
    card: getBoolean(values['withdrawals.methods.card_account'], defaultSettings.card),
    businessAccount: getBoolean(values['withdrawals.methods.ip_account'], defaultSettings.businessAccount),
    usdt: getBoolean(values['withdrawals.methods.usdt'], defaultSettings.usdt),
    contacts: getString(values['contacts.public']) || defaultSettings.contacts,
    supportEmail: getString(values['support.email']) || defaultSettings.supportEmail,
    supportPhone: getString(values['support.phone']) || defaultSettings.supportPhone,
    companyLegalName: getString(values.company_legal_name) || defaultSettings.companyLegalName,
    companyBin: getString(values.company_bin) || defaultSettings.companyBin,
    legalAddress: getString(values.legal_address) || defaultSettings.legalAddress,
    actualAddress: getString(values.actual_address) || defaultSettings.actualAddress,
    bankName: getString(values.bank_name) || defaultSettings.bankName,
    iban: getString(values.iban) || defaultSettings.iban,
    bik: getString(values.bik) || defaultSettings.bik,
    kbe: getString(values.kbe) || defaultSettings.kbe,
    legalSupportPhone: getString(values.support_phone) || defaultSettings.legalSupportPhone,
    legalSupportEmail: getString(values.support_email) || defaultSettings.legalSupportEmail,
    disputeEmail: getString(values.dispute_email) || defaultSettings.disputeEmail,
    websiteUrl: getString(values.website_url) || defaultSettings.websiteUrl,
    directorName: getString(values.director_name) || defaultSettings.directorName,
    privacyEmail: getString(values.privacy_email) || defaultSettings.privacyEmail,
  };
}

function getString(value: unknown) {
  if (typeof value === 'string' && value.trim() !== '') {
    return value;
  }

  if (typeof value === 'number') {
    return String(value);
  }

  return undefined;
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
