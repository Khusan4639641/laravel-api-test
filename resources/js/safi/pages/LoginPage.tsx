import React, { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { ApiError, checkForgotPasswordEmail, createForgotPasswordRequest, getMyPermissions, login } from '../lib/api';
import { normalizePermissions } from '../lib/permissions';

type FieldErrors = Record<string, string[]>;

const inputClass = 'w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all placeholder:text-safi-text/40';

export default function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const redirectPath = new URLSearchParams(location.search).get('redirect');
  const [form, setForm] = useState({
    login: '',
    password: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [forgotOpen, setForgotOpen] = useState(false);
  const [forgotStep, setForgotStep] = useState<'email' | 'phone' | 'success'>('email');
  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotPhone, setForgotPhone] = useState('');
  const [forgotMessage, setForgotMessage] = useState('');
  const [forgotError, setForgotError] = useState('');
  const [forgotErrors, setForgotErrors] = useState<FieldErrors>({});
  const [forgotSubmitting, setForgotSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setError('');
    setFieldErrors({});

    try {
      const response = await login(form);
      if (redirectPath && redirectPath.startsWith('/')) {
        navigate(redirectPath, { replace: true });
        return;
      }

      try {
        const permissions = normalizePermissions(await getMyPermissions());
        navigate(permissions.redirect_after_login, { replace: true });
      } catch {
        navigate(getRedirectPath(response), { replace: true });
      }
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
        setFieldErrors(caughtError.errors || {});
      } else {
        setError('Не удалось войти. Проверьте соединение и попробуйте снова.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const openForgotPassword = () => {
    setForgotOpen(true);
    setForgotStep('email');
    setForgotEmail('');
    setForgotPhone('');
    setForgotMessage('');
    setForgotError('');
    setForgotErrors({});
  };

  const closeForgotPassword = () => {
    if (forgotSubmitting) {
      return;
    }

    setForgotOpen(false);
    setForgotStep('email');
    setForgotEmail('');
    setForgotPhone('');
    setForgotMessage('');
    setForgotError('');
    setForgotErrors({});
  };

  const submitForgotEmail = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setForgotSubmitting(true);
    setForgotError('');
    setForgotErrors({});

    try {
      await checkForgotPasswordEmail({ email: forgotEmail.trim() });
      setForgotStep('phone');
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setForgotError(caughtError.message);
        setForgotErrors(caughtError.errors || {});
      } else {
        setForgotError('Не удалось проверить email. Попробуйте позже.');
      }
    } finally {
      setForgotSubmitting(false);
    }
  };

  const submitForgotRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setForgotSubmitting(true);
    setForgotError('');
    setForgotErrors({});

    try {
      const response = await createForgotPasswordRequest({ email: forgotEmail.trim(), phone: forgotPhone.trim() });
      setForgotMessage(getResponseMessage(response) || 'Обращение передано в администрацию. Администратор свяжется с вами.');
      setForgotStep('success');
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setForgotError(caughtError.message);
        setForgotErrors(caughtError.errors || {});
      } else {
        setForgotError('Не удалось отправить обращение. Попробуйте позже.');
      }
    } finally {
      setForgotSubmitting(false);
    }
  };

  return (
    <div className="py-20 bg-safi-bg min-h-[calc(100vh-80px)] flex flex-col justify-center relative overflow-hidden">
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>
      <div className="absolute bottom-0 left-0 w-[500px] h-[500px] bg-safi-gold/5 rounded-full blur-3xl translate-y-1/4 -translate-x-1/4 pointer-events-none"></div>

      <Container className="relative z-10 w-full">
        <div className="max-w-md mx-auto bg-white rounded-[40px] shadow-xl border border-safi-green/5 p-8 md:p-10 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-32 h-32 bg-safi-gold/10 rounded-bl-full -z-10"></div>

          <div className="text-center mb-10">
            <h1 className="text-3xl font-serif text-safi-green mb-2">{t('auth.loginTitle', 'Вход в')} кабинет</h1>
            <p className="text-sm text-safi-text opacity-70">С возвращением в Safi Life</p>
          </div>

          {error && (
            <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
              {error}
            </div>
          )}

          <form className="space-y-6" onSubmit={handleSubmit}>
            <FormField label="Email или Телефон" error={fieldErrors.login?.[0] || fieldErrors.email?.[0]}>
              <input
                type="text"
                value={form.login}
                onChange={(event) => setForm((current) => ({ ...current, login: event.target.value }))}
                className={inputClass}
                placeholder="mail@example.com"
                autoComplete="username"
                required
              />
            </FormField>

            <FormField
              label="Пароль"
              error={fieldErrors.password?.[0]}
              aside={(
                <button
                  type="button"
                  onClick={openForgotPassword}
                  className="text-[10px] uppercase tracking-widest text-safi-gold font-bold hover:underline"
                >
                  Забыли пароль?
                </button>
              )}
            >
              <input
                type="password"
                value={form.password}
                onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
                className={inputClass}
                placeholder="********"
                autoComplete="current-password"
                required
              />
            </FormField>

            <div className="pt-2">
              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Входим...' : t('auth.loginAction', 'Войти')}
              </Button>
            </div>

            <div className="text-center text-[10px] uppercase tracking-widest text-safi-text opacity-70 pt-4">
              Самостоятельная регистрация временно недоступна. Обратитесь к администратору.
            </div>
          </form>
        </div>
      </Container>

      {forgotOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/35 px-4 py-6 backdrop-blur-sm">
          <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-[28px] border border-safi-green/10 bg-white p-6 shadow-[0_24px_70px_rgba(11,23,18,0.2)]">
            <div className="mb-6 flex items-start justify-between gap-4">
              <div>
                <h2 className="font-serif text-2xl font-semibold text-safi-green">Восстановление пароля</h2>
                <p className="mt-2 text-sm leading-6 text-safi-muted">
                  Администратор проверит обращение и свяжется с вами.
                </p>
              </div>
              <button
                type="button"
                onClick={closeForgotPassword}
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-safi-green/10 bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                aria-label="Закрыть"
              >
                ×
              </button>
            </div>

            {forgotError && (
              <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
                {forgotError}
              </div>
            )}

            {forgotStep === 'email' && (
              <form className="space-y-5" onSubmit={submitForgotEmail}>
                <FormField label="Email" error={forgotErrors.email?.[0]}>
                  <input
                    type="email"
                    value={forgotEmail}
                    onChange={(event) => setForgotEmail(event.target.value)}
                    className={inputClass}
                    placeholder="mail@example.com"
                    autoComplete="email"
                    required
                  />
                </FormField>
                <Button type="submit" className="w-full" disabled={forgotSubmitting}>
                  {forgotSubmitting ? 'Проверяем...' : 'Продолжить'}
                </Button>
              </form>
            )}

            {forgotStep === 'phone' && (
              <form className="space-y-5" onSubmit={submitForgotRequest}>
                <div className="rounded-2xl border border-safi-green/10 bg-[#F5F5F0] p-4 text-sm font-bold text-safi-green">
                  Email: {forgotEmail}
                </div>
                <FormField label="Номер телефона" error={forgotErrors.phone?.[0]}>
                  <input
                    type="tel"
                    value={forgotPhone}
                    onChange={(event) => setForgotPhone(event.target.value)}
                    className={inputClass}
                    placeholder="+77000000000"
                    autoComplete="tel"
                    required
                  />
                </FormField>
                <Button type="submit" className="w-full" disabled={forgotSubmitting}>
                  {forgotSubmitting ? 'Отправляем...' : 'Отправить обращение'}
                </Button>
              </form>
            )}

            {forgotStep === 'success' && (
              <div className="space-y-5">
                <div className="rounded-2xl border border-green-200 bg-green-50 px-4 py-4 text-sm font-bold leading-6 text-green-700">
                  {forgotMessage || 'Обращение передано в администрацию. Администратор свяжется с вами.'}
                </div>
                <Button type="button" className="w-full" onClick={closeForgotPassword}>
                  Закрыть
                </Button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function getRedirectPath(response: unknown) {
  const role = extractRole(response);

  if (role === 'admin' || role === 'accountant' || role === 'super_admin' || role === 'support') {
    return '/admin';
  }

  return '/dashboard';
}

function extractRole(response: unknown) {
  if (!response || typeof response !== 'object') {
    return 'user';
  }

  const record = response as Record<string, unknown>;
  const user = getRecord(record.user) || getRecord(getRecord(record.data)?.user) || getRecord(record.data);
  const role = user?.role;

  return typeof role === 'string' ? role.toLowerCase() : 'user';
}

function getRecord(value: unknown): Record<string, unknown> | undefined {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : undefined;
}

function getResponseMessage(response: unknown) {
  const record = getRecord(response);
  const message = record?.message;

  return typeof message === 'string' ? message : '';
}

function FormField({
  label,
  error,
  aside,
  children,
}: {
  label: string;
  error?: string;
  aside?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <label className="block">
      <span className="flex justify-between items-center mb-2">
        <span className="block text-[10px] font-bold tracking-widest uppercase text-safi-green opacity-80">{label}</span>
        {aside}
      </span>
      {children}
      {error && <span className="mt-2 block text-xs font-bold text-red-600">{error}</span>}
    </label>
  );
}
