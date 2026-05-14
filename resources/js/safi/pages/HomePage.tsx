import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { ArrowRight, Calendar, Check, Leaf, Layers, RefreshCw, ShieldCheck, TrendingUp, Wallet } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
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
  const heroProduct = useMemo(() => products.find((product) => product.name === 'Safi Face Serum') || products[0], [products]);
  const highlightedProducts = useMemo(() => products.slice(0, 3), [products]);
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
      desc: t('benefits.domesticDesc', 'Производим товары для здоровья, красоты и ежедневного ухода из качественного сырья.'),
      icon: Leaf,
    },
    {
      title: t('benefits.binary', 'Бинар + Классика'),
      desc: t('benefits.binaryDesc', 'Сбалансированная модель дохода: личные продажи, структура, кэшбэк и статусы.'),
      icon: Layers,
    },
    {
      title: t('benefits.payout', 'Выплаты каждые 14 дней'),
      desc: t('benefits.payoutDesc', 'Понятный цикл выплат и прозрачная история начислений в личном кабинете.'),
      icon: Wallet,
    },
    {
      title: t('benefits.upgrade', 'Апгрейд пакетов'),
      desc: t('benefits.upgradeDesc', 'Начните с комфортного уровня и повышайте пакет по мере развития команды.'),
      icon: TrendingUp,
    },
    {
      title: t('benefits.pv', 'Накопительные PV'),
      desc: t('benefits.pvDesc', 'Объемы помогают двигаться по статусам и видеть реальный прогресс структуры.'),
      icon: RefreshCw,
    },
    {
      title: t('benefits.transparent', 'Прозрачные правила'),
      desc: t('benefits.transparentDesc', 'Фокус на продукте, активности и понятных условиях маркетинг-плана.'),
      icon: ShieldCheck,
    },
  ];

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg">
        <Container>
          <div className="grid min-h-[calc(100vh-72px)] items-center gap-12 py-14 md:py-20 lg:grid-cols-[minmax(0,1.02fr)_minmax(360px,0.82fr)] lg:gap-16 lg:py-20">
            <div className="mx-auto flex max-w-3xl flex-col items-center text-center lg:mx-0 lg:items-start lg:text-left">
              <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-safi-border bg-white px-4 py-2 shadow-[0_12px_30px_rgba(11,23,18,0.05)]">
                <span className="h-2 w-2 rounded-full bg-safi-gold" />
                <span className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-muted">
                  {t('hero.kzProducts', 'Отечественная продукция Казахстана')}
                </span>
              </div>

              <h1 className="max-w-4xl font-serif text-5xl font-semibold leading-[1.02] text-safi-green md:text-7xl lg:text-8xl">
                {t('hero.title1', 'Раскрой свой')}{' '}
                <span className="block">{t('hero.title2', 'потенциал с')}</span>
                <span className="italic text-safi-gold">{t('hero.title3', 'Safi')}</span>
              </h1>

              <p className="mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
                {t(
                  'hero.subtitle',
                  'Инновационные продукты для здоровья и продуманная бизнес-модель для роста команды, личных продаж и стабильного развития.'
                )}
              </p>

              <div className="mt-9 flex w-full max-w-md flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center lg:justify-start">
                <Button size="lg" to="/register" className="w-full sm:w-auto">
                  {t('hero.startNow', 'Начать бизнес')}
                </Button>
                <Button size="lg" variant="outline" to="/products" className="w-full sm:w-auto">
                  {t('hero.buyProducts', 'Купить продукцию')}
                </Button>
              </div>

              <div className="mt-10 grid w-full max-w-2xl grid-cols-3 divide-x divide-safi-border rounded-3xl border border-safi-border bg-white p-2 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
                <Metric value="20%" label={t('productCard.cashbackTitle', 'Кэшбэк')} />
                <Metric value="14" label={t('trustBadges.payouts', 'Дней выплат')} />
                <Metric value="PV" label={t('trustBadges.accumulation', 'Накопление')} />
              </div>
            </div>

            <div className="mx-auto w-full max-w-[420px] lg:mx-0 lg:justify-self-end">
              {productsLoading && (
                <LoadingState
                  title="Загружаем продукт"
                  description="Получаем актуальный product highlight."
                  className="min-h-[520px]"
                />
              )}

              {!productsLoading && productsError && (
                <ErrorState
                  title="Продукт недоступен"
                  description={productsError}
                  onRetry={loadProducts}
                  className="min-h-[520px]"
                />
              )}

              {!productsLoading && !productsError && heroProduct && <HeroProductCard heroProduct={heroProduct} />}

              {!productsLoading && !productsError && !heroProduct && (
                <EmptyState
                  title="Нет продукта для витрины"
                  description="Product highlight появится после публикации продуктов."
                  className="min-h-[520px]"
                />
              )}
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div className="max-w-2xl">
              <span className="safi-kicker">Product highlight</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Продукты для ежедневного доверия
              </h2>
            </div>
            <p className="max-w-md text-sm leading-7 text-safi-muted md:text-base">
              Каталог Safi Life строится вокруг понятных категорий: здоровье, красота и ежедневное использование.
            </p>
          </div>

          <div className="grid gap-5 md:grid-cols-3">
            {productsLoading && (
              <LoadingState
                title="Загружаем продукты"
                description="Получаем product highlight из публичного API."
                className="md:col-span-3"
              />
            )}

            {!productsLoading && productsError && (
              <ErrorState description={productsError} onRetry={loadProducts} className="md:col-span-3" />
            )}

            {!productsLoading && !productsError && highlightedProducts.length === 0 && (
              <EmptyState
                title="Продукты пока не опубликованы"
                description="После добавления продуктов они появятся в этом блоке."
                className="md:col-span-3"
              />
            )}

            {!productsLoading && !productsError && highlightedProducts.map((product, index) => (
              <article
                key={product.id}
                className="group overflow-hidden rounded-3xl border border-safi-border bg-safi-cream shadow-[0_18px_48px_rgba(11,23,18,0.06)]"
              >
                <div className="aspect-[4/3] overflow-hidden bg-white">
                  <img
                    src={product.image}
                    alt={product.name}
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                </div>
                <div className="p-6">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span className="rounded-full bg-white px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                      {product.category}
                    </span>
                    <span className="rounded-full bg-safi-green px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white">
                      {product.pv} PV
                    </span>
                  </div>
                  <h3 className="font-serif text-2xl font-semibold text-safi-green">{product.name}</h3>
                  <p className="mt-3 min-h-[72px] text-sm leading-6 text-safi-muted">{product.shortDescription}</p>
                  <div className="mt-6 flex items-center justify-between border-t border-safi-border pt-5">
                    <span className="text-xl font-extrabold text-safi-green">{product.price.toLocaleString('ru-RU')} ₸</span>
                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-safi-green transition-colors group-hover:bg-safi-green group-hover:text-white">
                      <ArrowRight className="h-4 w-4" />
                    </span>
                  </div>
                  {index === 0 && <span className="sr-only">Featured Safi product</span>}
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div>
              <span className="safi-kicker">Business opportunity</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Бизнес, который начинается с продукта
              </h2>
              <p className="mt-6 max-w-xl text-base leading-8 text-safi-muted">
                Safi Life соединяет продажи, личные рекомендации и командный рост. Партнер видит ключевые метрики в кабинете и может развивать структуру по левой и правой ветке.
              </p>
              <div className="mt-8">
                <Button to="/business" variant="outline">
                  Узнать больше
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <OpportunityCard title="Личные продажи" value="10%" desc="Реферальный бонус с выбранного пакета" />
              <OpportunityCard title="Бинарная модель" value="7-10%" desc="Расчет с меньшей ветки структуры" />
              <OpportunityCard title="Классика" value="20%" desc="Кэшбэк за личные покупки продукции" />
            </div>
          </div>
        </Container>
      </section>

      <section className="bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mb-10 text-center md:mb-14">
            <span className="safi-kicker">Packages</span>
            <h2 className="mx-auto mt-3 max-w-3xl font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Пакеты для старта и роста
            </h2>
            <p className="mx-auto mt-5 max-w-2xl text-base leading-8 text-safi-muted">
              Выберите комфортный уровень входа и повышайте пакет по мере развития бизнеса.
            </p>
          </div>

          <div className="grid gap-5 md:grid-cols-3">
            {packagesLoading && (
              <LoadingState
                title="Загружаем пакеты"
                description="Получаем стартовые пакеты из публичного API."
                className="md:col-span-3"
              />
            )}

            {!packagesLoading && packagesError && (
              <ErrorState description={packagesError} onRetry={loadPackages} className="md:col-span-3" />
            )}

            {!packagesLoading && !packagesError && packages.length === 0 && (
              <EmptyState
                title="Пакеты пока не опубликованы"
                description="После настройки пакетов они появятся в этом блоке."
                className="md:col-span-3"
              />
            )}

            {!packagesLoading && !packagesError && packages.map((pkg) => (
              <article
                key={pkg.id}
                className={`relative flex flex-col rounded-3xl border p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] ${
                  pkg.isPopular
                    ? 'border-safi-green bg-safi-green text-white'
                    : 'border-safi-border bg-white text-safi-green'
                }`}
              >
                {pkg.isPopular && (
                  <span className="absolute right-5 top-5 rounded-full bg-safi-gold px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-black">
                    {t('packages.hit', 'Хит продаж')}
                  </span>
                )}
                <h3 className={`font-serif text-3xl font-semibold ${pkg.isPopular ? 'text-white' : 'text-safi-green'}`}>{pkg.name}</h3>
                <div className={`mt-3 text-4xl font-extrabold ${pkg.isPopular ? 'text-safi-gold' : 'text-safi-green'}`}>
                  {pkg.price.toLocaleString('ru-RU')} ₸
                </div>
                <div className="mt-7 grid grid-cols-2 gap-3">
                  <PackageStat label="Реф." value={`${pkg.referralBonus}%`} dark={pkg.isPopular} />
                  <PackageStat label="Бинар" value={pkg.binaryBonus ? `${pkg.binaryBonus}%` : '—'} dark={pkg.isPopular} />
                </div>
                <ul className="mt-7 flex-1 space-y-3">
                  {pkg.features.slice(0, 4).map((feature) => (
                    <li key={feature} className={`flex gap-3 text-sm leading-6 ${pkg.isPopular ? 'text-white/80' : 'text-safi-muted'}`}>
                      <Check className={`mt-1 h-4 w-4 shrink-0 ${pkg.isPopular ? 'text-safi-gold' : 'text-safi-gold'}`} />
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
                <Button
                  to="/register"
                  variant={pkg.isPopular ? 'secondary' : 'outline'}
                  className="mt-8 w-full"
                >
                  {t('packages.selectBtn', 'Выбрать пакет')}
                </Button>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mb-10 max-w-2xl md:mb-14">
            <span className="safi-kicker">Why Safi</span>
            <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
              Почему выбирают Safi Life
            </h2>
          </div>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {benefits.map(({ title, desc, icon: Icon }) => (
              <article key={title} className="rounded-3xl border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="font-serif text-2xl font-semibold text-safi-green">{title}</h3>
                <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
              </article>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div className="max-w-2xl">
              <span className="safi-kicker">News</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Последние новости Safi
              </h2>
            </div>
            <Button to="/news" variant="outline">
              Все новости
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>

          <div className="grid gap-5 md:grid-cols-3">
            {newsLoading && (
              <LoadingState
                title="Загружаем новости"
                description="Получаем последние публикации из публичного API."
                className="md:col-span-3"
              />
            )}

            {!newsLoading && newsError && (
              <ErrorState description={newsError} onRetry={loadNews} className="md:col-span-3" />
            )}

            {!newsLoading && !newsError && newsPreview.length === 0 && (
              <EmptyState
                title="Новостей пока нет"
                description="После публикации новости появятся на главной странице."
                className="md:col-span-3"
              />
            )}

            {!newsLoading && !newsError && newsPreview.map((article) => (
              <NewsPreviewCard key={article.id} article={article} />
            ))}
          </div>
        </Container>
      </section>

      <section className="bg-safi-green py-16 text-white md:py-24">
        <Container>
          <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div className="max-w-3xl">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">Safi Life</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight md:text-6xl">
                Готовы начать бизнес с Safi?
              </h2>
              <p className="mt-5 max-w-2xl text-base leading-8 text-white/70">
                Оставьте заявку или изучите маркетинг-план, чтобы понять механику дохода, пакеты и этапы роста.
              </p>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
              <Button size="lg" variant="secondary" to="/register">
                Начать бизнес
              </Button>
              <Button size="lg" variant="outline" to="/marketing" className="border-white/25 bg-transparent text-white hover:bg-white hover:text-safi-green">
                Маркетинг-план
              </Button>
            </div>
          </div>
        </Container>
      </section>
    </div>
  );
}

function HeroProductCard({ heroProduct }: { heroProduct: Product }) {
  return (
    <article className="relative rounded-[36px] border border-safi-border bg-white p-4 shadow-[0_28px_90px_rgba(11,23,18,0.14)]">
      <div className="absolute -right-3 -top-3 z-10 flex h-24 w-24 rotate-6 flex-col items-center justify-center rounded-full border-4 border-white bg-safi-gold text-safi-black shadow-[0_18px_40px_rgba(201,166,70,0.32)] sm:-right-5 sm:-top-5 sm:h-28 sm:w-28">
        <span className="text-[10px] font-extrabold uppercase tracking-[0.12em]">Кэшбэк</span>
        <span className="font-serif text-3xl font-semibold leading-none">20%</span>
        <span className="text-[8px] font-extrabold uppercase tracking-[0.16em]">бонус</span>
      </div>

      <div className="overflow-hidden rounded-[28px] bg-safi-cream">
        <img src={heroProduct.image} alt={heroProduct.name} className="h-[330px] w-full object-cover sm:h-[380px]" />
      </div>

      <div className="p-4 sm:p-5">
        <div className="mb-4 flex items-start justify-between gap-4">
          <div>
            <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold">{heroProduct.category}</span>
            <h3 className="mt-2 font-serif text-2xl font-semibold text-safi-green">{heroProduct.name}</h3>
          </div>
          <span className="shrink-0 rounded-full bg-safi-cream px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green">
            {heroProduct.pv} PV
          </span>
        </div>
        <p className="text-sm leading-6 text-safi-muted">{heroProduct.shortDescription}</p>
        <div className="mt-5 flex items-center justify-between border-t border-safi-border pt-5">
          <span className="text-2xl font-extrabold text-safi-green">{heroProduct.price.toLocaleString('ru-RU')} ₸</span>
          <Button to="/products" size="sm" variant="ghost" className="bg-safi-cream">
            Каталог
          </Button>
        </div>
      </div>
    </article>
  );
}

function NewsPreviewCard({ article }: { article: NewsArticle }) {
  return (
    <article className="overflow-hidden rounded-3xl border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
      {article.imageUrl && (
        <div className="aspect-[16/10] overflow-hidden bg-safi-bg">
          <img src={article.imageUrl} alt={article.title} className="h-full w-full object-cover" />
        </div>
      )}
      <div className="p-7">
        <div className="flex flex-wrap items-center gap-3">
          <span className="rounded-full bg-safi-green px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white">
            {article.category}
          </span>
          <span className="inline-flex items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">
            <Calendar className="h-4 w-4 text-safi-gold" />
            {article.date}
          </span>
        </div>
        <h3 className="mt-5 font-serif text-3xl font-semibold leading-tight text-safi-green">{article.title}</h3>
        <p className="mt-4 line-clamp-3 text-sm leading-7 text-safi-muted">{article.content}</p>
      </div>
    </article>
  );
}

function Metric({ value, label }: { value: string; label: string }) {
  return (
    <div className="px-2 py-3 text-center">
      <div className="font-serif text-2xl font-semibold text-safi-green md:text-3xl">{value}</div>
      <div className="mt-1 text-[9px] font-extrabold uppercase tracking-[0.14em] text-safi-muted md:text-[10px]">{label}</div>
    </div>
  );
}

function OpportunityCard({ title, value, desc }: { title: string; value: string; desc: string }) {
  return (
    <article className="rounded-3xl border border-safi-border bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
      <div className="font-serif text-4xl font-semibold text-safi-gold">{value}</div>
      <h3 className="mt-5 font-serif text-2xl font-semibold text-safi-green">{title}</h3>
      <p className="mt-3 text-sm leading-7 text-safi-muted">{desc}</p>
    </article>
  );
}

function PackageStat({ label, value, dark = false }: { label: string; value: string; dark?: boolean }) {
  return (
    <div className={`rounded-2xl border px-4 py-3 ${dark ? 'border-white/10 bg-white/[0.08]' : 'border-safi-border bg-safi-cream'}`}>
      <div className={`text-[9px] font-extrabold uppercase tracking-[0.16em] ${dark ? 'text-white/60' : 'text-safi-muted'}`}>{label}</div>
      <div className={`mt-1 text-lg font-extrabold ${dark ? 'text-safi-gold' : 'text-safi-green'}`}>{value}</div>
    </div>
  );
}
