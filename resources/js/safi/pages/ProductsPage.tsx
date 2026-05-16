import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, X } from 'lucide-react';
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
    <div className="py-20 bg-safi-bg min-h-screen relative overflow-hidden">
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
            <div key={product.id} className="bg-white rounded-[32px] overflow-hidden shadow-sm border border-safi-green/5 flex flex-col group hover:-translate-y-2 transition-all duration-300">
              <button
                type="button"
                onClick={() => setSelectedProduct(product)}
                className="aspect-[4/3] bg-[#F5F5F0] relative overflow-hidden text-left"
              >
                <img src={product.image} alt={product.name} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                <div className="absolute top-4 right-4 bg-white/90 backdrop-blur-md px-4 py-2 rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green shadow-sm">
                  {product.pv} PV
                </div>
              </button>
              <div className="p-8 flex flex-col flex-1">
                <div className="text-[10px] font-bold text-safi-gold mb-3 uppercase tracking-widest">{product.category}</div>
                <h3 className="text-xl font-serif font-bold text-safi-green mb-3">{product.name}</h3>
                <p className="text-safi-text opacity-70 text-sm mb-6 flex-1 leading-relaxed">{product.shortDescription}</p>
                <div className="text-3xl font-serif font-bold text-safi-green mb-6">{product.price.toLocaleString('ru-RU')} ₸</div>
                <div className="flex flex-col gap-3">
                  <Button className="w-full" to="/contacts">{t('productsPage.orderBtn', 'Заказать')}</Button>
                  <Button variant="outline" className="w-full" onClick={() => setSelectedProduct(product)}>
                    {t('productsPage.moreBtn', 'Подробнее')}
                  </Button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </Container>

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
        className="absolute inset-0 bg-safi-green/40 backdrop-blur-sm transition-opacity"
        onClick={onClose}
      />
      <div className="relative bg-white rounded-[40px] shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto z-10 border border-safi-green/5 animate-in fade-in zoom-in-95 duration-300 hide-scrollbar">
        <button
          type="button"
          onClick={onClose}
          aria-label="Закрыть"
          className="absolute top-6 right-6 w-10 h-10 bg-[#F5F5F0] rounded-full flex items-center justify-center text-safi-green hover:bg-safi-green hover:text-white transition-colors z-20"
        >
          <X className="w-5 h-5" />
        </button>
        <div className="grid md:grid-cols-2 gap-0 h-full">
          <div className="h-[300px] md:h-auto md:min-h-[500px] bg-[#F5F5F0] relative overflow-hidden">
            <img src={product.image} alt={product.name} className="w-full h-full object-cover" />
            <div className="absolute top-6 left-6 bg-white/90 backdrop-blur-md px-4 py-2 rounded-xl text-[10px] uppercase font-bold tracking-widest text-safi-green shadow-sm">
              {product.pv} PV
            </div>
          </div>
          <div className="p-8 md:p-12 flex flex-col h-full bg-white">
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
              <Button size="lg" className="w-full" to="/contacts">{t('productsPage.addCartBtn', 'Добавить в корзину')}</Button>
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
