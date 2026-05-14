import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Check, X } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
import { getApiErrorState, getPublicProducts, Product } from '../lib/api';

export default function ProductsPage() {
  const { t } = useTranslation();
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedCategory, setSelectedCategory] = useState('Все');
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);

  const loadProducts = React.useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      setProducts(await getPublicProducts());
    } catch (caughtError) {
      setProducts([]);
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadProducts();
  }, [loadProducts]);

  const categories = useMemo(() => ['Все', ...Array.from(new Set(products.map((product) => product.category)))], [products]);
  const filteredProducts = selectedCategory === 'Все'
    ? products
    : products.filter((product) => product.category === selectedCategory);

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
            <div>
              <span className="safi-kicker">Products</span>
              <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
                {t('productsPage.title1', 'Каталог')}{' '}
                <span className="italic text-safi-gold">{t('productsPage.title2', 'продукции')}</span>
              </h1>
            </div>
            <p className="max-w-2xl text-base leading-8 text-safi-muted md:text-xl lg:justify-self-end">
              {t('productsPage.subtitle', 'Здоровье, красота и ежедневное использование')}
            </p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-8">
        <Container>
          <div className="flex gap-3 overflow-x-auto pb-2">
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                onClick={() => setSelectedCategory(category)}
                className={`shrink-0 rounded-full border px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-all ${
                  selectedCategory === category
                    ? 'border-safi-green bg-safi-green text-white'
                    : 'border-safi-border bg-safi-bg text-safi-muted hover:border-safi-green hover:text-safi-green'
                }`}
              >
                {category}
              </button>
            ))}
          </div>
        </Container>
      </section>

      <section className="bg-safi-bg py-12 md:py-20">
        <Container>
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {isLoading && (
              <LoadingState
                title="Загружаем каталог"
                description="Получаем актуальные продукты Safi Life из API."
                className="sm:col-span-2 lg:col-span-3 xl:col-span-4"
              />
            )}

            {!isLoading && error && (
              <ErrorState
                description={error}
                onRetry={loadProducts}
                className="sm:col-span-2 lg:col-span-3 xl:col-span-4"
              />
            )}

            {!isLoading && !error && filteredProducts.length === 0 && (
              <EmptyState
                title="Продукты не найдены"
                description="В этой категории пока нет опубликованных продуктов."
                className="sm:col-span-2 lg:col-span-3 xl:col-span-4"
              />
            )}

            {!isLoading && !error && filteredProducts.map((product) => (
              <article
                key={product.id}
                className="group flex flex-col overflow-hidden rounded-3xl border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.06)]"
              >
                <button
                  type="button"
                  onClick={() => setSelectedProduct(product)}
                  className="aspect-[4/3] overflow-hidden bg-safi-cream text-left"
                >
                  <img
                    src={product.image}
                    alt={product.name}
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                </button>
                <div className="flex flex-1 flex-col p-6">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span className="rounded-full bg-safi-cream px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                      {product.category}
                    </span>
                    <span className="rounded-full bg-safi-green px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-white">
                      {product.pv} PV
                    </span>
                  </div>
                  <h3 className="font-serif text-2xl font-semibold leading-tight text-safi-green">{product.name}</h3>
                  <p className="mt-3 flex-1 text-sm leading-6 text-safi-muted">{product.shortDescription}</p>
                  <div className="mt-6 border-t border-safi-border pt-5">
                    <div className="mb-4 text-2xl font-extrabold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</div>
                    <div className="grid gap-3">
                      <Button to="/contacts" className="w-full">{t('productsPage.orderBtn', 'Заказать')}</Button>
                      <Button variant="outline" className="w-full" onClick={() => setSelectedProduct(product)}>
                        {t('productsPage.moreBtn', 'Подробнее')}
                      </Button>
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-t border-safi-border bg-white py-16 md:py-20">
        <Container>
          <div className="grid gap-8 rounded-[36px] border border-safi-border bg-safi-cream p-8 md:p-10 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <span className="safi-kicker">Safi Life catalog</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">
                Нужна консультация по продуктам?
              </h2>
              <p className="mt-4 max-w-2xl text-sm leading-7 text-safi-muted md:text-base">
                Оставьте заявку, и консультант поможет подобрать категорию под вашу цель.
              </p>
            </div>
            <Button to="/contacts" size="lg">
              Связаться
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>
        </Container>
      </section>

      {selectedProduct && (
        <ProductModal product={selectedProduct} onClose={() => setSelectedProduct(null)} />
      )}
    </div>
  );
}

function ProductModal({ product, onClose }: { product: Product; onClose: () => void }) {
  const { t } = useTranslation();

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <button
        type="button"
        aria-label="Закрыть карточку продукта"
        className="absolute inset-0 bg-safi-green/45 backdrop-blur-sm"
        onClick={onClose}
      />
      <div className="relative z-10 max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-[36px] border border-safi-border bg-white shadow-[0_30px_100px_rgba(11,23,18,0.24)]">
        <button
          type="button"
          onClick={onClose}
          aria-label="Закрыть"
          className="absolute right-5 top-5 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-white text-safi-green shadow-[0_12px_30px_rgba(11,23,18,0.12)] transition-colors hover:bg-safi-green hover:text-white"
        >
          <X className="h-5 w-5" />
        </button>

        <div className="grid md:grid-cols-[0.9fr_1.1fr]">
          <div className="min-h-[320px] bg-safi-cream md:min-h-[620px]">
            <img src={product.image} alt={product.name} className="h-full w-full object-cover" />
          </div>
          <div className="p-7 md:p-10">
            <span className="safi-kicker">{product.category}</span>
            <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">{product.name}</h2>
            <div className="mt-5 flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-safi-green px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white">
                {product.pv} PV
              </span>
              <span className="font-serif text-3xl font-semibold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</span>
            </div>

            <div className="mt-8 space-y-7">
              <InfoBlock title={t('productsPage.descLabel', 'Описание')}>
                <p>{product.description}</p>
              </InfoBlock>

              <InfoBlock title={t('productsPage.benefitsLabel', 'Преимущества')}>
                <ul className="space-y-3">
                  {product.benefits.map((benefit) => (
                    <li key={benefit} className="flex gap-3">
                      <Check className="mt-1 h-4 w-4 shrink-0 text-safi-gold" />
                      <span>{benefit}</span>
                    </li>
                  ))}
                </ul>
              </InfoBlock>

              <InfoBlock title={t('productsPage.compLabel', 'Состав')}>
                <p>{product.composition.join(', ')}</p>
              </InfoBlock>

              <InfoBlock title={t('productsPage.usageLabel', 'Способ применения')}>
                <p>{product.usage}</p>
              </InfoBlock>
            </div>

            <Button to="/contacts" size="lg" className="mt-8 w-full">
              {t('productsPage.addCartBtn', 'Добавить в корзину')}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

function InfoBlock({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h3 className="border-b border-safi-border pb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green">{title}</h3>
      <div className="mt-3 text-sm leading-7 text-safi-muted">{children}</div>
    </section>
  );
}
