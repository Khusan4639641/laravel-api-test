import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { ApiError, getRegistrationPackages, Package, register } from '../lib/api';

type FieldErrors = Record<string, string[]>;
type ReferralBranch = 'left' | 'right';

const inputClass = 'w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all placeholder:text-safi-text/40';
const lockedInputClass = 'w-full px-5 py-4 rounded-xl border border-safi-gold/30 bg-safi-cream font-bold text-safi-green outline-none transition-all read-only:cursor-not-allowed disabled:cursor-not-allowed disabled:opacity-100';
const branchLabels: Record<ReferralBranch, string> = {
  left: 'Левая ветка',
  right: 'Правая ветка',
};

export default function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const searchParams = useMemo(() => new URLSearchParams(location.search), [location.search]);
  const normalizedPath = location.pathname.replace(/\/+$/, '') || '/';
  const urlReferralCode = (searchParams.get('ref') || searchParams.get('referral_code') || searchParams.get('sponsor_code') || '').trim();
  const urlBranch = (searchParams.get('branch') || '').trim().toLowerCase();
  const hasReferralQuery = searchParams.has('ref') || searchParams.has('referral_code') || searchParams.has('sponsor_code') || searchParams.has('branch');
  const isReferralMode = normalizedPath === '/register-ref-branch' || hasReferralQuery;
  const normalizedBranch = isReferralBranch(urlBranch) ? urlBranch : '';
  const referralLinkError = useMemo(() => {
    if (!isReferralMode) {
      return '';
    }

    if (!urlReferralCode || !urlBranch) {
      return 'Некорректная реферальная ссылка';
    }

    if (!isReferralBranch(urlBranch)) {
      return 'Некорректная ветка';
    }

    return '';
  }, [isReferralMode, urlBranch, urlReferralCode]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [packagesLoading, setPackagesLoading] = useState(true);
  const [form, setForm] = useState({
    name: '',
    login: '',
    email: '',
    password: '',
    password_confirmation: '',
    referral_code: '',
    branch: '',
    package_id: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  useEffect(() => {
    void getRegistrationPackages()
      .then((items) => setPackages(items.filter((pkg) => isStarterPackage(pkg))))
      .catch(() => setPackages([]))
      .finally(() => setPackagesLoading(false));
  }, []);

  useEffect(() => {
    setForm((current) => ({
      ...current,
      referral_code: isReferralMode ? urlReferralCode : '',
      branch: isReferralMode ? normalizedBranch : '',
    }));
  }, [isReferralMode, normalizedBranch, urlReferralCode]);

  const updateField = (field: keyof typeof form, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError('');
    setFieldErrors({});

    if (referralLinkError) {
      setError(referralLinkError);
      return;
    }

    setIsSubmitting(true);

    try {
      await register({
        name: form.name,
        login: form.login,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
        package_id: form.package_id,
        ...(isReferralMode ? {
          referral_code: form.referral_code,
          branch: form.branch,
        } : {}),
      });
      navigate('/dashboard', { replace: true });
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
        setFieldErrors(caughtError.errors || {});
      } else {
        setError('Не удалось зарегистрироваться. Проверьте соединение и попробуйте снова.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const visibleError = error || referralLinkError;
  const referralFieldError = fieldErrors.referral_code?.[0] || fieldErrors.ref?.[0] || fieldErrors.sponsor_code?.[0] || fieldErrors.sponsor_id?.[0];

  return (
    <div className="py-20 bg-safi-bg min-h-[calc(100vh-80px)] flex flex-col justify-center relative overflow-hidden">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>
      <div className="absolute bottom-0 left-0 w-[500px] h-[500px] bg-safi-gold/5 rounded-full blur-3xl translate-y-1/4 -translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10 w-full lg:max-w-4xl">
        <div className="max-w-2xl mx-auto bg-white rounded-[40px] shadow-xl border border-safi-green/5 p-8 md:p-12 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-40 h-40 bg-safi-gold/10 rounded-bl-full -z-10"></div>

          <div className="text-center mb-10">
            <h1 className="text-3xl font-serif text-safi-green mb-2">Регистрация</h1>
            <p className="text-sm text-safi-text opacity-70">Станьте партнером Safi Life</p>
          </div>

          {visibleError && (
            <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
              {visibleError}
            </div>
          )}

          <form className="space-y-6" onSubmit={handleSubmit}>
            <div className="grid sm:grid-cols-2 gap-6">
              <FormField label="Имя" error={fieldErrors.name?.[0]}>
                <input
                  type="text"
                  value={form.name}
                  onChange={(event) => updateField('name', event.target.value)}
                  className={inputClass}
                  placeholder="Иван Иванов"
                  autoComplete="name"
                  required
                />
              </FormField>
              <FormField label="Телефон / логин" error={fieldErrors.login?.[0]}>
                <input
                  type="text"
                  value={form.login}
                  onChange={(event) => updateField('login', event.target.value)}
                  className={inputClass}
                  placeholder="+7 (___) ___-__-__"
                  autoComplete="username"
                  required
                />
              </FormField>
            </div>

            <FormField label="Email" error={fieldErrors.email?.[0]}>
              <input
                type="email"
                value={form.email}
                onChange={(event) => updateField('email', event.target.value)}
                className={inputClass}
                placeholder="mail@example.com"
                autoComplete="email"
                required
              />
            </FormField>

            <div className="grid sm:grid-cols-2 gap-6">
              <FormField label="Пароль" error={fieldErrors.password?.[0]}>
                <input
                  type="password"
                  value={form.password}
                  onChange={(event) => updateField('password', event.target.value)}
                  className={inputClass}
                  placeholder="********"
                  autoComplete="new-password"
                  required
                />
              </FormField>

              <FormField label="Повторите пароль" error={fieldErrors.password_confirmation?.[0]}>
                <input
                  type="password"
                  value={form.password_confirmation}
                  onChange={(event) => updateField('password_confirmation', event.target.value)}
                  className={inputClass}
                  placeholder="********"
                  autoComplete="new-password"
                  required
                />
              </FormField>
            </div>

            {isReferralMode && (
              <>
                {!referralLinkError && (
                  <div className="rounded-xl border border-safi-green/15 bg-safi-green/5 px-4 py-3 text-sm font-bold text-safi-green">
                    Вы регистрируетесь по приглашению партнёра
                  </div>
                )}

                <div className="grid sm:grid-cols-2 gap-6">
                  <FormField label="Код пригласителя (Реферал)" error={referralFieldError}>
                    <input
                      type="text"
                      value={form.referral_code}
                      className={lockedInputClass}
                      placeholder="Код партнёра"
                      readOnly
                      required
                    />
                  </FormField>

                  <FormField label="Ветка" error={fieldErrors.branch?.[0]}>
                    <select
                      value={form.branch}
                      className={`${lockedInputClass} text-sm`}
                      disabled
                      required
                    >
                      <option value="">Не выбрана</option>
                      <option value="left">{branchLabels.left}</option>
                      <option value="right">{branchLabels.right}</option>
                    </select>
                  </FormField>
                </div>
              </>
            )}

            <FormField label="Стартовый пакет" error={fieldErrors.package_id?.[0]}>
              <select
                value={form.package_id}
                onChange={(event) => updateField('package_id', event.target.value)}
                className={`${inputClass} text-sm text-safi-green`}
              >
                <option value="">{packagesLoading ? 'Загружаем пакеты...' : 'Выберите пакет'}</option>
                {packages.map((pkg) => (
                  <option key={pkg.id} value={pkg.id}>
                    {pkg.label || pkg.name} — {pkg.price.toLocaleString('ru-RU')} ₸
                  </option>
                ))}
              </select>
            </FormField>

            <div className="flex items-start gap-3 mt-4">
              <input type="checkbox" id="agree" required className="mt-1 rounded text-safi-green focus:ring-safi-green w-4 h-4" />
              <label htmlFor="agree" className="text-xs text-safi-text opacity-70 cursor-pointer">
                Я согласен с <Link to="/legal" className="text-safi-green font-bold hover:underline">условиями и политикой конфиденциальности</Link>.
              </label>
            </div>

            <div className="pt-4">
              <Button type="submit" className="w-full" disabled={isSubmitting || Boolean(referralLinkError)}>
                {isSubmitting ? 'Регистрируем...' : t('auth.regAction', 'Зарегистрироваться')}
              </Button>
            </div>

            <div className="text-center text-[10px] uppercase tracking-widest text-safi-text opacity-70 pt-4">
              Уже есть {t('auth.regAccount', 'аккаунт')}? <Link to="/login" className="text-safi-green font-bold hover:underline">Войти</Link>
            </div>
          </form>
        </div>
      </Container>
    </div>
  );
}

function isReferralBranch(value: string): value is ReferralBranch {
  return value === 'left' || value === 'right';
}

function isStarterPackage(pkg: Package) {
  const code = String(pkg.code || pkg.name || '').trim().toUpperCase();

  return code === 'START' || code === 'VIP';
}

function FormField({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="block">
      <span className="block text-[10px] font-bold tracking-widest uppercase text-safi-green mb-2 opacity-80">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-bold text-red-600">{error}</span>}
    </label>
  );
}
