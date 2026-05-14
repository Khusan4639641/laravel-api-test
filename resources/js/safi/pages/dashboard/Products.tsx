import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ShoppingBag, Star } from 'lucide-react';
import { products as mockProducts, Product } from '../../data/products';
import { ApiError, createOrder, getProducts } from '../../lib/api';
import { Badge } from '../../components/dashboard/ui';

export default function Products() {
  const [products, setProducts] = useState<Product[]>(mockProducts);
  const [selectedCategory, setSelectedCategory] = useState('Все');
  const [isUsingFallback, setIsUsingFallback] = useState(true);
  const [orderingProductId, setOrderingProductId] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let isMounted = true;

    async function loadProducts() {
      try {
        const response = await getProducts();
        const apiProducts = normalizeProducts(response);

        if (isMounted && apiProducts.length > 0) {
          setProducts(apiProducts);
          setIsUsingFallback(false);
        }
      } catch {
        if (isMounted) {
          setProducts(mockProducts);
          setIsUsingFallback(true);
        }
      }
    }

    void loadProducts();

    return () => {
      isMounted = false;
    };
  }, []);

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
          {isUsingFallback && <Badge variant="warning">Mock fallback</Badge>}
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
        {visibleProducts.map((product) => (
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

function normalizeProducts(response: unknown): Product[] {
  const list = getArray(response);

  return list.map((item, index) => {
    const record = isRecord(item) ? item : {};

    return {
      id: getString(record, ['id', 'uuid']) || String(index + 1),
      name: getString(record, ['name', 'title']) || `Safi Product ${index + 1}`,
      category: getString(record, ['category', 'category_name', 'categoryName']) || 'Safi Life',
      shortDescription: getString(record, ['shortDescription', 'short_description', 'description']) || '',
      description: getString(record, ['description']) || '',
      benefits: getStringArray(record, ['benefits']) || [],
      composition: getStringArray(record, ['composition']) || [],
      usage: getString(record, ['usage']) || '',
      price: getNumber(record, ['price', 'amount']) ?? 0,
      pv: getNumber(record, ['pv', 'points']) ?? 0,
      image: getString(record, ['image', 'image_url', 'imageUrl']) || mockProducts[0].image,
    };
  });
}

function getArray(response: unknown) {
  if (Array.isArray(response)) {
    return response;
  }

  if (isRecord(response)) {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (isRecord(response.data) && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    if (Array.isArray(response.products)) {
      return response.products;
    }
  }

  return [];
}

function getString(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

function getNumber(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const normalized = Number(value.replace(/\s/g, ''));

      if (Number.isFinite(normalized)) {
        return normalized;
      }
    }
  }

  return undefined;
}

function getStringArray(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (Array.isArray(value)) {
      return value.map(String);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
