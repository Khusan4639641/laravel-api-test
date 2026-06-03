import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Minus, Plus, ShieldCheck, ShoppingBag, Trash2 } from 'lucide-react';
import { Button } from '../components/ui/Button';
import { Container } from '../components/ui/Container';
import { ToastItem, ToastStack, ToastType } from '../components/ui/Toast';
import { ApiError, createOrder, getApiErrorState, getAuthToken, getPublicProducts } from '../lib/api';
import { getAvailableStock, isProductOrderable, useCart } from '../context/CartContext';

const formatCurrency = (value: number) => `${value.toLocaleString('ru-RU')} ₸`;

export default function CartPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const {
    items,
    totalItems,
    totalPrice,
    totalPv,
    decrementProduct,
    addProduct,
    removeProduct,
    clearCart,
    syncProducts,
  } = useCart();
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [checkoutMessage, setCheckoutMessage] = useState('');
  const [checkoutError, setCheckoutError] = useState('');

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
      syncProducts(await getPublicProducts());
    } catch {
      showToast(t('cart.stockSyncFailed', 'Не удалось обновить остатки товаров.'), 'error');
    } finally {
      setIsSyncing(false);
    }
  }, [items.length, showToast, syncProducts, t]);

  useEffect(() => {
    void refreshProducts();
  }, []);

  const invalidItems = useMemo(() => items.filter((item) => !isProductOrderable(item.product) || item.quantity > getAvailableStock(item.product)), [items]);
  const canCheckout = items.length > 0 && invalidItems.length === 0 && !isCheckingOut;

  const handleIncrease = (productId: string) => {
    const item = items.find((cartItem) => String(cartItem.product.id) === String(productId));

    if (!item) {
      return;
    }

    const result = addProduct(item.product);

    if (!result.ok) {
      showToast(result.reason === 'stock_limit'
        ? t('cart.stockLimitReached', 'Недостаточно товара на складе')
        : t('cart.outOfStock', 'Нет в наличии'), 'error');
    }
  };

  const handleCheckout = async () => {
    setCheckoutMessage('');
    setCheckoutError('');

    if (items.length === 0) {
      setCheckoutError(t('cart.emptyTitle', 'Корзина пуста'));
      return;
    }

    if (!getAuthToken()) {
      navigate('/login?redirect=/cart');
      return;
    }

    if (invalidItems.length > 0) {
      setCheckoutError(t('cart.invalidStockWarning', 'В корзине есть товары, которых нет в нужном количестве.'));
      return;
    }

    setIsCheckingOut(true);

    try {
      await createOrder({
        items: items.map((item) => ({
          product_id: item.product.id,
          quantity: item.quantity,
        })),
      });

      clearCart();
      setCheckoutMessage(t('cart.orderCreated', 'Заказ создан'));
      showToast(t('cart.orderCreated', 'Заказ создан'));
      window.setTimeout(() => navigate('/dashboard/products'), 1200);
    } catch (caughtError) {
      const message = caughtError instanceof ApiError
        ? caughtError.message
        : getApiErrorState(caughtError).error;
      setCheckoutError(message || t('cart.checkoutFailed', 'Не удалось оформить заказ.'));
      showToast(message || t('cart.checkoutFailed', 'Не удалось оформить заказ.'), 'error');
      await refreshProducts();
    } finally {
      setIsCheckingOut(false);
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
              const stock = getAvailableStock(item.product);
              const isInvalid = !isProductOrderable(item.product) || item.quantity > stock;

              return (
                <div key={item.product.id} className="grid gap-5 rounded-[28px] border border-safi-green/5 bg-white p-4 shadow-sm md:grid-cols-[140px_minmax(0,1fr)] md:p-5">
                  <div className="aspect-[4/3] overflow-hidden rounded-2xl bg-[#F5F5F0] md:aspect-square">
                    <img src={item.product.image} alt={item.product.name} className="h-full w-full object-cover" />
                  </div>

                  <div className="flex min-w-0 flex-col gap-5">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                      <div className="min-w-0">
                        <div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold">{item.product.category}</div>
                        <h2 className="font-serif text-2xl font-bold text-safi-green">{item.product.name}</h2>
                        <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-safi-text/70">{item.product.shortDescription}</p>
                        <div className={`mt-3 inline-flex rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] ${isInvalid ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'}`}>
                          {isInvalid ? t('cart.outOfStock', 'Нет в наличии') : `${t('cart.stock', 'Остаток')}: ${stock}`}
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
                            className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-safi-green transition-colors hover:bg-white"
                            aria-label="-"
                          >
                            <Minus className="h-4 w-4" />
                          </button>
                          <span className="min-w-10 text-center text-sm font-bold text-safi-green">{item.quantity}</span>
                          <button
                            type="button"
                            disabled={item.quantity >= stock || isInvalid}
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
                        <div className="mt-1 text-xs font-bold uppercase tracking-widest text-safi-gold">{item.pvTotal} PV</div>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          <aside className="h-fit rounded-[28px] bg-safi-green p-6 text-white shadow-xl lg:sticky lg:top-28">
            <div className="mb-6 flex items-center justify-between border-b border-white/10 pb-5">
              <h2 className="font-serif text-2xl font-bold">{t('cart.summary', 'Итого')}</h2>
              <ShoppingBag className="h-6 w-6 text-safi-gold" />
            </div>

            <div className="space-y-4 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-white/60">{t('cart.items', 'Товары')}</span>
                <span className="font-bold">{totalItems}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-white/60">{t('cart.totalPv', 'PV')}</span>
                <span className="font-bold text-safi-gold">{totalPv} PV</span>
              </div>
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
              {isCheckingOut ? t('cart.checkoutLoading', 'Оформляем...') : t('cart.checkout', 'Оформить заказ')}
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
