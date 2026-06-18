import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, CreditCard, Minus, Plus, ShieldCheck, ShoppingBag, Trash2 } from 'lucide-react';
import { Button } from '../components/ui/Button';
import { Container } from '../components/ui/Container';
import { ToastItem, ToastStack, ToastType } from '../components/ui/Toast';
import { ApiError, createOrder, createTipTopPayPaymentIntent, getApiErrorState, getAuthToken, getNumber, getPublicDepositProducts, getPublicProducts, getString, getTipTopPayStatus, me, OrderPayload, TipTopPayStatus, unwrapRecord } from '../lib/api';
import { getStockLimit, isDepositProduct, isProductOrderable, useCart } from '../context/CartContext';
import { useTipTopPayWidget } from '../hooks/useTipTopPayWidget';

const formatCurrency = (value: number) => `${value.toLocaleString('ru-RU')} ₸`;
type CartPaymentStrategy = 'card_100' | 'card_50_deposit_50' | 'deposit_100';

const emptyDeliveryForm = {
  recipientName: '',
  phone: '',
  city: '',
  deliveryAddress: '',
  comment: '',
};

export default function CartPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const {
    items,
    totalItems,
    totalPrice,
    incrementProduct,
    decrementProduct,
    removeProduct,
    clearCart,
    syncProducts,
  } = useCart();
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const [isStartingOnlinePayment, setIsStartingOnlinePayment] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [checkoutMessage, setCheckoutMessage] = useState('');
  const [checkoutError, setCheckoutError] = useState('');
  const [tipTopStatus, setTipTopStatus] = useState<TipTopPayStatus | null>(null);
  const [depositBalance, setDepositBalance] = useState(0);
  const [paymentStrategy, setPaymentStrategy] = useState<CartPaymentStrategy>('card_100');
  const [deliveryForm, setDeliveryForm] = useState(emptyDeliveryForm);
  const { isWidgetLoading, startPayment } = useTipTopPayWidget();

  const showToast = useCallback((message: string, type: ToastType = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id, message, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3200);
  }, []);

  const refreshProducts = useCallback(async () => {
    if (items.length === 0) {
      return;
    }

    setIsSyncing(true);

    try {
      const [regularProducts, depositProducts] = await Promise.all([
        getPublicProducts(),
        getPublicDepositProducts(),
      ]);
      syncProducts([...regularProducts, ...depositProducts]);
    } catch {
      showToast(t('cart.stockSyncFailed', 'Не удалось обновить остатки товаров.'), 'error');
    } finally {
      setIsSyncing(false);
    }
  }, [items.length, showToast, syncProducts, t]);

  useEffect(() => {
    void refreshProducts();
  }, []);

  useEffect(() => {
    void getTipTopPayStatus()
      .then(setTipTopStatus)
      .catch(() => setTipTopStatus(null));
  }, []);

  useEffect(() => {
    if (!getAuthToken()) {
      return;
    }

    void me()
      .then((response) => {
        const user = unwrapRecord(response, ['user']);
        const profile = user.profile && typeof user.profile === 'object' && !Array.isArray(user.profile)
          ? user.profile as Record<string, unknown>
          : undefined;

        setDeliveryForm((current) => ({
          ...current,
          recipientName: current.recipientName || getString(user, ['name']) || '',
          phone: current.phone || getString(user, ['phone']) || getString(profile, ['phone']) || '',
          city: current.city || getString(user, ['city']) || getString(profile, ['city']) || '',
        }));
        setDepositBalance(getNumber(user, ['deposit_balance', 'depositBalance']) ?? 0);
      })
      .catch(() => {
        // Checkout validation still requires delivery fields if the profile cannot be loaded.
      });
  }, []);

  const invalidItems = useMemo(() => items.filter((item) => {
    const stockLimit = getStockLimit(item.product);

    return !isProductOrderable(item.product) || (stockLimit !== null && item.quantity > stockLimit);
  }), [items]);
  const hasDepositItems = useMemo(() => items.some((item) => isDepositProduct(item.product)), [items]);
  const hasRegularItems = useMemo(() => items.some((item) => !isDepositProduct(item.product)), [items]);
  const isDepositCart = hasDepositItems && !hasRegularItems;
  const hasMixedItems = hasDepositItems && hasRegularItems;
  const cardHalfAmount = Math.ceil(totalPrice / 2);
  const depositHalfAmount = Math.max(0, totalPrice - cardHalfAmount);
  const selectedCardAmount = paymentStrategy === 'card_50_deposit_50'
    ? cardHalfAmount
    : paymentStrategy === 'card_100' ? totalPrice : 0;
  const selectedDepositAmount = paymentStrategy === 'deposit_100'
    ? totalPrice
    : paymentStrategy === 'card_50_deposit_50' ? depositHalfAmount : 0;
  const depositCashbackAmount = Math.round(totalPrice * 0.2 * 100) / 100;
  const hasEnoughDepositForFifty = depositBalance >= depositHalfAmount;
  const hasEnoughDepositForSelected = depositBalance >= selectedDepositAmount;
  const isTipTopPayAvailable = tipTopStatus === null || (tipTopStatus.enabled && tipTopStatus.currency === 'KZT' && tipTopStatus.publicTerminalIdSet);
  const requiresOnlinePayment = paymentStrategy !== 'deposit_100';
  const hasValidPaymentStrategy = isDepositCart
    ? paymentStrategy === 'deposit_100'
    : !hasMixedItems && (paymentStrategy === 'card_100' || paymentStrategy === 'card_50_deposit_50');
  const hasDeliveryRequiredFields = deliveryForm.recipientName.trim() !== ''
    && deliveryForm.phone.trim() !== ''
    && deliveryForm.city.trim() !== ''
    && deliveryForm.deliveryAddress.trim() !== '';
  const isCheckoutBusy = isCheckingOut || isStartingOnlinePayment || isWidgetLoading;
  const canCheckout = items.length > 0
    && invalidItems.length === 0
    && !hasMixedItems
    && hasDeliveryRequiredFields
    && hasValidPaymentStrategy
    && hasEnoughDepositForSelected
    && (!requiresOnlinePayment || isTipTopPayAvailable)
    && !isCheckoutBusy;
  const checkoutButtonLabel = paymentStrategy === 'deposit_100'
    ? 'Оформить с депозита'
    : paymentStrategy === 'card_50_deposit_50' ? 'Оплатить 50/50' : 'Купить картой';

  useEffect(() => {
    if (isDepositCart && paymentStrategy !== 'deposit_100') {
      setPaymentStrategy('deposit_100');
      return;
    }

    if (!isDepositCart && paymentStrategy === 'deposit_100') {
      setPaymentStrategy('card_100');
    }
  }, [isDepositCart, paymentStrategy]);

  const handleIncrease = (productId: string) => {
    const item = items.find((cartItem) => String(cartItem.product.id) === String(productId));

    if (item && isDepositProduct(item.product) && totalPrice + item.product.price > depositBalance) {
      showToast('Недостаточно средств на депозитном балансе.', 'error');
      return;
    }

    const result = incrementProduct(productId);

    if (!result.ok) {
      showToast(result.reason === 'stock_limit'
        ? t('cart.stockLimitReached', 'Недостаточно товара на складе')
        : t('cart.outOfStock', 'Нет в наличии'), 'error');
    }
  };

  const buildOrderPayload = (): OrderPayload => ({
    items: items.map((item) => ({
      product_id: item.product.id,
      quantity: item.quantity,
    })),
    recipient_name: deliveryForm.recipientName.trim(),
    phone: deliveryForm.phone.trim(),
    city: deliveryForm.city.trim(),
    delivery_address: deliveryForm.deliveryAddress.trim(),
    comment: deliveryForm.comment.trim(),
    payment_strategy: paymentStrategy,
  });

  const validateCheckout = () => {
    setCheckoutMessage('');
    setCheckoutError('');

    if (items.length === 0) {
      setCheckoutError(t('cart.emptyTitle', 'Корзина пуста'));
      return false;
    }

    if (!getAuthToken()) {
      navigate('/login?redirect=/cart');
      return false;
    }

    if (invalidItems.length > 0) {
      setCheckoutError(t('cart.invalidStockWarning', 'В корзине есть товары, которых нет в нужном количестве.'));
      return false;
    }

    if (hasMixedItems) {
      setCheckoutError(t('cart.mixedDepositCart', 'Нельзя смешивать депозитные и обычные товары в одной корзине. Очистите корзину.'));
      return false;
    }

    if (isDepositCart && paymentStrategy !== 'deposit_100') {
      setCheckoutError('Депозитные товары можно оплатить только с депозитного баланса.');
      return false;
    }

    if (!isDepositCart && paymentStrategy === 'deposit_100') {
      setCheckoutError('Обычные товары нельзя оплатить только депозитом.');
      return false;
    }

    if (!hasEnoughDepositForSelected) {
      setCheckoutError('Недостаточно средств на депозитном балансе.');
      return false;
    }

    if (requiresOnlinePayment && !isTipTopPayAvailable) {
      setCheckoutError(t('orders.onlinePaymentUnavailable', 'Онлайн-оплата временно недоступна'));
      return false;
    }

    if (!hasDeliveryRequiredFields) {
      setCheckoutError(t('cart.deliveryRequired', 'Укажите телефон и адрес доставки.'));
      return false;
    }

    return true;
  };

  const handleCheckout = async () => {
    if (!validateCheckout()) {
      return;
    }

    setIsCheckingOut(true);

    try {
      const order = await createOrder(buildOrderPayload());
      clearCart();
      setDeliveryForm(emptyDeliveryForm);

      if (paymentStrategy === 'deposit_100') {
        const message = t('cart.depositPurchaseCreated', 'Покупка с депозитного баланса выполнена.');
        setCheckoutMessage(message);
        showToast(message);
        navigate('/dashboard/orders', { state: { orderCreated: true } });
        return;
      }

      setIsStartingOnlinePayment(true);
      showToast(t('orders.orderCreated'));

      const intent = await createTipTopPayPaymentIntent(order.id);
      await startPayment(intent, {
        onSuccess: () => {
          showToast(t('orders.paymentSubmitted'));
          navigate(`/payment/success?order=${encodeURIComponent(order.id)}`);
        },
        onFail: (error) => {
          const message = error instanceof Error
            ? error.message
            : t('orders.paymentFailed');
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
      setCheckoutError(message || t('orders.paymentStartError'));
      showToast(message || t('orders.paymentStartError'), 'error');
      await refreshProducts();
    } finally {
      setIsCheckingOut(false);
      setIsStartingOnlinePayment(false);
    }
  };

  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-safi-bg py-20">
        <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />
        <Container>
          <div className="mx-auto flex max-w-2xl flex-col items-center text-center">
            <div className="mb-8 flex h-24 w-24 items-center justify-center rounded-[28px] border border-safi-green/5 bg-white text-safi-gold shadow-sm">
              <ShoppingBag className="h-10 w-10" />
            </div>
            <h1 className="mb-4 font-serif text-4xl font-bold text-safi-green md:text-5xl">
              {t('cart.emptyTitle', 'Корзина пуста')}
            </h1>
            <p className="mb-8 max-w-xl text-base leading-relaxed text-safi-text/70">
              {t('cart.emptyText', 'Добавьте продукты из каталога, чтобы они появились здесь.')}
            </p>
            <Button to="/products" size="lg">
              {t('cart.continueShopping', 'Продолжить покупки')}
            </Button>
          </div>
        </Container>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-safi-bg py-16 md:py-20">
      <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />
      <Container>
        <div className="mb-10 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <Link to="/products" className="mb-5 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-safi-green/60 transition-colors hover:text-safi-gold">
              <ArrowLeft className="h-4 w-4" />
              {t('cart.continueShopping', 'Продолжить покупки')}
            </Link>
            <h1 className="mb-3 font-serif text-4xl font-bold text-safi-green md:text-5xl">
              {t('cart.title', 'Корзина')}
            </h1>
            <p className="max-w-2xl text-sm font-bold uppercase tracking-wider text-safi-text/60">
              {t('cart.subtitle', 'Проверьте товары перед оформлением заказа.')}
            </p>
          </div>

          <button
            type="button"
            onClick={clearCart}
            className="w-fit cursor-pointer rounded-xl border border-red-500/10 px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-red-500 transition-colors hover:bg-red-50"
          >
            {t('cart.clear', 'Очистить корзину')}
          </button>
        </div>

        {(checkoutMessage || checkoutError || invalidItems.length > 0) && (
          <div className={`mb-6 rounded-2xl border px-4 py-3 text-sm font-bold ${checkoutError || invalidItems.length > 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {checkoutError || checkoutMessage || t('cart.invalidStockWarning', 'В корзине есть товары, которых нет в нужном количестве.')}
          </div>
        )}

        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div className="space-y-4">
            {items.map((item) => {
              const stockLimit = getStockLimit(item.product);
              const hasStockLimit = stockLimit !== null;
              const isOrderable = isProductOrderable(item.product);
              const isInvalid = !isOrderable || (hasStockLimit && item.quantity > stockLimit);
              const isDepositItem = isDepositProduct(item.product);
              const canDecrease = item.quantity > 1;
              const canIncrease = isOrderable
                && (!hasStockLimit || item.quantity < stockLimit)
                && (!isDepositItem || totalPrice + item.product.price <= depositBalance);

              return (
                <div key={item.product.id} className="grid gap-5 rounded-[28px] border border-safi-green/5 bg-white p-4 shadow-sm md:grid-cols-[140px_minmax(0,1fr)] md:p-5">
                  <div className="aspect-[4/3] overflow-hidden rounded-2xl bg-[#F5F5F0] md:aspect-square">
                    <img src={item.product.image} alt={item.product.name} className="h-full w-full object-cover" />
                  </div>

                  <div className="flex min-w-0 flex-col gap-5">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                      <div className="min-w-0">
                        <div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold">{item.product.category}</div>
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="font-serif text-2xl font-bold text-safi-green">{item.product.name}</h2>
                          {isDepositItem && (
                            <span className="rounded-full bg-safi-gold/15 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green">
                              Только депозит
                            </span>
                          )}
                        </div>
                        <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-safi-text/70">{item.product.shortDescription}</p>
                        <div className={`mt-3 inline-flex rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] ${isInvalid ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'}`}>
                          {isInvalid
                            ? t('cart.outOfStock', 'Нет в наличии')
                            : hasStockLimit ? `${t('cart.stock', 'Остаток')}: ${stockLimit}` : t('cart.inStock', 'В наличии')}
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={() => removeProduct(item.product.id)}
                        className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-[#F5F5F0] text-red-500 transition-colors hover:bg-red-50"
                        aria-label={t('cart.remove', 'Удалить')}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>

                    <div className="grid gap-4 border-t border-safi-green/5 pt-5 sm:grid-cols-3 sm:items-center">
                      <div>
                        <div className="mb-1 text-[10px] font-bold uppercase tracking-widest text-safi-text/40">{t('cart.price', 'Цена')}</div>
                        <div className="text-lg font-bold text-safi-green">{formatCurrency(item.product.price)}</div>
                      </div>

                      <div>
                        <div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-safi-text/40">{t('cart.quantity', 'Количество')}</div>
                        <div className="flex w-fit items-center rounded-xl border border-safi-green/10 bg-[#F5F5F0] p-1">
                          <button
                            type="button"
                            onClick={() => decrementProduct(item.product.id)}
                            disabled={!canDecrease}
                            className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-safi-green transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="-"
                          >
                            <Minus className="h-4 w-4" />
                          </button>
                          <span className="min-w-10 text-center text-sm font-bold text-safi-green">{item.quantity}</span>
                          <button
                            type="button"
                            disabled={!canIncrease || isInvalid}
                            onClick={() => handleIncrease(item.product.id)}
                            className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-safi-green transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="+"
                          >
                            <Plus className="h-4 w-4" />
                          </button>
                        </div>
                      </div>

                      <div className="sm:text-right">
                        <div className="mb-1 text-[10px] font-bold uppercase tracking-widest text-safi-text/40">{t('cart.subtotal', 'Сумма')}</div>
                        <div className="font-serif text-xl font-bold text-safi-green">{formatCurrency(item.subtotal)}</div>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          <aside className="h-fit rounded-[28px] bg-safi-green p-6 text-white shadow-xl lg:sticky lg:top-28">
            <div className="mb-6 border-b border-white/10 pb-6">
              <h2 className="font-serif text-2xl font-bold">{t('cart.deliveryTitle', 'Доставка')}</h2>
              <div className="mt-5 space-y-4">
                <DeliveryInput
                  label={t('cart.recipientName', 'Получатель')}
                  value={deliveryForm.recipientName}
                  onChange={(value) => setDeliveryForm((current) => ({ ...current, recipientName: value }))}
                  required
                />
                <DeliveryInput
                  label={t('cart.deliveryPhone', 'Телефон получателя')}
                  value={deliveryForm.phone}
                  onChange={(value) => setDeliveryForm((current) => ({ ...current, phone: value }))}
                  required
                />
                <DeliveryInput
                  label={t('cart.deliveryCity', 'Город')}
                  value={deliveryForm.city}
                  onChange={(value) => setDeliveryForm((current) => ({ ...current, city: value }))}
                  required
                />
                <DeliveryInput
                  label={t('cart.deliveryAddress', 'Адрес доставки')}
                  value={deliveryForm.deliveryAddress}
                  onChange={(value) => setDeliveryForm((current) => ({ ...current, deliveryAddress: value }))}
                  required
                  multiline
                />
                <DeliveryInput
                  label={t('cart.deliveryComment', 'Комментарий')}
                  value={deliveryForm.comment}
                  onChange={(value) => setDeliveryForm((current) => ({ ...current, comment: value }))}
                  multiline
                />
              </div>
            </div>

            <div className="mb-6 flex items-center justify-between border-b border-white/10 pb-5">
              <h2 className="font-serif text-2xl font-bold">{t('cart.summary', 'Итого')}</h2>
              <ShoppingBag className="h-6 w-6 text-safi-gold" />
            </div>

            <div className="space-y-4 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-white/60">{t('cart.items', 'Товары')}</span>
                <span className="font-bold">{totalItems}</span>
              </div>
              {isDepositCart && (
                <label className="flex items-center justify-between gap-4 rounded-2xl border border-safi-gold/30 bg-white/10 p-4">
                  <span>
                    <span className="block text-[10px] font-extrabold uppercase tracking-[0.14em] text-white/50">Способ оплаты</span>
                    <span className="mt-1 block font-bold text-white">100% депозит</span>
                  </span>
                  <input
                    type="radio"
                    checked
                    disabled
                    readOnly
                    className="h-4 w-4 border-white/30 text-safi-gold"
                  />
                </label>
              )}
              {!isDepositCart && !hasMixedItems && (
                <div className="space-y-3">
                  <div className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-white/50">Способ оплаты</div>
                  <PaymentOption
                    checked={paymentStrategy === 'card_100'}
                    label="100% с карты"
                    onChange={() => setPaymentStrategy('card_100')}
                  />
                  <PaymentOption
                    checked={paymentStrategy === 'card_50_deposit_50'}
                    disabled={!hasEnoughDepositForFifty}
                    label="50% с карты / 50% с депозита"
                    note={!hasEnoughDepositForFifty ? 'Недостаточно средств на депозитном балансе.' : undefined}
                    onChange={() => setPaymentStrategy('card_50_deposit_50')}
                  />
                </div>
              )}
              <div className="rounded-2xl bg-white/5 p-4 text-xs font-bold leading-6 text-white/70">
                <SummaryLine label="Сумма заказа" value={formatCurrency(totalPrice)} />
                <SummaryLine label="К оплате картой" value={formatCurrency(selectedCardAmount)} />
                <SummaryLine label="С депозита" value={formatCurrency(selectedDepositAmount)} />
                {isDepositCart && (
                  <>
                    <SummaryLine label="Доступно на депозите" value={formatCurrency(depositBalance)} />
                    <SummaryLine label="Cashback 20%" value={formatCurrency(depositCashbackAmount)} />
                  </>
                )}
              </div>
              {selectedDepositAmount > 0 && !hasEnoughDepositForSelected && (
                <div className="rounded-2xl border border-red-300/30 bg-red-500/10 px-4 py-3 text-xs font-bold text-red-100">
                  Недостаточно средств на депозитном балансе.
                </div>
              )}
              <div className="flex items-end justify-between border-t border-white/10 pt-5">
                <span className="text-white/60">{t('cart.subtotal', 'Сумма')}</span>
                <span className="font-serif text-3xl font-bold text-safi-gold">{formatCurrency(totalPrice)}</span>
              </div>
            </div>

            <button
              type="button"
              disabled={!canCheckout || isSyncing}
              onClick={() => void handleCheckout()}
              className="mt-8 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-safi-gold bg-safi-gold px-8 py-4 text-sm font-bold uppercase tracking-widest text-safi-green shadow-lg transition-all hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {paymentStrategy !== 'deposit_100' && <CreditCard className="h-4 w-4" />}
              {isCheckingOut || isWidgetLoading
                ? paymentStrategy === 'deposit_100' ? t('cart.depositCheckoutLoading', 'Покупаем...') : t('orders.openingPayment', 'Открываем оплату...')
                : checkoutButtonLabel}
            </button>

            <div className="mt-5 flex items-start gap-3 rounded-2xl bg-white/5 p-4 text-xs leading-relaxed text-white/60">
              <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-safi-gold" />
              <span>{t('cart.checkoutNote', 'Менеджер подтвердит наличие товаров и условия доставки после заявки.')}</span>
            </div>
          </aside>
        </div>
      </Container>
    </div>
  );
}

function PaymentOption({
  checked,
  disabled = false,
  label,
  note,
  onChange,
}: {
  checked: boolean;
  disabled?: boolean;
  label: string;
  note?: string;
  onChange: () => void;
}) {
  return (
    <label className={`flex cursor-pointer items-start justify-between gap-4 rounded-2xl border p-4 transition-colors ${checked ? 'border-safi-gold/60 bg-white/15' : 'border-white/10 bg-white/5'} ${disabled ? 'cursor-not-allowed opacity-55' : 'hover:bg-white/10'}`}>
      <span>
        <span className="block text-sm font-bold text-white">{label}</span>
        {note && <span className="mt-1 block text-xs font-bold text-red-100">{note}</span>}
      </span>
      <input
        type="radio"
        checked={checked}
        disabled={disabled}
        onChange={onChange}
        className="mt-1 h-4 w-4 border-white/30 text-safi-gold"
      />
    </label>
  );
}

function SummaryLine({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span>{label}</span>
      <span className="text-white">{value}</span>
    </div>
  );
}

function DeliveryInput({
  label,
  value,
  onChange,
  required = false,
  multiline = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
  multiline?: boolean;
}) {
  return (
    <label className="block">
      <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.14em] text-white/60">
        {label}{required ? ' *' : ''}
      </span>
      {multiline ? (
        <textarea
          rows={3}
          value={value}
          required={required}
          onChange={(event) => onChange(event.target.value)}
          className="w-full resize-none rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none placeholder:text-white/35 focus:border-safi-gold focus:bg-white/15"
        />
      ) : (
        <input
          type="text"
          value={value}
          required={required}
          onChange={(event) => onChange(event.target.value)}
          className="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none placeholder:text-white/35 focus:border-safi-gold focus:bg-white/15"
        />
      )}
    </label>
  );
}
