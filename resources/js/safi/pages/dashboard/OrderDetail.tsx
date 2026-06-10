import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, CreditCard, PackageCheck } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack } from '../../components/ui/Toast';
import { ApiError, createTipTopPayPaymentIntent, getApiErrorState, getOrder, Order } from '../../lib/api';
import { useTipTopPayWidget } from '../../hooks/useTipTopPayWidget';

export default function OrderDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isStartingPayment, setIsStartingPayment] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const { isWidgetLoading, startPayment } = useTipTopPayWidget();
  const language = i18n.resolvedLanguage || i18n.language;

  const showToast = useCallback((message: string, type: ToastItem['type'] = 'success') => {
    const toastId = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id: toastId, message, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== toastId)), 3200);
  }, []);

  const loadOrder = useCallback(async () => {
    if (!id) {
      setOrder(null);
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      setOrder(await getOrder(id));
    } catch (caughtError) {
      setOrder(null);
      setError(getApiErrorState(caughtError).error || t('orders.detailError'));
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  if (isLoading) {
    return <LoadingState title={t('orders.detailLoading')} />;
  }

  if (error) {
    return <ErrorState title={t('orders.detailError')} description={error} onRetry={loadOrder} />;
  }

  if (!order) {
    return <EmptyState title={t('orders.notFound')} />;
  }

  const canPayOnline = !['paid', 'refunded', 'cancelled'].includes(order.paymentStatus || '')
    && !['cancelled', 'completed', 'voided'].includes(order.status)
    && order.items.length > 0
    && order.totalAmount > 0;

  const handleOnlinePayment = async () => {
    if (!order) {
      return;
    }

    setIsStartingPayment(true);

    try {
      const intent = await createTipTopPayPaymentIntent(order.id);

      await startPayment(intent, {
        onSuccess: async () => {
          showToast(t('orders.paymentSubmitted'));
          await loadOrder();
          navigate(`/payment/success?order=${encodeURIComponent(order.id)}`);
        },
        onFail: (caughtError) => {
          const message = caughtError instanceof Error ? caughtError.message : t('orders.paymentFailed');
          showToast(message, 'error');
          navigate(`/payment/fail?order=${encodeURIComponent(order.id)}`);
        },
      });
    } catch (caughtError) {
      const message = caughtError instanceof ApiError
        ? caughtError.message
        : caughtError instanceof Error
          ? caughtError.message
          : getApiErrorState(caughtError).error;
      showToast(message || t('orders.paymentStartError'), 'error');
    } finally {
      setIsStartingPayment(false);
    }
  };

  return (
    <div className="space-y-8">
      <ToastStack toasts={toasts} onDismiss={(toastId) => setToasts((current) => current.filter((toast) => toast.id !== toastId))} />
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <Link to="/dashboard/orders" className="mb-5 inline-flex cursor-pointer items-center gap-2 text-xs font-bold uppercase tracking-widest text-safi-green/60 transition-colors hover:text-safi-gold">
          <ArrowLeft className="h-4 w-4" />
          {t('orders.backToOrders')}
        </Link>
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">{t('orders.order')}</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">#{order.id}</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">{t('orders.detailSubtitle')}</p>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {canPayOnline && (
              <button
                type="button"
                onClick={() => void handleOnlinePayment()}
                disabled={isStartingPayment || isWidgetLoading}
                className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-safi-green px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green-hover disabled:cursor-not-allowed disabled:opacity-60"
              >
                <CreditCard className="h-4 w-4" />
                {isStartingPayment || isWidgetLoading ? t('orders.openingPayment') : t('orders.payOnline')}
              </button>
            )}
            <OrderStatusBadge status={order.status} />
          </div>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-4">
        <InfoCard label={t('orders.orderDate')} value={formatDate(order.createdAt, language)} />
        <InfoCard label={t('orders.items')} value={order.itemsCount.toLocaleString('ru-RU')} />
        <InfoCard label={t('orders.totalAmount')} value={formatCurrency(order.totalAmount)} />
        <InfoCard label={t('orders.paymentInfo')} value={order.paymentStatusLabel || t(`orders.paymentStatusLabels.${order.paymentStatus || 'unpaid'}`, { defaultValue: t('orders.notPaid') })} />
      </section>

      <section className="rounded-[32px] border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-7">
        <h2 className="font-serif text-2xl font-semibold text-safi-green">{t('orders.deliveryInfo')}</h2>
        <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <InfoCard compact label={t('orders.recipientName')} value={order.recipientName || '-'} />
          <InfoCard compact label={t('orders.deliveryPhone')} value={order.phone || '-'} />
          <InfoCard compact label={t('orders.deliveryCity')} value={order.city || '-'} />
          <InfoCard compact label={t('orders.deliveryAddress')} value={order.deliveryAddress || t('orders.addressNotProvided', 'Адрес не указан')} />
          {order.comment && <InfoCard compact label={t('orders.deliveryComment')} value={order.comment} />}
        </div>
      </section>

      <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
        <div className="border-b border-safi-border p-6">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-safi-cream text-safi-gold">
              <PackageCheck className="h-5 w-5" />
            </div>
            <h2 className="font-serif text-2xl font-semibold text-safi-green">{t('orders.items')}</h2>
          </div>
        </div>

        <div className="hidden overflow-x-auto md:block">
          <table className="safi-numeric w-full min-w-[760px] text-left">
            <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
              <tr>
                <th className="px-7 py-4">{t('orders.tableProduct')}</th>
                <th className="px-7 py-4">{t('orders.quantity')}</th>
                <th className="px-7 py-4">{t('cart.price')}</th>
                <th className="px-7 py-4">{t('orders.amount')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-safi-border text-sm">
              {order.items.map((item) => (
                <tr key={item.id} className="transition-colors hover:bg-safi-cream/70">
                  <td className="px-7 py-5">
                    <div className="flex items-center gap-4">
                      <ProductImage image={item.image} alt={`${t('orders.productImage')}: ${item.productName}`} />
                      <span className="font-extrabold text-safi-green">{item.productName}</span>
                    </div>
                  </td>
                  <td className="px-7 py-5 font-bold text-safi-green">{item.quantity.toLocaleString('ru-RU')}</td>
                  <td className="px-7 py-5 text-safi-muted">{formatCurrency(item.unitPrice)}</td>
                  <td className="px-7 py-5 font-extrabold text-safi-green">{formatCurrency(item.totalPrice)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="divide-y divide-safi-border md:hidden">
          {order.items.map((item) => (
            <article key={item.id} className="p-5">
              <div className="flex items-center gap-4">
                <ProductImage image={item.image} alt={`${t('orders.productImage')}: ${item.productName}`} />
                <h3 className="font-serif text-xl font-semibold text-safi-green">{item.productName}</h3>
              </div>
              <div className="mt-4 grid grid-cols-2 gap-3">
                <InfoCard compact label={t('orders.quantity')} value={item.quantity.toLocaleString('ru-RU')} />
                <InfoCard compact label={t('cart.price')} value={formatCurrency(item.unitPrice)} />
                <InfoCard compact label={t('orders.amount')} value={formatCurrency(item.totalPrice)} />
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}

function OrderStatusBadge({ status }: { status: string }) {
  const { t } = useTranslation();
  const variant = status === 'cancelled' ? 'danger' : status === 'pending' ? 'warning' : 'success';

  return <Badge variant={variant}>{t(`orders.statusLabels.${status}`, { defaultValue: status })}</Badge>;
}

function ProductImage({ image, alt }: { image?: string; alt: string }) {
  const { t } = useTranslation();

  if (!image) {
    return (
      <div
        className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-[#F5F5F0] text-[9px] font-extrabold uppercase tracking-widest text-safi-muted"
        title={t('orders.noImage')}
        aria-label={t('orders.noImage')}
      >
        {t('orders.imagePlaceholder')}
      </div>
    );
  }

  return <img src={image} alt={alt} className="h-16 w-16 shrink-0 rounded-xl object-cover" />;
}

function InfoCard({ label, value, compact = false }: { label: string; value: string; compact?: boolean }) {
  return (
    <div className={`rounded-3xl border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)] ${compact ? 'p-4 shadow-none' : 'p-6'}`}>
      <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{label}</div>
      <div className="safi-numeric mt-2 font-serif text-2xl font-semibold text-safi-green">{value}</div>
    </div>
  );
}

function formatCurrency(value: number) {
  return `${value.toLocaleString('ru-RU')} ₸`;
}

function formatDate(value: string, language: string) {
  if (!value) {
    return '-';
  }

  return new Date(value).toLocaleDateString(language, { day: '2-digit', month: '2-digit', year: 'numeric' });
}
