import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useUiText } from '../i18n/useUiText';
import { Check, ShoppingCart, X } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
import { ToastItem, ToastStack } from '../components/ui/Toast';
import { getAvailableStock, isProductOrderable, useCart } from '../context/CartContext';
import { getApiErrorState, getPublicProducts, Product } from '../lib/api';

export default function ProductsPage() {
  const { t } = useTranslation();
  const ui = useUiText();
  const { addProduct } = useCart();
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedCategory, setSelectedCategory] = useState('Все');
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [addedProductId, setAddedProductId] = useState<string | null>(null);
  const [toasts, setToasts] = useState<ToastItem[]>([]);

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

  const showToast = React.useCallback((message: string, type: ToastItem['type'] = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((current) => [...current, { id, message, type }]);
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 3000);
  }, []);

  const handleAddToCart = (product: Product) => {
    const result = addProduct(product);

    if (!result.ok) {
      showToast(result.reason === 'mixed_product_type'
        ? t('cart.mixedDepositCart', 'Нельзя смешивать депозитные и обычные товары в одной корзине. Очистите корзину.')
        : result.reason === 'stock_limit'
          ? t('cart.stockLimitReached', 'Недостаточно товара на складе')
          : t('cart.outOfStock', 'Нет в наличии'), 'error');
      return;
    }

    setAddedProductId(product.id);
    showToast(t('cart.added', 'Товар добавлен в корзину'));
    window.setTimeout(() => {
      setAddedProductId((currentId) => currentId === product.id ? null : currentId);
    }, 1200);
  };

  return (
    <div className="py-20 bg-safi-bg min-h-screen relative overflow-hidden">
      <ToastStack toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-safi-green/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none z-0"></div>

      <Container className="relative z-10">
        <div className="text-center mb-16">
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-safi-green mb-4">
            {t('productsPage.title1', 'Каталог')}{' '}
            <span className="italic text-safi-gold">{t('productsPage.title2', 'продукции')}</span>
          </h2>
          <p className="text-safi-text opacity-70 max-w-2xl mx-auto uppercase tracking-wider text-xs font-bold">
            {t('productsPage.subtitle', 'Здоровье, красота и ежедневное использование')}
          </p>
        </div>

        <div className="flex gap-4 mb-10 overflow-x-auto pb-4 hide-scrollbar">
          {categories.map((category) => (
            <button
              key={category}
              type="button"
              onClick={() => setSelectedCategory(category)}
              className={`px-6 py-3 rounded-full whitespace-nowrap text-[10px] uppercase tracking-widest font-bold transition-all ${
                selectedCategory === category
                  ? 'bg-safi-green text-safi-gold shadow-md'
                  : 'bg-white border border-safi-green/10 text-safi-text opacity-70 hover:bg-[#F5F5F0]'
              }`}
            >
              {category}
            </button>
          ))}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
          {isLoading && (
            <LoadingState
              title={ui('Загружаем каталог')}
              description={ui('Получаем актуальные продукты Safi Life из API.')}
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
              title={ui('Продукты не найдены')}
              description={ui('В этой категории пока нет опубликованных продуктов.')}
              className="sm:col-span-2 lg:col-span-3 xl:col-span-4"
            />
          )}

          {!isLoading && !error && filteredProducts.map((product) => (
            <ProductCard
              key={product.id}
              product={product}
              addedProductId={addedProductId}
              onAddToCart={handleAddToCart}
              onOpen={setSelectedProduct}
            />
          ))}
        </div>
      </Container>

      {selectedProduct && (
        <ProductModal
          product={selectedProduct}
          addedProductId={addedProductId}
          onAddToCart={handleAddToCart}
          onClose={() => setSelectedProduct(null)}
        />
      )}
    </div>
  );
}

function ProductCard({
  product,
  addedProductId,
  onAddToCart,
  onOpen,
}: {
  product: Product;
  addedProductId: string | null;
  onAddToCart: (product: Product) => void;
  onOpen: (product: Product) => void;
}) {
  const { t } = useTranslation();
  const stock = getAvailableStock(product);
  const orderable = isProductOrderable(product);

  return (
    <div className="bg-white rounded-[32px] overflow-hidden shadow-sm border border-safi-green/5 flex flex-col group hover:-translate-y-2 transition-all duration-300">
              <button
                type="button"
        onClick={() => onOpen(product)}
        className="aspect-[4/3] bg-[#F5F5F0] relative overflow-hidden text-left cursor-pointer"
              >
                <img src={product.image} alt={product.name} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
        <div className={`absolute left-4 top-4 rounded-xl px-4 py-2 text-[10px] font-bold uppercase tracking-widest shadow-sm ${orderable ? 'bg-green-50/95 text-green-700' : 'bg-red-50/95 text-red-600'}`}>
          {orderable ? `${t('cart.stock', 'Остаток')}: ${stock}` : t('cart.outOfStock', 'Нет в наличии')}
        </div>
              </button>
              <div className="p-8 flex flex-col flex-1">
                <div className="text-[10px] font-bold text-safi-gold mb-3 uppercase tracking-widest">{product.category}</div>
                <h3 className="text-xl font-serif font-bold text-safi-green mb-3">{product.name}</h3>
                <p className="text-safi-text opacity-70 text-sm mb-6 flex-1 leading-relaxed">{product.shortDescription}</p>
                <div className="mb-6 flex items-end justify-between gap-4">
                  <div className="text-3xl font-serif font-bold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</div>
                  <button
                    type="button"
                    disabled={!orderable}
                    aria-label={orderable ? t('productsPage.addCartBtn', 'Добавить в корзину') : t('cart.outOfStock', 'Нет в наличии')}
                    title={orderable ? t('productsPage.addCartBtn', 'Добавить в корзину') : t('cart.outOfStock', 'Нет в наличии')}
                    onClick={() => onAddToCart(product)}
                    className={`inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-white shadow-lg shadow-safi-green/20 transition-all ${
                      orderable
                        ? 'cursor-pointer bg-safi-green hover:bg-safi-green-hover hover:-translate-y-0.5'
                        : 'cursor-not-allowed bg-safi-green/30 opacity-60'
                    }`}
                  >
                    {addedProductId === product.id ? <Check className="w-5 h-5" /> : <ShoppingCart className="w-5 h-5" />}
                  </button>
                </div>
                <div className="flex flex-col gap-3">
                  <Button variant="outline" className="w-full" onClick={() => onOpen(product)}>
                    {t('productsPage.moreBtn', 'Подробнее')}
                  </Button>
                </div>
              </div>
            </div>
  );
}

