import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Layers, Leaf, RefreshCw, ShieldCheck, TrendingUp, Wallet } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
import { cn } from '../lib/utils';
import { getApiErrorState, getPublicNews, getPublicPackages, getPublicProducts, NewsArticle, Package, Product } from '../lib/api';

export default function HomePage() {
  const { t } = useTranslation();
  const [products, setProducts] = useState<Product[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [newsArticles, setNewsArticles] = useState<NewsArticle[]>([]);
  const [productsLoading, setProductsLoading] = useState(true);
  const [packagesLoading, setPackagesLoading] = useState(true);
  const [newsLoading, setNewsLoading] = useState(true);
  const [productsError, setProductsError] = useState<string | null>(null);
  const [packagesError, setPackagesError] = useState<string | null>(null);
  const [newsError, setNewsError] = useState<string | null>(null);

  const heroProduct = useMemo(
    () => products.find((product) => product.name === 'Safi Face Serum') || products[0],
    [products]
  );
  const popularProducts = useMemo(() => products.slice(0, 4), [products]);
  const newsPreview = useMemo(() => newsArticles.slice(0, 3), [newsArticles]);

  const loadProducts = React.useCallback(async () => {
    setProductsLoading(true);
    setProductsError(null);

    try {
      setProducts(await getPublicProducts());
    } catch (caughtError) {
      setProducts([]);
      setProductsError(getApiErrorState(caughtError).error);
    } finally {
      setProductsLoading(false);
    }
  }, []);

  const loadPackages = React.useCallback(async () => {
    setPackagesLoading(true);
    setPackagesError(null);

    try {
      setPackages(await getPublicPackages());
    } catch (caughtError) {
      setPackages([]);
      setPackagesError(getApiErrorState(caughtError).error);
    } finally {
      setPackagesLoading(false);
    }
  }, []);

  const loadNews = React.useCallback(async () => {
    setNewsLoading(true);
    setNewsError(null);

    try {
      setNewsArticles(await getPublicNews());
    } catch (caughtError) {
      setNewsArticles([]);
      setNewsError(getApiErrorState(caughtError).error);
    } finally {
      setNewsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadProducts();
    void loadPackages();
    void loadNews();
  }, [loadProducts, loadPackages, loadNews]);

  const benefits = [
    {
      title: t('benefits.domestic', 'Отечественная продукция'),
      desc: t('benefits.domesticDesc', 'Производим товары для здоровья и красоты из качественного сырья.'),
      icon: Leaf,
    },
    {
      title: t('benefits.binary', 'Бинар + Классика'),
      desc: t('benefits.binaryDesc', 'Сбалансированная система выплат с левой и правой веток, плюс кэшбэк.'),
      icon: Layers,
    },
    {
      title: t('benefits.payout', 'Выплаты каждые 14 дней'),
      desc: t('benefits.payoutDesc', 'Стабильные начисления прямо на ваш счет без скрытых комиссий.'),
      icon: Wallet,
    },
    {
      title: t('benefits.upgrade', 'Апгрейд пакетов'),
      desc: t('benefits.upgradeDesc', 'Начните с малого и повышайте свой пакет по мере роста бизнеса.'),
      icon: TrendingUp,
    },
    {
      title: t('benefits.pv', 'Накопительные баллы (PV)'),
      desc: t('benefits.pvDesc', 'Баллы сохраняются и переносятся, помогая быстрее достигать статусов.'),
      icon: RefreshCw,
    },
    {
      title: t('benefits.transparent', 'Прозрачный маркетинг'),
      desc: t('benefits.transparentDesc', 'Никаких сложных условий. Реальные достижения и справедливые выплаты.'),
      icon: ShieldCheck,
    },
  ];

  return (
    <div className="flex flex-col bg-safi-bg text-safi-green">
      <section className="relative flex min-h-[700px] items-center overflow-hidden py-24">
        <Container>
          <div className="relative z-10 grid grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-8">
            <div className="flex flex-col items-center space-y-8 text-center lg:items-start lg:text-left">
              <div className="inline-flex items-center gap-2 rounded-full border border-safi-green/10 bg-safi-green/5 px-4 py-2">
                <span className="h-2 w-2 rounded-full bg-safi-gold" />
                <span className="text-[10px] font-bold uppercase tracking-widest opacity-70">
                  {t('hero.kzProducts', 'Отечественная продукция Казахстана')}
                </span>
              </div>

              <h1 className="font-serif text-5xl font-medium leading-[1.1] text-safi-green md:text-7xl">
                {t('hero.title1', 'Раскрой свой')} <br className="hidden md:block" />
                {t('hero.title2', 'потенциал с')} <br className="hidden md:block" />
                <span className="italic text-safi-gold">{t('hero.title3', 'Safi')}</span>
              </h1>

              <p className="max-w-2xl text-lg leading-relaxed text-safi-text opacity-80 md:text-xl">
                {t(
                  'hero.subtitle',
                  'Инновационные продукты для здоровья и уникальная бизнес-модель для вашего успеха.'
                )}
              </p>

              <div className="flex h-auto w-full max-w-md flex-col justify-center gap-4 sm:flex-row lg:max-w-none lg:justify-start">
                <Button size="lg" to="/register" className="h-auto w-full flex-col items-center px-8 py-4 lg:w-auto">
                  <span className="text-sm">{t('hero.startNow', 'Начать бизнес')}</span>
                </Button>
                <Button
                  size="lg"
                  variant="outline"
                  to="/products"
                  className="h-auto w-full flex-col items-center border-safi-green/20 px-8 py-4 hover:border-safi-green lg:w-auto"
                >
                  <span className="text-sm">{t('hero.buyProducts', 'Купить продукцию')}</span>
                </Button>
              </div>

              <p className="mt-4 max-w-lg text-xs italic text-safi-text opacity-40">
                {t('hero.disclaimer', '*Доход зависит от личной активности. Информация не является гарантией заработка.')}
              </p>

              <div className="mt-8 flex w-full max-w-3xl flex-wrap justify-center gap-8 border-t border-safi-green/5 pt-8 lg:justify-start">
                <TrustBadge value={t('trustBadges.binary', 'Бинар +')} label={t('trustBadges.classic', 'Классика')} />
                <div className="hidden h-10 w-px bg-safi-green/10 sm:block" />
                <TrustBadge value={t('trustBadges.days', '14 дней')} label={t('trustBadges.payouts', 'Выплаты бонусов')} />
                <div className="hidden h-10 w-px bg-safi-green/10 sm:block" />
                <TrustBadge value={t('trustBadges.pv', 'PV')} label={t('trustBadges.accumulation', 'Накопительная система')} />
              </div>
            </div>

            <div className="relative mt-10 flex h-full w-full items-center justify-center py-12 lg:mt-0 lg:justify-end lg:py-0">
              {productsLoading && (
                <LoadingState
                  title="Загружаем продукт"
                  description="Получаем актуальный product highlight."
                  className="aspect-[3/4] min-h-0 w-full max-w-[340px] rounded-[40px] sm:max-w-sm"
                />
              )}

              {!productsLoading && productsError && (
                <ErrorState
                  title="Продукт недоступен"
                  description={productsError}
                  onRetry={loadProducts}
                  className="aspect-[3/4] min-h-0 w-full max-w-[340px] rounded-[40px] sm:max-w-sm"
                />
              )}

              {!productsLoading && !productsError && heroProduct && <HeroProductCard product={heroProduct} />}

              {!productsLoading && !productsError && !heroProduct && (
                <EmptyState
                  title="Нет продукта для витрины"
                  description="Product highlight появится после публикации продуктов."
                  className="aspect-[3/4] min-h-0 w-full max-w-[340px] rounded-[40px] sm:max-w-sm"
                />
              )}
            </div>
          </div>
        </Container>
      </section>

      <section className="border-b border-safi-green/5 bg-white py-6">
        <Container>
          <div className="flex flex-col items-start gap-4 overflow-hidden md:flex-row md:items-center md:gap-8">
            <div className="flex w-full shrink-0 items-center justify-between md:w-auto">
              <div className="flex items-center gap-2 text-safi-gold">
                <span className="h-2 w-2 animate-pulse rounded-full bg-safi-gold" />
                <span className="text-[10px] font-bold uppercase tracking-widest text-safi-green">
                  {t('news.title', 'Новости')}
                </span>
              </div>
              <div className="md:hidden">
                <Link
                  to="/news"
                  className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-safi-green"
                >
                  {t('news.viewAll', 'Все')} <ArrowRight className="h-3 w-3" />
                </Link>
              </div>
            </div>

            <NewsStrip
              articles={newsPreview}
              isLoading={newsLoading}
              error={newsError}
              onRetry={loadNews}
            />

            <div className="hidden shrink-0 md:block">
              <Link
                to="/news"
                className="flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-safi-green"
              >
                {t('news.viewAllBtn', 'Смотреть всё')} <ArrowRight className="h-3 w-3" />
              </Link>
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-[#F5F5F0] py-24">
        <Container>
          <div className="mb-16 text-center">
            <h2 className="mb-4 font-serif text-4xl text-safi-green md:text-5xl">
              {t('benefits.title1', 'Почему выбирают')}{' '}
              <span className="italic text-safi-gold">{t('benefits.title2', 'Safi Life')}</span>
            </h2>
            <p className="mx-auto max-w-2xl text-lg text-safi-text opacity-80">
              {t(
                'benefits.subtitle',
                'Натуральные продукты из Казахстана и прозрачный маркетинг-план, который действительно работает.'
              )}
            </p>
          </div>

          <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            {benefits.map(({ title, desc, icon: Icon }) => (
              <article
                key={title}
                className="group rounded-3xl border border-safi-green/5 bg-white p-8 transition-all duration-300 hover:shadow-xl"
              >
                <div className="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-safi-gold/20 bg-safi-bg text-safi-green shadow-sm transition-transform duration-300 group-hover:scale-110">
                  <Icon className="h-6 w-6" />
                </div>
                <h3 className="mb-3 font-serif text-xl font-bold text-safi-green">{title}</h3>
                <p className="text-sm leading-relaxed text-safi-text opacity-70">{desc}</p>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="relative bg-white py-24">
        <Container>
          <div className="mb-16 flex flex-col items-end justify-between gap-6 md:flex-row">
            <div>
              <h2 className="mb-4 font-serif text-4xl text-safi-green md:text-5xl">
                {t('popular.title1', 'Популярные')}{' '}
                <span className="italic text-safi-gold">{t('popular.title2', 'продукты')}</span>
              </h2>
              <p className="max-w-xl text-lg text-safi-text opacity-80">
                {t('popular.subtitle', 'Начните знакомство с хитов продаж, которые уже выбрали тысячи партнеров.')}
              </p>
            </div>
            <Button variant="outline" to="/products" className="hidden shrink-0 bg-transparent md:inline-flex">
              {t('popular.allCatalogBtn', 'ВЕСЬ КАТАЛОГ')}
            </Button>
          </div>

          {productsLoading && (
            <LoadingState title="Загружаем продукты" description="Получаем продукцию из публичного API." />
          )}

          {!productsLoading && productsError && <ErrorState description={productsError} onRetry={loadProducts} />}

          {!productsLoading && !productsError && popularProducts.length === 0 && (
            <EmptyState title="Продукты пока не опубликованы" description="После публикации продукты появятся на главной." />
          )}

          {!productsLoading && !productsError && popularProducts.length > 0 && (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
              {popularProducts.map((product) => (
                <PopularProductCard key={product.id} product={product} />
              ))}
            </div>
          )}

          <div className="mt-8 text-center md:hidden">
            <Button variant="outline" to="/products" className="w-full bg-transparent">
              {t('popular.allCatalogBtn', 'ВЕСЬ КАТАЛОГ')}
            </Button>
          </div>
        </Container>
      </section>

      <section className="bg-[#F5F5F0] py-24">
        <Container>
          <div className="mb-16 text-center">
            <h2 className="mb-4 font-serif text-4xl text-safi-green md:text-5xl">
              {t('packages.title1', 'Стартовые')}{' '}
              <span className="italic text-safi-gold">{t('packages.title2', 'пакеты')}</span>
            </h2>
            <p className="mx-auto max-w-2xl text-lg text-safi-text opacity-80">
              {t('packages.subtitle', 'Выберите пакет, который подходит именно вам, и начните зарабатывать с Safi Life.')}
            </p>
          </div>

          {packagesLoading && (
            <LoadingState title="Загружаем пакеты" description="Получаем стартовые пакеты из публичного API." />
          )}

          {!packagesLoading && packagesError && <ErrorState description={packagesError} onRetry={loadPackages} />}

          {!packagesLoading && !packagesError && packages.length === 0 && (
            <EmptyState title="Пакеты пока не опубликованы" description="После настройки пакетов они появятся на главной." />
          )}

          {!packagesLoading && !packagesError && packages.length > 0 && (
            <div
              className={cn(
                'mx-auto grid grid-cols-1 gap-6',
                packages.length > 3 ? 'max-w-7xl sm:grid-cols-2 lg:grid-cols-4' : 'max-w-5xl md:grid-cols-3'
              )}
            >
              {packages.map((pkg) => (
                <PackageCard key={pkg.id} pkg={pkg} />
              ))}
            </div>
          )}
        </Container>
      </section>

      <section className="relative overflow-hidden bg-safi-green py-24 text-white">
        <div className="absolute inset-0 bg-safi-bg/10" />
        <div className="absolute left-1/2 top-1/2 z-0 h-[820px] w-[980px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(ellipse_at_center,rgba(212,175,55,0.34)_0%,rgba(139,128,62,0.24)_34%,rgba(12,21,17,0)_70%)] blur-3xl" />
        <Container className="relative z-10 text-center">
          <h2 className="mb-6 font-serif text-4xl leading-tight text-white md:text-5xl">
            {t('cta.title1', 'Готовы начать')}{' '}
            <span className="italic text-safi-gold">{t('cta.title2', 'бизнес?')}</span>
          </h2>
          <p className="mx-auto mb-10 max-w-2xl text-xl font-light leading-relaxed text-white/80">
            {t(
              'cta.subtitle',
              'Оставьте заявку, и наш менеджер свяжется с вами, чтобы проконсультировать по пакетам и маркетинг-плану.'
            )}
          </p>
          <div className="flex flex-col justify-center gap-4 sm:flex-row">
            <Button size="lg" variant="secondary" to="/contacts" className="px-8 py-4">
              {t('cta.consultBtn', 'Получить консультацию')}
            </Button>
            <Button
              size="lg"
              variant="outline"
              to="/marketing"
              className="border-white px-8 py-4 text-white hover:bg-white/10"
            >
              {t('cta.marketingBtn', 'Изучить маркетинг-план')}
            </Button>
          </div>
        </Container>
      </section>
    </div>
  );
}

function TrustBadge({ value, label }: { value: string; label: string }) {
  return (
    <div className="flex flex-col items-center text-safi-green lg:items-start">
      <span className="font-serif text-xl font-bold md:text-2xl">{value}</span>
      <span className="text-[10px] uppercase italic tracking-wider opacity-60">{label}</span>
    </div>
  );
}

function HeroProductCard({ product }: { product: Product }) {
  const { t } = useTranslation();

  return (
    <article className="group relative flex aspect-[3/4] w-full max-w-[340px] flex-col overflow-visible rounded-[40px] border border-white bg-white/60 p-4 shadow-2xl backdrop-blur-2xl sm:max-w-sm">
      <div className="absolute -right-6 -top-6 z-20 flex h-24 w-24 rotate-12 flex-col items-center justify-center rounded-full border-4 border-white bg-safi-gold text-white shadow-xl transition-transform duration-500 group-hover:rotate-0 sm:h-28 sm:w-28">
        <span className="text-[10px] font-bold uppercase tracking-tighter">{t('productCard.cashbackTitle', 'Кэшбэк')}</span>
        <span className="font-serif text-xl font-bold sm:text-2xl">20%</span>
        <span className="text-[8px] uppercase tracking-widest opacity-80">{t('productCard.cashbackBonus', 'бонус')}</span>
      </div>

      <div className="absolute -bottom-4 -left-4 z-20 flex h-16 w-16 flex-col items-center justify-center rounded-full border-4 border-white bg-safi-green text-white shadow-lg sm:h-20 sm:w-20">
        <Leaf className="mb-1 h-5 w-5 opacity-80 sm:h-6 sm:w-6" />
        <span className="text-[7px] font-bold uppercase tracking-widest sm:text-[8px]">100% Nat</span>
      </div>

      <div className="relative flex h-[55%] w-full items-center justify-center overflow-hidden rounded-[32px] bg-[#F5F5F0] shadow-inner">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
        />
        <div className="absolute bottom-4 left-4 right-4 z-10 flex items-center justify-between">
          <span className="rounded-full border border-safi-green/5 bg-white/90 px-3 py-1.5 text-[9px] font-bold uppercase tracking-widest text-safi-green shadow-sm backdrop-blur sm:text-[10px]">
            {t('productCard.hit', 'Хит продаж')}
          </span>
          <Link
            to="/products"
            aria-label="Открыть каталог"
            className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-xs font-bold text-safi-green shadow-sm backdrop-blur transition-colors hover:bg-safi-green hover:text-white sm:h-8 sm:w-8"
          >
            +
          </Link>
        </div>
      </div>

      <div className="mt-4 flex flex-1 flex-col px-3 sm:px-4">
        <div className="mb-2 flex items-center justify-between gap-3">
          <h3 className="line-clamp-1 font-serif text-lg font-bold text-safi-green sm:text-xl">{product.name}</h3>
          <span className="shrink-0 text-lg font-bold text-safi-gold">{formatPrice(product.price)}</span>
        </div>
        <p className="mb-4 line-clamp-3 flex-1 text-[11px] text-safi-text opacity-70 sm:text-xs">
          {product.shortDescription || t('productCard.desc', 'Омолаживающая сыворотка с пептидами.')}
        </p>
        <div className="mt-auto flex items-center justify-between border-t border-safi-green/10 pb-2 pt-3 sm:pt-4">
          <div className="rounded-md bg-[#F5F5F0] px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-safi-green">
            {product.pv} PV
          </div>
          <Button
            variant="ghost"
            size="sm"
            className="rounded-full bg-[#F5F5F0] px-4 py-2 text-[9px] transition-colors hover:bg-safi-green hover:text-white sm:text-[10px]"
            to="/products"
          >
            {t('productCard.catalog', 'Каталог')}
          </Button>
        </div>
      </div>
    </article>
  );
}

function NewsStrip({
  articles,
  isLoading,
  error,
  onRetry,
}: {
  articles: NewsArticle[];
  isLoading: boolean;
  error: string | null;
  onRetry: () => void;
}) {
  if (isLoading) {
    return (
      <div className="flex-1 overflow-hidden">
        <div className="-mb-4 flex gap-4 overflow-x-auto pb-4 scrollbar-none">
          {[1, 2, 3].map((item) => (
            <div key={item} className="h-[88px] w-[85vw] shrink-0 animate-pulse rounded-xl bg-[#F5F5F0] md:w-[280px]" />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex-1">
        <ErrorState description={error} onRetry={onRetry} className="min-h-[120px] rounded-xl p-4" />
      </div>
    );
  }

  if (articles.length === 0) {
    return (
      <div className="flex-1">
        <EmptyState title="Новостей пока нет" description="Публикации появятся здесь после добавления." className="min-h-[120px] rounded-xl p-4" />
      </div>
    );
  }

  return (
    <div className="min-w-0 flex-1">
      <div className="-mb-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 scrollbar-none">
        {articles.map((article) => (
          <Link
            key={article.id}
            to="/news"
            className="group flex w-[85vw] shrink-0 snap-center cursor-pointer flex-col justify-between rounded-xl border border-safi-green/5 bg-[#F5F5F0] p-4 transition-colors hover:bg-safi-green/5 md:w-[280px] md:snap-start"
          >
            <div className="mb-2 flex items-center justify-between text-[10px] text-safi-text/50">
              <span className="rounded-full bg-white px-2 py-0.5 font-bold text-safi-green">{article.category}</span>
              <span>{article.date}</span>
            </div>
            <h4 className="line-clamp-2 text-sm font-bold leading-tight text-safi-green transition-colors group-hover:text-safi-gold">
              {article.title}
            </h4>
          </Link>
        ))}
      </div>
    </div>
  );
}

function PopularProductCard({ product }: { product: Product }) {
  return (
    <article className="group relative flex flex-col overflow-hidden rounded-[32px] bg-[#F5F5F0] transition-transform duration-500 hover:-translate-y-2">
      <div className="relative aspect-[4/5] overflow-hidden bg-safi-bg p-6">
        <img
          src={product.image}
          alt={product.name}
          className="h-full w-full rounded-2xl object-cover shadow-sm transition-transform duration-700 group-hover:scale-105"
        />
        <div className="absolute left-4 top-4 flex items-center gap-1 rounded-full border border-safi-green/5 bg-white/90 px-3 py-1.5 text-[10px] font-bold tracking-widest text-safi-green shadow-sm backdrop-blur-md">
          <span className="h-1.5 w-1.5 rounded-full bg-safi-gold" />
          {product.pv} PV
        </div>
      </div>

      <div className="z-10 mx-2 -mt-6 mb-2 flex flex-1 flex-col rounded-[24px] bg-white p-6">
        <div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-safi-gold">{product.category}</div>
        <h3 className="mb-2 line-clamp-1 font-serif text-xl text-safi-green">{product.name}</h3>
        <p className="mb-6 line-clamp-2 text-sm text-safi-text opacity-60">{product.shortDescription}</p>
        <div className="mt-auto flex items-center justify-between border-t border-safi-green/5 pt-4">
          <span className="text-xl font-bold text-safi-green">{formatPrice(product.price)}</span>
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-safi-green/5 text-safi-green transition-colors group-hover:bg-safi-green group-hover:text-white">
            <ArrowRight className="h-4 w-4" />
          </div>
        </div>
      </div>
    </article>
  );
}

function PackageCard({ pkg }: { pkg: Package }) {
  const { t } = useTranslation();
  const isPopular = pkg.isPopular;
  const features = pkg.features.slice(0, 5);

  return (
    <article
      className={cn(
        'relative flex flex-col rounded-3xl border p-8 transition-transform duration-500 hover:-translate-y-2',
        isPopular ? 'border-safi-green bg-safi-green text-white shadow-2xl' : 'border-safi-green/5 bg-white'
      )}
    >
      {isPopular && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-safi-gold px-4 py-1 text-[10px] font-bold uppercase tracking-widest text-white shadow-md">
          {t('packages.hit', 'Хит продаж')}
        </div>
      )}

      <h3 className={cn('mb-2 font-serif text-2xl', isPopular ? 'text-white' : 'text-safi-green')}>{pkg.name}</h3>
      <div className={cn('mb-8 text-4xl font-bold', isPopular ? 'text-safi-gold' : 'text-safi-green')}>
        {formatPrice(pkg.price)}
      </div>

      <ul className="mb-8 flex-1 space-y-4">
        <PackageLine
          dark={isPopular}
          active
          label={`${t('packages.refBonus', 'Реферальный бонус')}: ${pkg.referralBonus}%`}
        />
        <PackageLine
          dark={isPopular}
          active={Boolean(pkg.binaryBonus)}
          label={`${t('packages.binBonus', 'Бинарный бонус')}: ${
            pkg.binaryBonus ? `${pkg.binaryBonus}%` : t('packages.binNone', 'Нет')
          }`}
        />
        {features.map((feature) => (
          <PackageLine key={feature} dark={isPopular} label={feature} subtle />
        ))}
      </ul>

      <Button variant={isPopular ? 'secondary' : 'outline'} className="w-full" to="/register">
        {t('packages.selectBtn', 'Выбрать пакет')}
      </Button>
    </article>
  );
}

function PackageLine({
  label,
  active = true,
  dark = false,
  subtle = false,
}: {
  label: string;
  active?: boolean;
  dark?: boolean;
  subtle?: boolean;
}) {
  return (
    <li className="flex items-start gap-3">
      <div
        className={cn(
          'mt-1 shrink-0 rounded-full',
          subtle ? 'h-1.5 w-1.5 opacity-40' : 'h-2 w-2',
          active ? (dark ? 'bg-safi-gold' : 'bg-safi-green') : 'bg-gray-300'
        )}
      />
      <span
        className={cn(
          'text-sm',
          subtle && dark && 'opacity-80',
          subtle && !dark && 'text-safi-text text-opacity-70',
          !subtle && active && 'font-medium',
          !subtle && !active && 'opacity-40',
          !subtle && !dark && active && 'text-safi-text text-opacity-80'
        )}
      >
        {label}
      </span>
    </li>
  );
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ru-RU')} ₸`;
}
