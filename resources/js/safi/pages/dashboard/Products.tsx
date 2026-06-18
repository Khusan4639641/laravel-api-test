import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { CheckCircle2, ShoppingCart } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ToastItem, ToastStack } from '../../components/ui/Toast';
import { getAvailableStock, isDepositProduct, isProductOrderable, useCart } from '../../context/CartContext';
import { getApiErrorState, getDashboardDepositProducts, getDashboardEarningsSummary, getDashboardProducts, Product } from '../../lib/api';

type ProductMode = 'regular' | 'deposit';

export default function Products() {
  const { t } = useTranslation();
  const { addProduct, items, totalPrice } = useCart();
  const [regularProducts, setRegularProducts] = useState<Product[]>([]);
  const [depositProducts, setDepositProducts] = useState<Product[]>([]);
  const [depositBalance, setDepositBalance] = useState(0);
  const [productMode, setProductMode] = useState<ProductMode>('regular');
  const [selectedCategory, setSelectedCategory] = useState('Все');
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [addedProductId, setAddedProductId] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const loadProducts = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const [apiProducts, apiDepositProducts, earningsSummary] = await Promise.all([
        getDashboardProducts(),
        getDashboardDepositProducts(),
        getDashboardEarningsSummary(),
      ]);
      setRegularProducts(apiProducts);
      setDepositProducts(apiDepositProducts);
      setDepositBalance(earningsSummary.depositBalance);
    } catch (caughtError) {
      setRegularProducts([]);
      setDepositProducts([]);
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadProducts();
  }, [loadProducts]);

  useEffect(() => {
    setSelectedCategory('Все');
  }, [productMode]);

  const products = productMode === 'deposit' ? depositProducts : regularProducts;
  const categories = useMemo(() => ['Все', ...Array.from(new Set(products.map((product) => product.category)))], [products]);
  const visibleProducts = selectedCategory === 'Все'
    ? products
    : products.filter((product) => product.category === selectedCategory);

  const showToast = useCallback((toastMessage: string, type: ToastItem['type'] = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id, message: toastMessage, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3000);
  }, []);

  const handleAddToCart = (product: Product) => {
    setMessage('');
    setError('');

    const cartHasRegularItems = items.some((item) => !isDepositProduct(item.product));

    if (isDepositProduct(product) && !cartHasRegularItems && totalPrice + product.price > depositBalance) {
      const nextError = 'Недостаточно средств на депозитном балансе.';
      setError(nextError);
      showToast(nextError, 'error');
      return;
    }

    const result = addProduct(product);

    if (!result.ok) {
      const nextError = result.reason === 'mixed_product_type'
        ? t('cart.mixedDepositCart', 'Нельзя смешивать депозитные и обычные товары в одной корзине. Очистите корзину.')
        : result.reason === 'stock_limit'
          ? t('cart.stockLimitReached', 'Недостаточно товара на складе')
          : t('cart.outOfStock', 'Нет в наличии');
      setError(nextError);
      showToast(nextError, 'error');
      return;
    }

    setAddedProductId(product.id);
    setMessage(`${t('cart.added', 'Товар добавлен в корзину')}: ${product.name}`);
    showToast(t('cart.added', 'Товар добавлен в корзину'));
    window.setTimeout(() => setAddedProductId((current) => current === product.id ? '' : current), 1200);
  };

  return (
    <div className="space-y-8">
      <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">{t('nav.products', 'Products')}</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Магазин продуктов</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Каталог для личных покупок и создания заказов из кабинета.
            </p>
          </div>
          <Link
            to="/cart"
            className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green-hover"
          >
            <ShoppingCart className="h-4 w-4" />
            {t('cart.title', 'Корзина')}
          </Link>
        </div>

        {(message || error) && (
          <div className={`mt-6 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {error || message}
          </div>
        )}
      </section>

      <section className="flex flex-wrap gap-3">
        {([
          ['regular', 'Обычная продукция'],
          ['deposit', 'Депозитная продукция'],
        ] as Array<[ProductMode, string]>).map(([mode, label]) => (
          <button
            key={mode}
            type="button"
            onClick={() => setProductMode(mode)}
            className={`rounded-full border px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-all ${
              productMode === mode
                ? 'border-safi-green bg-safi-green text-white'
                : 'border-safi-border bg-white text-safi-muted hover:border-safi-green hover:text-safi-green'
            }`}
          >
            {label}
          </button>
        ))}
      </section>

      <section className="flex gap-3 overflow-x-auto pb-1">
        {categories.map((category) => (
          <button
            key={category}
            type="button"
            onClick={() => setSelectedCategory(category)}
            className={`shrink-0 rounded-full border px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-all ${
              selectedCategory === category
                ? 'border-safi-green bg-safi-green text-white'
                : 'border-safi-border bg-white text-safi-muted hover:border-safi-green hover:text-safi-green'
            }`}
          >
            {category}
          </button>
        ))}
      </section>

      <section className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        {isLoading && (
          <LoadingState
            title="Загружаем продукты"
            description="Получаем каталог продуктов из dashboard API."
            className="sm:col-span-2 xl:col-span-4"
          />
        )}

        {!isLoading && loadError && (
          <ErrorState description={loadError} onRetry={() => void loadProducts()} className="sm:col-span-2 xl:col-span-4" />
        )}

        {!isLoading && !loadError && visibleProducts.length === 0 && (
          <EmptyState
            title="Продукты не найдены"
            description="В выбранной категории пока нет доступных продуктов."
            className="sm:col-span-2 xl:col-span-4"
          />
        )}

        {!isLoading && !loadError && visibleProducts.map((product) => (
          <ProductCard
            key={product.id}
            product={product}
            addedProductId={addedProductId}
            onAddToCart={handleAddToCart}
            labels={{
              added: t('cart.addedShort', 'Добавлено'),
              addToCart: t('productsPage.addCartBtn', 'Добавить в корзину'),
              outOfStock: t('cart.outOfStock', 'Нет в наличии'),
              stock: t('cart.stock', 'Остаток'),
              price: t('cart.price', 'Цена'),
              depositOnly: 'Только депозит',
            }}
          />
        ))}
      </section>
    </div>
  );
}

function ProductCard({
  product,
  addedProductId,
  onAddToCart,
  labels,
}: {
  product: Product;
  addedProductId: string;
  onAddToCart: (product: Product) => void;
  labels: {
    added: string;
    addToCart: string;
    outOfStock: string;
    stock: string;
    price: string;
    depositOnly: string;
  };
}) {
  const stock = getAvailableStock(product);
  const orderable = isProductOrderable(product);
  const depositOnly = isDepositProduct(product);

  return (
    <article className="group flex flex-col overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="relative aspect-[4/3] overflow-hidden bg-safi-cream">
              <img
                src={product.image}
                alt={product.name}
                className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
              <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green">
                {depositOnly ? labels.depositOnly : product.category}
              </span>
              <span className={`absolute right-4 top-4 rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] ${orderable ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`}>
                {orderable ? `${labels.stock}: ${stock}` : labels.outOfStock}
              </span>
            </div>

            <div className="flex flex-1 flex-col p-6">
              <h2 className="font-serif text-2xl font-semibold leading-tight text-safi-green">{product.name}</h2>
              <p className="mt-3 flex-1 text-sm leading-6 text-safi-muted">{product.shortDescription}</p>

              <div className="mt-6 border-t border-safi-border pt-5">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-bold text-safi-muted">{labels.price}</span>
                  <span className="font-extrabold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</span>
                </div>
              </div>

              <div className="mt-6 flex items-center justify-end">
                <button
                  type="button"
                  disabled={!orderable}
                  aria-label={orderable ? labels.addToCart : labels.outOfStock}
                  title={orderable ? labels.addToCart : labels.outOfStock}
                  onClick={() => onAddToCart(product)}
                  className={`inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full border transition-colors ${
                    orderable
                      ? 'cursor-pointer border-safi-border bg-safi-cream text-safi-green hover:border-safi-green hover:bg-safi-green hover:text-white'
                      : 'cursor-not-allowed border-safi-border bg-safi-cream text-safi-muted opacity-60'
                  }`}
                >
                  {addedProductId === product.id ? <CheckCircle2 className="h-5 w-5" /> : <ShoppingCart className="h-5 w-5" />}
                </button>
              </div>
            </div>
          </article>
  );
}
