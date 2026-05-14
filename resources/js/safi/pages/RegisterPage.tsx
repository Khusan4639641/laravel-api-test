import React, { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowRight, Check, Network, PackageCheck, UserPlus } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { packages } from '../data/packages';
import { ApiError, register } from '../lib/api';

type FieldErrors = Record<string, string[]>;

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '',
    login: '',
    email: '',
    password: '',
    password_confirmation: '',
    sponsor_id: '',
    branch: '',
    package_id: '',
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
      await register(form);
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

  const updateField = (field: keyof typeof form, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  return (
    <div className="bg-safi-bg text-safi-green">
      <Container>
        <div className="grid min-h-[calc(100vh-72px)] gap-10 py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-center lg:py-20">
          <section className="mx-auto max-w-2xl text-center lg:mx-0 lg:text-left">
            <span className="safi-kicker">Become a partner</span>
            <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
              Регистрация{' '}
              <span className="italic text-safi-gold">Safi Life</span>
            </h1>
            <p className="mt-6 text-base leading-8 text-safi-muted md:text-xl">
              Создайте аккаунт партнера, выберите стартовый пакет и укажите данные спонсора, если вы пришли по рекомендации.
            </p>

            <div className="mt-9 grid gap-4 sm:grid-cols-3">
              <AuthStep title="Аккаунт" desc="Имя, логин и email" icon={UserPlus} />
              <AuthStep title="Спонсор" desc="Опциональные поля" icon={Network} />
              <AuthStep title="Пакет" desc="Можно выбрать позже" icon={PackageCheck} />
            </div>
          </section>

          <section className="mx-auto w-full max-w-3xl rounded-[36px] border border-safi-border bg-white p-6 shadow-[0_24px_70px_rgba(11,23,18,0.10)] md:p-9">
            <div className="mb-8 text-center">
              <span className="safi-kicker">Register</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold text-safi-green">Стать партнером</h2>
              <p className="mt-3 text-sm leading-7 text-safi-muted">После успешной регистрации вы попадете в личный кабинет партнера.</p>
            </div>

            {error && (
              <div className="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
                {error}
              </div>
            )}

            <form className="space-y-6" onSubmit={handleSubmit}>
              <div className="grid gap-5 md:grid-cols-2">
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
                <FormField label="Логин" error={fieldErrors.login?.[0]}>
                  <input
                    type="text"
                    value={form.login}
                    onChange={(event) => updateField('login', event.target.value)}
                    className={inputClass}
                    placeholder="safi_partner"
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

              <div className="grid gap-5 md:grid-cols-2">
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
                <FormField label="Подтверждение пароля" error={fieldErrors.password_confirmation?.[0]}>
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

              <div className="grid gap-5 md:grid-cols-2">
                <FormField label="ID спонсора" error={fieldErrors.sponsor_id?.[0]}>
                  <input
                    type="text"
                    value={form.sponsor_id}
                    onChange={(event) => updateField('sponsor_id', event.target.value)}
                    className={inputClass}
                    placeholder="Необязательно"
                  />
                </FormField>
                <FormField label="Ветка" error={fieldErrors.branch?.[0]}>
                  <select
                    value={form.branch}
                    onChange={(event) => updateField('branch', event.target.value)}
                    className={inputClass}
                  >
                    <option value="">Не выбрана</option>
                    <option value="left">Левая</option>
                    <option value="right">Правая</option>
                  </select>
                </FormField>
              </div>

              <FormField label="Стартовый пакет" error={fieldErrors.package_id?.[0]}>
                <div className="grid gap-3 sm:grid-cols-3">
                  {packages.map((pkg) => {
                    const isSelected = form.package_id === pkg.id;

                    return (
                      <button
                        key={pkg.id}
                        type="button"
                        onClick={() => updateField('package_id', isSelected ? '' : pkg.id)}
                        className={`rounded-3xl border p-5 text-left transition-all ${
                          isSelected
                            ? 'border-safi-green bg-safi-green text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)]'
                            : 'border-safi-border bg-safi-bg text-safi-green hover:border-safi-green'
                        }`}
                      >
                        <div className="flex items-center justify-between gap-3">
                          <span className={`font-serif text-2xl font-semibold ${isSelected ? 'text-white' : 'text-safi-green'}`}>{pkg.name}</span>
                          {isSelected && <Check className="h-5 w-5 text-safi-gold" />}
                        </div>
                        <div className={`mt-2 text-sm font-extrabold ${isSelected ? 'text-safi-gold' : 'text-safi-green'}`}>
                          {pkg.price.toLocaleString('ru-RU')} ₸
                        </div>
                        <div className={`mt-2 text-[10px] font-extrabold uppercase tracking-[0.14em] ${isSelected ? 'text-white/70' : 'text-safi-muted'}`}>
                          Реф. {pkg.referralBonus}% / Бинар {pkg.binaryBonus || 0}%
                        </div>
                      </button>
                    );
                  })}
                </div>
              </FormField>

              <label className="flex items-start gap-3 rounded-2xl border border-safi-border bg-safi-bg p-4">
                <input type="checkbox" required className="mt-1 h-4 w-4 rounded border-safi-border text-safi-green focus:ring-safi-gold" />
                <span className="text-xs leading-6 text-safi-muted">
                  Я согласен с{' '}
                  <Link to="/legal" className="font-bold text-safi-green transition-colors hover:text-safi-gold">
                    условиями и политикой конфиденциальности
                  </Link>
                  .
                </span>
              </label>

              <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? 'Регистрируем...' : t('auth.regAction', 'Зарегистрироваться')}
                <ArrowRight className="h-4 w-4" />
              </Button>

              <div className="text-center text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                Уже есть {t('auth.regAccount', 'аккаунт')}?{' '}
                <Link to="/login" className="text-safi-green transition-colors hover:text-safi-gold">
                  Войти
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

function AuthStep({
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
