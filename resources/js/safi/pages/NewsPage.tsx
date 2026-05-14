import React from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Calendar } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { newsArticles } from '../data/news';

export default function NewsPage() {
  const { t } = useTranslation();
  const featuredArticle = newsArticles[0];
  const otherArticles = newsArticles.slice(1);

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mx-auto max-w-4xl text-center">
            <span className="safi-kicker">News</span>
            <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
              {t('news.pageTitle', 'Новости компании')}
            </h1>
            <p className="mx-auto mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
              Будьте в курсе событий, обновлений, продуктовых запусков и важных объявлений Safi Life.
            </p>
          </div>
        </Container>
      </section>

      {featuredArticle && (
        <section className="bg-white py-16 md:py-24">
          <Container>
            <article className="grid overflow-hidden rounded-[36px] border border-safi-border bg-safi-cream shadow-[0_24px_70px_rgba(11,23,18,0.10)] lg:grid-cols-[0.95fr_1.05fr]">
              <div className="min-h-[320px] bg-white">
                <img
                  src={featuredArticle.imageUrl || 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&q=80&w=1100'}
                  alt={featuredArticle.title}
                  className="h-full w-full object-cover"
                />
              </div>
              <div className="flex flex-col justify-center p-8 md:p-10">
                <ArticleMeta category={featuredArticle.category} date={featuredArticle.date} />
                <h2 className="mt-5 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                  {featuredArticle.title}
                </h2>
                <p className="mt-5 text-base leading-8 text-safi-muted">{featuredArticle.content}</p>
                <Button to="/contacts" variant="outline" className="mt-8 w-full sm:w-fit">
                  Задать вопрос
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </article>
          </Container>
        </section>
      )}

      <section className="border-y border-safi-border bg-safi-cream py-16 md:py-24">
        <Container>
          <div className="mb-10 flex flex-col gap-5 md:mb-14 md:flex-row md:items-end md:justify-between">
            <div>
              <span className="safi-kicker">Updates</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold leading-tight text-safi-green md:text-6xl">
                Последние обновления
              </h2>
            </div>
            <Button to="/contacts" variant="outline">
              Подписаться
            </Button>
          </div>

          <div className="grid gap-5 md:grid-cols-2">
            {otherArticles.map((article) => (
              <article key={article.id} className="overflow-hidden rounded-3xl border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
                {article.imageUrl && (
                  <div className="aspect-[16/9] overflow-hidden bg-safi-cream">
                    <img src={article.imageUrl} alt={article.title} className="h-full w-full object-cover" />
                  </div>
                )}
                <div className="p-7">
                  <ArticleMeta category={article.category} date={article.date} />
                  <h3 className="mt-5 font-serif text-3xl font-semibold leading-tight text-safi-green">{article.title}</h3>
                  <p className="mt-4 text-sm leading-7 text-safi-muted">{article.content}</p>
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>
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
