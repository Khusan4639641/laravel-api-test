import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { CheckCircle2, CreditCard, Globe2, Package, ShieldCheck, XCircle } from 'lucide-react';
import { AdminBadge, AdminStatCard } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getAdminPaymentReadiness, getApiErrorState, PaymentReadiness } from '../../lib/api';
import { useAdminContext } from '../../components/admin/AdminLayout';

export default function AdminPaymentReadiness() {
  const { currentUser } = useAdminContext();
  const [readiness, setReadiness] = useState<PaymentReadiness | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadReadiness = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setReadiness(await getAdminPaymentReadiness());
    } catch (caughtError) {
      setReadiness(null);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить checklist готовности TipTop Pay');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (currentUser.role === 'super_admin') {
      void loadReadiness();
      return;
    }

    setIsLoading(false);
  }, [currentUser.role]);

  const failedChecks = useMemo(() => {
    if (!readiness) {
      return 0;
    }

    return [
      readiness.httpsEnabled,
      readiness.tiptopEnabled,
      readiness.publicTerminalIdSet,
      readiness.currency === 'KZT',
      readiness.requisitesFilled,
      readiness.productsActiveCount > 0,
      readiness.productsWithoutImageCount === 0,
      readiness.productsWithoutStockCount === 0,
      readiness.ordersPaymentStatusSupport,
      readiness.webhookRoutesWork,
      readiness.checkoutRequiresDeliveryFields,
      readiness.legalPages.every((page) => page.available),
    ].filter((value) => !value).length;
  }, [readiness]);

  if (currentUser.role !== 'super_admin') {
    return <ErrorState title="Доступ запрещён" description="Checklist готовности TipTop Pay доступен только super_admin." />;
  }

  if (isLoading) {
    return <LoadingState title="Проверяем готовность TipTop Pay" />;
  }

  if (error) {
    return <ErrorState title="Не удалось загрузить readiness" description={error} onRetry={loadReadiness} />;
  }

  if (!readiness) {
    return <EmptyState title="Нет данных readiness" />;
  }

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">TipTop Pay</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Готовность сайта к проверке</h1>
            <p className="mt-3 max-w-3xl text-sm leading-7 text-safi-muted">
              Скрытый checklist для production-проверки перед отправкой сайта на модерацию TipTop Pay. Страница не заменяет ручную проверку тестового платежа.
            </p>
          </div>
          <StatusBadge ok={failedChecks === 0} okText="Готово" failText={`${failedChecks} проблем`} />
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <AdminStatCard title="APP_URL" value={readiness.appUrl || '-'} icon={Globe2} />
        <AdminStatCard title="HTTPS" value={readiness.httpsEnabled ? 'Да' : 'Нет'} icon={ShieldCheck} />
        <AdminStatCard title="TipTop enabled" value={readiness.tiptopEnabled ? 'Да' : 'Нет'} icon={CreditCard} />
        <AdminStatCard title="Active products" value={readiness.productsActiveCount.toLocaleString('ru-RU')} icon={Package} />
      </section>

      <section className="grid gap-5 xl:grid-cols-2">
        <ReadinessPanel title="Платёжная конфигурация">
          <CheckRow label="Currency = KZT" ok={readiness.currency === 'KZT'} value={readiness.currency} />
          <CheckRow label="TipTop Pay включён" ok={readiness.tiptopEnabled} />
          <CheckRow label="Public terminal id задан" ok={readiness.publicTerminalIdSet} />
          <CheckRow label="Payment status columns в orders" ok={readiness.ordersPaymentStatusSupport} />
          <CheckRow label="Webhook routes работают" ok={readiness.webhookRoutesWork} />
          <CheckRow label="Checkout требует телефон и адрес" ok={readiness.checkoutRequiresDeliveryFields} />
        </ReadinessPanel>

        <ReadinessPanel title="Юридические страницы">
          {readiness.legalPages.map((page) => (
            <CheckRow key={page.path} label={page.path} ok={page.available} />
          ))}
        </ReadinessPanel>

        <ReadinessPanel title="Реквизиты">
          <CheckRow label="Реквизиты заполнены реальными данными" ok={readiness.requisitesFilled} />
          {readiness.requisitesMissing.length > 0 && (
            <div className="rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
              <div className="mb-2 font-extrabold uppercase tracking-[0.12em]">Нужно заполнить</div>
              <div className="safi-numeric break-words">{readiness.requisitesMissing.join(', ')}</div>
            </div>
          )}
        </ReadinessPanel>

        <ReadinessPanel title="Каталог и остатки">
          <CheckRow label="Есть активные товары" ok={readiness.productsActiveCount > 0} value={readiness.productsActiveCount.toLocaleString('ru-RU')} />
          <CheckRow label="Товары без фото" ok={readiness.productsWithoutImageCount === 0} value={readiness.productsWithoutImageCount.toLocaleString('ru-RU')} />
          <CheckRow label="Товары без остатка" ok={readiness.productsWithoutStockCount === 0} value={readiness.productsWithoutStockCount.toLocaleString('ru-RU')} />
        </ReadinessPanel>
      </section>

      <ReadinessPanel title="Webhook endpoints">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {Object.entries(readiness.webhookRoutes).map(([route, ok]) => (
            <CheckRow key={route} label={route} ok={ok} />
          ))}
        </div>
      </ReadinessPanel>
    </div>
  );
}

function ReadinessPanel({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-[28px] border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      <h2 className="mb-5 font-serif text-2xl font-semibold text-safi-green">{title}</h2>
      <div className="grid gap-3">{children}</div>
    </section>
  );
}

function CheckRow({ label, ok, value }: { label: string; ok: boolean; value?: string }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3">
      <div className="min-w-0">
        <div className="truncate text-sm font-extrabold text-safi-green">{label}</div>
        {value && <div className="safi-numeric mt-1 text-xs text-safi-muted">{value}</div>}
      </div>
      <StatusBadge ok={ok} />
    </div>
  );
}

function StatusBadge({ ok, okText = 'Да', failText = 'Нет' }: { ok: boolean; okText?: string; failText?: string }) {
  return (
    <AdminBadge variant={ok ? 'success' : 'danger'} className="shrink-0">
      <span className="inline-flex items-center gap-1.5">
        {ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
        {ok ? okText : failText}
      </span>
    </AdminBadge>
  );
}
