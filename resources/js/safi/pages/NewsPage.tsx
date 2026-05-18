import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Calendar } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { EmptyState, ErrorState, LoadingState } from '../components/ui/AsyncState';
import { getApiErrorState, getPublicNews, NewsArticle } from '../lib/api';

export default function NewsPage() {
  const { t } = useTranslation();
  const [newsArticles, setNewsArticles] = useState<NewsArticle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadNews = React.useCallback(async () => {
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
    <div className="min-h-screen bg-[#F5F5F0] py-24">
      <Container>
        <div className="mx-auto max-w-4xl space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div className="mb-12 text-center md:text-left">
            <h1 className="mb-4 font-serif text-4xl font-bold text-safi-green md:text-5xl">
              {t('news.pageTitle', 'Новости компании')}
            </h1>
            <p className="max-w-2xl text-lg text-safi-text/70">
              Будьте в курсе всех последних событий, обновлений и специальных предложений.
            </p>
          </div>

          {isLoading && <LoadingState title="Загружаем новости" description="Получаем актуальные публикации из API." />}

          {!isLoading && error && <ErrorState description={error} onRetry={loadNews} />}

          {!isLoading && !error && newsArticles.length === 0 && (
            <EmptyState title="Новостей пока нет" description="После публикации новости появятся на этой странице." />
          )}

          {!isLoading && !error && newsArticles.length > 0 && (
            <div className="grid gap-8">
              {newsArticles.map((article) => <NewsCard key={article.id} article={article} />)}
            </div>
          )}
        </div>
      </Container>
    </div>
  );
}

function NewsCard({ article }: { article: NewsArticle }) {
  const text = article.excerpt || article.content;

  return (
    <article className="flex flex-col overflow-hidden rounded-[32px] border border-safi-green/5 bg-white shadow-sm transition-shadow hover:shadow-md md:flex-row">
      {article.imageUrl && (
        <div className="relative h-64 shrink-0 overflow-hidden md:h-auto md:w-1/3">
          <img src={article.imageUrl} alt={article.title} className="h-full w-full object-cover" />
        </div>
      )}

      <div className="flex flex-1 flex-col justify-center p-8 md:p-10">
        <div className="mb-6 flex flex-wrap items-center gap-3">
          <span className="rounded-full bg-safi-green/5 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-safi-green">
            {article.category || 'Новости'}
          </span>
          {article.date && (
            <span className="flex items-center gap-1.5 font-mono text-sm text-safi-text/50">
              <Calendar className="h-4 w-4" />
              {article.date}
            </span>
          )}
        </div>
        <h3 className="mb-4 font-serif text-2xl font-bold text-safi-green md:text-3xl">{article.title}</h3>
        <p className="text-base leading-relaxed text-safi-text/80">{text}</p>
      </div>
    </article>
  );
}
