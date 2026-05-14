import { useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ShoppingBag, Star } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, createOrder, getApiErrorState, getDashboardProducts, Product } from '../../lib/api';

export default function Products() {
  const [products, setProducts] = useState<Product[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('Все');
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [orderingProductId, setOrderingProductId] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const loadProducts = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const apiProducts = await getDashboardProducts();
      setProducts(apiProducts);
    } catch (caughtError) {
      setProducts([]);
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadProducts();
  }, [loadProducts]);

  const categories = useMemo(() => ['Все', ...Array.from(new Set(products.map((product) => product.category)))], [products]);
  const visibleProducts = selectedCategory === 'Все'
    ? products
    : products.filter((product) => product.category === selectedCategory);

  const handleCreateOrder = async (product: Product) => {
    setOrderingProductId(product.id);
    setMessage('');
    setError('');

    try {
      await createOrder({
        product_id: product.id,
        quantity: 1,
      });
      setMessage(`Заказ на ${product.name} создан.`);
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось создать заказ. Попробуйте позже.');
      }
    } finally {
      setOrderingProductId('');
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
          <div>
            <span className="safi-kicker">Products</span>
            <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Магазин продуктов</h1>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
              Каталог для личных покупок и создания заказов из кабинета.
            </p>
          </div>
        </div>

        {(message || error) && (
          <div className={`mt-6 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {error || message}
          </div>
        )}
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
          <article key={product.id} className="group flex flex-col overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="relative aspect-[4/3] overflow-hidden bg-safi-cream">
              <img
                src={product.image}
                alt={product.name}
                className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
              <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green">
                {product.category}
              </span>
            </div>

            <div className="flex flex-1 flex-col p-6">
              <h2 className="font-serif text-2xl font-semibold leading-tight text-safi-green">{product.name}</h2>
              <p className="mt-3 flex-1 text-sm leading-6 text-safi-muted">{product.shortDescription}</p>

              <div className="mt-6 space-y-3 border-t border-safi-border pt-5">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-bold text-safi-muted">Цена</span>
                  <span className="font-extrabold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="font-bold text-safi-muted">PV</span>
                  <span className="inline-flex items-center gap-1 font-extrabold text-safi-gold">
                    <Star className="h-4 w-4 fill-current" />
                    {product.pv} PV
                  </span>
                </div>
              </div>

              <button
                type="button"
                disabled={orderingProductId === product.id}
                onClick={() => handleCreateOrder(product)}
                className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green hover:bg-safi-green hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {orderingProductId === product.id ? <CheckCircle2 className="h-4 w-4" /> : <ShoppingBag className="h-4 w-4" />}
                {orderingProductId === product.id ? 'Создаем...' : 'Создать заказ'}
              </button>
            </div>
          </article>
        ))}
      </section>
    </div>
  );
}
