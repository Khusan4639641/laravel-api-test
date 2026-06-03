import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Eye, PackageCheck, ShoppingBag } from 'lucide-react';
import { Badge, StatCard } from '../../components/dashboard/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack } from '../../components/ui/Toast';
import { getApiErrorState, getOrders, Order } from '../../lib/api';

export default function Orders() {
  const { t, i18n } = useTranslation();
  const location = useLocation();
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const language = i18n.resolvedLanguage || i18n.language;

  const showToast = useCallback((message: string, type: ToastItem['type'] = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id, message, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3200);
  }, []);

  const loadOrders = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      setOrders(await getOrders());
    } catch (caughtError) {
      setOrders([]);
      setError(getApiErrorState(caughtError).error || t('orders.error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  useEffect(() => {
    if ((location.state as { orderCreated?: boolean } | null)?.orderCreated) {
      showToast(t('orders.orderCreated'));
      window.history.replaceState({}, document.title);
    }
  }, [location.state, showToast, t]);

  const summary = useMemo(() => ({
    total: orders.length,
    amount: orders.reduce((sum, order) => sum + order.totalAmount, 0),
    pv: orders.reduce((sum, order) => sum + order.totalPv, 0),
  }), [orders]);

  return (
    <div className="space-y-8">
      <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />

      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">{t('orders.orders')}</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">{t('orders.myOrders')}</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">{t('orders.pageSubtitle')}</p>
          </div>
          <Link
            to="/dashboard/products"
            className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green-hover"
          >
            <ShoppingBag className="h-4 w-4" />
            {t('menu.products')}
          </Link>
        </div>
      </section>

      {isLoading && <LoadingState title={t('orders.loading')} />}
      {!isLoading && error && <ErrorState title={t('orders.error')} description={error} onRetry={loadOrders} />}

      {!isLoading && !error && (
        <>
          <section className="grid gap-4 md:grid-cols-3">
            <StatCard title={t('orders.orders')} value={summary.total.toLocaleString('ru-RU')} icon={<PackageCheck className="h-5 w-5" />} variant="dark" />
            <StatCard title={t('orders.totalAmount')} value={formatCurrency(summary.amount)} />
            <StatCard title={t('orders.totalPv')} value={`${summary.pv.toLocaleString('ru-RU')} PV`} />
          </section>

          {orders.length === 0 ? (
            <EmptyState title={t('orders.empty')} />
          ) : (
            <section className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
              <div className="hidden overflow-x-auto md:block">
                <table className="safi-numeric w-full min-w-[900px] text-left">
                  <thead className="bg-safi-cream text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                    <tr>
                      <th className="px-7 py-4">{t('orders.orderId')}</th>
                      <th className="px-7 py-4">{t('orders.date')}</th>
                      <th className="px-7 py-4">{t('orders.items')}</th>
                      <th className="px-7 py-4">{t('orders.amount')}</th>
                      <th className="px-7 py-4">{t('orders.pv')}</th>
                      <th className="px-7 py-4">{t('orders.status')}</th>
                      <th className="px-7 py-4 text-right">{t('orders.actions')}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-safi-border text-sm">
                    {orders.map((order) => (
                      <tr key={order.id} className="transition-colors hover:bg-safi-cream/70">
                        <td className="px-7 py-5 font-extrabold text-safi-green">#{order.id}</td>
                        <td className="px-7 py-5 text-safi-muted">{formatDate(order.createdAt, language)}</td>
                        <td className="px-7 py-5">
                          <div className="flex items-center gap-3">
                            <OrderItemThumbs order={order} />
                            <span className="font-bold text-safi-green">{order.itemsCount.toLocaleString('ru-RU')}</span>
                          </div>
                        </td>
                        <td className="px-7 py-5 font-extrabold text-safi-green">{formatCurrency(order.totalAmount)}</td>
                        <td className="px-7 py-5 font-extrabold text-safi-gold">{order.totalPv.toLocaleString('ru-RU')} PV</td>
                        <td className="px-7 py-5"><OrderStatusBadge status={order.status} /></td>
                        <td className="px-7 py-5 text-right">
                          <Link to={`/dashboard/orders/${order.id}`} className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white">
                            <Eye className="h-4 w-4" />
                            {t('orders.details')}
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="divide-y divide-safi-border md:hidden">
                {orders.map((order) => (
                  <article key={order.id} className="p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <h2 className="font-serif text-2xl font-semibold text-safi-green">#{order.id}</h2>
                        <p className="mt-1 text-xs font-bold uppercase tracking-[0.12em] text-safi-muted">{formatDate(order.createdAt, language)}</p>
                      </div>
                      <OrderStatusBadge status={order.status} />
                    </div>
                    <div className="mt-5 grid grid-cols-3 gap-3 text-sm">
                      <div className="col-span-3 mb-1">
                        <OrderItemThumbs order={order} />
                      </div>
                      <Metric label={t('orders.items')} value={order.itemsCount.toLocaleString('ru-RU')} />
                      <Metric label={t('orders.amount')} value={formatCurrency(order.totalAmount)} />
                      <Metric label={t('orders.pv')} value={`${order.totalPv.toLocaleString('ru-RU')} PV`} />
                    </div>
                    <Link to={`/dashboard/orders/${order.id}`} className="mt-5 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white">
                      <Eye className="h-4 w-4" />
                      {t('orders.details')}
                    </Link>
                  </article>
                ))}
              </div>
            </section>
          )}
        </>
      )}
    </div>
  );
}

function OrderItemThumbs({ order }: { order: Order }) {
  const { t } = useTranslation();
  const thumbs = order.items.slice(0, 3);

  return (
    <div className="flex -space-x-2">
      {thumbs.length === 0 && <ProductImage image={undefined} alt={t('orders.noImage')} small />}
      {thumbs.map((item) => (
        <ProductImage key={item.id} image={item.image} alt={`${t('orders.productImage')}: ${item.productName}`} small />
      ))}
      {order.items.length > 3 && (
        <div className="safi-numeric flex h-10 w-10 items-center justify-center rounded-xl border-2 border-white bg-safi-cream text-[10px] font-extrabold text-safi-green">
          +{order.items.length - 3}
        </div>
      )}
    </div>
  );
}

function ProductImage({ image, alt, small = false }: { image?: string; alt: string; small?: boolean }) {
  const { t } = useTranslation();
  const size = small ? 'h-10 w-10' : 'h-14 w-14';

  if (!image) {
    return (
      <div
        className={`${size} flex shrink-0 items-center justify-center rounded-xl border-2 border-white bg-[#F5F5F0] text-[9px] font-extrabold uppercase tracking-widest text-safi-muted`}
        title={t('orders.noImage')}
        aria-label={t('orders.noImage')}
      >
        {t('orders.imagePlaceholder')}
      </div>
    );
  }

  return <img src={image} alt={alt} className={`${size} shrink-0 rounded-xl border-2 border-white object-cover`} />;
}

function OrderStatusBadge({ status }: { status: string }) {
  const { t } = useTranslation();
  const variant = status === 'cancelled' ? 'danger' : status === 'pending' ? 'warning' : 'success';

  return <Badge variant={variant}>{t(`orders.statusLabels.${status}`, { defaultValue: status })}</Badge>;
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-safi-border bg-safi-cream p-3">
      <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">{label}</div>
      <div className="safi-numeric mt-1 font-extrabold text-safi-green">{value}</div>
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
