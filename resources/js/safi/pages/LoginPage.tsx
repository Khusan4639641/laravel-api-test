import React, { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowRight, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { ApiError, login } from '../lib/api';

type FieldErrors = Record<string, string[]>;

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

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
    <div className="min-h-[calc(100vh-72px)] bg-safi-bg text-safi-green">
      <Container>
        <div className="grid min-h-[calc(100vh-72px)] gap-10 py-14 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:py-20">
          <section className="mx-auto max-w-2xl text-center lg:mx-0 lg:text-left">
            <span className="safi-kicker">Safi Life account</span>
            <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
              {t('auth.loginTitle', 'Вход в')}{' '}
              <span className="italic text-safi-gold">кабинет</span>
            </h1>
            <p className="mt-6 text-base leading-8 text-safi-muted md:text-xl">
              Войдите в личный кабинет, чтобы видеть структуру, бонусы, покупки и текущий статус партнера.
            </p>

            <div className="mt-9 grid gap-4 sm:grid-cols-3">
              <AuthBenefit title="Защита" desc="Безопасный вход" icon={ShieldCheck} />
              <AuthBenefit title="Кабинет" desc="Бонусы и структура" icon={LockKeyhole} />
              <AuthBenefit title="Связь" desc="Поддержка партнера" icon={Mail} />
            </div>
          </section>

          <section className="mx-auto w-full max-w-xl rounded-[36px] border border-safi-border bg-white p-6 shadow-[0_24px_70px_rgba(11,23,18,0.10)] md:p-9">
            <div className="mb-8 text-center">
              <span className="safi-kicker">Login</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold text-safi-green">С возвращением</h2>
              <p className="mt-3 text-sm leading-7 text-safi-muted">Введите логин или email и пароль.</p>
            </div>

            {error && (
              <div className="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
                {error}
              </div>
            )}

            <form className="space-y-5" onSubmit={handleSubmit}>
              <FormField label="Логин или email" error={fieldErrors.login?.[0] || fieldErrors.email?.[0]}>
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

              <FormField label="Пароль" error={fieldErrors.password?.[0]}>
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

              <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Входим...' : t('auth.loginAction', 'Войти')}
                <ArrowRight className="h-4 w-4" />
              </Button>

              <div className="text-center text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                Нет аккаунта?{' '}
                <Link to="/register" className="text-safi-green transition-colors hover:text-safi-gold">
                  Стать партнером
                </Link>
              </div>
            </form>
          </section>
        </div>
      </Container>
    </div>
  );
}

function FormField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-bold text-red-600">{error}</span>}
    </label>
  );
}

function AuthBenefit({
  title,
  desc,
  icon: Icon,
}: {
  title: string;
  desc: string;
  icon: React.ComponentType<{ className?: string }>;
}) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-5 text-left shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
        <Icon className="h-5 w-5" />
      </div>
      <h3 className="font-serif text-xl font-semibold text-safi-green">{title}</h3>
      <p className="mt-1 text-xs font-bold uppercase tracking-[0.12em] text-safi-muted">{desc}</p>
    </article>
  );
}
