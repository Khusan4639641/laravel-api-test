import React, { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { ApiError, login } from '../lib/api';

type FieldErrors = Record<string, string[]>;

const inputClass = 'w-full px-5 py-4 rounded-xl border border-safi-green/20 bg-[#F5F5F0] focus:ring-2 focus:ring-safi-green focus:border-safi-green focus:bg-white outline-none transition-all placeholder:text-safi-text/40';

export default function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    login: '',
    password: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setError('');
    setFieldErrors({});

    try {
      await login(form);
      navigate('/dashboard', { replace: true });
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

            <FormField label="Пароль" error={fieldErrors.password?.[0]} aside={<a href="#" className="text-[10px] uppercase tracking-widest text-safi-gold font-bold hover:underline">Забыли пароль?</a>}>
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
              Нет аккаунта? <Link to="/register" className="text-safi-green font-bold hover:underline">Стать партнёром</Link>
            </div>
          </form>
        </div>
      </Container>
    </div>
  );
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
