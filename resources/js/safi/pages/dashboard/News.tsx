import { useCallback, useEffect, useState } from 'react';
import { Calendar } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { getApiErrorState, getPublicNews, NewsArticle } from '../../lib/api';

export default function News() {
  const [newsArticles, setNewsArticles] = useState<NewsArticle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const featuredArticle = newsArticles[0];
  const otherArticles = newsArticles.slice(1);

  const loadNews = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      setNewsArticles(await getPublicNews());
    } catch (caughtError) {
      setNewsArticles([]);
      setError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadNews();
  }, [loadNews]);

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <span className="safi-kicker">News</span>
        <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Новости платформы</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
          Обновления компании, продуктовые анонсы и важные сообщения для партнеров.
        </p>
      </section>

      {isLoading && (
        <LoadingState title="Загружаем новости" description="Получаем новости из публичного API." />
      )}

      {!isLoading && error && (
        <ErrorState description={error} onRetry={loadNews} />
      )}

      {!isLoading && !error && !featuredArticle && (
        <EmptyState title="Новостей пока нет" description="После публикации новости появятся в этом разделе." />
      )}

      {!isLoading && !error && featuredArticle && (
        <article className="grid overflow-hidden rounded-[36px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.06)] lg:grid-cols-[0.95fr_1.05fr]">
          <div className="min-h-[280px] bg-safi-cream">
            <img
              src={featuredArticle.imageUrl || 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=900'}
              alt={featuredArticle.title}
              className="h-full w-full object-cover"
            />
          </div>
          <div className="flex flex-col justify-center p-7 md:p-9">
            <ArticleMeta category={featuredArticle.category} date={featuredArticle.date} />
            <h2 className="mt-5 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl">{featuredArticle.title}</h2>
            <p className="mt-5 text-sm leading-7 text-safi-muted md:text-base">{featuredArticle.content}</p>
          </div>
        </article>
      )}

      {!isLoading && !error && featuredArticle && (
        <section className="grid gap-5 md:grid-cols-2">
        {otherArticles.length === 0 && (
          <EmptyState
            title="Других новостей пока нет"
            description="Новые публикации появятся здесь."
            className="md:col-span-2"
          />
        )}

        {otherArticles.map((article) => (
          <article key={article.id} className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            {article.imageUrl && (
              <div className="aspect-[16/9] overflow-hidden bg-safi-cream">
                <img src={article.imageUrl} alt={article.title} className="h-full w-full object-cover" />
              </div>
            )}
            <div className="p-7">
              <ArticleMeta category={article.category} date={article.date} />
              <h2 className="mt-5 font-serif text-3xl font-semibold leading-tight text-safi-green">{article.title}</h2>
              <p className="mt-4 text-sm leading-7 text-safi-muted">{article.content}</p>
            </div>
          </article>
        ))}
        </section>
      )}
    </div>
  );
}

function ArticleMeta({ category, date }: { category: string; date: string }) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <span className="rounded-full bg-safi-green px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white">
        {category}
      </span>
      <span className="inline-flex items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">
        <Calendar className="h-4 w-4 text-safi-gold" />
        {date}
      </span>
    </div>
  );
}