function ProductModal({
  product,
  addedProductId,
  onAddToCart,
  onClose,
}: {
  product: Product;
  addedProductId: string | null;
  onAddToCart: (product: Product) => void;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const ui = useUiText();
  const stock = getAvailableStock(product);
  const orderable = isProductOrderable(product);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
      <button
        type="button"
        aria-label={ui('Закрыть карточку продукта')}
        className="absolute inset-0 bg-safi-green/40 backdrop-blur-sm transition-opacity"
        onClick={onClose}
      />
      <div className="safi-responsive-modal relative z-10 w-full max-w-4xl overflow-y-auto rounded-[28px] border border-safi-green/5 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-300 hide-scrollbar sm:rounded-[40px] [--safi-modal-width:56rem]">
        <button
          type="button"
          onClick={onClose}
          aria-label={ui('Закрыть')}
          className="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-[#F5F5F0] text-safi-green transition-colors hover:bg-safi-green hover:text-white sm:right-6 sm:top-6"
        >
          <X className="w-5 h-5" />
        </button>
        <div className="grid md:grid-cols-2 gap-0 h-full">
          <div className="relative h-[240px] overflow-hidden bg-[#F5F5F0] sm:h-[300px] md:h-auto md:min-h-[500px]">
            <img src={product.image} alt={product.name} className="w-full h-full object-cover" />
            <div className={`absolute right-6 top-6 rounded-xl px-4 py-2 text-[10px] font-bold uppercase tracking-widest shadow-sm ${orderable ? 'bg-green-50/95 text-green-700' : 'bg-red-50/95 text-red-600'}`}>
              {orderable ? `${t('cart.stock', 'Остаток')}: ${stock}` : t('cart.outOfStock', 'Нет в наличии')}
            </div>
          </div>
          <div className="flex h-full flex-col bg-white p-5 sm:p-8 md:p-12">
            <div className="text-[10px] font-bold text-safi-gold mb-4 uppercase tracking-widest">{product.category}</div>
            <h2 className="text-3xl md:text-4xl font-serif font-bold text-safi-green mb-4">{product.name}</h2>
            <div className="text-3xl font-serif font-bold text-safi-green mb-8">{product.price.toLocaleString('ru-RU')} ₸</div>

            <div className="space-y-8 flex-1">
              <InfoBlock title={t('productsPage.descLabel', 'Описание')}>
                <p>{product.description}</p>
              </InfoBlock>

              <InfoBlock title={t('productsPage.benefitsLabel', 'Преимущества')}>
                <ul className="space-y-2">
                  {product.benefits.map((benefit, index) => (
                    <li key={`${benefit}-${index}`} className="flex items-start gap-3 text-sm text-safi-text opacity-80">
                      <Check className="w-4 h-4 mt-0.5 text-safi-gold shrink-0" />
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

            <div className="pt-8 mt-8 border-t border-safi-green/5">
              <button
                type="button"
                disabled={!orderable}
                onClick={() => onAddToCart(product)}
                className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-safi-green px-8 py-4 text-sm font-bold uppercase tracking-widest text-white shadow-lg shadow-safi-green/20 transition-all hover:bg-safi-green-hover disabled:cursor-not-allowed disabled:opacity-50"
              >
                {addedProductId === product.id ? <Check className="w-5 h-5" /> : <ShoppingCart className="w-5 h-5" />}
                {orderable ? (addedProductId === product.id ? t('cart.addedShort', 'Добавлено') : t('productsPage.addCartBtn', 'Добавить в корзину')) : t('cart.outOfStock', 'Нет в наличии')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function InfoBlock({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h4 className="text-[10px] uppercase font-bold text-safi-green tracking-widest opacity-80 mb-3 border-b border-safi-green/10 pb-2">{title}</h4>
      <div className="text-sm text-safi-text leading-relaxed opacity-80">{children}</div>
    </section>
  );
}
