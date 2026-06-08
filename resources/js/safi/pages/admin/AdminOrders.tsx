import { Fragment, useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, ChevronUp, Filter, RefreshCcw, Search, ShoppingBag } from 'lucide-react';
import { AdminBadge, AdminStatCard, AdminTable } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack } from '../../components/ui/Toast';
import { getAdminOrders, getApiErrorState, Order, updateAdminOrderStatus } from '../../lib/api';
import { useAdminContext } from '../../components/admin/AdminLayout';

const orderStatuses = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'] as const;

export default function AdminOrders() {
  const { t, i18n } = useTranslation();
  const { currentUser } = useAdminContext();
  const [orders, setOrders] = useState<Order[]>([]);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [status, setStatus] = useState('');
  const [expandedOrderId, setExpandedOrderId] = useState<string | null>(null);
  const [updatingOrderId, setUpdatingOrderId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const language = i18n.resolvedLanguage || i18n.language;
  const canManageStatus = ['admin', 'super_admin'].includes(currentUser.role);

  const showToast = useCallback((message: string, type: ToastItem['type'] = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id, message, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3200);
  }, []);

  const loadOrders = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminOrders({
        search: debouncedSearch || undefined,
        status: status || undefined,
        per_page: 50,
      });
      setOrders(response.orders);
    } catch (caughtError) {
      setOrders([]);
      setError(getApiErrorState(caughtError).error || t('orders.error'));
    } finally {
      setIsLoading(false);
    }
  }, [debouncedSearch, status, t]);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  const summary = useMemo(() => ({
    total: orders.length,
    amount: orders.reduce((sum, order) => sum + order.totalAmount, 0),
    pv: orders.reduce((sum, order) => sum + order.totalPv, 0),
    pending: orders.filter((order) => order.status === 'pending').length,
  }), [orders]);

  const handleStatusChange = async (order: Order, nextStatus: string) => {
    if (!canManageStatus || nextStatus === order.status) {
      return;
    }

    setUpdatingOrderId(order.id);

    try {
      const updatedOrder = await updateAdminOrderStatus(order.id, nextStatus);
      setOrders((current) => current.map((currentOrder) => currentOrder.id === updatedOrder.id ? updatedOrder : currentOrder));
      showToast(t('orders.statusUpdated'));
    } catch (caughtError) {
      showToast(getApiErrorState(caughtError).error || t('orders.detailError'), 'error');
    } finally {
      setUpdatingOrderId(null);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />

      <section className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 className="mb-1 font-serif text-3xl font-bold text-safi-green">{t('orders.orders')}</h1>
          <p className="text-sm text-safi-text/70">{t('orders.adminSubtitle')}</p>
        </div>
        <button
          type="button"
          onClick={() => void loadOrders()}
          className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#F5F5F0] px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
        >
          <RefreshCcw className="h-4 w-4" />
          {t('orders.refresh')}
        </button>
      </section>

      <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <AdminStatCard title={t('orders.orders')} value={summary.total.toLocaleString('ru-RU')} icon={ShoppingBag} />
        <AdminStatCard title={t('orders.totalAmount')} value={formatCurrency(summary.amount)} icon={ShoppingBag} />
        <AdminStatCard title={t('orders.totalPv')} value={`${summary.pv.toLocaleString('ru-RU')} PV`} icon={ShoppingBag} />
        <AdminStatCard title={t('orders.statusLabels.pending')} value={summary.pending.toLocaleString('ru-RU')} icon={ShoppingBag} />
      </section>

      <section className="flex flex-col gap-4 rounded-[24px] border border-safi-green/5 bg-white p-4 shadow-sm md:flex-row">
        <label className="relative flex-1">
          <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-text/40" />
          <input
            type="text"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('orders.searchPlaceholder')}
            className="w-full rounded-xl bg-[#F5F5F0] py-3 pl-12 pr-4 text-sm font-medium text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
          />
        </label>
        <label className="relative md:w-64">
          <Filter className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-safi-text/40" />
          <select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            className="w-full cursor-pointer appearance-none rounded-xl bg-[#F5F5F0] py-3 pl-12 pr-4 text-sm font-bold text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
            aria-label={t('orders.statusFilter')}
          >
            <option value="">{t('orders.allStatuses')}</option>
            {orderStatuses.map((item) => (
              <option key={item} value={item}>{t(`orders.statusLabels.${item}`)}</option>
            ))}
          </select>
        </label>
      </section>

      {isLoading && <LoadingState title={t('orders.loading')} />}
      {!isLoading && error && <ErrorState title={t('orders.error')} description={error} onRetry={loadOrders} />}
      {!isLoading && !error && orders.length === 0 && <EmptyState title={t('orders.notFound')} />}

      {!isLoading && !error && orders.length > 0 && (
        <AdminTable headers={[
          t('orders.orderId'),
          t('orders.partner'),
          t('orders.contacts'),
          t('orders.items'),
          t('orders.amount'),
          t('orders.pv'),
          t('orders.status'),
          t('orders.date'),
          t('orders.actions'),
        ]}>
          {orders.map((order) => (
            <Fragment key={order.id}>
              <tr className="transition-colors hover:bg-safi-green/5">
                <td className="px-6 py-4">
                  <div className="font-bold text-safi-text">#{order.id}</div>
                  {order.orderNumber && <div className="mt-1 text-[10px] font-mono text-safi-text/50">{order.orderNumber}</div>}
                </td>
                <td className="px-6 py-4">
                  <div className="font-bold text-safi-green">{order.user?.name || '-'}</div>
                  <div className="mt-1 text-[10px] font-mono text-safi-text/50">{order.user?.id || order.userId || '-'}</div>
                </td>
                <td className="px-6 py-4">
                  <div className="text-sm font-bold text-safi-green">{order.user?.login || '-'}</div>
                  <div className="mt-1 text-xs text-safi-text/60">{order.user?.email || '-'}</div>
                  <div className="mt-2 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-gold">{order.city || '-'}</div>
                  <div className="mt-1 text-xs text-safi-text/60">{order.phone || order.user?.phone || '-'}</div>
                </td>
                <td className="px-6 py-4 font-bold text-safi-green">{order.itemsCount.toLocaleString('ru-RU')}</td>
                <td className="px-6 py-4 font-bold text-safi-green">{formatCurrency(order.totalAmount)}</td>
                <td className="px-6 py-4 font-bold text-safi-gold">{order.totalPv.toLocaleString('ru-RU')} PV</td>
                <td className="px-6 py-4">
                  {canManageStatus ? (
                    <select
                      value={order.status}
                      disabled={updatingOrderId === order.id}
                      onChange={(event) => void handleStatusChange(order, event.target.value)}
                      className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-safi-green outline-none disabled:cursor-not-allowed disabled:opacity-60"
                      aria-label={t('orders.changeStatus')}
                    >
                      {orderStatuses.map((item) => (
                        <option key={item} value={item}>{t(`orders.statusLabels.${item}`)}</option>
                      ))}
                    </select>
                  ) : (
                    <OrderStatusBadge status={order.status} />
                  )}
                </td>
                <td className="px-6 py-4 text-xs text-safi-text/70">{formatDate(order.createdAt, language)}</td>
                <td className="px-6 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => setExpandedOrderId((current) => current === order.id ? null : order.id)}
                    className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white"
                  >
                    {expandedOrderId === order.id ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                    {t('orders.details')}
                  </button>
                </td>
              </tr>
              {expandedOrderId === order.id && (
                <tr className="bg-safi-cream/60">
                  <td colSpan={9} className="px-6 py-5">
                    <div className="grid gap-5 xl:grid-cols-[0.34fr_0.66fr]">
                      <section className="rounded-2xl border border-safi-border bg-white p-5">
                        <h3 className="font-serif text-xl font-semibold text-safi-green">{t('orders.deliveryInfo')}</h3>
                        <div className="mt-4 grid gap-3 text-sm">
                          <Metric label={t('orders.recipientName')} value={order.recipientName || order.user?.name || '-'} />
                          <Metric label={t('orders.deliveryPhone')} value={order.phone || order.user?.phone || '-'} />
                          <Metric label={t('orders.deliveryCity')} value={order.city || '-'} />
                          <Metric label={t('orders.deliveryAddress')} value={order.deliveryAddress || t('orders.addressNotProvided', 'Адрес не указан')} />
                          {order.comment && <Metric label={t('orders.deliveryComment')} value={order.comment} />}
                        </div>
                      </section>
                      <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {order.items.map((item) => (
                          <div key={item.id} className="rounded-2xl border border-safi-border bg-white p-4">
                            <div className="flex items-center gap-4">
                              <ProductImage image={item.image} alt={`${t('orders.productImage')}: ${item.productName}`} />
                              <div className="font-serif text-lg font-semibold text-safi-green">{item.productName}</div>
                            </div>
                            <div className="mt-3 grid grid-cols-3 gap-2 text-xs">
                              <Metric label={t('orders.quantity')} value={item.quantity.toLocaleString('ru-RU')} />
                              <Metric label={t('orders.amount')} value={formatCurrency(item.totalPrice)} />
                              <Metric label={t('orders.pv')} value={`${item.totalPv.toLocaleString('ru-RU')} PV`} />
                            </div>
                          </div>
                        ))}
                      </section>
                    </div>
                  </td>
                </tr>
              )}
            </Fragment>
          ))}
        </AdminTable>
      )}
    </div>
  );
}

function OrderStatusBadge({ status }: { status: string }) {
  const { t } = useTranslation();
  const variant = status === 'cancelled' ? 'danger' : status === 'pending' ? 'warning' : 'success';

  return <AdminBadge variant={variant}>{t(`orders.statusLabels.${status}`, { defaultValue: status })}</AdminBadge>;
}

function ProductImage({ image, alt }: { image?: string; alt: string }) {
  const { t } = useTranslation();

  if (!image) {
    return (
      <div
        className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#F5F5F0] text-[9px] font-extrabold uppercase tracking-widest text-safi-muted"
        title={t('orders.noImage')}
        aria-label={t('orders.noImage')}
      >
        {t('orders.imagePlaceholder')}
      </div>
    );
  }

  return <img src={image} alt={alt} className="h-14 w-14 shrink-0 rounded-xl object-cover" />;
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">{label}</div>
      <div className="safi-numeric mt-1 font-bold text-safi-green">{value}</div>
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
